<h1 class="h3 mb-4"><?= e($title) ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<form method="post" action="/admin/structure/<?= e($entity) ?>/save" class="card" style="max-width: 640px;" autocomplete="off">
  <div class="card-body">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id !== null ? (int)$id : '' ?>">

    <?php foreach ($fields as $f):
        $n = $f['name'];
        $v = $values[$n] ?? '';
        $req = !empty($f['required']);
    ?>
      <div class="mb-3">
        <?php if ($f['type'] === 'checkbox'): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="<?= e($n) ?>" id="f_<?= e($n) ?>" value="1" <?= $v ? 'checked' : '' ?>>
            <label class="form-check-label" for="f_<?= e($n) ?>"><?= e($f['label']) ?></label>
          </div>
        <?php elseif ($f['type'] === 'select'): ?>
          <label class="form-label" for="f_<?= e($n) ?>"><?= e($f['label']) ?><?= $req ? ' *' : '' ?></label>
          <select class="form-select" name="<?= e($n) ?>" id="f_<?= e($n) ?>" <?= $req ? 'required' : '' ?>>
            <?php foreach ($f['options'] as $ov => $ol): ?>
              <option value="<?= e((string)$ov) ?>" <?= (string)$v === (string)$ov ? 'selected' : '' ?>><?= e($ol) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <label class="form-label" for="f_<?= e($n) ?>"><?= e($f['label']) ?><?= $req ? ' *' : '' ?></label>
          <input class="form-control" type="<?= e($f['type']) ?>" name="<?= e($n) ?>" id="f_<?= e($n) ?>"
                 value="<?= e($v) ?>" <?= $req ? 'required' : '' ?>
                 <?= isset($f['max']) ? 'maxlength="' . (int)$f['max'] . '"' : '' ?>>
        <?php endif; ?>
        <?php if (!empty($f['help'])): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit">Сохранить</button>
      <a class="btn btn-outline-secondary" href="/admin/structure#<?= e($entity) ?>">Отмена</a>
    </div>
  </div>
</form>
