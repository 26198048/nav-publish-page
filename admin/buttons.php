<?php
require_once __DIR__ . '/_common.php';
require_admin();
$pdo = db();
ensure_runtime_schema();

function admin_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function icon_presets(): array
{
    // 按使用场景分组，后台更好找，也避免大量 Emoji 混在一起显得杂乱。
    return [
        '系统平台' => ['assets/img/icon-ios.svg', 'assets/img/icon-android.svg', '🍎', '🤖', '📱', '💻', '🖥️'],
        '常用入口' => ['🔗', '🌐', '🏠', '🧭', '📌', '⭐', '🚀', '✅'],
        '沟通客服' => ['💬', '📢', '📞', '☎️', '📧', '🔔'],
        '下载工具' => ['📥', '📦', '🧰', '🧩', '🛠️', '🗂️', '📄'],
        '活动权益' => ['💰', '🎁', '🛒', '💎', '👑', '🔥', '⚡', '🎯'],
        '内容娱乐' => ['🎮', '🎬', '🎵', '📷', '🖼️', '☁️', '❤️', '👍'],
        '安全状态' => ['🛡️', '🔐', '🟢', '🟡', '🔴'],
    ];
}

function icon_preset_flatten(array $groups): array
{
    $icons = [];
    foreach ($groups as $items) {
        foreach ($items as $icon) {
            $icons[] = $icon;
        }
    }
    return array_values(array_unique($icons));
}

function uploaded_icon_presets(int $limit = 120): array
{
    // 将用户上传到 /i/ 目录的图标集中放到“我的上传”分类，方便后台直接点选复用。
    $dir = dirname(__DIR__) . '/i';
    if (!is_dir($dir)) {
        return [];
    }

    $items = [];
    $files = glob($dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE) ?: [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $name = basename($file);
        $icon = 'i/' . $name;
        if (!is_image_icon($icon)) {
            continue;
        }
        $items[] = [
            'icon' => $icon,
            'time' => @filemtime($file) ?: 0,
        ];
    }

    usort($items, static function (array $a, array $b): int {
        return $b['time'] <=> $a['time'];
    });

    return array_slice(array_column($items, 'icon'), 0, max(1, $limit));
}

function uploaded_icon_path(): ?string
{
    if (empty($_FILES['icon_upload']) || !is_array($_FILES['icon_upload'])) {
        return null;
    }
    $file = $_FILES['icon_upload'];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('图标上传失败，请重新选择图片。');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('图标文件不能超过 2MB。');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('图标上传文件无效。');
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('图标只支持 png、jpg、jpeg、gif、webp。');
    }

    $info = @getimagesize($tmp);
    if ($info === false || empty($info['mime'])) {
        throw new RuntimeException('请选择真实图片文件作为图标。');
    }
    $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    if (!in_array((string)$info['mime'], $allowedMime, true)) {
        throw new RuntimeException('图标图片格式不受支持。');
    }
    if (!empty($info[0]) && !empty($info[1]) && ($info[0] > 2048 || $info[1] > 2048)) {
        throw new RuntimeException('图标尺寸过大，建议不超过 2048×2048。');
    }

    $dir = dirname(__DIR__) . '/i';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('无法创建图标上传目录 i/。');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('图标上传目录 i/ 不可写，请检查文件夹权限。');
    }

    // 文件名控制在较短长度内，兼容旧版本 icon VARCHAR(30) 字段。
    $name = bin2hex(random_bytes(6)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('图标保存失败，请检查 i/ 文件夹权限。');
    }
    @chmod($target, 0644);
    return 'i/' . $name;
}

function clean_icon_value(string $icon): string
{
    $icon = trim($icon);
    if ($icon === '') {
        return '🔗';
    }
    if (is_image_icon($icon)) {
        return $icon;
    }
    return mb_substr($icon, 0, 30);
}

function button_from_post(): array
{
    $action = (string)($_POST['action_type'] ?? 'link');
    $action = in_array($action, ['link', 'popup', 'copy'], true) ? $action : 'link';
    $linkUrl = trim((string)($_POST['link_url'] ?? ''));
    if ($action === 'link' && !is_valid_http_url($linkUrl)) {
        throw new RuntimeException('跳转链接必须是 http:// 或 https:// 开头的有效网址。');
    }
    $copyContent = trim((string)($_POST['copy_content'] ?? ''));
    if ($action === 'copy' && $copyContent === '') {
        throw new RuntimeException('点击复制模式下，复制内容不能为空。');
    }

    $icon = clean_icon_value((string)($_POST['icon'] ?? '🔗'));
    $uploadedIcon = uploaded_icon_path();
    if ($uploadedIcon !== null) {
        $icon = $uploadedIcon;
    }

    return [
        'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 80),
        'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 255),
        'category' => mb_substr(trim((string)($_POST['category'] ?? '默认分类')), 0, 80) ?: '默认分类',
        'icon' => $icon,
        'action_type' => $action,
        'link_url' => $action === 'link' ? $linkUrl : null,
        'open_new_tab' => isset($_POST['open_new_tab']) ? 1 : 0,
        'popup_title' => mb_substr(trim((string)($_POST['popup_title'] ?? '')), 0, 120),
        'popup_content' => trim((string)($_POST['popup_content'] ?? '')),
        'copy_content' => $copyContent,
        'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 100),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    try {
        if ($op === 'reorder') {
            $orderRaw = trim((string)($_POST['order'] ?? ''));
            $ids = array_values(array_filter(array_map('intval', explode(',', $orderRaw)), static fn($v) => $v > 0));
            $ids = array_values(array_unique($ids));
            if (!$ids) {
                throw new RuntimeException('排序数据为空。');
            }
            $pdo->beginTransaction();
            $sort = 10;
            $stmt = $pdo->prepare('UPDATE buttons SET sort_order=?, updated_at=NOW() WHERE id=?');
            foreach ($ids as $id) {
                $stmt->execute([$sort, $id]);
                $sort += 10;
            }
            $pdo->commit();
            admin_json_response(['ok' => true, 'message' => '排序已保存。']);
        }
        if ($op === 'save') {
            $data = button_from_post();
            if ($data['title'] === '') {
                throw new RuntimeException('按钮名称不能为空。');
            }
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare('UPDATE buttons SET title=?, description=?, category=?, icon=?, action_type=?, link_url=?, open_new_tab=?, popup_title=?, popup_content=?, copy_content=?, is_enabled=?, sort_order=?, updated_at=NOW() WHERE id=?');
                $stmt->execute([$data['title'],$data['description'],$data['category'],$data['icon'],$data['action_type'],$data['link_url'],$data['open_new_tab'],$data['popup_title'],$data['popup_content'],$data['copy_content'],$data['is_enabled'],$data['sort_order'],$id]);
                flash_set('success', '按钮已更新。');
            } else {
                $stmt = $pdo->prepare('INSERT INTO buttons (title, description, category, icon, action_type, link_url, open_new_tab, popup_title, popup_content, copy_content, is_enabled, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$data['title'],$data['description'],$data['category'],$data['icon'],$data['action_type'],$data['link_url'],$data['open_new_tab'],$data['popup_title'],$data['popup_content'],$data['copy_content'],$data['is_enabled'],$data['sort_order']]);
                flash_set('success', '按钮已添加。');
            }
            redirect_admin('buttons.php');
        }
        if ($op === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare('DELETE FROM buttons WHERE id=?');
                $stmt->execute([$id]);
                flash_set('success', '按钮已删除。');
            }
            redirect_admin('buttons.php');
        }
        if ($op === 'reset_clicks') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare('UPDATE buttons SET click_count=0 WHERE id=?');
                $stmt->execute([$id]);
                $stmt = $pdo->prepare('DELETE FROM click_logs WHERE button_id=?');
                $stmt->execute([$id]);
                flash_set('success', '该按钮点击统计已清零。');
            }
            redirect_admin('buttons.php');
        }
        if ($op === 'toggle') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare('UPDATE buttons SET is_enabled = 1 - is_enabled WHERE id=?');
                $stmt->execute([$id]);
                flash_set('success', '显示状态已切换。');
            }
            redirect_admin('buttons.php');
        }
        if ($op === 'batch') {
            $idsRaw = $_POST['ids'] ?? [];
            if (!is_array($idsRaw)) {
                $idsRaw = [$idsRaw];
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', $idsRaw), static fn($v) => $v > 0)));
            $action = (string)($_POST['batch_action'] ?? '');
            if (!$ids) {
                throw new RuntimeException('请先勾选要批量管理的按钮。');
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($action === 'show') {
                $stmt = $pdo->prepare("UPDATE buttons SET is_enabled=1, updated_at=NOW() WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                flash_set('success', '已批量设置为前台显示。');
            } elseif ($action === 'hide') {
                $stmt = $pdo->prepare("UPDATE buttons SET is_enabled=0, updated_at=NOW() WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                flash_set('success', '已批量设置为前台隐藏。');
            } elseif ($action === 'reset_clicks') {
                $stmt = $pdo->prepare("UPDATE buttons SET click_count=0, updated_at=NOW() WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $stmt = $pdo->prepare("DELETE FROM click_logs WHERE button_id IN ($placeholders)");
                $stmt->execute($ids);
                flash_set('success', '已批量清空点击统计。');
            } elseif ($action === 'delete') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM click_logs WHERE button_id IN ($placeholders)");
                $stmt->execute($ids);
                $stmt = $pdo->prepare("DELETE FROM buttons WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $pdo->commit();
                flash_set('success', '已批量删除选中的按钮。');
            } else {
                throw new RuntimeException('请选择有效的批量操作。');
            }
            redirect_admin('buttons.php');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (($op ?? '') === 'reorder' || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')) {
            admin_json_response(['ok' => false, 'message' => $e->getMessage()], 400);
        }
        flash_set('error', $e->getMessage());
        redirect_admin('buttons.php' . (!empty($_POST['id']) ? '?edit=' . (int)$_POST['id'] : ''));
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$edit = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM buttons WHERE id=?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}
$categories = $pdo->query("SELECT DISTINCT category FROM buttons WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$buttons = $pdo->query('SELECT * FROM buttons ORDER BY sort_order ASC, id ASC')->fetchAll();
$uploadedIconPresets = uploaded_icon_presets();
$iconPresetGroups = ['我的上传' => $uploadedIconPresets] + icon_presets();
$iconPresets = icon_preset_flatten($iconPresetGroups);
$currentIcon = clean_icon_value((string)($edit['icon'] ?? '🔗'));
admin_header('按钮管理');
flash_show();
?>
<section class="panel">
  <div class="panel-head">
    <h2><?= $edit ? '编辑按钮 #' . (int)$edit['id'] : '添加按钮' ?></h2>
    <?php if ($edit): ?><a class="btn ghost" href="buttons.php">取消编辑</a><?php endif; ?>
  </div>
  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="save">
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

    <div><label>按钮名称</label><input name="title" maxlength="80" value="<?= e($edit['title'] ?? '') ?>" required></div>
    <div><label>描述</label><input name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>"></div>
    <div>
      <label>按钮分类</label>
      <input name="category" maxlength="80" list="categoryList" value="<?= e($edit['category'] ?? '默认分类') ?>" placeholder="例如：官方入口 / 下载工具 / 客服联系">
      <datalist id="categoryList">
        <?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?php endforeach; ?>
      </datalist>
      <p class="help">用于前台分类筛选；同名分类会自动归到一起。</p>
    </div>

    <div class="full icon-picker-block">
      <label>图标 / Emoji</label>
      <input type="hidden" name="icon" id="iconValue" value="<?= e($currentIcon) ?>">
      <div class="icon-current">
        <span class="icon-picker-preview" id="iconPreview"><?= render_icon_html($currentIcon, '🔗') ?></span>
        <span><b>当前图标</b><small>可直接点选分类里的图标/Emoji；上传过的图标会自动出现在“我的上传”分类。</small></span>
      </div>
      <div class="emoji-picker" data-icon-picker>
        <?php foreach ($iconPresetGroups as $groupName => $groupIcons): ?>
          <div class="emoji-group <?= $groupName === '我的上传' ? 'uploaded-icons-group' : '' ?>">
            <div class="emoji-group-title"><?= e($groupName) ?><?= $groupName === '我的上传' ? '<span class="emoji-group-tip">按上传时间排序</span>' : '' ?></div>
            <div class="emoji-grid">
              <?php if (!$groupIcons && $groupName === '我的上传'): ?>
                <div class="uploaded-icons-empty">暂无上传图标，选择下方“上传自定义图标”后会自动收录到这里。</div>
              <?php endif; ?>
              <?php foreach ($groupIcons as $emoji): ?>
                <button type="button" class="emoji-choice <?= $currentIcon === $emoji ? 'selected' : '' ?>" data-icon="<?= e($emoji) ?>" title="<?= is_image_icon($emoji) ? e(basename($emoji, '.svg')) : e($emoji) ?>"><?= render_icon_html($emoji, '🔗') ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="upload-row">
        <label class="upload-btn">上传自定义图标
          <input type="file" name="icon_upload" id="iconUpload" accept="image/png,image/jpeg,image/gif,image/webp">
        </label>
        <p class="help">支持 png / jpg / jpeg / gif / webp，单个文件 ≤ 2MB。上传成功后会自动进入“我的上传”分类，便于下次直接选择。</p>
      </div>
    </div>

    <div><label>排序值</label><input type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? '100') ?>"></div>
    <div>
      <label>交互方式</label>
      <select name="action_type" id="actionType">
        <option value="link" <?= (($edit['action_type'] ?? 'link') === 'link') ? 'selected' : '' ?>>跳转链接</option>
        <option value="popup" <?= (($edit['action_type'] ?? 'link') === 'popup') ? 'selected' : '' ?>>弹窗提示</option>
        <option value="copy" <?= (($edit['action_type'] ?? 'link') === 'copy') ? 'selected' : '' ?>>点击复制内容</option>
      </select>
    </div>
    <div class="checkline">
      <label><input type="checkbox" name="is_enabled" <?= !isset($edit['is_enabled']) || $edit['is_enabled'] ? 'checked' : '' ?>> 前台显示</label>
      <label><input type="checkbox" name="open_new_tab" <?= !isset($edit['open_new_tab']) || $edit['open_new_tab'] ? 'checked' : '' ?>> 链接新窗口打开</label>
    </div>

    <div class="full action-link-fields">
      <label>跳转链接</label>
      <input name="link_url" placeholder="https://example.com/" value="<?= e($edit['link_url'] ?? '') ?>">
      <p class="help">仅允许 http:// 或 https://，防止 javascript: 等危险链接。</p>
    </div>

    <div class="action-popup-fields"><label>弹窗标题</label><input name="popup_title" maxlength="120" value="<?= e($edit['popup_title'] ?? '') ?>"></div>
    <div class="action-popup-fields full"><label>弹窗内容</label><textarea name="popup_content" rows="5"><?= e($edit['popup_content'] ?? '') ?></textarea></div>

    <div class="action-copy-fields full"><label>点击复制的指定内容</label><textarea name="copy_content" rows="5" placeholder="用户点击按钮后会复制这里填写的内容"><?= e($edit['copy_content'] ?? '') ?></textarea><p class="help">适合复制微信号、QQ群号、邮箱、下载码、访问口令、客服话术等文本。</p></div>

    <div class="full"><button class="btn" type="submit"><?= $edit ? '保存修改' : '添加按钮' ?></button></div>
  </form>
</section>

<section class="panel">
  <div class="panel-head">
    <div>
      <h2>全部按钮（不限数量）</h2>
      <span class="muted">按住左侧“☰”即可拖拽排序，松手后自动保存；也可通过“排序值”精确控制前台顺序。</span>
    </div>
    <span id="sortStatus" class="sort-status" aria-live="polite"></span>
  </div>
  <form method="post" id="bulkForm" class="bulk-actions js-bulk-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="batch">
    <select name="batch_action" aria-label="批量操作">
      <option value="">批量管理</option>
      <option value="show">批量显示</option>
      <option value="hide">批量隐藏</option>
      <option value="reset_clicks">批量清空点击量</option>
      <option value="delete">批量删除</option>
    </select>
    <button class="btn small ghost" type="submit">应用</button>
    <span class="bulk-count" data-bulk-count>已选择 0 项</span>
  </form>
  <div class="table-wrap">
    <table id="buttonsTable" class="sortable-table" data-csrf="<?= e(csrf_token()) ?>">
      <thead><tr><th><input type="checkbox" class="bulk-select-all" aria-label="全选按钮"></th><th>拖拽</th><th>ID</th><th>图标</th><th>名称</th><th>分类</th><th>交互方式</th><th>排序</th><th>状态</th><th>点击量</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach ($buttons as $b): ?>
        <tr data-id="<?= (int)$b['id'] ?>">
          <td><input type="checkbox" class="bulk-check" value="<?= (int)$b['id'] ?>" aria-label="选择按钮 <?= e($b['title']) ?>"></td>
          <td><span class="drag-handle" title="按住拖拽排序" aria-label="拖拽排序" role="button" tabindex="0">☰</span></td>
          <td><?= (int)$b['id'] ?></td>
          <td><span class="admin-icon-preview"><?= render_icon_html($b['icon'] ?? '', '🔗') ?></span></td>
          <td><b><?= e($b['title']) ?></b><br><small><?= e($b['description']) ?></small></td>
          <td><span class="category-badge"><?= e($b['category'] ?? '默认分类') ?></span></td>
          <td><?= e(button_action_label((string)$b['action_type'])) ?></td>
          <td class="sort-order-cell"><?= (int)$b['sort_order'] ?></td>
          <td><?= $b['is_enabled'] ? '<span class="badge on">显示</span>' : '<span class="badge off">隐藏</span>' ?></td>
          <td><?= (int)$b['click_count'] ?></td>
          <td class="actions">
            <a class="btn small ghost" href="buttons.php?edit=<?= (int)$b['id'] ?>">编辑</a>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="op" value="toggle"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn small ghost" type="submit">切换显示</button></form>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="op" value="reset_clicks"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn small ghost" type="submit">清零</button></form>
            <form method="post" class="js-confirm-delete"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn small danger" type="submit">删除</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$buttons): ?><tr><td colspan="11">暂无按钮</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php admin_footer(); ?>
