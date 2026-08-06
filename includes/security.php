<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $config = require __DIR__ . '/config.php';
    $secure = (bool)($config['force_https_cookie'] ?? false);
    if (!$secure) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
    session_name($config['session_name'] ?? 'NAV_ADMIN_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    }
    if (time() - (int)$_SESSION['created_at'] > 3600) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }
}

function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    admin_session_start();
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$token)) {
        http_response_code(419);
        exit('CSRF 安全校验失败，请刷新页面后重试。');
    }
}

function admin_is_logged_in(): bool
{
    admin_session_start();
    if (empty($_SESSION['admin_user_id'])) {
        return false;
    }
    $fingerprint = hash('sha256', client_ip() . '|' . user_agent());
    return hash_equals($_SESSION['fingerprint'] ?? '', $fingerprint);
}

function require_admin(): void
{
    if (!admin_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function admin_login(string $username, string $password): array
{
    admin_session_start();
    $config = require __DIR__ . '/config.php';
    $pdo = db();
    $ip = client_ip();
    $window = (int)($config['login_window_minutes'] ?? 15);
    $maxFailures = (int)($config['login_max_failures'] ?? 5);
    $lockMinutes = (int)($config['login_lock_minutes'] ?? 15);

    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)');
    $stmt->execute([$ip, $window]);
    $failures = (int)$stmt->fetchColumn();
    if ($failures >= $maxFailures) {
        return [false, "登录失败次数过多，请 {$lockMinutes} 分钟后再试。"];
    }

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    $ok = $user && password_verify($password, $user['password_hash']);

    $log = $pdo->prepare('INSERT INTO login_attempts (username, ip, user_agent, success) VALUES (?, ?, ?, ?)');
    $log->execute([$username, $ip, user_agent(), $ok ? 1 : 0]);

    if (!$ok) {
        return [false, '账号或密码错误'];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$newHash, $user['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int)$user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['fingerprint'] = hash('sha256', client_ip() . '|' . user_agent());
    $_SESSION['created_at'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return [true, '登录成功'];
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
}
