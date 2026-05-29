<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';

/* ── Stat cards ── */
$total_views     = (int)$pdo->query('SELECT COUNT(*) FROM page_views')->fetchColumn();
$views_today     = (int)$pdo->query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= CURDATE()')->fetchColumn();
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

/* ── Traffic sources: bucket referrers + collect top hostnames ──
   Direct = no referrer. Search/Social = known host patterns. Referral = the rest. */
$src_buckets = ['direct' => 0, 'search' => 0, 'social' => 0, 'referral' => 0];
$src_hosts   = [];

$search_hosts = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'yandex.', 'baidu.', 'ecosia.', 'brave.com', 'kagi.com'];
$social_hosts = ['facebook.', 'fb.com', 'twitter.', 'x.com', 't.co', 'linkedin.', 'lnkd.in',
                 'instagram.', 'tiktok.', 'youtube.', 'youtu.be', 'pinterest.', 'reddit.',
                 'threads.net', 'whatsapp.', 'telegram.', 'discord.', 'dribbble.'];

$ref_rows = $pdo->query(
    "SELECT referrer, COUNT(*) AS v FROM page_views GROUP BY referrer"
)->fetchAll();

foreach ($ref_rows as $r) {
    $count = (int)$r['v'];
    $ref   = trim((string)$r['referrer']);

    if ($ref === '') { $src_buckets['direct'] += $count; continue; }

    $host = parse_url($ref, PHP_URL_HOST);
    if (!$host) { $src_buckets['direct'] += $count; continue; }
    $host = strtolower(preg_replace('/^www\./', '', $host));

    /* Skip self-referrals (internal navigation isn't a "source") */
    if ($host === 'dennypratama.com') { continue; }

    $bucket = 'referral';
    foreach ($search_hosts as $needle) { if (strpos($host, $needle) !== false) { $bucket = 'search'; break; } }
    if ($bucket === 'referral') {
        foreach ($social_hosts as $needle) { if (strpos($host, $needle) !== false) { $bucket = 'social'; break; } }
    }
    $src_buckets[$bucket] += $count;
    $src_hosts[$host] = ($src_hosts[$host] ?? 0) + $count;
}

arsort($src_hosts);
$top_referrers   = array_slice($src_hosts, 0, 10, true);
$src_total       = array_sum($src_buckets);

/* ── Top countries (NULL = pre-migration or non-Cloudflare traffic, skipped) ── */
$top_countries = $pdo->query(
    "SELECT country, COUNT(*) AS v
     FROM page_views
     WHERE country IS NOT NULL
     GROUP BY country
     ORDER BY v DESC
     LIMIT 10"
)->fetchAll();
$country_total = (int)$pdo->query("SELECT COUNT(*) FROM page_views WHERE country IS NOT NULL")->fetchColumn();

/* ISO-2 → readable name (only the codes likely to appear; fall back to the code itself) */
$country_names = [
    'ID' => 'Indonesia', 'US' => 'United States', 'GB' => 'United Kingdom', 'SG' => 'Singapore',
    'MY' => 'Malaysia', 'AU' => 'Australia', 'CA' => 'Canada', 'DE' => 'Germany', 'FR' => 'France',
    'NL' => 'Netherlands', 'IN' => 'India', 'JP' => 'Japan', 'KR' => 'South Korea', 'PH' => 'Philippines',
    'TH' => 'Thailand', 'VN' => 'Vietnam', 'BR' => 'Brazil', 'MX' => 'Mexico', 'ES' => 'Spain',
    'IT' => 'Italy', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'NG' => 'Nigeria',
    'ZA' => 'South Africa', 'TR' => 'Turkey', 'PL' => 'Poland', 'SE' => 'Sweden', 'CH' => 'Switzerland',
    'IE' => 'Ireland', 'HK' => 'Hong Kong', 'TW' => 'Taiwan', 'CN' => 'China', 'PK' => 'Pakistan',
    'BD' => 'Bangladesh', 'EG' => 'Egypt', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
    'NZ' => 'New Zealand', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'BE' => 'Belgium',
    'AT' => 'Austria', 'PT' => 'Portugal', 'RO' => 'Romania', 'CZ' => 'Czechia',
];

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

/* ── Views-over-time chart: daily / weekly / monthly series ── */
$chart_series = ['daily' => [], 'weekly' => [], 'monthly' => []];

/* Daily — last 14 days */
$d_rows = $pdo->query(
    "SELECT DATE(viewed_at) AS k, COUNT(*) AS v
     FROM page_views
     WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY k"
)->fetchAll(PDO::FETCH_KEY_PAIR);
for ($i = 13; $i >= 0; $i--) {
    $k = date('Y-m-d', strtotime("-{$i} days"));
    $chart_series['daily'][] = ['label' => date('d/m', strtotime($k)), 'value' => (int)($d_rows[$k] ?? 0)];
}

/* Weekly — last 12 weeks (bucketed by Monday of each week) */
$w_rows = $pdo->query(
    "SELECT DATE(DATE_SUB(viewed_at, INTERVAL WEEKDAY(viewed_at) DAY)) AS k, COUNT(*) AS v
     FROM page_views
     WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL 11 WEEK)
     GROUP BY k"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$w_base = strtotime('monday this week');
for ($i = 11; $i >= 0; $i--) {
    $k = date('Y-m-d', strtotime("-{$i} week", $w_base));
    $chart_series['weekly'][] = ['label' => date('d/m', strtotime($k)), 'value' => (int)($w_rows[$k] ?? 0)];
}

/* Monthly — last 12 months (bucketed by first of month) */
$m_rows = $pdo->query(
    "SELECT DATE_FORMAT(viewed_at, '%Y-%m-01') AS k, COUNT(*) AS v
     FROM page_views
     WHERE viewed_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
     GROUP BY k"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$m_base = strtotime(date('Y-m-01'));
for ($i = 11; $i >= 0; $i--) {
    $k = date('Y-m-01', strtotime("-{$i} month", $m_base));
    $chart_series['monthly'][] = ['label' => date('M', strtotime($k)), 'value' => (int)($m_rows[$k] ?? 0)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — Admin</title>
  <meta name="robots" content="noindex, nofollow"/>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css?v=1"/>
  <style>
    /* ── Stat cards ── */
    .stats-grid {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 16px; margin-bottom: 16px;
    }
    .stats-grid-2 {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 16px; margin-bottom: 40px;
    }
    .stat-card {
      background: rgba(236,234,226,0.04);
      border: 1px solid rgba(236,234,226,0.07);
      padding: 24px;
    }
    .stat-label {
      font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); margin-bottom: 12px;
    }
    .stat-value {
      font-size: 32px; font-weight: 600; letter-spacing: -0.03em; color: #ECEAE2;
    }
    .stat-value.accent { color: #E8320A; }
    .stat-sub {
      font-size: 14px; color: rgba(236,234,226,0.25); margin-top: 8px;
    }

    /* ── Section heading ── */
    .section-heading {
      font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); margin-bottom: 16px;
      padding-bottom: 12px; border-bottom: 1px solid rgba(236,234,226,0.07);
    }

    /* ── Visitor split ── */
    .visitor-split {
      display: grid; grid-template-columns: repeat(2, 1fr);
      gap: 16px; margin-bottom: 40px;
    }
    .vsplit-card {
      background: rgba(236,234,226,0.03);
      border: 1px solid rgba(236,234,226,0.07);
      padding: 24px; display: flex; align-items: center; gap: 20px;
    }
    .vsplit-icon { font-size: 24px; opacity: 0.4; }
    .vsplit-label { font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(236,234,226,0.3); margin-bottom: 8px; }
    .vsplit-value { font-size: 28px; font-weight: 600; letter-spacing: -0.02em; }
    .vsplit-pct { font-size: 14px; color: rgba(236,234,226,0.25); margin-top: 4px; }

    /* ── Breakdown ── */
    .breakdown-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 16px; margin-bottom: 40px;
    }
    .breakdown-card {
      background: rgba(236,234,226,0.03);
      border: 1px solid rgba(236,234,226,0.07);
      padding: 20px 24px;
    }
    .breakdown-name {
      font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(236,234,226,0.35); margin-bottom: 16px;
    }
    .breakdown-row { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 8px; }
    .breakdown-metric { font-size: 14px; color: rgba(236,234,226,0.35); }
    .breakdown-num { font-size: 20px; font-weight: 600; letter-spacing: -0.02em; }
    .breakdown-num.dim { font-size: 16px; color: rgba(236,234,226,0.5); }
    .breakdown-divider { border: none; border-top: 1px solid rgba(236,234,226,0.05); margin: 12px 0; }

    /* ── Top posts table ── */
    table { margin-bottom: 40px; }
    .rank { color: rgba(236,234,226,0.25); font-size: 14px; width: 32px; }
    .post-title-cell { color: #ECEAE2; font-weight: 500; }
    .post-slug-cell { font-size: 14px; color: rgba(236,234,226,0.3); margin-top: 4px; }
    .view-bar-wrap { display: flex; align-items: center; gap: 12px; }
    .view-bar-bg { flex: 1; height: 3px; background: rgba(236,234,226,0.07); max-width: 160px; }
    .view-bar-fill { height: 100%; background: #E8320A; }
    .view-count { font-size: 14px; color: rgba(236,234,226,0.8); min-width: 28px; text-align: right; font-weight: 500; }
    .uniq-count { font-size: 14px; color: rgba(236,234,226,0.35); min-width: 52px; }

    /* ── Line chart ── */
    .chart-wrap {
      border: 1px solid rgba(236,234,226,0.07);
      padding: 32px 24px 20px;
      margin-bottom: 40px; overflow-x: auto;
    }
    .chart-svg { width: 100%; min-width: 560px; height: auto; display: block; }
    .chart-axis { stroke: rgba(236,234,226,0.08); stroke-width: 1; }
    .chart-area { fill: rgba(232,50,10,0.10); stroke: none; }
    .chart-line {
      fill: none; stroke: #E8320A; stroke-width: 2;
      stroke-linejoin: round; stroke-linecap: round;
      vector-effect: non-scaling-stroke;
    }
    .chart-dot { fill: #E8320A; transition: r 0.12s; }
    .chart-hit { fill: transparent; cursor: pointer; }
    /* Value shows only on hover of its point */
    .chart-val {
      fill: #ECEAE2; font-size: 11px;
      font-family: 'Geist Mono', monospace;
      opacity: 0; transition: opacity 0.12s;
    }
    .chart-pt:hover .chart-val { opacity: 1; }
    .chart-pt:hover .chart-dot { r: 4.5; }
    .chart-xlabel {
      fill: rgba(236,234,226,0.3); font-size: 11px;
      font-family: 'Geist Mono', monospace; letter-spacing: 0.04em;
    }

    /* ── Chart filter tabs ── */
    .chart-head {
      display: flex; align-items: center; justify-content: space-between;
      gap: 16px; flex-wrap: wrap; margin-bottom: 16px;
    }
    .chart-head .section-heading { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .chart-tabs { display: flex; border: 1px solid rgba(236,234,226,0.1); }
    .chart-tab {
      background: none; border: none; cursor: pointer;
      font-family: 'Geist Mono', monospace; font-size: 12px;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(236,234,226,0.4); padding: 8px 16px;
      border-right: 1px solid rgba(236,234,226,0.1);
      transition: color 0.2s, background 0.2s;
    }
    .chart-tab:last-child { border-right: none; }
    .chart-tab:hover { color: #ECEAE2; }
    .chart-tab.is-active { color: #E8320A; background: rgba(232,50,10,0.08); }

    .empty { color: rgba(236,234,226,0.2); font-size: 14px; padding: 32px 0; }

    /* ── Traffic sources grid (4 buckets) ── */
    .breakdown-grid.src-grid { grid-template-columns: repeat(4, 1fr); }
    .section-heading.sub-heading { margin-top: 8px; }
    @media (max-width: 768px) {
      .breakdown-grid.src-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/partials/sidebar.php'; ?>

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

    <!-- ── Views-over-time chart ── -->
    <div class="chart-head">
      <div class="section-heading">Views Over Time</div>
      <div class="chart-tabs" role="tablist">
        <button class="chart-tab is-active" data-range="daily"   type="button">Daily</button>
        <button class="chart-tab"           data-range="weekly"  type="button">Weekly</button>
        <button class="chart-tab"           data-range="monthly" type="button">Monthly</button>
      </div>
    </div>
    <div class="chart-wrap">
      <svg id="views-chart" class="chart-svg" viewBox="0 0 760 180" preserveAspectRatio="xMidYMid meet"
           role="img" aria-label="Page views over time"></svg>
    </div>
    <script>
    (function () {
      var SERIES = <?= json_encode($chart_series, JSON_UNESCAPED_UNICODE) ?>;
      var svg  = document.getElementById('views-chart');
      var tabs = document.querySelectorAll('.chart-tab');
      var H = 180, padL = 10, padR = 10, padT = 24, padB = 30;
      var currentRange = 'daily';

      /* Render at the chart's real pixel width so it fills the container 1:1 (text stays crisp) */
      function chartWidth() {
        var w = svg.clientWidth || (svg.parentNode && svg.parentNode.clientWidth) || 760;
        return Math.max(560, Math.round(w));
      }

      /* Catmull-Rom → cubic bezier for a smooth line; control points clamped to the plot */
      function smoothPath(pts, baseY) {
        if (pts.length < 2) return pts.length ? 'M' + pts[0].x + ',' + pts[0].y : '';
        var d = 'M' + pts[0].x + ',' + pts[0].y;
        for (var i = 0; i < pts.length - 1; i++) {
          var p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2;
          var c1x = p1.x + (p2.x - p0.x) / 6, c1y = p1.y + (p2.y - p0.y) / 6;
          var c2x = p2.x - (p3.x - p1.x) / 6, c2y = p2.y - (p3.y - p1.y) / 6;
          c1y = Math.max(padT, Math.min(baseY, c1y));
          c2y = Math.max(padT, Math.min(baseY, c2y));
          d += 'C' + c1x.toFixed(1) + ',' + c1y.toFixed(1) + ' '
                   + c2x.toFixed(1) + ',' + c2y.toFixed(1) + ' '
                   + p2.x + ',' + p2.y;
        }
        return d;
      }

      function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;'); }

      function render() {
        var data = SERIES[currentRange] || [];
        var n = data.length;
        if (!n) { svg.innerHTML = ''; return; }
        var W = chartWidth();
        var plotW = W - padL - padR, plotH = H - padT - padB, baseY = padT + plotH;
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);

        var max = 1;
        data.forEach(function (d) { if (d.value > max) max = d.value; });
        var step = n > 1 ? plotW / (n - 1) : 0;
        var pts = data.map(function (d, i) {
          return {
            x: +(padL + i * step).toFixed(1),
            y: +(baseY - (d.value / max) * plotH).toFixed(1),
            v: d.value, label: d.label
          };
        });
        var line = smoothPath(pts, baseY);
        var area = line + ' L' + pts[n - 1].x + ',' + baseY + ' L' + pts[0].x + ',' + baseY + ' Z';

        var s = '<line class="chart-axis" x1="' + padL + '" y1="' + baseY + '" x2="' + (W - padR) + '" y2="' + baseY + '"/>';
        s += '<path class="chart-area" d="' + area + '"/>';
        s += '<path class="chart-line" d="' + line + '"/>';
        pts.forEach(function (p) {
          var bandW = step > 0 ? step : 40;
          s += '<g class="chart-pt">'
            +    '<text class="chart-val" x="' + p.x + '" y="' + Math.max(padT - 6, p.y - 10) + '" text-anchor="middle">' + p.v + '</text>'
            +    '<circle class="chart-dot" cx="' + p.x + '" cy="' + p.y + '" r="3"/>'
            +    '<rect class="chart-hit" x="' + (p.x - bandW / 2).toFixed(1) + '" y="' + padT + '" width="' + bandW.toFixed(1) + '" height="' + plotH + '">'
            +      '<title>' + esc(p.label) + ' — ' + p.v + ' views</title></rect>'
            +  '</g>';
          s += '<text class="chart-xlabel" x="' + p.x + '" y="' + (baseY + 18) + '" text-anchor="middle">' + esc(p.label) + '</text>';
        });
        svg.innerHTML = s;
      }

      tabs.forEach(function (t) {
        t.addEventListener('click', function () {
          tabs.forEach(function (x) { x.classList.remove('is-active'); });
          t.classList.add('is-active');
          currentRange = t.dataset.range;
          render();
        });
      });

      var resizeTimer;
      window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(render, 150);
      });
      render();
    })();
    </script>

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

    <!-- ── Traffic sources ── -->
    <div class="section-heading">Traffic Sources</div>
    <?php if ($src_total === 0): ?>
      <div class="empty">No traffic source data yet.</div>
    <?php else: ?>
      <div class="breakdown-grid src-grid">
        <?php
          $src_meta = [
            'direct'   => ['name' => 'Direct',   'sub' => 'Typed or bookmarked'],
            'search'   => ['name' => 'Search',   'sub' => 'Google, Bing, etc.'],
            'social'   => ['name' => 'Social',   'sub' => 'X, LinkedIn, etc.'],
            'referral' => ['name' => 'Referral', 'sub' => 'Other websites'],
          ];
        ?>
        <?php foreach ($src_meta as $key => $meta): $n = $src_buckets[$key]; ?>
        <div class="breakdown-card">
          <div class="breakdown-name"><?= $meta['name'] ?></div>
          <div class="breakdown-row">
            <span class="breakdown-metric"><?= $meta['sub'] ?></span>
            <span class="breakdown-num"><?= number_format($n) ?></span>
          </div>
          <hr class="breakdown-divider"/>
          <div class="breakdown-row">
            <span class="breakdown-metric">Share</span>
            <span class="breakdown-num dim"><?= $src_total > 0 ? round($n / $src_total * 100) : 0 ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($top_referrers)): $max_ref = max($top_referrers); ?>
        <div class="section-heading sub-heading">Top Referrers</div>
        <table>
          <thead>
            <tr><th>#</th><th>Source</th><th>Views</th></tr>
          </thead>
          <tbody>
            <?php $i = 0; foreach ($top_referrers as $host => $count): $i++; ?>
            <tr>
              <td class="rank"><?= $i ?></td>
              <td class="post-title-cell"><?= htmlspecialchars($host) ?></td>
              <td>
                <div class="view-bar-wrap">
                  <div class="view-bar-bg">
                    <div class="view-bar-fill" style="width:<?= round($count / $max_ref * 100) ?>%"></div>
                  </div>
                  <span class="view-count"><?= number_format($count) ?></span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ── Top countries ── -->
    <div class="section-heading">Top Countries</div>
    <?php if (empty($top_countries)): ?>
      <div class="empty">No country data yet. Cloudflare must be in front of the site and the migration must be run.</div>
    <?php else: $max_country = (int)$top_countries[0]['v']; ?>
      <table>
        <thead>
          <tr><th>#</th><th>Country</th><th>Views</th></tr>
        </thead>
        <tbody>
          <?php foreach ($top_countries as $i => $row):
            $code = $row['country'];
            $name = $country_names[$code] ?? $code;
          ?>
          <tr>
            <td class="rank"><?= $i + 1 ?></td>
            <td>
              <div class="post-title-cell"><?= htmlspecialchars($name) ?></div>
              <div class="post-slug-cell"><?= htmlspecialchars($code) ?></div>
            </td>
            <td>
              <div class="view-bar-wrap">
                <div class="view-bar-bg">
                  <div class="view-bar-fill" style="width:<?= round($row['v'] / $max_country * 100) ?>%"></div>
                </div>
                <span class="view-count"><?= number_format($row['v']) ?></span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

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

  <script src="admin.js"></script>
</body>
</html>
