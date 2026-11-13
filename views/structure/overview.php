<?php
$self = $base;
$toggleForm = function (string $entity, int $id, string $label, string $confirm) use ($base): string {
    return '<form method="post" action="' . e($base) . '/' . e($entity) . '/' . $id . '/toggle" class="d-inline"'
        . ' onsubmit="return confirm(\'' . e($confirm) . '\')">' . csrf_field()
        . '<button class="btn btn-link btn-sm text-danger p-0 ms-2">' . e($label) . '</button></form>';
};
?>
<h1 class="h3 mb-4">Структура компании</h1>

<section id="directorate" class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 mb-0">Дирекция</h2>
    <?php if ($edit && !$directorate): ?>
      <a class="btn btn-primary btn-sm" href="<?= e($base) ?>/directorate/new">Создать дирекцию</a>
    <?php endif; ?>
  </div>
  <?php if ($directorate): ?>
    <div class="card"><div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-bold"><?= e($directorate['name']) ?></div>
        <div class="text-muted small">Руководитель: <?= e($directorate['head_name'] ?? 'не назначен') ?></div>
      </div>
      <?php if ($edit): ?>
        <a class="btn btn-outline-primary btn-sm" href="<?= e($base) ?>/directorate/<?= (int)$directorate['id'] ?>/edit">Изменить</a>
      <?php endif; ?>
    </div></div>
  <?php else: ?>
    <p class="text-muted">Дирекция не создана.</p>
  <?php endif; ?>
</section>

<section id="departments" class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 mb-0">Отделы</h2>
    <?php if ($edit): ?><a class="btn btn-primary btn-sm" href="<?= e($base) ?>/departments/new">Добавить отдел</a><?php endif; ?>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Название</th><th>Руководитель</th><th>Сотрудников</th><th>Статус</th><?php if ($edit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($departments as $u): ?>
      <tr class="<?= $u['is_active'] ? '' : 'text-muted' ?>">
        <td><a href="<?= e($self) ?>?unit=<?= (int)$u['id'] ?>#employees"><?= e($u['name']) ?></a></td>
        <td><?= e($u['head_name'] ?? '—') ?></td>
        <td><?= (int)$u['staff'] ?></td>
        <td><?= $u['is_active'] ? 'работает' : 'отключён' ?></td>
        <?php if ($edit): ?><td class="text-end">
          <a href="<?= e($base) ?>/departments/<?= (int)$u['id'] ?>/edit">Изменить</a>
          <?= $toggleForm('departments', (int)$u['id'], $u['is_active'] ? 'Отключить' : 'Включить', 'Изменить статус отдела?') ?>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$departments): ?><tr><td colspan="5" class="text-muted">Отделов пока нет.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>

<section id="positions" class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 mb-0">Должности</h2>
    <?php if ($edit): ?><a class="btn btn-primary btn-sm" href="<?= e($base) ?>/positions/new">Добавить должность</a><?php endif; ?>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Должность</th><th>Руководящая</th><th>Сотрудников</th><th>Статус</th><?php if ($edit): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($positions as $p): ?>
      <tr class="<?= $p['is_active'] ? '' : 'text-muted' ?>">
        <td><?= e($p['title']) ?></td>
        <td><?= $p['is_head'] ? 'да' : '—' ?></td>
        <td><?= (int)$p['staff'] ?></td>
        <td><?= $p['is_active'] ? 'используется' : 'отключена' ?></td>
        <?php if ($edit): ?><td class="text-end">
          <a href="<?= e($base) ?>/positions/<?= (int)$p['id'] ?>/edit">Изменить</a>
          <?= $toggleForm('positions', (int)$p['id'], $p['is_active'] ? 'Отключить' : 'Включить', 'Изменить статус должности?') ?>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$positions): ?><tr><td colspan="5" class="text-muted">Должностей пока нет.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>

<section id="employees" class="mb-5">
  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h2 class="h5 mb-0">Сотрудники</h2>
    <div class="d-flex gap-2 align-items-center">
      <form method="get" action="<?= e($self) ?>" class="d-flex gap-2">
        <select name="unit" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">Все подразделения</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $unitFilter === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php if ($edit): ?><a class="btn btn-primary btn-sm" href="<?= e($base) ?>/employees/new">Добавить</a><?php endif; ?>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr>
      <th>ФИО</th><th>Должность</th><th>Подразделение</th>
      <?php if ($edit): ?><th>Дата рождения</th><th>Контакты</th><?php endif; ?>
      <th>Статус</th><?php if ($edit): ?><th></th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($employees as $x): ?>
      <tr class="<?= $x['status'] === 'active' ? '' : 'text-muted' ?>">
        <td><?= e($x['full_name']) ?><?php if ($x['is_unit_head']): ?> <span class="badge badge-primary">руководитель</span><?php endif; ?></td>
        <td><?= e($x['position_title']) ?></td>
        <td><?= e($x['unit_name']) ?></td>
        <?php if ($edit): ?>
          <td><?= d($x['birth_date']) ?></td>
          <td class="small"><?= e($x['email']) ?><?= ($x['email'] && $x['phone']) ? '<br>' : '' ?><?= e($x['phone']) ?></td>
        <?php endif; ?>
        <td><?= $x['status'] === 'active' ? 'работает' : 'уволен' ?></td>
        <?php if ($edit): ?><td class="text-end">
          <a href="<?= e($base) ?>/employees/<?= (int)$x['id'] ?>/edit">Изменить</a>
          <?= $toggleForm('employees', (int)$x['id'], $x['status'] === 'active' ? 'Уволить' : 'Вернуть', 'Изменить статус сотрудника?') ?>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$employees): ?><tr><td colspan="7" class="text-muted">Сотрудников пока нет.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>

<?php if ($edit && $log): ?>
<section id="log" class="mb-5">
  <h2 class="h5 mb-2">Последние изменения</h2>
  <div class="table-responsive"><table class="table table-sm small">
    <thead><tr><th>Время</th><th>Кто</th><th>Действие</th><th>Объект</th></tr></thead>
    <tbody>
    <?php foreach ($log as $l): ?>
      <tr><td><?= e($l['ts']) ?></td><td><?= e($l['actor']) ?></td><td><?= e($l['action']) ?></td>
          <td><?= e($l['entity']) ?><?= $l['entity_id'] ? ' #' . (int)$l['entity_id'] : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
