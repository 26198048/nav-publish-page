<?php
require_once __DIR__ . '/../includes/security.php';
security_headers();
admin_session_start();

function admin_header(string $title): void
{
    $username = $_SESSION['admin_username'] ?? 'admin';
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> - 后台管理</title>
<link rel="stylesheet" href="../assets/css/admin.css?v=final-20260514-search-category-copy">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <div class="brand">导航后台</div>
    <nav>
      <a href="dashboard.php">总览</a>
      <a href="buttons.php">按钮管理</a>
      <a href="settings.php">站点设置</a>
      <a href="password.php">修改密码</a>
      <a href="../" target="_blank" rel="noopener">预览首页</a>
      <a href="logout.php">退出登录</a>
    </nav>
  </aside>
  <main class="admin-main">
    <div class="topbar">
      <div><h1><?= e($title) ?></h1><p>当前账号：<?= e($username) ?></p></div>
    </div>
<?php
}

function admin_footer(): void
{
    ?>
  </main>
</div>
<script src="../assets/js/admin.js?v=final-20260514-search-category-copy"></script>
</body>
</html>
<?php
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_show(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $type = $f['type'] === 'error' ? 'error' : 'success';
        echo '<div class="alert ' . e($type) . '">' . e($f['message']) . '</div>';
    }
}

function redirect_admin(string $path): void
{
    header('Location: ' . $path);
    exit;
}
