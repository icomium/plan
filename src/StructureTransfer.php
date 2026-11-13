<?php
declare(strict_types=1);

namespace App;

use PDO;

final class StructureTransfer
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Db::pdo();
    }

    public function export(bool $withBirthDates): array
    {
        $units = $this->db->query("SELECT u.uuid, u.kind, u.name, u.sort, u.is_active, h.uuid AS head_uuid
            FROM units u LEFT JOIN employees h ON h.id = u.head_employee_id
            ORDER BY (u.kind = 'directorate') DESC, u.sort, u.name")->fetchAll();
        $positions = $this->db->query('SELECT uuid, title, is_head, is_active FROM positions ORDER BY title')->fetchAll();
        $employees = $this->db->query('SELECT e.uuid, e.last_name, e.first_name, e.middle_name, e.birth_date, e.email, e.phone,
                u.uuid AS unit_uuid, p.uuid AS position_uuid, e.status
            FROM employees e JOIN units u ON u.id = e.unit_id JOIN positions p ON p.id = e.position_id
            ORDER BY e.last_name, e.first_name')->fetchAll();

        foreach ($units as &$u) {
            $u['sort'] = (int)$u['sort'];
            $u['is_active'] = (int)$u['is_active'];
        }
        unset($u);
        foreach ($positions as &$p) {
            $p['is_head'] = (int)$p['is_head'];
            $p['is_active'] = (int)$p['is_active'];
        }
        unset($p);
        if (!$withBirthDates) {
            foreach ($employees as &$e) {
                unset($e['birth_date']);
            }
            unset($e);
        }

        return [
            'format' => 'plan-structure',
            'version' => 1,
            'exported_at' => date('c'),
            'source_env' => Env::get('APP_ENV'),
            'include_birth_dates' => $withBirthDates,
            'units' => $units,
            'positions' => $positions,
            'employees' => $employees,
        ];
    }

    public function validate(array $d): array
    {
        if (($d['format'] ?? null) !== 'plan-structure' || ($d['version'] ?? null) !== 1) {
            return ['Неизвестный формат файла (ожидается plan-structure, version 1).'];
        }
        foreach (['units', 'positions', 'employees'] as $k) {
            if (!isset($d[$k]) || !is_array($d[$k])) {
                return ["В файле нет раздела «$k»."];
            }
        }
        $re = '/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i';
        $err = [];
        $seen = [];
        $check = function (array $row, string $what) use ($re, &$seen, &$err): ?string {
            $u = $row['uuid'] ?? null;
            if (!is_string($u) || !preg_match($re, $u)) {
                $err[] = "$what: некорректный uuid.";
                return null;
            }
            $u = strtolower($u);
            if (isset($seen[$u])) {
                $err[] = "$what: повторяющийся uuid $u.";
            }
            $seen[$u] = true;
            return $u;
        };

        $unitU = [];
        $unitKeys = [];
        $dirs = 0;
        foreach ($d['units'] as $i => $u) {
            $what = 'units[' . $i . ']';
            $uu = is_array($u) ? $check($u, $what) : null;
            if ($uu === null) {
                continue;
            }
            $unitU[$uu] = $u;
            if (!in_array($u['kind'] ?? '', ['directorate', 'department'], true)) {
                $err[] = "$what: неверный kind.";
            }
            if (($u['kind'] ?? '') === 'directorate') {
                $dirs++;
            }
            $name = StructureRepo::clean($u['name'] ?? null);
            if ($name === null) {
                $err[] = "$what: пустое название.";
            } elseif (!empty($u['is_active'])) {
                $k = StructureRepo::key($name);
                if (isset($unitKeys[$k])) {
                    $err[] = "$what: повторяется название «$name».";
                }
                $unitKeys[$k] = true;
            }
        }
        if ($dirs > 1) {
            $err[] = 'В файле больше одной дирекции.';
        }

        $posU = [];
        $posKeys = [];
        foreach ($d['positions'] as $i => $p) {
            $what = 'positions[' . $i . ']';
            $pu = is_array($p) ? $check($p, $what) : null;
            if ($pu === null) {
                continue;
            }
            $posU[$pu] = $p;
            $t = StructureRepo::clean($p['title'] ?? null);
            if ($t === null) {
                $err[] = "$what: пустое название.";
            } else {
                $k = StructureRepo::key($t);
                if (isset($posKeys[$k])) {
                    $err[] = "$what: повторяется должность «$t».";
                }
                $posKeys[$k] = true;
            }
        }

        $empU = [];
        foreach ($d['employees'] as $i => $e) {
            $what = 'employees[' . $i . ']';
            $eu = is_array($e) ? $check($e, $what) : null;
            if ($eu === null) {
                continue;
            }
            $empU[$eu] = $e;
            if (StructureRepo::clean($e['last_name'] ?? null) === null || StructureRepo::clean($e['first_name'] ?? null) === null) {
                $err[] = "$what: нужны фамилия и имя.";
            }
            if (!isset($unitU[strtolower((string)($e['unit_uuid'] ?? ''))])) {
                $err[] = "$what: подразделение не найдено в файле.";
            }
            if (!isset($posU[strtolower((string)($e['position_uuid'] ?? ''))])) {
                $err[] = "$what: должность не найдена в файле.";
            }
            if (!in_array($e['status'] ?? '', ['active', 'dismissed'], true)) {
                $err[] = "$what: неверный status.";
            }
            $bd = $e['birth_date'] ?? null;
            if ($bd !== null) {
                $dt = is_string($bd) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $bd) : false;
                if (!$dt || $dt->format('Y-m-d') !== $bd) {
                    $err[] = "$what: некорректная дата рождения.";
                }
            }
        }

        foreach ($unitU as $uu => $u) {
            $h = $u['head_uuid'] ?? null;
            if ($h === null) {
                continue;
            }
            $h = strtolower((string)$h);
            $emp = $empU[$h] ?? null;
            if (!$emp || ($emp['status'] ?? '') !== 'active' || strtolower((string)($emp['unit_uuid'] ?? '')) !== $uu) {
                $err[] = 'Руководитель подразделения «' . ($u['name'] ?? $uu) . '» должен быть активным сотрудником этого подразделения.';
            }
        }
        return $err;
    }

    public function import(array $d, bool $dryRun, bool $deactivateMissing, string $actor): array
    {
        $rep = [];
        foreach (['units', 'positions', 'employees'] as $t) {
            $rep[$t] = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deactivated' => 0];
        }
        $rep['heads_changed'] = 0;

        $this->db->beginTransaction();
        try {
            $unitId = [];
            $posId = [];
            $empId = [];

            foreach ($d['units'] as $u) {
                $name = StructureRepo::clean($u['name']);
                $unitId[strtolower($u['uuid'])] = $this->upsert('units', strtolower($u['uuid']), [
                    'kind' => $u['kind'],
                    'name' => $name,
                    'name_key' => StructureRepo::key($name),
                    'sort' => (int)($u['sort'] ?? 0),
                    'is_active' => empty($u['is_active']) ? 0 : 1,
                ], $rep);
            }
            foreach ($d['positions'] as $p) {
                $title = StructureRepo::clean($p['title']);
                $posId[strtolower($p['uuid'])] = $this->upsert('positions', strtolower($p['uuid']), [
                    'title' => $title,
                    'title_key' => StructureRepo::key($title),
                    'is_head' => empty($p['is_head']) ? 0 : 1,
                    'is_active' => empty($p['is_active']) ? 0 : 1,
                ], $rep);
            }
            foreach ($d['employees'] as $e) {
                $vals = [
                    'last_name' => StructureRepo::clean($e['last_name']),
                    'first_name' => StructureRepo::clean($e['first_name']),
                    'middle_name' => StructureRepo::clean($e['middle_name'] ?? null),
                    'email' => StructureRepo::clean($e['email'] ?? null),
                    'phone' => StructureRepo::clean($e['phone'] ?? null),
                    'unit_id' => $unitId[strtolower($e['unit_uuid'])],
                    'position_id' => $posId[strtolower($e['position_uuid'])],
                    'status' => $e['status'],
                ];
                if (array_key_exists('birth_date', $e)) {
                    $vals['birth_date'] = $e['birth_date'];
                }
                $empId[strtolower($e['uuid'])] = $this->upsert('employees', strtolower($e['uuid']), $vals, $rep);
            }

            foreach ($d['units'] as $u) {
                $uid = $unitId[strtolower($u['uuid'])];
                $head = isset($u['head_uuid']) ? ($empId[strtolower((string)$u['head_uuid'])] ?? null) : null;
                $cur = $this->db->query('SELECT head_employee_id FROM units WHERE id = ' . (int)$uid)->fetchColumn();
                if ((string)($cur ?? '') !== (string)($head ?? '')) {
                    $this->db->prepare("UPDATE units SET head_employee_id = ?, updated_at = datetime('now') WHERE id = ?")->execute([$head, $uid]);
                    $rep['heads_changed']++;
                }
            }

            if ($deactivateMissing) {
                $fileUnits = array_flip(array_map(fn($u) => strtolower($u['uuid']), $d['units']));
                $filePos = array_flip(array_map(fn($p) => strtolower($p['uuid']), $d['positions']));
                $fileEmp = array_flip(array_map(fn($e) => strtolower($e['uuid']), $d['employees']));

                foreach ($this->db->query("SELECT id, uuid FROM employees WHERE status = 'active'")->fetchAll() as $r) {
                    if (!isset($fileEmp[strtolower($r['uuid'])])) {
                        $this->db->prepare("UPDATE employees SET status = 'dismissed', updated_at = datetime('now') WHERE id = ?")->execute([$r['id']]);
                        $this->db->prepare("UPDATE units SET head_employee_id = NULL WHERE head_employee_id = ?")->execute([$r['id']]);
                        $rep['employees']['deactivated']++;
                    }
                }
                foreach ($this->db->query("SELECT id, uuid FROM units WHERE kind = 'department' AND is_active = 1")->fetchAll() as $r) {
                    if (!isset($fileUnits[strtolower($r['uuid'])])) {
                        $this->db->prepare("UPDATE units SET is_active = 0, head_employee_id = NULL, updated_at = datetime('now') WHERE id = ?")->execute([$r['id']]);
                        $rep['units']['deactivated']++;
                    }
                }
                foreach ($this->db->query('SELECT id, uuid FROM positions WHERE is_active = 1')->fetchAll() as $r) {
                    if (!isset($filePos[strtolower($r['uuid'])])) {
                        $this->db->prepare("UPDATE positions SET is_active = 0, updated_at = datetime('now') WHERE id = ?")->execute([$r['id']]);
                        $rep['positions']['deactivated']++;
                    }
                }
            }

            if (!$dryRun) {
                $this->db->prepare('INSERT INTO structure_log(actor, action, entity, after_json) VALUES (?, ?, ?, ?)')
                    ->execute([$actor, 'import', 'structure', json_encode($rep, JSON_UNESCAPED_UNICODE)]);
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $rep;
    }

    private function upsert(string $table, string $uuid, array $vals, array &$rep): int
    {
        $st = $this->db->prepare("SELECT * FROM $table WHERE uuid = ?");
        $st->execute([$uuid]);
        $row = $st->fetch();

        if (!$row) {
            $cols = array_merge(['uuid'], array_keys($vals));
            $sql = "INSERT INTO $table(" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $this->db->prepare($sql)->execute(array_merge([$uuid], array_values($vals)));
            $rep[$table]['created']++;
            return (int)$this->db->lastInsertId();
        }

        $diff = [];
        foreach ($vals as $k => $v) {
            if ((string)($row[$k] ?? '') !== (string)($v ?? '')) {
                $diff[$k] = $v;
            }
        }
        if ($diff) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($diff)));
            $this->db->prepare("UPDATE $table SET $set, updated_at = datetime('now') WHERE id = ?")
                ->execute(array_merge(array_values($diff), [$row['id']]));
            $rep[$table]['updated']++;
        } else {
            $rep[$table]['unchanged']++;
        }
        return (int)$row['id'];
    }
}
