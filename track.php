<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = (string)($_POST['action'] ?? 'popup');
$action = in_array($action, ['popup', 'copy'], true) ? $action : 'popup';
if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'invalid id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();
ensure_runtime_schema();
$stmt = $pdo->prepare('SELECT id FROM buttons WHERE id = ? AND is_enabled = 1 AND action_type = ? LIMIT 1');
$stmt->execute([$id, $action]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo->beginTransaction();
$update = $pdo->prepare('UPDATE buttons SET click_count = click_count + 1 WHERE id = ?');
$update->execute([$id]);
$log = $pdo->prepare('INSERT INTO click_logs (button_id, action_type, ip, user_agent, referer) VALUES (?, ?, ?, ?, ?)');
$log->execute([$id, $action, client_ip(), user_agent(), substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500)]);
$pdo->commit();

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
