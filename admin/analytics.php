<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../api/db.php';

/* ── Stat cards ── */
$total_views     = (int)$pdo->query('SELECT COUNT(*) FROM page_views')->fetchColumn();
$views_today     = (int)$pdo->query('SELECT COUNT(DISTINCT ip_hash) FROM page_views WHERE viewed_at >= CURDATE()')->fetchColumn();
$views_week      = (int)$pdo->query('SELECT COUNT(*) FROM page_views WHERE YEARWEEK(viewed_at,1) = YEARWEEK(NOW(),1)')->fetchColumn();
$views_month     = (int)$pdo->query('SELECT COUNT(*) FROM page_views WHERE YEAR(viewed_at)=YEAR(NOW()) AND MONTH(viewed_at)=MONTH(NOW())')->fetchColumn();
$unique_visitors = (int)$pdo->query('SELECT COUNT(DISTINCT ip_hash) FROM page_views')->fetchColumn();

/* ── Avg session duration ── */
$avg_duration_sec = (int)$pdo->query(
    'SELECT COALESCE(AVG(time_on_page), 0) FROM page_views WHERE time_on_page IS NOT NULL'
)->fetchColumn();

function fmt_duration($secs) {
    if ($secs <= 0) return '—';
    $m = floor($secs / 60);
    $s = $secs % 60;
    return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
}

/* ── Avg duration per page type ── */
$dur_rows = $pdo->query(
    "SELECT page_type, COALESCE(AVG(time_on_page),0) AS avg_sec
     FROM page_views WHERE time_on_page IS NOT NULL GROUP BY page_type"
)->fetchAll(PDO::FETCH_KEY_PAIR);

/* ── Returning vs new visitors ── */
$returning = (int)$pdo->query(
    'SELECT COUNT(*) FROM (
        SELECT ip_hash FROM page_views
        GROUP BY ip_hash
        HAVING COUNT(DISTINCT DATE(viewed_at)) > 1
    ) t'
)->fetchColumn();
$new_visitors = $unique_visitors - $returning;

/* ── Per-page breakdown (views + unique visitors) ── */
$breakdown = $pdo->query(
    "SELECT page_type,
            COUNT(*) AS views,
            COUNT(DISTINCT ip_hash) AS unique_v
     FROM page_views
     GROUP BY page_type"
)->fetchAll(PDO::FETCH_UNIQUE);
// $breakdown['home']['views'], $breakdown['blog']['views'], etc.
$home_views   = (int)($breakdown['home']['views']   ?? 0);
$home_uniq    = (int)($breakdown['home']['unique_v'] ?? 0);
$blog_views   = (int)($breakdown['blog']['views']   ?? 0);
$blog_uniq    = (int)($breakdown['blog']['unique_v'] ?? 0);
$post_views   = (int)($breakdown['post']['views']   ?? 0);
$post_uniq    = (int)($breakdown['post']['unique_v'] ?? 0);

/* ── Top 5 posts (views + unique visitors) ── */
$top_posts = $pdo->query(
    "SELECT pv.post_slug, p.title,
            COUNT(*) AS view_count,
            COUNT(DISTINCT pv.ip_hash) AS unique_count
     FROM page_views pv
     LEFT JOIN posts p ON p.slug = pv.post_slug
     WHERE pv.page_type = 'post' AND pv.post_slug IS NOT NULL
     GROUP BY pv.post_slug, p.title
     ORDER BY view_count DESC
     LIMIT 5"
)->fetchAll();

/* ── Last 14 days chart ── */
$chart_rows = $pdo->query(
    "SELECT DATE(viewed_at) AS day, COUNT(*) AS views
     FROM page_views
     WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(viewed_at)
     ORDER BY day ASC"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_data = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $chart_data[$day] = (int)($chart_rows[$day] ?? 0);
}
$max_views = max($chart_data) ?: 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — Admin</title>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/admin/admin.css"/>
  <link rel="stylesheet" href="/admin/theme.css"/>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-logo"><img src="/assets/logo.png" alt="Denny Pratama" style="height:28px;width:auto;opacity:0.85;"/></div>
    <nav class="sidebar-nav">
      <a class="sidebar-link active" href="analytics.php">Dashboard</a>
      <a class="sidebar-link" href="index.php">Posts</a>
      <a class="sidebar-link" href="auto-post.php">Auto Post</a>
      <a class="sidebar-link" href="change-password.php">Change Password</a>
      <a class="sidebar-link" href="../index.html" target="_blank">View Site →</a>
    </nav>
    <div class="sidebar-bottom">
      <button class="theme-toggle" id="theme-toggle">◑ Light mode</button>
      <a class="sidebar-logout" href="logout.php">Sign out</a>
    </div>
  </aside>

  <main class="main">
    <div class="top-bar">
      <h1>Dashboard</h1>
    </div>

    <!-- ── Row 1: Core stats ── -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Views</div>
        <div class="stat-value"><?= number_format($total_views) ?></div>
        <div class="stat-sub">all time</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Unique Visitors</div>
        <div class="stat-value"><?= number_format($unique_visitors) ?></div>
        <div class="stat-sub">all time</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Today</div>
        <div class="stat-value accent"><?= number_format($views_today) ?></div>
        <div class="stat-sub">views</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">This Month</div>
        <div class="stat-value"><?= number_format($views_month) ?></div>
        <div class="stat-sub"><?= number_format($views_week) ?> this week</div>
      </div>
    </div>

    <!-- ── Row 2: Session + visitor stats ── -->
    <div class="stats-grid-2">
      <div class="stat-card">
        <div class="stat-label">Avg Session Duration</div>
        <div class="stat-value"><?= fmt_duration($avg_duration_sec) ?></div>
        <div class="stat-sub">across all pages</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Avg Time on Posts</div>
        <div class="stat-value"><?= fmt_duration((int)($dur_rows['post'] ?? 0)) ?></div>
        <div class="stat-sub">blog post pages</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Returning Visitors</div>
        <div class="stat-value"><?= number_format($returning) ?></div>
        <div class="stat-sub"><?= $unique_visitors > 0 ? round($returning / $unique_visitors * 100) : 0 ?>% of total</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">New Visitors</div>
        <div class="stat-value"><?= number_format($new_visitors) ?></div>
        <div class="stat-sub"><?= $unique_visitors > 0 ? round($new_visitors / $unique_visitors * 100) : 0 ?>% of total</div>
      </div>
    </div>

    <!-- ── Last 14 days chart ── -->
    <div class="section-heading">Last 14 Days</div>
    <div class="chart-wrap">
      <div class="chart-bars">
        <?php foreach ($chart_data as $day => $views): ?>
          <?php $pct = round($views / $max_views * 100); ?>
          <div class="chart-col">
            <div class="chart-bar-wrap">
              <div class="chart-bar" style="height:<?= $pct ?>%" title="<?= $views ?> views"></div>
            </div>
            <div class="chart-label"><?= date('d/m', strtotime($day)) ?></div>
            <div class="chart-value"><?= $views ?: '' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Per-page breakdown ── -->
    <div class="section-heading">By Page</div>
    <div class="breakdown-grid">
      <?php
        $pages = [
          ['name' => 'Homepage',     'views' => $home_views, 'uniq' => $home_uniq],
          ['name' => 'Blog Listing', 'views' => $blog_views, 'uniq' => $blog_uniq],
          ['name' => 'Blog Posts',   'views' => $post_views, 'uniq' => $post_uniq],
        ];
      ?>
      <?php foreach ($pages as $pg): ?>
      <div class="breakdown-card">
        <div class="breakdown-name"><?= $pg['name'] ?></div>
        <div class="breakdown-row">
          <span class="breakdown-metric">Views</span>
          <span class="breakdown-num"><?= number_format($pg['views']) ?></span>
        </div>
        <hr class="breakdown-divider"/>
        <div class="breakdown-row">
          <span class="breakdown-metric">Unique visitors</span>
          <span class="breakdown-num dim"><?= number_format($pg['uniq']) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Top posts ── -->
    <div class="section-heading">Top Posts</div>
    <?php if (empty($top_posts)): ?>
      <div class="empty">No post views recorded yet.</div>
    <?php else: ?>
      <?php $max_post_views = (int)$top_posts[0]['view_count']; ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Post</th>
            <th>Views</th>
            <th>Unique Visitors</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_posts as $i => $row): ?>
          <tr>
            <td class="rank"><?= $i + 1 ?></td>
            <td>
              <div class="post-title-cell"><?= htmlspecialchars($row['title'] ?? 'Deleted post') ?></div>
              <div class="post-slug-cell">/<?= htmlspecialchars($row['post_slug']) ?></div>
            </td>
            <td>
              <div class="view-bar-wrap">
                <div class="view-bar-bg">
                  <div class="view-bar-fill" style="width:<?= round($row['view_count'] / $max_post_views * 100) ?>%"></div>
                </div>
                <span class="view-count"><?= number_format($row['view_count']) ?></span>
              </div>
            </td>
            <td class="uniq-count"><?= number_format($row['unique_count']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  </main>

  <script>
    (function(){
      var btn = document.getElementById('theme-toggle');
      function update(){
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.textContent = dark ? '◑ Light mode' : '◐ Dark mode';
      }
      update();
      btn.addEventListener('click', function(){
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('admin-theme', next);
        update();
      });
    })();
  </script>
</body>
</html>
