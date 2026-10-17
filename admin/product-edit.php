<?php
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    flash('error', 'Product not found.');
    redirect('admin/products.php');
}
$errors = [];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete' && $p) {
        foreach (rows('SELECT path FROM product_images WHERE product_id = ?', [$id]) as $im) {
            delete_upload($im['path']);
        }
        delete_upload($p['image']);
        q('DELETE FROM product_images WHERE product_id = ?', [$id]);
        q('UPDATE order_items SET product_id = NULL WHERE product_id = ?', [$id]);
        q('DELETE FROM stock_log WHERE product_id = ?', [$id]);
        q('DELETE FROM products WHERE id = ?', [$id]);
        flash('success', 'Product "' . $p['name'] . '" deleted. Old orders still show it.');
        redirect('admin/products.php');
    }

    if ($action === 'duplicate' && $p) {
        $copy = $p;
        unset($copy['id']);
        $copy['name'] = $p['name'] . ' (copy)';
        $copy['slug'] = unique_slug('products', $copy['name']);
        $copy['sku'] = $p['sku'] ? $p['sku'] . '-COPY' : null;
        $copy['is_active'] = 0;
        $copy['quantity'] = 0;
        $copy['image'] = null; // pictures are not shared, so deleting one product never removes the other's photo
        $copy['created_at'] = $copy['updated_at'] = now();
        $newId = insert('products', $copy);
        flash('success', 'Copy created (hidden, stock 0). Add pictures and stock, then tick "Show in shop".');
        redirect('admin/product-edit.php?id=' . $newId);
    }

    $f = [
        'name' => clean('name', 200),
        'name_bn' => clean('name_bn', 200),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'sku' => clean('sku', 64),
        'price' => (float)str_replace(',', '', (string)($_POST['price'] ?? '0')),
        'sale_price' => trim((string)($_POST['sale_price'] ?? '')) === '' ? null : (float)str_replace(',', '', (string)$_POST['sale_price']),
        'quantity' => (int)($_POST['quantity'] ?? 0),
        'weight' => (float)($_POST['weight'] ?? 0),
        'description' => clean('description', 10000),
        'description_bn' => clean('description_bn', 10000),
        'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    if ($f['name'] === '') {
        $errors[] = 'Please enter the product name.';
    }
    if (!$f['category_id'] || !val('SELECT COUNT(*) FROM categories WHERE id = ?', [$f['category_id']])) {
        $errors[] = 'Please choose a category.';
    }
    if ($f['price'] <= 0) {
        $errors[] = 'Price must be more than 0.';
    }
    if ($f['sale_price'] !== null && ($f['sale_price'] <= 0 || $f['sale_price'] >= $f['price'])) {
        $errors[] = 'Sale price must be lower than the regular price (or leave it empty).';
    }
    if ($f['quantity'] < 0) {
        $errors[] = 'Quantity cannot be negative.';
    }
    if ($f['weight'] < 0) {
        $errors[] = 'Weight cannot be negative.';
    }

    $mainImage = $p['image'] ?? null;
    $newMain = null;
    $newGallery = [];
    if (!$errors) {
        try {
            if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $newMain = save_uploaded_image($_FILES['image']);
            }
            foreach (files_array('gallery') as $gf) {
                $newGallery[] = save_uploaded_image($gf);
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
            delete_upload($newMain);
            foreach ($newGallery as $g) {
                delete_upload($g);
            }
            $newMain = null;
            $newGallery = [];
        }
    }

    if (!$errors) {
        if ($newMain) {
            delete_upload($mainImage);
            $mainImage = $newMain;
        } elseif (!empty($_POST['remove_image'])) {
            delete_upload($mainImage);
            $mainImage = null;
        }
        $f['image'] = $mainImage;
        $f['updated_at'] = now();
        if ($p) {
            $f['slug'] = unique_slug('products', clean('slug', 150) ?: $f['name'], $id);
            update('products', $f, 'id = ?', [$id]);
            if ((int)$p['quantity'] !== $f['quantity']) {
                log_stock($id, $f['quantity'] - (int)$p['quantity'], $f['quantity'], 'Edited in product page');
            }
        } else {
            $f['slug'] = unique_slug('products', $f['name']);
            $f['created_at'] = $f['updated_at'];
            $id = insert('products', $f);
            log_stock($id, $f['quantity'], $f['quantity'], 'New product');
        }
        // Gallery: remove ticked, add new
        foreach ((array)($_POST['remove_gallery'] ?? []) as $gid) {
            $g = row('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int)$gid, $id]);
            if ($g) {
                delete_upload($g['path']);
                q('DELETE FROM product_images WHERE id = ?', [$g['id']]);
            }
        }
        $sort = (int)val('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?', [$id]);
        foreach ($newGallery as $g) {
            insert('product_images', ['product_id' => $id, 'path' => $g, 'sort_order' => ++$sort]);
        }
        // If there is no main photo but there are gallery photos, use the first one
        if (!$mainImage) {
            $first = row('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$id]);
            if ($first) {
                update('products', ['image' => $first['path']], 'id = ?', [$id]);
                q('DELETE FROM product_images WHERE id = ?', [$first['id']]);
            }
        }
        flash('success', 'Product "' . $f['name'] . '" saved.');
        redirect(($_POST['after'] ?? '') === 'new' ? 'admin/product-edit.php?cat=' . $f['category_id'] : 'admin/product-edit.php?id=' . $id);
    }
    $p = array_merge($p ?? [], $f, ['image' => $mainImage]);
}

$f = $p ?? [
    'id' => 0, 'name' => '', 'name_bn' => '', 'slug' => '', 'category_id' => (int)($_GET['cat'] ?? 0), 'sku' => '',
    'price' => '', 'sale_price' => '', 'quantity' => 1, 'weight' => '0.5', 'description' => '', 'description_bn' => '',
    'image' => '', 'is_featured' => 0, 'is_active' => 1,
];
$gallery = !empty($f['id']) ? rows('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$f['id']]) : [];
$log = !empty($f['id']) ? rows('SELECT * FROM stock_log WHERE product_id = ? ORDER BY id DESC LIMIT 8', [$f['id']]) : [];

admin_header(!empty($f['id']) ? 'Edit product' : 'Add product', 'products');
?>
<p><a href="products.php">← All products</a><?php if (!empty($f['id']) && !empty($f['slug'])): ?> · <a href="<?= e(url('product.php?slug=' . urlencode($f['slug']))) ?>" target="_blank">View in shop ↗</a><?php endif; ?></p>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form cols cols-wide-left" id="productForm">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div>
    <div class="panel">
      <h2>Basic information</h2>
      <label>Product name (English) *<input name="name" value="<?= e($f['name']) ?>" required maxlength="200"></label>
      <label>Product name (বাংলা)<input name="name_bn" value="<?= e($f['name_bn']) ?>" maxlength="200"></label>
      <div class="grid2">
        <label>Category *<select name="category_id" required><option value="">Choose…</option><?= category_options((int)$f['category_id'] ?: null) ?></select></label>
        <label>Product code (SKU)<input name="sku" value="<?= e($f['sku']) ?>" placeholder="optional, e.g. BAG-001"></label>
      </div>
      <label>Description (English)<textarea name="description" rows="5" placeholder="Material, size, colour, age group…"><?= e($f['description']) ?></textarea></label>
      <label>Description (বাংলা)<textarea name="description_bn" rows="5"><?= e($f['description_bn']) ?></textarea></label>
      <?php if (!empty($f['id'])): ?><label>Web address name<input name="slug" value="<?= e($f['slug']) ?>"></label><?php endif; ?>
    </div>

    <div class="panel">
      <h2>Pictures</h2>
      <label>Main picture
        <?php if (!empty($f['image'])): ?><span class="thumb-row"><img src="<?= e(img_url($f['image'])) ?>" alt="" width="96" height="96"><label class="check"><input type="checkbox" name="remove_image" value="1"> Remove</label></span><?php endif; ?>
        <input type="file" name="image" accept="image/*" data-preview="mainPreview">
        <img id="mainPreview" class="preview" alt="" hidden>
      </label>
      <label>More pictures (you can choose several)
        <input type="file" name="gallery[]" accept="image/*" multiple>
      </label>
      <?php if ($gallery): ?>
        <div class="gallery-admin">
          <?php foreach ($gallery as $g): ?>
            <label><img src="<?= e(img_url($g['path'])) ?>" alt="" width="96" height="96"><span class="check"><input type="checkbox" name="remove_gallery[]" value="<?= (int)$g['id'] ?>"> Remove</span></label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="hint">JPG, PNG or WEBP. Big photos are resized automatically. Square photos look best.</p>
    </div>
  </div>

  <div>
    <div class="panel">
      <h2>Price &amp; stock</h2>
      <div class="grid2">
        <label>Price (৳) *<input type="number" name="price" value="<?= e($f['price']) ?>" min="1" step="0.01" required inputmode="decimal"></label>
        <label>Sale price (৳)<input type="number" name="sale_price" value="<?= e($f['sale_price']) ?>" min="0" step="0.01" inputmode="decimal" placeholder="optional"></label>
      </div>
      <div class="grid2">
        <label>Quantity in stock *<input type="number" name="quantity" value="<?= e($f['quantity']) ?>" min="0" step="1" required></label>
        <label>Weight (kg) *<input type="number" name="weight" value="<?= e($f['weight']) ?>" min="0" step="0.001" required inputmode="decimal"><small class="hint">Used for the delivery charge. 500 g = 0.5</small></label>
      </div>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= !empty($f['is_active']) ? 'checked' : '' ?>> Show in shop</label>
      <label class="check"><input type="checkbox" name="is_featured" value="1" <?= !empty($f['is_featured']) ? 'checked' : '' ?>> Featured on home page</label>
    </div>
    <div class="panel sticky-actions">
      <button class="btn btn-block" type="submit">Save product</button>
      <?php if (empty($f['id'])): ?><button class="btn btn-light btn-block" type="submit" name="after" value="new">Save &amp; add another</button><?php endif; ?>
    </div>
    <?php if ($log): ?>
      <div class="panel">
        <h2>Recent stock changes</h2>
        <ul class="list small">
          <?php foreach ($log as $l): ?>
            <li><span><?= e($l['reason']) ?><br><small class="muted"><?= e(date('d M, h:i A', strtotime($l['created_at']))) ?></small></span><span class="<?= $l['change_qty'] < 0 ? 'neg' : 'pos' ?>"><?= $l['change_qty'] > 0 ? '+' : '' ?><?= (int)$l['change_qty'] ?> → <?= (int)$l['qty_after'] ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php if (!empty($f['id'])): ?>
<div class="danger-zone">
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><button class="btn btn-light btn-sm" type="submit">Make a copy</button></form>
  <form method="post" class="inline" onsubmit="return confirm('Delete this product for good? Old orders will still show its name.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Delete product</button></form>
  <span class="hint">Tip: untick "Show in shop" to hide a product without deleting it.</span>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
