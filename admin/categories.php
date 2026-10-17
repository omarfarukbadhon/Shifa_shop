<?php
require __DIR__ . '/_layout.php';
require_admin();

$editId = (int)($_GET['edit'] ?? 0);
$edit = $editId ? row('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
$errors = [];

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $subs = (int)val('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$id]);
        $prods = (int)val('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
        if ($subs || $prods) {
            flash('error', 'This category still has ' . ($subs ? $subs . ' sub-categories' : '') . ($subs && $prods ? ' and ' : '') . ($prods ? $prods . ' products' : '') . '. Move or delete them first — or just hide the category.');
        } else {
            $c = row('SELECT image FROM categories WHERE id = ?', [$id]);
            delete_upload($c['image'] ?? null);
            q('DELETE FROM categories WHERE id = ?', [$id]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = clean('name', 150);
        $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($name === '') {
            $errors[] = 'Please enter a category name.';
        }
        if ($parent) {
            $p = row('SELECT * FROM categories WHERE id = ?', [$parent]);
            if (!$p || $p['parent_id']) {
                $errors[] = 'A sub-category must be placed under a main category.';
            }
            if ($id && $parent === $id) {
                $errors[] = 'A category cannot be inside itself.';
            }
            if ($id && val('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$id])) {
                $errors[] = 'This category has its own sub-categories, so it must stay a main category.';
            }
        }
        $image = $id ? (string)val('SELECT image FROM categories WHERE id = ?', [$id]) : '';
        if (!$errors && !empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $new = save_uploaded_image($_FILES['image'], 'categories', 600);
                delete_upload($image);
                $image = $new;
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        if (!empty($_POST['remove_image'])) {
            delete_upload($image);
            $image = '';
        }
        if (!$errors) {
            $data = [
                'parent_id' => $parent,
                'name' => $name,
                'name_bn' => clean('name_bn', 150),
                'description' => clean('description', 2000),
                'image' => $image,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                'coming_soon' => !empty($_POST['coming_soon']) ? 1 : 0,
            ];
            if ($id) {
                $data['slug'] = unique_slug('categories', clean('slug', 150) ?: $name, $id);
                update('categories', $data, 'id = ?', [$id]);
                flash('success', 'Category "' . $name . '" saved.');
            } else {
                $data['slug'] = unique_slug('categories', $name);
                $data['created_at'] = now();
                insert('categories', $data);
                flash('success', 'Category "' . $name . '" added.');
            }
            redirect('admin/categories.php');
        }
        $edit = array_merge($edit ?? [], $_POST, ['image' => $image]);
    }
}

$mains = main_categories(false);
$counts = [];
foreach (rows('SELECT category_id, COUNT(*) n FROM products GROUP BY category_id') as $r) {
    $counts[(int)$r['category_id']] = (int)$r['n'];
}
$f = $edit ?? ['id' => 0, 'parent_id' => (int)($_GET['parent'] ?? 0) ?: null, 'name' => '', 'name_bn' => '', 'slug' => '', 'description' => '', 'image' => '', 'sort_order' => 0, 'is_active' => 1, 'coming_soon' => 0];

admin_header('Categories', 'categories');
?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>' . e($er) . '</div>'; ?></div><?php endif; ?>
<div class="cols cols-wide-left">
  <div class="panel">
    <div class="panel-head"><h2>Your categories</h2><a class="btn btn-sm" href="categories.php#form">+ Add main category</a></div>
    <p class="hint">Main categories show in the top menu. Sub-categories (like Baby Toy › Robot) hold your products.</p>
    <ul class="cat-tree">
      <?php foreach ($mains as $m): $subs = sub_categories((int)$m['id'], false); $total = ($counts[(int)$m['id']] ?? 0) + array_sum(array_map(fn($s) => $counts[(int)$s['id']] ?? 0, $subs)); ?>
        <li class="main-cat">
          <div class="cat-line">
            <img src="<?= e(img_url($m['image'], 'assets/img/cat-default.svg')) ?>" alt="" width="36" height="36">
            <div class="grow"><strong><?= e($m['name']) ?></strong> <?php if ($m['name_bn']): ?><span class="muted"><?= e($m['name_bn']) ?></span><?php endif; ?>
              <div class="tags">
                <span class="muted small"><?= $total ?> products</span>
                <?php if (!$m['is_active']): ?><span class="pill pill-cancelled">Hidden</span><?php endif; ?>
                <?php if ($m['coming_soon']): ?><span class="pill pill-warn">Shows "available soon"</span><?php endif; ?>
              </div>
            </div>
            <a class="btn btn-sm btn-light" href="categories.php?parent=<?= (int)$m['id'] ?>#form">+ Sub-category</a>
            <a class="btn btn-sm btn-light" href="categories.php?edit=<?= (int)$m['id'] ?>#form">Edit</a>
            <a class="btn btn-sm btn-light" href="stock.php?cat=<?= (int)$m['id'] ?>">Stock</a>
          </div>
          <?php if ($subs): ?>
            <ul>
              <?php foreach ($subs as $s): ?>
                <li class="cat-line sub">
                  <div class="grow"><?= e($s['name']) ?> <?php if ($s['name_bn']): ?><span class="muted"><?= e($s['name_bn']) ?></span><?php endif; ?>
                    <span class="muted small">· <?= $counts[(int)$s['id']] ?? 0 ?> products</span>
                    <?php if (!$s['is_active']): ?><span class="pill pill-cancelled">Hidden</span><?php endif; ?>
                    <?php if ($s['coming_soon']): ?><span class="pill pill-warn">Available soon</span><?php endif; ?>
                  </div>
                  <a class="btn btn-sm btn-light" href="product-edit.php?cat=<?= (int)$s['id'] ?>">+ Product</a>
                  <a class="btn btn-sm btn-light" href="products.php?cat=<?= (int)$s['id'] ?>">Products</a>
                  <a class="btn btn-sm btn-light" href="categories.php?edit=<?= (int)$s['id'] ?>#form">Edit</a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="panel" id="form">
    <h2><?= !empty($f['id']) ? 'Edit category' : ($f['parent_id'] ? 'Add sub-category' : 'Add category') ?></h2>
    <form method="post" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <label>Place it under
        <select name="parent_id">
          <option value="">— Nothing (it is a main category) —</option>
          <?php foreach ($mains as $m): if ((int)$m['id'] === (int)$f['id']) continue; ?>
            <option value="<?= (int)$m['id'] ?>" <?= (int)$f['parent_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Name (English) *<input name="name" value="<?= e($f['name']) ?>" required placeholder="e.g. Educational Toy"></label>
      <label>Name (বাংলা)<input name="name_bn" value="<?= e($f['name_bn']) ?>" placeholder="যেমন: শিক্ষামূলক খেলনা"></label>
      <?php if (!empty($f['id'])): ?><label>Web address name<input name="slug" value="<?= e($f['slug']) ?>"><small class="hint">Used in the link: category.php?slug=<?= e($f['slug']) ?></small></label><?php endif; ?>
      <label>Short description<textarea name="description" rows="2"><?= e($f['description']) ?></textarea></label>
      <label>Picture (shown on the home page for main categories)
        <?php if (!empty($f['image'])): ?><span class="thumb-row"><img src="<?= e(img_url($f['image'])) ?>" alt="" width="56" height="56"><label class="check"><input type="checkbox" name="remove_image" value="1"> Remove picture</label></span><?php endif; ?>
        <input type="file" name="image" accept="image/*">
      </label>
      <div class="grid2">
        <label>Order in menu<input type="number" name="sort_order" value="<?= (int)$f['sort_order'] ?>"><small class="hint">Smaller number shows first</small></label>
        <div>
          <label class="check"><input type="checkbox" name="is_active" value="1" <?= !empty($f['is_active']) ? 'checked' : '' ?>> Show in shop</label>
          <label class="check"><input type="checkbox" name="coming_soon" value="1" <?= !empty($f['coming_soon']) ? 'checked' : '' ?>> Show "Will be available soon"</label>
        </div>
      </div>
      <div class="btn-row left">
        <button class="btn" type="submit">Save category</button>
        <?php if (!empty($f['id'])): ?><a class="btn btn-light" href="categories.php">Cancel</a><?php endif; ?>
      </div>
    </form>
    <?php if (!empty($f['id'])): ?>
      <form method="post" class="danger-zone" onsubmit="return confirm('Delete this category? This cannot be undone.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
        <button class="btn btn-danger btn-sm" type="submit">Delete category</button>
        <span class="hint">Only empty categories can be deleted.</span>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
