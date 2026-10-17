<?php
require __DIR__ . '/includes/bootstrap.php';

$email = strtolower(trim((string)($_GET['e'] ?? '')));
$token = (string)($_GET['t'] ?? '');
$valid = $email !== '' && hash_equals(unsubscribe_token($email), $token);
$done = false;
if ($valid && is_post()) {
    csrf_check();
    q('UPDATE customers SET marketing_opt_in = 0 WHERE email = ?', [$email]);
    $done = true;
}
$pageTitle = t('unsubscribe');
require __DIR__ . '/includes/header.php';
?>
<div class="container section auth-wrap">
  <div class="card auth-card center">
    <h1><?= e(t('unsubscribe')) ?></h1>
    <?php if (!$valid): ?>
      <p><?= e(t('unsub_invalid')) ?></p>
    <?php elseif ($done): ?>
      <p><?= e(t('unsub_done')) ?></p>
      <a class="btn btn-light" href="<?= e(url()) ?>"><?= e(t('back_home')) ?></a>
    <?php else: ?>
      <p><?= e(t('unsub_confirm', ['email' => $email])) ?></p>
      <form method="post"><?= csrf_field() ?><button class="btn" type="submit"><?= e(t('unsubscribe')) ?></button></form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
