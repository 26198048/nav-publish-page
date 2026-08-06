<?php
require_once __DIR__ . '/_common.php';
require_admin();
$pdo = db();
ensure_runtime_schema();
$totalButtons = (int)$pdo->query('SELECT COUNT(*) FROM buttons')->fetchColumn();
$enabledButtons = (int)$pdo->query('SELECT COUNT(*) FROM buttons WHERE is_enabled = 1')->fetchColumn();
$totalClicks = (int)$pdo->query('SELECT COALESCE(SUM(click_count),0) FROM buttons')->fetchColumn();
$todayClicksStmt = $pdo->query('SELECT COUNT(*) FROM click_logs WHERE created_at >= CURDATE()');
$todayClicks = (int)$todayClicksStmt->fetchColumn();
$top = $pdo->query('SELECT id,title,action_type,click_count,is_enabled FROM buttons ORDER BY click_count DESC, id ASC LIMIT 10')->fetchAll();
admin_header('总览');
flash_show();
?>
<div class="stats-grid">
  <div class="stat-card"><b><?= $totalButtons ?></b><span>按钮总数</span></div>
  <div class="stat-card"><b><?= $enabledButtons ?></b><span>前台显示</span></div>
  <div class="stat-card"><b><?= $totalClicks ?></b><span>累计点击</span></div>
  <div class="stat-card"><b><?= $todayClicks ?></b><span>今日点击</span></div>
</div>

<section class="panel">
  <div class="panel-head"><h2>点击排行</h2><a class="btn" href="buttons.php">管理按钮</a></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>名称</th><th>交互</th><th>状态</th><th>点击量</th></tr></thead>
      <tbody>
      <?php foreach ($top as $row): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><?= e($row['title']) ?></td>
          <td><?= e(button_action_label((string)$row['action_type'])) ?></td>
          <td><?= $row['is_enabled'] ? '显示' : '隐藏' ?></td>
          <td><?= (int)$row['click_count'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$top): ?><tr><td colspan="5">暂无数据</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_footer(); ?>
