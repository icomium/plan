<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Guard;
use App\StructureRepo;
use App\StructureTransfer;
use App\ValidationError;

final class StructureController
{
    private const ENTITIES = ['directorate', 'departments', 'positions', 'employees'];

    private StructureRepo $repo;

    public function __construct()
    {
        $this->repo = new StructureRepo();
    }

    public function overview(bool $edit): void
    {
        $unit = (isset($_GET['unit']) && is_string($_GET['unit']) && ctype_digit($_GET['unit'])) ? (int)$_GET['unit'] : null;
        render('structure/overview', [
            'title' => 'Структура компании',
            'edit' => $edit,
            'base' => $edit ? '/admin/structure' : '/structure',
            'directorate' => $this->repo->directorate(),
            'departments' => $this->repo->departments(),
            'positions' => $this->repo->positions(),
            'employees' => $this->repo->employees($unit),
            'unitFilter' => $unit,
            'units' => $this->repo->activeUnits(),
            'log' => $edit ? $this->repo->recentLog(15) : [],
        ]);
    }

    public function form(string $entity, ?int $id, array $values = [], array $errors = [], int $status = 200): void
    {
        $this->checkEntity($entity);
        $row = [];
        if ($id !== null) {
            $row = $this->load($entity, $id);
            if (!$row) {
                render('error', ['code' => 404], 404);
                return;
            }
        }
        $values = $values ?: $row;
        if ($entity === 'directorate' && $id === null && $this->repo->directorate()) {
            flash('Дирекция уже создана.', 'danger');
            redirect('/admin/structure');
        }
        render('structure/form', [
            'title' => $this->titleFor($entity, $id),
            'entity' => $entity,
            'id' => $id,
            'fields' => $this->fields($entity, $id),
            'values' => $values,
            'errors' => $errors,
        ], $status);
    }

    public function save(string $entity): void
    {
        csrf_check();
        $this->checkEntity($entity);
        $raw = $_POST['id'] ?? '';
        $id = (is_string($raw) && ctype_digit($raw)) ? (int)$raw : null;
        $actor = Guard::actor();

        try {
            match ($entity) {
                'directorate' => $this->repo->saveUnit($id, 'directorate', $_POST, $actor),
                'departments' => $this->repo->saveUnit($id, 'department', $_POST, $actor),
                'positions' => $this->repo->savePosition($id, $_POST, $actor),
                'employees' => $this->repo->saveEmployee($id, $_POST, $actor),
            };
        } catch (ValidationError $e) {
            $this->form($entity, $id, $_POST, $e->errors, 422);
            return;
        }
        flash('Сохранено.');
        redirect('/admin/structure#' . $entity);
    }

    public function toggle(string $entity, int $id): void
    {
        csrf_check();
        $this->checkEntity($entity);
        try {
            flash($this->repo->toggle($entity, $id, Guard::actor()));
        } catch (ValidationError $e) {
            flash($e->getMessage(), 'danger');
        }
        redirect('/admin/structure#' . $entity);
    }

    public function export(): void
    {
        $withBirth = ($_GET['birth'] ?? '') === '1';
        $data = (new StructureTransfer())->export($withBirth);
        $name = 'plan-structure-' . date('Ymd-His') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    // ---------- вспомогательное ----------

    private function checkEntity(string $entity): void
    {
        if (!in_array($entity, self::ENTITIES, true)) {
            render('error', ['code' => 404], 404);
            exit;
        }
    }

    private function load(string $entity, int $id): ?array
    {
        $table = match ($entity) {
            'positions' => 'positions',
            'employees' => 'employees',
            default => 'units',
        };
        $row = $this->repo->find($table, $id);
        if ($row && $table === 'units') {
            $kind = $entity === 'directorate' ? 'directorate' : 'department';
            if ($row['kind'] !== $kind) {
                return null;
            }
        }
        return $row;
    }

    private function titleFor(string $entity, ?int $id): string
    {
        $new = $id === null;
        return match ($entity) {
            'directorate' => $new ? 'Новая дирекция' : 'Дирекция',
            'departments' => $new ? 'Новый отдел' : 'Отдел',
            'positions' => $new ? 'Новая должность' : 'Должность',
            'employees' => $new ? 'Новый сотрудник' : 'Сотрудник',
        };
    }

    private function fields(string $entity, ?int $id): array
    {
        switch ($entity) {
            case 'directorate':
            case 'departments':
                $f = [['name' => 'name', 'label' => 'Название', 'type' => 'text', 'required' => true, 'max' => 150]];
                if ($entity === 'departments') {
                    $f[] = ['name' => 'sort', 'label' => 'Порядок в списке', 'type' => 'number', 'help' => 'Меньше число — выше в списке'];
                }
                if ($id !== null) {
                    $opts = ['' => '— не назначен —'];
                    foreach ($this->repo->headCandidates($id) as $c) {
                        $opts[$c['id']] = $c['full_name'] . ' — ' . $c['title'];
                    }
                    $f[] = ['name' => 'head_employee_id', 'label' => 'Руководитель', 'type' => 'select', 'options' => $opts,
                        'help' => 'Только работающие сотрудники этого подразделения'];
                }
                return $f;

            case 'positions':
                return [
                    ['name' => 'title', 'label' => 'Название должности', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'is_head', 'label' => 'Руководящая должность', 'type' => 'checkbox'],
                ];

            default:
                $units = [];
                foreach ($this->repo->activeUnits() as $u) {
                    $units[$u['id']] = $u['name'];
                }
                $pos = [];
                foreach ($this->repo->activePositions() as $p) {
                    $pos[$p['id']] = $p['title'];
                }
                return [
                    ['name' => 'last_name', 'label' => 'Фамилия', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'first_name', 'label' => 'Имя', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'middle_name', 'label' => 'Отчество', 'type' => 'text', 'max' => 80],
                    ['name' => 'birth_date', 'label' => 'Дата рождения', 'type' => 'date'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'max' => 150],
                    ['name' => 'phone', 'label' => 'Телефон', 'type' => 'text', 'max' => 32],
                    ['name' => 'unit_id', 'label' => 'Подразделение', 'type' => 'select', 'required' => true, 'options' => ['' => '— выберите —'] + $units],
                    ['name' => 'position_id', 'label' => 'Должность', 'type' => 'select', 'required' => true, 'options' => ['' => '— выберите —'] + $pos],
                ];
        }
    }
}
