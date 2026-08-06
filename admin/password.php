<?php
require_once __DIR__ . '/_common.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    try {
        if (strlen($new) < 8) throw new RuntimeException('新密码至少 8 位。');
        if ($new !== $confirm) throw new RuntimeException('两次输入的新密码不一致。');
        $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_user_id']]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($old, $hash)) throw new RuntimeException('原密码错误。');
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newHash, $_SESSION['admin_user_id']]);
        flash_set('success', '密码已修改，下次登录请使用新密码。');
        redirect_admin('password.php');
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
        redirect_admin('password.php');
    }
}
admin_header('修改密码');
flash_show();
?>
<section class="panel narrow">
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>原密码</label><input type="password" name="old_password" autocomplete="current-password" required>
    <label>新密码</label><input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
    <label>确认新密码</label><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
    <button class="btn" type="submit">修改密码</button>
  </form>
</section>
<?php admin_footer(); ?>
