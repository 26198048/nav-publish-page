<?php
require_once __DIR__ . '/_common.php';
require_admin();
ensure_runtime_schema();

function uploaded_logo_path(string $field = 'logo_upload'): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo 上传失败，请重新选择图片。');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('Logo 图片不能超过 2MB。');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Logo 上传文件无效。');
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Logo 只支持 png、jpg、jpeg、gif、webp。');
    }

    $info = @getimagesize($tmp);
    if ($info === false || empty($info['mime'])) {
        throw new RuntimeException('请选择真实图片文件作为 Logo。');
    }
    $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    if (!in_array((string)$info['mime'], $allowedMime, true)) {
        throw new RuntimeException('Logo 图片格式不受支持。');
    }
    if (!empty($info[0]) && !empty($info[1]) && ($info[0] > 4096 || $info[1] > 4096)) {
        throw new RuntimeException('Logo 图片尺寸过大，建议不超过 4096×4096。');
    }

    $dir = dirname(__DIR__) . '/l';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('无法创建 Logo 上传目录 l/。');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Logo 上传目录 l/ 不可写，请检查文件夹权限。');
    }

    $name = bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Logo 保存失败，请检查 l/ 文件夹权限。');
    }
    @chmod($target, 0644);
    return 'l/' . $name;
}

function logo_preview_src(string $logo): string
{
    return media_src($logo, 'assets/img/logo.png');
}

function normalize_logo_value(string $logo): string
{
    $logo = trim($logo);
    if ($logo === '') {
        return 'assets/img/logo.png';
    }
    if (preg_match('/^https?:\/\//i', $logo)) {
        if (!is_valid_http_url($logo)) {
            throw new RuntimeException('Logo 地址必须是有效的 http:// 或 https:// 图片地址。');
        }
        return mb_substr($logo, 0, 500);
    }
    // 兼容用户输入 /assets/img/logo.png、assets/img/logo.png、/l/xxx.webp 等写法。
    $logo = ltrim($logo, '/');
    if (preg_match('/^(?:assets\/img\/[A-Za-z0-9._\/-]+|i\/[a-f0-9]{12}\.(?:png|jpe?g|gif|webp)|l\/[a-f0-9]{16}\.(?:png|jpe?g|gif|webp))$/i', $logo)) {
        return $logo;
    }
    throw new RuntimeException('Logo 地址只允许 http(s) 图片地址、内置 assets/img/ 图片、上传图标 i/ 或上传 Logo l/ 路径。');
}

function uploaded_background_path(string $field): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('背景图上传失败，请重新选择图片。');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 6 * 1024 * 1024) {
        throw new RuntimeException('背景图片不能超过 6MB。');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('背景上传文件无效。');
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('背景图只支持 png、jpg、jpeg、gif、webp。');
    }

    $info = @getimagesize($tmp);
    if ($info === false || empty($info['mime'])) {
        throw new RuntimeException('请选择真实图片文件作为背景。');
    }
    $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    if (!in_array((string)$info['mime'], $allowedMime, true)) {
        throw new RuntimeException('背景图片格式不受支持。');
    }
    if (!empty($info[0]) && !empty($info[1]) && ($info[0] > 8000 || $info[1] > 8000)) {
        throw new RuntimeException('背景图片尺寸过大，建议不超过 8000×8000。');
    }

    $dir = dirname(__DIR__) . '/b';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('无法创建背景上传目录 b/。');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('背景上传目录 b/ 不可写，请检查文件夹权限。');
    }

    $name = bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('背景保存失败，请检查 b/ 文件夹权限。');
    }
    @chmod($target, 0644);
    return 'b/' . $name;
}

function selected_background_value(string $selectName, string $current): string
{
    $choice = (string)($_POST[$selectName] ?? '');
    $presets = background_presets();
    if (array_key_exists($choice, $presets)) {
        return 'preset:' . $choice;
    }
    return normalize_background_value($current);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $theme = normalize_color($_POST['theme_color'] ?? '#2FD18A');
        $permanent = trim((string)($_POST['permanent_url'] ?? ''));
        if ($permanent !== '' && !is_valid_http_url($permanent)) {
            throw new RuntimeException('永久地址必须是 http:// 或 https:// 开头的有效网址。');
        }
        $old = settings_get_all();
        $desktopBg = selected_background_value('desktop_background_choice', $old['desktop_background'] ?? 'preset:aurora_dark');
        $mobileBg = selected_background_value('mobile_background_choice', $old['mobile_background'] ?? 'preset:tech_blue');
        $desktopUpload = uploaded_background_path('desktop_background_upload');
        $mobileUpload = uploaded_background_path('mobile_background_upload');
        if ($desktopUpload !== null) {
            $desktopBg = $desktopUpload;
        }
        if ($mobileUpload !== null) {
            $mobileBg = $mobileUpload;
        }

        setting_save('site_title', mb_substr(trim((string)$_POST['site_title']), 0, 120));
        setting_save('page_title', mb_substr(trim((string)$_POST['page_title']), 0, 120));
        setting_save('subtitle', mb_substr(trim((string)$_POST['subtitle']), 0, 255));
        setting_save('permanent_url', $permanent);
        $logo = mb_substr(trim((string)($_POST['logo'] ?? '')), 0, 500);
        $logoUpload = uploaded_logo_path('logo_upload');
        if ($logoUpload !== null) {
            $logo = $logoUpload;
        }
        $logo = normalize_logo_value($logo);

        setting_save('notice', trim((string)$_POST['notice']));
        setting_save('logo', $logo);
        setting_save('theme_color', $theme);
        setting_save('footer', mb_substr(trim((string)$_POST['footer']), 0, 255));
        setting_save('enable_search', isset($_POST['enable_search']) ? '1' : '0');
        setting_save('enable_categories', isset($_POST['enable_categories']) ? '1' : '0');
        setting_save('desktop_background', normalize_background_value($desktopBg, 'preset:aurora_dark'));
        setting_save('mobile_background', normalize_background_value($mobileBg, 'preset:tech_blue'));
        flash_set('success', '站点设置已保存。');
        redirect_admin('settings.php');
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
        redirect_admin('settings.php');
    }
}

$s = settings_get_all();
$presets = background_presets();
$desktopBg = normalize_background_value($s['desktop_background'] ?? 'preset:aurora_dark', 'preset:aurora_dark');
$mobileBg = normalize_background_value($s['mobile_background'] ?? 'preset:tech_blue', 'preset:tech_blue');
admin_header('站点设置');
flash_show();
?>
<section class="panel">
  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div><label>站点标题</label><input name="site_title" value="<?= e($s['site_title'] ?? '') ?>"></div>
    <div><label>页面大标题</label><input name="page_title" value="<?= e($s['page_title'] ?? '') ?>"></div>
    <div><label>副标题</label><input name="subtitle" value="<?= e($s['subtitle'] ?? '') ?>"></div>
    <div><label>永久地址</label><input name="permanent_url" value="<?= e($s['permanent_url'] ?? '') ?>" placeholder="https://example.com/"></div>
    <div class="full logo-settings">
      <label>页面 Logo / 主图</label>
      <div class="logo-setting-row">
        <div class="logo-setting-preview">
          <img src="<?= e(logo_preview_src($s['logo'] ?? 'assets/img/logo.png')) ?>" alt="当前 Logo" onerror="this.style.display='none'">
        </div>
        <div class="logo-setting-fields">
          <input name="logo" value="<?= e($s['logo'] ?? 'assets/img/logo.png') ?>" placeholder="/assets/img/logo.png 或 https://...">
          <p class="help">可填写图片地址，也可以直接上传替换前台左上方 Logo。上传后会自动保存到 /l/ 目录。</p>
          <label class="upload-btn logo-upload">上传更换 Logo
            <input type="file" name="logo_upload" data-logo-upload accept="image/png,image/jpeg,image/gif,image/webp">
          </label>
          <small>建议方形图片，png / jpg / jpeg / gif / webp，≤ 2MB。前台会自动裁切适应圆角方形显示。</small>
        </div>
      </div>
    </div>
    <div><label>主题色</label><input name="theme_color" value="<?= e($s['theme_color'] ?? '#2FD18A') ?>" placeholder="#2FD18A"></div>
    <div class="full"><label>公告说明</label><textarea name="notice" rows="5"><?= e($s['notice'] ?? '') ?></textarea></div>
    <div class="full"><label>页脚文字</label><input name="footer" value="<?= e($s['footer'] ?? '') ?>"></div>

    <div class="full feature-switches">
      <h2>前台检索与分类开关</h2>
      <p class="help">关闭后前台不显示对应功能，但后台按钮分类数据仍会保留。</p>
      <div class="feature-switch-grid">
        <label class="switch-card"><input type="checkbox" name="enable_search" <?= bool_setting($s, 'enable_search', true) ? 'checked' : '' ?>> <span><b>开启按钮搜索</b><small>用户可输入关键词快速搜索入口。</small></span></label>
        <label class="switch-card"><input type="checkbox" name="enable_categories" <?= bool_setting($s, 'enable_categories', true) ? 'checked' : '' ?>> <span><b>开启按钮分类</b><small>用户可按分类筛选相关内容。</small></span></label>
      </div>
    </div>

    <div class="full background-settings">
      <h2>背景设置</h2>
      <p class="help">电脑端与手机端可分别设置背景。选择预设可立即套用；上传图片后自动设为自定义背景，前台会使用 cover 自适应铺满屏幕。</p>
      <div class="background-grid">
        <div class="background-card">
          <label>电脑端背景</label>
          <select name="desktop_background_choice" data-bg-select>
            <?php foreach ($presets as $key => $preset): ?>
              <option value="<?= e($key) ?>" data-bg="<?= e($preset['css']) ?>" <?= background_select_value($desktopBg) === $key ? 'selected' : '' ?>><?= e($preset['name']) ?></option>
            <?php endforeach; ?>
            <option value="custom" <?= background_select_value($desktopBg) === 'custom' ? 'selected' : '' ?>>当前自定义上传背景</option>
          </select>
          <div class="bg-preview" style="background-image: <?= e(background_value_to_css($desktopBg)) ?>"></div>
          <label class="upload-btn bg-upload">上传电脑端背景
            <input type="file" name="desktop_background_upload" data-bg-upload accept="image/png,image/jpeg,image/gif,image/webp">
          </label>
          <small>建议 1920×1080 或更高，≤ 6MB。</small>
        </div>
        <div class="background-card">
          <label>手机端背景</label>
          <select name="mobile_background_choice" data-bg-select>
            <?php foreach ($presets as $key => $preset): ?>
              <option value="<?= e($key) ?>" data-bg="<?= e($preset['css']) ?>" <?= background_select_value($mobileBg) === $key ? 'selected' : '' ?>><?= e($preset['name']) ?></option>
            <?php endforeach; ?>
            <option value="custom" <?= background_select_value($mobileBg) === 'custom' ? 'selected' : '' ?>>当前自定义上传背景</option>
          </select>
          <div class="bg-preview phone" style="background-image: <?= e(background_value_to_css($mobileBg)) ?>"></div>
          <label class="upload-btn bg-upload">上传手机端背景
            <input type="file" name="mobile_background_upload" data-bg-upload accept="image/png,image/jpeg,image/gif,image/webp">
          </label>
          <small>建议 1080×1920 竖图，≤ 6MB。</small>
        </div>
      </div>
    </div>

    <div class="full"><button class="btn" type="submit">保存设置</button></div>
  </form>
</section>
<?php admin_footer(); ?>
