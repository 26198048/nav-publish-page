<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit('链接不存在');
}

$pdo = db();
ensure_runtime_schema();
$stmt = $pdo->prepare('SELECT * FROM buttons WHERE id = ? AND is_enabled = 1 AND action_type = "link" LIMIT 1');
$stmt->execute([$id]);
$button = $stmt->fetch();

if (!$button || !is_valid_http_url((string)$button['link_url'])) {
    http_response_code(404);
    exit('链接不存在、未启用，或链接格式不正确');
}

$pdo->beginTransaction();
$update = $pdo->prepare('UPDATE buttons SET click_count = click_count + 1 WHERE id = ?');
$update->execute([$id]);
$log = $pdo->prepare('INSERT INTO click_logs (button_id, action_type, ip, user_agent, referer) VALUES (?, "link", ?, ?, ?)');
$log->execute([$id, client_ip(), user_agent(), substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500)]);
$pdo->commit();

header('Location: ' . $button['link_url'], true, 302);
exit;
