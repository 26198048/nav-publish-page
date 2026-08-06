<?php
require_once __DIR__ . '/../includes/security.php';
security_headers();
admin_session_start();
if (admin_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$ok, $msg] = admin_login(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''));
    if ($ok) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $msg;
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>后台登录</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>后台登录</h1>
    <p class="muted">前台不显示后台入口，请通过 /admin/ 手动访问。</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>账号</label>
      <input name="username" autocomplete="username" required>
      <label>密码</label>
      <input type="password" name="password" autocomplete="current-password" required>
      <button class="btn" type="submit">登录</button>
    </form>
  </div>
</div>
</body>
</html>
