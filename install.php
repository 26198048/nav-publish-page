<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';
security_headers();

function form_value(string $key, string $default = ''): string
{
    return (string)($_POST[$key] ?? $default);
}

function run_schema(PDO $pdo, string $sqlFile): void
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('无法读取 database.sql。');
    }
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $queries = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($queries as $query) {
        if ($query !== '') {
            $pdo->exec($query);
        }
    }
}

function config_db_ready(): bool
{
    $cfg = require __DIR__ . '/includes/config.php';
    $db = $cfg['db'] ?? [];
    return !empty($db['host']) && !empty($db['database']) && !empty($db['username']);
}

function installed_by_database(): bool
{
    if (!config_db_ready()) {
        return false;
    }
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM admin_users");
        return ((int)($stmt->fetch()['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

// 免权限版：不再要求 PHP 写入 config.php，也不要求删除 install.php。
// 只要数据库中已有管理员账号，就视为已安装，安装页会自动跳转首页。
if (installed_by_database()) {
    header('Location: index.php');
    exit;
}

$error = '';
$configReady = config_db_ready();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = trim(form_value('admin_user', 'admin'));
    $adminPass = form_value('admin_pass');
    $adminPass2 = form_value('admin_pass_confirm');

    $siteTitle = trim(form_value('site_title', '安全导航发布页'));
    $pageTitle = trim(form_value('page_title', '发布页'));
    $subtitle = trim(form_value('subtitle', '请 Ctrl+D 收藏本页到浏览器收藏夹'));
    $permanentUrl = trim(form_value('permanent_url', ''));
    $notice = trim(form_value('notice', '所有入口均可在后台自由增删改。支持跳转链接、弹窗提示、点击复制三种交互方式。'));
    $logo = trim(form_value('logo', 'assets/img/logo.png'));
    $themeColor = normalize_color(form_value('theme_color', '#2FD18A'));
    $footer = trim(form_value('footer', '© ' . date('Y') . ' All Rights Reserved'));

    if (!$configReady) {
        $error = '请先用文件管理的铅笔图标编辑 includes/config.php，填入数据库信息并保存。';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $adminUser)) {
        $error = '后台账号只能使用 3-50 位英文、数字或下划线。';
    } elseif (strlen($adminPass) < 8) {
        $error = '后台密码至少 8 位。';
    } elseif ($adminPass !== $adminPass2) {
        $error = '两次输入的后台密码不一致。';
    } elseif ($permanentUrl !== '' && !is_valid_http_url($permanentUrl)) {
        $error = '永久地址必须以 http:// 或 https:// 开头。';
    } else {
        try {
            $pdo = db();
            run_schema($pdo, __DIR__ . '/database.sql');

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), updated_at = NOW()');
            $stmt->execute([$adminUser, $hash]);

            $settings = [
                'site_title' => $siteTitle ?: '安全导航发布页',
                'page_title' => $pageTitle ?: '发布页',
                'subtitle' => $subtitle,
                'permanent_url' => $permanentUrl,
                'notice' => $notice,
                'logo' => $logo ?: 'assets/img/logo.png',
                'theme_color' => $themeColor,
                'footer' => $footer,
                'desktop_background' => 'preset:aurora_dark',
                'mobile_background' => 'preset:tech_blue',
                'enable_search' => '1',
                'enable_categories' => '1',
            ];
            foreach ($settings as $key => $value) {
                save_setting($pdo, $key, (string)$value);
            }

            // 可写就创建安装锁；不可写也不影响使用，首页会通过数据库判断安装状态。
            @file_put_contents(__DIR__ . '/.installed', 'installed at ' . date('c'), LOCK_EX);
            // 可删就删；不可删也不影响，安装页会因已安装自动跳走。
            @unlink(__FILE__);

            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $error = '安装失败：' . $e->getMessage();
        }
    }
}

$defaultPermanent = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '你的域名') . '/';
$configExample = <<<'PHP'
<?php
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => '你的数据库名',
        'username' => '你的数据库用户名',
        'password' => '你的数据库密码',
        'charset' => 'utf8mb4',
    ],
    'setup_key' => 'MANUAL_CONFIG_MODE',
    'session_name' => 'NAV_ADMIN_SESSID',
    'force_https_cookie' => false,
    'login_max_failures' => 5,
    'login_window_minutes' => 15,
    'login_lock_minutes' => 15,
];
PHP;
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>安装导航站</title>
<link rel="stylesheet" href="assets/css/admin.css">
<style>
.install-page{min-height:100vh;background:#f4f7fb;padding:32px 16px;color:#1f2937;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif}.install-card{width:min(880px,100%);margin:0 auto;background:#fff;border-radius:22px;box-shadow:0 18px 55px rgba(20,30,50,.10);overflow:hidden}.install-head{padding:28px 32px;background:linear-gradient(135deg,#111827,#243244);color:#fff}.install-head h1{margin:0 0 8px;font-size:30px}.install-head p{margin:0;color:#cbd5e1;line-height:1.65}.install-body{padding:28px 32px}.install-section{padding:22px 0;border-bottom:1px solid #edf1f7}.install-section:first-child{padding-top:0}.install-section:last-child{border-bottom:0}.install-section h2{font-size:18px;margin:0 0 16px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field-full{grid-column:1/-1}.install-page label{display:block;margin-bottom:7px;font-weight:800;color:#374151}.install-page input,.install-page textarea{width:100%;border:1px solid #d8e0ea;border-radius:12px;padding:12px 13px;font-size:15px;background:#fff!important;color:#0f172a!important;caret-color:#2563eb;outline:none;-webkit-text-fill-color:#0f172a!important;box-sizing:border-box}.install-page input::placeholder,.install-page textarea::placeholder{color:#94a3b8!important;-webkit-text-fill-color:#94a3b8!important}.install-page input:-webkit-autofill,.install-page textarea:-webkit-autofill{box-shadow:0 0 0 1000px #fff inset!important;-webkit-box-shadow:0 0 0 1000px #fff inset!important;-webkit-text-fill-color:#0f172a!important;transition:background-color 9999s ease-in-out 0s}.install-page input::selection,.install-page textarea::selection{background:#bfdbfe;color:#0f172a}.install-page textarea{min-height:96px;resize:vertical}.install-page input:focus,.install-page textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.help{margin-top:6px;font-size:12px;color:#6b7280;line-height:1.5}.alert{padding:13px 15px;border-radius:14px;margin-bottom:18px;font-weight:700}.alert.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}.alert.warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}.actions{display:flex;gap:12px;align-items:center;justify-content:flex-end;padding-top:20px}.btn-install{border:0;border-radius:13px;background:#2563eb;color:#fff;font-weight:900;padding:13px 22px;cursor:pointer}.tips{background:#f8fafc;border:1px solid #e5edf6;border-radius:16px;padding:14px 16px;color:#475569;line-height:1.7}.codebox{background:#0f172a;color:#e5e7eb;border-radius:14px;padding:14px;white-space:pre-wrap;word-break:break-all;font-size:13px;line-height:1.65;max-height:260px;overflow:auto}@media(max-width:720px){.install-head,.install-body{padding:22px}.form-grid{grid-template-columns:1fr}.actions{justify-content:stretch}.btn-install{width:100%}}
</style>
</head>
<body class="install-page">
  <div class="install-card">
    <div class="install-head">
      <h1>网站初始化安装</h1>
      <p>免权限版：无需 PHP 写入 config.php。先手动编辑数据库配置，然后在本页设置站点信息和后台账号。</p>
    </div>
    <div class="install-body">
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <?php if (!$configReady): ?>
        <div class="alert warn">当前还没有检测到数据库配置。请先到文件管理打开 <b>includes/config.php</b>，点铅笔编辑，按下面格式填好数据库信息并保存，然后刷新本页。</div>
        <div class="codebox"><?= e($configExample) ?></div>
      <?php else: ?>
        <div class="tips">已检测到 <b>includes/config.php</b> 数据库配置。现在只需要设置站点信息和后台账号即可。</div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <div class="install-section">
          <h2>1. 站点信息</h2>
          <div class="form-grid">
            <div>
              <label>网站标题</label>
              <input name="site_title" value="<?= e(form_value('site_title', '安全导航发布页')) ?>">
            </div>
            <div>
              <label>页面大标题</label>
              <input name="page_title" value="<?= e(form_value('page_title', '发布页')) ?>">
            </div>
            <div class="field-full">
              <label>副标题</label>
              <input name="subtitle" value="<?= e(form_value('subtitle', '请 Ctrl+D 收藏本页到浏览器收藏夹')) ?>">
            </div>
            <div class="field-full">
              <label>永久地址</label>
              <input name="permanent_url" value="<?= e(form_value('permanent_url', $defaultPermanent)) ?>">
            </div>
            <div class="field-full">
              <label>公告说明</label>
              <textarea name="notice"><?= e(form_value('notice', '所有入口均可在后台自由增删改。支持跳转链接、弹窗提示、点击复制三种交互方式。')) ?></textarea>
            </div>
            <div>
              <label>Logo 地址</label>
              <input name="logo" value="<?= e(form_value('logo', 'assets/img/logo.png')) ?>">
            </div>
            <div>
              <label>主题色</label>
              <input name="theme_color" value="<?= e(form_value('theme_color', '#2FD18A')) ?>">
            </div>
            <div class="field-full">
              <label>页脚文字</label>
              <input name="footer" value="<?= e(form_value('footer', '© ' . date('Y') . ' All Rights Reserved')) ?>">
            </div>
          </div>
        </div>

        <div class="install-section">
          <h2>2. 后台管理员</h2>
          <div class="form-grid">
            <div>
              <label>后台账号</label>
              <input name="admin_user" value="<?= e(form_value('admin_user', 'admin')) ?>" required>
            </div>
            <div></div>
            <div>
              <label>后台密码</label>
              <input type="password" name="admin_pass" autocomplete="new-password" required>
              <div class="help">至少 8 位，建议包含大小写字母、数字和符号。</div>
            </div>
            <div>
              <label>确认密码</label>
              <input type="password" name="admin_pass_confirm" autocomplete="new-password" required>
            </div>
          </div>
        </div>

        <div class="actions">
          <button class="btn-install" type="submit" <?= $configReady ? '' : 'disabled style="opacity:.55;cursor:not-allowed"' ?>>开始安装并进入首页</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
