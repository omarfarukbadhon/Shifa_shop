<?php
require __DIR__ . '/_layout.php';
$admin = require_admin();

function campaign_products(array $campaign): array
{
    $ids = array_filter(array_map('intval', explode(',', (string)$campaign['product_ids'])));
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return rows("SELECT * FROM products WHERE id IN ($ph) AND is_active = 1", $ids);
}

if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Send the next small batch (called again and again by the page until done)
    if ($action === 'batch') {
        header('Content-Type: application/json');
        @set_time_limit(120);
        $c = row('SELECT * FROM campaigns WHERE id = ?', [(int)$_POST['id']]);
        if (!$c || $c['status'] === 'done') {
            echo json_encode(['done' => true, 'sent' => (int)($c['sent'] ?? 0), 'failed' => (int)($c['failed'] ?? 0), 'total' => (int)($c['total'] ?? 0)]);
            exit;
        }
        $products = campaign_products($c);
        $batch = rows('SELECT id, name, email FROM customers WHERE marketing_opt_in = 1 AND id > ? ORDER BY id LIMIT 10', [(int)$c['last_customer_id']]);
        $sent = 0;
        $failed = 0;
        $lastId = (int)$c['last_customer_id'];
        $errors = [];
        foreach ($batch as $cu) {
            $html = marketing_email_html($c, $products, $cu['name'], $cu['email']);
            if (send_mail($cu['email'], $c['subject'], $html, ['to_name' => $cu['name'], 'unsubscribe' => unsubscribe_url($cu['email'])])) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $cu['email'] . ': ' . $GLOBALS['MAIL_ERROR'];
            }
            $lastId = (int)$cu['id'];
        }
        $done = count($batch) < 10;
        q('UPDATE campaigns SET sent = sent + ?, failed = failed + ?, last_customer_id = ?, status = ? WHERE id = ?', [$sent, $failed, $lastId, $done ? 'done' : 'sending', $c['id']]);
        $c = row('SELECT * FROM campaigns WHERE id = ?', [$c['id']]);
        echo json_encode(['done' => $done, 'sent' => (int)$c['sent'], 'failed' => (int)$c['failed'], 'total' => (int)$c['total'], 'errors' => $errors]);
        exit;
    }

    $subject = clean('subject', 200);
    $body = clean('body', 10000);
    $pids = implode(',', array_map('intval', (array)($_POST['products'] ?? [])));
    $draft = ['subject' => $subject, 'body' => $body, 'product_ids' => $pids];

    if ($action === 'preview') {
        echo marketing_email_html($draft, campaign_products($draft), explode(' ', $admin['name'])[0], $admin['email']);
        exit;
    }
    if ($subject === '' || $body === '') {
        flash('error', 'Please write a subject and a message.');
        $_SESSION['mk_draft'] = $draft;
        redirect('admin/marketing.php');
    }
    if ($action === 'test') {
        $to = setting('notify_email', $admin['email']);
        $ok = send_mail($to, '[TEST] ' . $subject, marketing_email_html($draft, campaign_products($draft), explode(' ', $admin['name'])[0], $to), ['unsubscribe' => unsubscribe_url($to)]);
        flash($ok ? 'success' : 'error', $ok ? "Test email sent to $to. Check your inbox." : 'Test failed: ' . $GLOBALS['MAIL_ERROR']);
        $_SESSION['mk_draft'] = $draft;
        redirect('admin/marketing.php');
    }
    if ($action === 'send') {
        $total = (int)val('SELECT COUNT(*) FROM customers WHERE marketing_opt_in = 1');
        if (!$total) {
            flash('error', 'No customers have agreed to receive offer emails yet.');
            $_SESSION['mk_draft'] = $draft;
            redirect('admin/marketing.php');
        }
        $id = insert('campaigns', $draft + ['audience' => 'opted_in', 'total' => $total, 'status' => 'sending', 'created_at' => now()]);
        unset($_SESSION['mk_draft']);
        redirect('admin/marketing.php?sending=' . $id);
    }
    redirect('admin/marketing.php');
}

$draft = $_SESSION['mk_draft'] ?? null;
unset($_SESSION['mk_draft']);
$sendingId = (int)($_GET['sending'] ?? 0);
$sending = $sendingId ? row('SELECT * FROM campaigns WHERE id = ?', [$sendingId]) : null;
$audience = (int)val('SELECT COUNT(*) FROM customers WHERE marketing_opt_in = 1');
$recentProducts = rows('SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 24');
$selected = $draft ? array_map('intval', explode(',', $draft['product_ids'])) : array_map(fn($p) => (int)$p['id'], array_slice($recentProducts, 0, 4));
$history = rows('SELECT * FROM campaigns ORDER BY id DESC LIMIT 20');

admin_header('Marketing email', 'marketing');
?>
<?php if ($sending && $sending['status'] !== 'done'): ?>
  <div class="panel" id="sender" data-id="<?= (int)$sending['id'] ?>">
    <h2>Sending “<?= e($sending['subject']) ?>”</h2>
    <p>Please keep this page open until it finishes.</p>
    <div class="progress-bar"><div id="pbar" style="width:0%"></div></div>
    <p id="ptext" class="muted">Starting…</p>
    <ul id="perrors" class="small neg"></ul>
  </div>
<?php endif; ?>

<div class="cols cols-wide-left">
  <form method="post" class="panel form" id="mkForm">
    <?= csrf_field() ?>
    <h2>New email to your customers</h2>
    <p class="hint">It goes to the <strong><?= $audience ?></strong> customer(s) who agreed to receive offers. Every email has an unsubscribe link.</p>
    <label>Subject *<input name="subject" value="<?= e($draft['subject'] ?? '') ?>" maxlength="200" required placeholder="e.g. New baby toys just arrived 🎁"></label>
    <label>Message *<textarea name="body" rows="7" required placeholder="Hi {name},&#10;&#10;We have new products you might love…"><?= e($draft['body'] ?? '') ?></textarea>
      <small class="hint">Write <code>{name}</code> and it becomes each customer's name.</small></label>
    <fieldset>
      <legend>Show these products in the email</legend>
      <?php if ($recentProducts): ?>
        <div class="pick-grid">
          <?php foreach ($recentProducts as $p): ?>
            <label class="pick"><input type="checkbox" name="products[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $selected, true) ? 'checked' : '' ?>>
              <img src="<?= e(img_url($p['image'])) ?>" alt="" width="64" height="64"><span><?= e($p['name']) ?><br><b><?= money(effective_price($p), false) ?></b></span></label>
          <?php endforeach; ?>
        </div>
      <?php else: ?><p class="muted">Add products first.</p><?php endif; ?>
    </fieldset>
    <div class="btn-row left">
      <button class="btn btn-light" type="submit" name="action" value="preview" formtarget="_blank" formnovalidate>Preview</button>
      <button class="btn btn-light" type="submit" name="action" value="test">Send test to me</button>
      <button class="btn" type="submit" name="action" value="send" onclick="return confirm('Send this email to <?= $audience ?> customer(s) now?')">Send to <?= $audience ?> customers</button>
    </div>
  </form>

  <div class="panel">
    <h2>Sent emails</h2>
    <?php if ($history): ?>
      <ul class="list">
        <?php foreach ($history as $h): ?>
          <li><span><strong><?= e($h['subject']) ?></strong><br><small class="muted"><?= e(date('d M Y, h:i A', strtotime($h['created_at']))) ?></small></span>
            <span class="right"><?= (int)$h['sent'] ?>/<?= (int)$h['total'] ?> sent<?= $h['failed'] ? '<br><small class="neg">' . (int)$h['failed'] . ' failed</small>' : '' ?>
            <?php if ($h['status'] !== 'done'): ?><br><a href="marketing.php?sending=<?= (int)$h['id'] ?>">Continue sending</a><?php endif; ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><p class="muted">Nothing sent yet.</p><?php endif; ?>
    <p class="hint">Tip: a normal Gmail account can only send a few hundred emails a day. When your customer list grows, use an email service such as Brevo, or your hosting company's email.</p>
  </div>
</div>
<?php admin_footer(); ?>
