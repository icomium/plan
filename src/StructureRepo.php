<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class StructureRepo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Db::pdo();
    }

    public static function clean(mixed $s): ?string
    {
        if (!is_scalar($s)) {
            return null;
        }
        $s = trim(preg_replace('/\s+/u', ' ', trim((string)$s)) ?? '');
        return $s === '' ? null : $s;
    }

    public static function key(string $s): string
    {
        return mb_strtolower($s);
    }

    private function all(string $sql, array $p = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    private function one(string $sql, array $p = []): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    public function find(string $table, int $id): ?array
    {
        if (!in_array($table, ['units', 'positions', 'employees'], true)) {
            throw new \InvalidArgumentException('bad table');
        }
        return $this->one("SELECT * FROM $table WHERE id = ?", [$id]);
    }

    // ---------- чтение ----------

    public function directorate(): ?array
    {
        return $this->one("SELECT u.*, e.full_name AS head_name FROM units u
            LEFT JOIN employees e ON e.id = u.head_employee_id WHERE u.kind = 'directorate'");
    }

    public function departments(): array
    {
        return $this->all("SELECT u.*, e.full_name AS head_name,
                (SELECT COUNT(*) FROM employees x WHERE x.unit_id = u.id AND x.status = 'active') AS staff
            FROM units u LEFT JOIN employees e ON e.id = u.head_employee_id
            WHERE u.kind = 'department' ORDER BY u.is_active DESC, u.sort, u.name");
    }

    public function positions(): array
    {
        return $this->all("SELECT p.*,
                (SELECT COUNT(*) FROM employees x WHERE x.position_id = p.id AND x.status = 'active') AS staff
            FROM positions p ORDER BY p.is_active DESC, p.title");
    }

    public function employees(?int $unitId = null): array
    {
        $sql = "SELECT e.*, u.name AS unit_name, p.title AS position_title,
                EXISTS(SELECT 1 FROM units h WHERE h.head_employee_id = e.id) AS is_unit_head
            FROM employees e JOIN units u ON u.id = e.unit_id JOIN positions p ON p.id = e.position_id";
        $params = [];
        if ($unitId !== null) {
            $sql .= ' WHERE e.unit_id = ?';
            $params[] = $unitId;
        }
        $sql .= " ORDER BY (e.status = 'active') DESC, e.last_name, e.first_name";
        return $this->all($sql, $params);
    }

    public function activeUnits(): array
    {
        return $this->all("SELECT id, name, kind FROM units WHERE is_active = 1 ORDER BY (kind = 'directorate') DESC, sort, name");
    }

    public function activePositions(): array
    {
        return $this->all('SELECT id, title FROM positions WHERE is_active = 1 ORDER BY title');
    }

    public function headCandidates(int $unitId): array
    {
        return $this->all("SELECT e.id, e.full_name, p.title, p.is_head FROM employees e
            JOIN positions p ON p.id = e.position_id
            WHERE e.unit_id = ? AND e.status = 'active' ORDER BY p.is_head DESC, e.last_name, e.first_name", [$unitId]);
    }

    public function recentLog(int $n = 15): array
    {
        return $this->all('SELECT * FROM structure_log ORDER BY id DESC LIMIT ' . (int)$n);
    }

    // ---------- запись ----------

    public function saveUnit(?int $id, string $kind, array $in, string $actor): int
    {
        $name = self::clean($in['name'] ?? null);
        $sort = (int)self::clean($in['sort'] ?? null);
        $err = [];
        if ($name === null) {
            $err[] = 'Укажите название.';
        } elseif (mb_strlen($name) > 150) {
            $err[] = 'Название не длиннее 150 символов.';
        }

        $before = null;
        $head = null;
        if ($id !== null) {
            $before = $this->find('units', $id);
            if (!$before || $before['kind'] !== $kind) {
                throw new ValidationError(['Запись не найдена.']);
            }
            $h = self::clean($in['head_employee_id'] ?? null);
            if ($h !== null) {
                $head = (int)$h;
                $emp = $this->find('employees', $head);
                if (!$emp || $emp['status'] !== 'active' || (int)$emp['unit_id'] !== $id) {
                    $err[] = 'Руководителем может быть только работающий сотрудник этого подразделения.';
                }
            }
        }
        if ($err) {
            throw new ValidationError($err);
        }

        return $this->tx(function () use ($id, $kind, $name, $sort, $head, $before, $actor) {
            if ($id === null) {
                $this->db->prepare('INSERT INTO units(uuid, kind, name, name_key, sort) VALUES (?, ?, ?, ?, ?)')
                    ->execute([uuid4(), $kind, $name, self::key($name), $sort]);
                $nid = (int)$this->db->lastInsertId();
                $this->log($actor, 'create', 'units', $nid, null, $this->find('units', $nid));
                return $nid;
            }
            $this->db->prepare("UPDATE units SET name = ?, name_key = ?, sort = ?, head_employee_id = ?,
                    updated_at = datetime('now') WHERE id = ?")
                ->execute([$name, self::key($name), $sort, $head, $id]);
            $this->log($actor, 'update', 'units', $id, $before, $this->find('units', $id));
            return $id;
        });
    }

    public function savePosition(?int $id, array $in, string $actor): int
    {
        $title = self::clean($in['title'] ?? null);
        $isHead = empty($in['is_head']) ? 0 : 1;
        $err = [];
        if ($title === null) {
            $err[] = 'Укажите название должности.';
        } elseif (mb_strlen($title) > 120) {
            $err[] = 'Название не длиннее 120 символов.';
        }
        $before = $id !== null ? $this->find('positions', $id) : null;
        if ($id !== null && !$before) {
            $err[] = 'Должность не найдена.';
        }
        if ($err) {
            throw new ValidationError($err);
        }

        return $this->tx(function () use ($id, $title, $isHead, $before, $actor) {
            if ($id === null) {
                $this->db->prepare('INSERT INTO positions(uuid, title, title_key, is_head) VALUES (?, ?, ?, ?)')
                    ->execute([uuid4(), $title, self::key($title), $isHead]);
                $nid = (int)$this->db->lastInsertId();
                $this->log($actor, 'create', 'positions', $nid, null, $this->find('positions', $nid));
                return $nid;
            }
            $this->db->prepare("UPDATE positions SET title = ?, title_key = ?, is_head = ?,
                    updated_at = datetime('now') WHERE id = ?")
                ->execute([$title, self::key($title), $isHead, $id]);
            $this->log($actor, 'update', 'positions', $id, $before, $this->find('positions', $id));
            return $id;
        });
    }

    public function saveEmployee(?int $id, array $in, string $actor): int
    {
        $last = self::clean($in['last_name'] ?? null);
        $first = self::clean($in['first_name'] ?? null);
        $mid = self::clean($in['middle_name'] ?? null);
        $email = self::clean($in['email'] ?? null);
        $phone = self::clean($in['phone'] ?? null);
        $bd = self::clean($in['birth_date'] ?? null);
        $unitId = (int)self::clean($in['unit_id'] ?? null);
        $posId = (int)self::clean($in['position_id'] ?? null);

        $err = [];
        if ($last === null) {
            $err[] = 'Укажите фамилию.';
        }
        if ($first === null) {
            $err[] = 'Укажите имя.';
        }
        foreach ([[$last, 'Фамилия'], [$first, 'Имя'], [$mid, 'Отчество']] as [$v, $label]) {
            if ($v !== null && mb_strlen($v) > 80) {
                $err[] = "$label не длиннее 80 символов.";
            }
        }
        if ($email !== null && (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $err[] = 'Некорректный email.';
        }
        if ($phone !== null && !preg_match('/^[0-9+()\-\s.]{5,32}$/', $phone)) {
            $err[] = 'Некорректный телефон.';
        }
        if ($bd !== null) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $bd);
            if (!$dt || $dt->format('Y-m-d') !== $bd || $bd > date('Y-m-d') || $bd < '1900-01-01') {
                $err[] = 'Некорректная дата рождения.';
            }
        }

        $before = $id !== null ? $this->find('employees', $id) : null;
        if ($id !== null && !$before) {
            throw new ValidationError(['Сотрудник не найден.']);
        }
        $unit = $unitId ? $this->find('units', $unitId) : null;
        if (!$unit || ((int)$unit['is_active'] !== 1 && (int)($before['unit_id'] ?? 0) !== $unitId)) {
            $err[] = 'Выберите подразделение.';
        }
        $pos = $posId ? $this->find('positions', $posId) : null;
        if (!$pos || ((int)$pos['is_active'] !== 1 && (int)($before['position_id'] ?? 0) !== $posId)) {
            $err[] = 'Выберите должность.';
        }
        if ($err) {
            throw new ValidationError($err);
        }

        return $this->tx(function () use ($id, $last, $first, $mid, $bd, $email, $phone, $unitId, $posId, $before, $actor) {
            if ($id === null) {
                $this->db->prepare('INSERT INTO employees(uuid, last_name, first_name, middle_name, birth_date, email, phone, unit_id, position_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([uuid4(), $last, $first, $mid, $bd, $email, $phone, $unitId, $posId]);
                $eid = (int)$this->db->lastInsertId();
                $this->log($actor, 'create', 'employees', $eid, null, $this->find('employees', $eid));
                return $eid;
            }
            $this->db->prepare("UPDATE employees SET last_name = ?, first_name = ?, middle_name = ?, birth_date = ?,
                    email = ?, phone = ?, unit_id = ?, position_id = ?, updated_at = datetime('now') WHERE id = ?")
                ->execute([$last, $first, $mid, $bd, $email, $phone, $unitId, $posId, $id]);
            $this->db->prepare("UPDATE units SET head_employee_id = NULL, updated_at = datetime('now')
                    WHERE head_employee_id = ? AND id <> ?")->execute([$id, $unitId]);
            $this->log($actor, 'update', 'employees', $id, $before, $this->find('employees', $id));
            return $id;
        });
    }

    public function toggle(string $entity, int $id, string $actor): string
    {
        return $this->tx(function () use ($entity, $id, $actor) {
            if ($entity === 'departments') {
                $u = $this->find('units', $id);
                if (!$u || $u['kind'] !== 'department') {
                    throw new ValidationError(['Запись не найдена.']);
                }
                if ((int)$u['is_active'] === 1) {
                    $n = (int)$this->one("SELECT COUNT(*) AS c FROM employees WHERE unit_id = ? AND status = 'active'", [$id])['c'];
                    if ($n > 0) {
                        throw new ValidationError(["В отделе работают сотрудники ($n). Сначала переведите или уволите их."]);
                    }
                    $this->db->prepare("UPDATE units SET is_active = 0, head_employee_id = NULL, updated_at = datetime('now') WHERE id = ?")->execute([$id]);
                    $msg = 'Отдел отключён.';
                } else {
                    $this->db->prepare("UPDATE units SET is_active = 1, updated_at = datetime('now') WHERE id = ?")->execute([$id]);
                    $msg = 'Отдел включён.';
                }
                $this->log($actor, 'toggle', 'units', $id, $u, $this->find('units', $id));
                return $msg;
            }

            if ($entity === 'positions') {
                $p = $this->find('positions', $id);
                if (!$p) {
                    throw new ValidationError(['Запись не найдена.']);
                }
                if ((int)$p['is_active'] === 1) {
                    $n = (int)$this->one("SELECT COUNT(*) AS c FROM employees WHERE position_id = ? AND status = 'active'", [$id])['c'];
                    if ($n > 0) {
                        throw new ValidationError(["Должность занята сотрудниками ($n)."]);
                    }
                    $this->db->prepare("UPDATE positions SET is_active = 0, updated_at = datetime('now') WHERE id = ?")->execute([$id]);
                    $msg = 'Должность отключена.';
                } else {
                    $this->db->prepare("UPDATE positions SET is_active = 1, updated_at = datetime('now') WHERE id = ?")->execute([$id]);
                    $msg = 'Должность включена.';
                }
                $this->log($actor, 'toggle', 'positions', $id, $p, $this->find('positions', $id));
                return $msg;
            }

            if ($entity === 'employees') {
                $e = $this->find('employees', $id);
                if (!$e) {
                    throw new ValidationError(['Сотрудник не найден.']);
                }
                if ($e['status'] === 'active') {
                    $this->db->prepare("UPDATE employees SET status = 'dismissed', updated_at = datetime('now') WHERE id = ?")->execute([$id]);
                    $this->db->prepare("UPDATE units SET head_employee_id = NULL, updated_at = datetime('now') WHERE head_employee_id = ?")->execute([$id]);
                    $msg = 'Сотрудник переведён в уволенные.';
                } else {
                    $unit = $this->find('units', (int)$e['unit_id']);
                    $pos = $this->find('positions', (int)$e['position_id']);
                    if (!$unit || !(int)$unit['is_active'] || !$pos || !(int)$pos['is_active']) {
                        throw new ValidationError(['Подразделение или должность отключены. Сначала измените их у сотрудника.']);
                    }
                    $this->db->prepare("UPDATE employees SET status = 'active', updated_at = datetime('now') WHERE id = ?")->execute([$id]);
                    $msg = 'Сотрудник возвращён в штат.';
                }
                $this->log($actor, 'toggle', 'employees', $id, $e, $this->find('employees', $id));
                return $msg;
            }

            throw new ValidationError(['Неизвестный раздел.']);
        });
    }

    // ---------- служебное ----------

    private function log(string $actor, string $action, string $entity, ?int $id, ?array $before, ?array $after): void
    {
        $this->db->prepare('INSERT INTO structure_log(actor, action, entity, entity_id, before_json, after_json) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([
                $actor, $action, $entity, $id,
                $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            ]);
    }

    private function tx(callable $fn): mixed
    {
        $this->db->beginTransaction();
        try {
            $r = $fn();
            $this->db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e instanceof PDOException ? $this->friendly($e) : $e;
        }
    }

    private function friendly(PDOException $e): \Throwable
    {
        $map = [
            'units.name_key' => 'Подразделение с таким названием уже есть.',
            'uq_unit_name' => 'Подразделение с таким названием уже есть.',
            'positions.title_key' => 'Такая должность уже есть.',
            'units.kind' => 'Дирекция уже создана.',
        ];
        foreach ($map as $needle => $text) {
            if (str_contains($e->getMessage(), $needle)) {
                return new ValidationError([$text]);
            }
        }
        return $e;
    }
}
