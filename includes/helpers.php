<?php
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_url_path(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    if ($base === '/' || $base === '.') {
        $base = '';
    }
    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

function is_valid_http_url(string $url): bool
{
    $url = trim($url);
    return (bool)(filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url));
}

function normalize_color(?string $color, string $fallback = '#2FD18A'): string
{
    $color = trim((string)$color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $fallback;
}



function is_image_icon(string $icon): bool
{
    $icon = trim($icon);
    // 只允许本站内置图标或后台上传图标作为图片渲染，避免把任意 URL 当图片插入。
    return (bool)preg_match('/^(?:assets\/img\/[A-Za-z0-9._\/-]+|i\/[a-f0-9]{12}\.(?:png|jpe?g|gif|webp))$/i', $icon);
}

function local_asset_src(string $path): string
{
    $path = ltrim(trim($path), '/');
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    // 后台页面位于 /admin/ 下，图片路径需要返回上一级；前台保持原相对路径。
    if (preg_match('#/admin(?:/|$)#', $script)) {
        return '../' . $path;
    }
    return $path;
}

function media_src(?string $path, string $fallback = 'assets/img/logo.png'): string
{
    $path = trim((string)$path);
    if ($path === '') {
        $path = $fallback;
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return local_asset_src($path);
}

function render_icon_html(?string $icon, string $fallback = '🔗'): string
{
    $icon = trim((string)$icon);
    if ($icon === '') {
        $icon = $fallback;
    }
    if (is_image_icon($icon)) {
        return '<img src="' . e(local_asset_src($icon)) . '" alt="" loading="lazy" decoding="async">';
    }
    return e(mb_substr($icon, 0, 30));
}


function background_presets(): array
{
    return [
        'aurora_dark' => [
            'name' => '极光深色',
            'css' => 'radial-gradient(circle at 12% 15%, rgba(47,209,138,.30), transparent 34%), radial-gradient(circle at 82% 8%, rgba(91,141,255,.20), transparent 34%), linear-gradient(135deg, #05070b 0%, #101723 55%, #07130f 100%)',
        ],
        'tech_blue' => [
            'name' => '科技蓝',
            'css' => 'radial-gradient(circle at 20% 20%, rgba(59,130,246,.30), transparent 32%), radial-gradient(circle at 80% 30%, rgba(14,165,233,.18), transparent 38%), linear-gradient(135deg, #06111f 0%, #0d172a 48%, #020617 100%)',
        ],
        'carbon_grid' => [
            'name' => '碳黑网格',
            'css' => 'linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px), linear-gradient(135deg, #080b10 0%, #121820 100%)',
        ],
        'purple_neon' => [
            'name' => '紫色霓虹',
            'css' => 'radial-gradient(circle at 18% 18%, rgba(168,85,247,.28), transparent 34%), radial-gradient(circle at 82% 22%, rgba(236,72,153,.18), transparent 34%), linear-gradient(135deg, #12091f 0%, #0c1018 56%, #070710 100%)',
        ],
        'warm_gold' => [
            'name' => '暖金商务',
            'css' => 'radial-gradient(circle at 15% 18%, rgba(245,158,11,.22), transparent 35%), radial-gradient(circle at 80% 12%, rgba(250,204,21,.12), transparent 34%), linear-gradient(135deg, #11100b 0%, #171717 55%, #0b0c10 100%)',
        ],
        'pure_dark' => [
            'name' => '纯净深色',
            'css' => 'linear-gradient(135deg, #05070b 0%, #0b1017 100%)',
        ],
    ];
}

function normalize_background_value(?string $value, string $fallback = 'preset:aurora_dark'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }
    if (preg_match('/^preset:([a-z0-9_\-]+)$/i', $value, $m)) {
        $key = $m[1];
        return array_key_exists($key, background_presets()) ? 'preset:' . $key : $fallback;
    }
    if (is_background_image($value)) {
        return $value;
    }
    return $fallback;
}

function is_background_image(string $value): bool
{
    $value = trim($value);
    return (bool)preg_match('/^b\/[a-f0-9]{16}\.(?:png|jpe?g|gif|webp)$/i', $value);
}

function background_value_to_css(?string $value): string
{
    $value = normalize_background_value($value);
    if (strncmp($value, 'preset:', 7) === 0) {
        $key = substr($value, 7);
        $presets = background_presets();
        return $presets[$key]['css'] ?? $presets['aurora_dark']['css'];
    }
    if (is_background_image($value)) {
        return "url('" . str_replace("'", "%27", $value) . "')";
    }
    $presets = background_presets();
    return $presets['aurora_dark']['css'];
}

function background_select_value(?string $value): string
{
    $value = normalize_background_value($value);
    return strncmp($value, 'preset:', 7) === 0 ? substr($value, 7) : 'custom';
}

function settings_get_all(): array
{
    $stmt = db()->query('SELECT setting_key, setting_value FROM site_settings');
    $rows = $stmt->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        $settings = settings_get_all();
    }
    return (string)($settings[$key] ?? $default);
}

function setting_save(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
}

function client_ip(): string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return substr($ip, 0, 45);
}

function user_agent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}

function button_action_label(string $action): string
{
    if ($action === 'popup') {
        return '弹窗提示';
    }
    if ($action === 'copy') {
        return '点击复制';
    }
    return '跳转链接';
}

function bool_setting(array $settings, string $key, bool $default = true): bool
{
    if (!array_key_exists($key, $settings)) {
        return $default;
    }
    $value = strtolower(trim((string)$settings[$key]));
    return !in_array($value, ['0', 'false', 'off', 'no', ''], true);
}

function schema_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

function ensure_runtime_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = db();
    try {
        // 兼容旧版数据库：自动补齐新增字段与复制交互类型。
        if (!schema_column_exists($pdo, 'buttons', 'category')) {
            $pdo->exec("ALTER TABLE `buttons` ADD COLUMN `category` VARCHAR(80) NOT NULL DEFAULT '默认分类' AFTER `description`");
        }
        if (!schema_column_exists($pdo, 'buttons', 'copy_content')) {
            $pdo->exec("ALTER TABLE `buttons` ADD COLUMN `copy_content` TEXT NULL AFTER `popup_content`");
        }
        // ENUM 修改可重复执行，保证旧版 link/popup 能升级到 copy。
        $pdo->exec("ALTER TABLE `buttons` MODIFY `action_type` ENUM('link','popup','copy') NOT NULL DEFAULT 'link'");
        $pdo->exec("ALTER TABLE `click_logs` MODIFY `action_type` ENUM('link','popup','copy') NOT NULL");

        $defaults = [
            'enable_search' => '1',
            'enable_categories' => '1',
        ];
        $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = setting_value');
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    } catch (Throwable $e) {
        // 自动升级失败时不直接中断页面，后续 SQL 会给出更明确的错误提示。
    }
}

