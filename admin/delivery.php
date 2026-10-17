<?php
require __DIR__ . '/_layout.php';
require_admin();

$errors = [];
if (is_post()) {
    csrf_check();
    $newRates = [];
    foreach (['inside', 'outside'] as $zone) {
        $mins = (array)($_POST[$zone]['min'] ?? []);
        $maxs = (array)($_POST[$zone]['max'] ?? []);
        $charges = (array)($_POST[$zone]['charge'] ?? []);
        $list = [];
        foreach ($mins as $i => $min) {
            $min = trim((string)$min);
            $max = trim((string)($maxs[$i] ?? ''));
            $ch = trim((string)($charges[$i] ?? ''));
            if ($min === '' && $max === '' && $ch === '') {
                continue; // empty row
            }
            if (!is_numeric($min) || !is_numeric($max) || !is_numeric($ch)) {
                $errors[] = ucfirst($zone) . ' Dhaka: every row needs From, To and Charge as numbers.';
                continue;
            }
            if ((float)$max <= (float)$min) {
                $errors[] = ucfirst($zone) . " Dhaka: 'To' must be bigger than 'From' ($min – $max kg).";
                continue;
            }
            $list[] = [(float)$min, (float)$max, (float)$ch];
        }
        usort($list, fn($a, $b) => $a[0] <=> $b[0]);
        if (!$list) {
            $errors[] = ucfirst($zone) . ' Dhaka needs at least one weight range.';
        }
        $newRates[$zone] = $list;
    }
    if (!$errors) {
        db()->beginTransaction();
        q('DELETE FROM delivery_rates');
        foreach ($newRates as $zone => $list) {
            foreach ($list as $r) {
                insert('delivery_rates', ['zone' => $zone, 'min_kg' => $r[0], 'max_kg' => $r[1], 'charge' => $r[2]]);
            }
        }
        db()->commit();
        save_setting('extra_per_kg_inside', (string)max(0, (float)($_POST['extra_inside'] ?? 0)));
        save_setting('extra_per_kg_outside', (string)max(0, (float)($_POST['extra_outside'] ?? 0)));
        save_setting('free_delivery_over', (string)max(0, (float)($_POST['free_over'] ?? 0)));
        flash('success', 'Delivery charges saved.');
        redirect('admin/delivery.php');
    }
}

admin_header('Delivery charges', 'delivery');
$zones = ['inside' => 'Inside Dhaka', 'outside' => 'Outside Dhaka'];
?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
<p class="hint">The delivery charge is worked out from the <strong>total weight</strong> of the order (each product's weight × quantity). Set the weight of each product on its edit page.</p>
<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="cols">
    <?php foreach ($zones as $zone => $label): $rates = delivery_rates($zone); ?>
      <div class="panel">
        <h2><?= e($label) ?></h2>
        <table class="table rate-table" data-zone="<?= $zone ?>">
          <thead><tr><th>From (kg)</th><th>To (kg)</th><th>Charge (৳)</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rates as $r): ?>
              <tr>
                <td><input class="mini" type="number" step="0.001" min="0" name="<?= $zone ?>[min][]" value="<?= e((float)$r['min_kg']) ?>"></td>
                <td><input class="mini" type="number" step="0.001" min="0" name="<?= $zone ?>[max][]" value="<?= e((float)$r['max_kg']) ?>"></td>
                <td><input class="mini" type="number" step="1" min="0" name="<?= $zone ?>[charge][]" value="<?= e((float)$r['charge']) ?>"></td>
                <td><button type="button" class="linklike neg" data-remove-row>Remove</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="button" class="btn btn-light btn-sm" data-add-row="<?= $zone ?>">+ Add weight range</button>
        <label class="mt">For every extra kg above the last range, add (৳)
          <input class="mini" type="number" step="1" min="0" name="extra_<?= $zone ?>" value="<?= e(setting('extra_per_kg_' . $zone, '0')) ?>"></label>
        <p class="hint">Example: if the last range is 2–5 kg = ৳<?= e((float)(end($rates)['charge'] ?? 0)) ?>, a 6.5 kg order costs ৳<?= e((float)(end($rates)['charge'] ?? 0)) ?> + 2 × extra.</p>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <h2>Free delivery (optional)</h2>
    <label>Free delivery when the order subtotal is at least (৳) — 0 means never free
      <input class="mini" type="number" step="1" min="0" name="free_over" value="<?= e(setting('free_delivery_over', '0')) ?>"></label>
  </div>
  <button class="btn" type="submit">Save delivery charges</button>
</form>
<template id="rateRow">
  <tr>
    <td><input class="mini" type="number" step="0.001" min="0" data-n="min"></td>
    <td><input class="mini" type="number" step="0.001" min="0" data-n="max"></td>
    <td><input class="mini" type="number" step="1" min="0" data-n="charge"></td>
    <td><button type="button" class="linklike neg" data-remove-row>Remove</button></td>
  </tr>
</template>
<?php admin_footer(); ?>
