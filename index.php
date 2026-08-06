<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
security_headers();

$config = require __DIR__ . '/includes/config.php';
$dbConfig = $config['db'] ?? [];
if (empty($dbConfig['database']) || empty($dbConfig['username'])) {
    if (file_exists(__DIR__ . '/install.php')) {
        header('Location: install.php');
        exit;
    }
    http_response_code(503);
    exit('系统尚未配置数据库，且安装文件不存在。请重新上传源码包后访问域名进行安装。');
}

try {
    $adminCount = db()->query('SELECT COUNT(*) AS c FROM admin_users')->fetch();
    if (((int)($adminCount['c'] ?? 0)) < 1 && file_exists(__DIR__ . '/install.php')) {
        header('Location: install.php');
        exit;
    }
    ensure_runtime_schema();
    $settings = settings_get_all();
    $theme = normalize_color($settings['theme_color'] ?? '#2FD18A');
    $desktopBg = background_value_to_css($settings['desktop_background'] ?? 'preset:aurora_dark');
    $mobileBg = background_value_to_css($settings['mobile_background'] ?? 'preset:tech_blue');
    $stmt = db()->query('SELECT * FROM buttons WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC');
    $buttons = $stmt->fetchAll();
    $enableSearch = bool_setting($settings, 'enable_search', true);
    $enableCategories = bool_setting($settings, 'enable_categories', true);
    $categories = [];
    foreach ($buttons as $btn) {
        $cat = trim((string)($btn['category'] ?? '默认分类'));
        if ($cat !== '' && !in_array($cat, $categories, true)) {
            $categories[] = $cat;
        }
    }
} catch (Throwable $e) {
    // 数据库能连接但安装表不存在时，说明尚未完成安装或表被误删。
    // 这时不要直接报错，自动进入安装程序重新创建数据表。
    $msg = $e->getMessage();
    if (file_exists(__DIR__ . '/install.php') && (
        strpos($msg, 'Base table or view not found') !== false ||
        strpos($msg, '42S02') !== false ||
        strpos($msg, 'admin_users') !== false ||
        strpos($msg, 'site_settings') !== false ||
        strpos($msg, 'buttons') !== false
    )) {
        header('Location: install.php');
        exit;
    }
    http_response_code(500);
    exit('数据库连接失败，请检查 includes/config.php 配置。错误：' . e($msg));
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($settings['site_title'] ?? '导航发布页') ?></title>
<link rel="icon" href="favicon.ico">
<link rel="stylesheet" href="assets/css/style.css">
<style>:root{--theme:<?= e($theme) ?>;--desktop-bg:<?= e($desktopBg) ?>;--mobile-bg:<?= e($mobileBg) ?>;}</style>
</head>
<body>
<main class="wrap">
  <section class="main-card">
    <header class="hero">
      <img class="logo" src="<?= e(media_src($settings['logo'] ?? 'assets/img/logo.png', 'assets/img/logo.png')) ?>" alt="Logo" onerror="this.style.display='none'">
      <div>
        <h1><?= e($settings['page_title'] ?? '发布页') ?></h1>
        <p><?= e($settings['subtitle'] ?? '请收藏本页') ?></p>
      </div>
    </header>

    <?php if (!empty($settings['permanent_url'])): ?>
    <div class="permanent">
      <span>永久地址</span>
      <strong id="permanentUrl"><?= e($settings['permanent_url']) ?></strong>
      <button type="button" id="copyPermanent">复制</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($settings['notice'])): ?>
    <div class="notice"><?= nl2br(e($settings['notice'])) ?></div>
    <?php endif; ?>

    <?php if (($enableSearch && $buttons) || ($enableCategories && count($categories) > 1)): ?>
    <div class="front-controls">
      <?php if ($enableSearch && $buttons): ?>
        <div class="front-search">
          <span>搜索</span>
          <input type="search" id="buttonSearch" placeholder="输入关键词搜索入口" autocomplete="off">
        </div>
      <?php endif; ?>
      <?php if ($enableCategories && count($categories) > 1): ?>
        <div class="category-filter" id="categoryFilter">
          <button type="button" class="active" data-category="all">全部</button>
          <?php foreach ($categories as $cat): ?>
            <button type="button" data-category="<?= e($cat) ?>"><?= e($cat) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="grid" id="buttonGrid">
      <?php foreach ($buttons as $button): ?>
        <?php
          $actionType = (string)($button['action_type'] ?? 'link');
          $category = trim((string)($button['category'] ?? '默认分类')) ?: '默认分类';
          $searchText = trim(($button['title'] ?? '') . ' ' . ($button['description'] ?? '') . ' ' . $category);
        ?>
        <?php if ($actionType === 'popup'): ?>
          <button class="nav-item popup-trigger" type="button" data-nav-item
                  data-id="<?= (int)$button['id'] ?>"
                  data-category="<?= e($category) ?>"
                  data-search="<?= e($searchText) ?>"
                  data-title="<?= e($button['popup_title'] ?: $button['title']) ?>"
                  data-content="<?= e($button['popup_content'] ?? '') ?>">
            <span class="left"><span class="icon"><?= render_icon_html($button['icon'] ?? '', '💬') ?></span><span><b><?= e($button['title']) ?></b><em><?= e($button['description'] ?? '') ?></em></span></span>
            <span class="tag">查看</span>
          </button>
        <?php elseif ($actionType === 'copy'): ?>
          <button class="nav-item copy-trigger" type="button" data-nav-item
                  data-id="<?= (int)$button['id'] ?>"
                  data-category="<?= e($category) ?>"
                  data-search="<?= e($searchText) ?>"
                  data-copy="<?= e($button['copy_content'] ?? '') ?>">
            <span class="left"><span class="icon"><?= render_icon_html($button['icon'] ?? '', '📋') ?></span><span><b><?= e($button['title']) ?></b><em><?= e($button['description'] ?? '') ?></em></span></span>
            <span class="tag">复制</span>
          </button>
        <?php else: ?>
          <a class="nav-item" href="go.php?id=<?= (int)$button['id'] ?>" data-nav-item data-category="<?= e($category) ?>" data-search="<?= e($searchText) ?>" <?= !empty($button['open_new_tab']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <span class="left"><span class="icon"><?= render_icon_html($button['icon'] ?? '', '🔗') ?></span><span><b><?= e($button['title']) ?></b><em><?= e($button['description'] ?? '') ?></em></span></span>
            <span class="tag">进入</span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <?php if (!$buttons): ?>
      <div class="empty">暂无可显示按钮，请进入后台添加。</div>
    <?php else: ?>
      <div class="empty no-results" id="noResults" hidden>没有找到匹配的入口，请换个关键词或分类。</div>
    <?php endif; ?>
  </section>
  <footer><?= e($settings['footer'] ?? '© All Rights Reserved') ?></footer>
</main>

<div class="modal-mask" id="modalMask" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <button class="modal-close" type="button" id="modalClose" aria-label="关闭">×</button>
    <h2 id="modalTitle"></h2>
    <div id="modalContent" class="modal-content"></div>
    <button class="modal-ok" type="button" id="modalOk">我知道了</button>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
