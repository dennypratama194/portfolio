<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$config_file = __DIR__ . '/../api/.auto_post_config.json';
$config = file_exists($config_file)
    ? json_decode(file_get_contents($config_file), true)
    : [];

$saved = false;

/* ── Save settings ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }
    $action = $_POST['action'] ?? 'save';

    if ($action === 'regenerate_token') {
        $config['token'] = bin2hex(random_bytes(24));
    } else {
        $config['enabled']           = isset($_POST['enabled']) ? true : false;
        $config['model']             = in_array($_POST['model'] ?? '', ['claude-haiku-4-5-20251001', 'claude-sonnet-4-6'])
                                       ? $_POST['model'] : 'claude-haiku-4-5-20251001';
        /* Only update keys if non-empty (don't wipe on re-save) */
        if (!empty($_POST['anthropic_api_key'])) $config['anthropic_api_key'] = trim($_POST['anthropic_api_key']);
        if (!empty($_POST['openai_api_key']))    $config['openai_api_key']    = trim($_POST['openai_api_key']);
        if (!isset($config['token']))            $config['token']             = bin2hex(random_bytes(24));
    }

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    $saved = true;
    header('Location: /admin/auto-post?saved=1');
    exit;
}

$saved = isset($_GET['saved']);

/* ── Recent auto-posts ── */
require __DIR__ . '/../api/db.php';
$recent = $pdo->query(
    "SELECT title, slug, published_at FROM posts
     WHERE body LIKE '%<!-- auto-generated -->%'
     ORDER BY published_at DESC LIMIT 5"
)->fetchAll();

$token    = $config['token']    ?? '';
$last_run = $config['last_run'] ?? null;
$enabled  = $config['enabled']  ?? false;
$model    = $config['model']    ?? 'claude-haiku-4-5-20251001';
$has_ant  = !empty($config['anthropic_api_key']);
$has_oai  = !empty($config['openai_api_key']);
$site_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'dennypratama.com');
$cron_url = $site_host . '/api/auto-post.php?token=' . htmlspecialchars($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Auto Post — Admin</title>
  <meta name="robots" content="noindex, nofollow"/>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css"/>
  <style>
    .main { max-width: 760px; }
    input[type=text], input[type=password] { font-size: 14px; padding: 11px 14px; }

    .toggle-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border: 1px solid rgba(236,234,226,0.08);
      background: rgba(236,234,226,0.03); margin-bottom: 28px;
    }
    .toggle-label-text { font-size: 14px; color: rgba(236,234,226,0.8); }
    .toggle-sub { font-size: 11px; color: rgba(236,234,226,0.3); margin-top: 3px; }
    .toggle-switch { position: relative; width: 44px; height: 24px; cursor: pointer; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-track {
      position: absolute; inset: 0;
      background: rgba(236,234,226,0.1); transition: background 0.2s;
      border-radius: 24px;
    }
    .toggle-track::after {
      content: ''; position: absolute; top: 3px; left: 3px;
      width: 18px; height: 18px; background: rgba(236,234,226,0.4);
      border-radius: 50%; transition: transform 0.2s, background 0.2s;
    }
    .toggle-switch input:checked + .toggle-track { background: rgba(232,50,10,0.3); }
    .toggle-switch input:checked + .toggle-track::after {
      transform: translateX(20px); background: #E8320A;
    }

    .key-set { font-size: 11px; color: #E8320A; margin-top: 6px; }

    .cron-box {
      background: rgba(236,234,226,0.03); border: 1px solid rgba(236,234,226,0.08);
      padding: 24px; margin-bottom: 40px;
    }
    .cron-url {
      font-family: monospace; font-size: 12px; color: rgba(236,234,226,0.7);
      background: rgba(236,234,226,0.05); padding: 10px 14px;
      word-break: break-all; margin-bottom: 16px;
      border: 1px solid rgba(236,234,226,0.08);
    }
    .cron-copy {
      font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(236,234,226,0.4); background: none;
      border: 1px solid rgba(236,234,226,0.1);
      padding: 6px 14px; cursor: pointer; font-family: inherit;
      transition: color 0.2s, border-color 0.2s; margin-bottom: 20px;
    }
    .cron-copy:hover { color: #ECEAE2; border-color: rgba(236,234,226,0.3); }
    .cron-schedules { display: flex; flex-direction: column; gap: 8px; }
    .cron-row { display: flex; align-items: center; gap: 16px; }
    .cron-expr { font-family: monospace; font-size: 12px; color: #E8320A; min-width: 100px; }
    .cron-desc { font-size: 12px; color: rgba(236,234,226,0.4); }

    .run-btn {
      background: #E8320A; color: #ECEAE2; border: none;
      font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 12px 28px; cursor: pointer; transition: opacity 0.2s; margin-right: 12px;
    }
    .run-btn:hover { opacity: 0.85; }
    .run-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .run-status { font-size: 13px; color: rgba(236,234,226,0.5); display: inline-block; margin-top: 0; vertical-align: middle; }
    .run-status.ok  { color: #4ade80; }
    .run-status.err { color: #E8320A; }

    .btn-secondary {
      font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(236,234,226,0.35); background: none;
      border: 1px solid rgba(236,234,226,0.1);
      padding: 8px 16px; cursor: pointer; font-family: inherit;
      transition: color 0.2s, border-color 0.2s;
    }
    .btn-secondary:hover { color: #ECEAE2; border-color: rgba(236,234,226,0.25); }
    .btn-row { gap: 12px; margin-top: 32px; }

    .saved-banner {
      background: rgba(74,222,128,0.08); border: 1px solid rgba(74,222,128,0.2);
      padding: 12px 18px; font-size: 13px; color: #4ade80; margin-bottom: 28px;
    }

    table { margin-bottom: 40px; }
    td { padding: 14px 16px 14px 0; font-size: 13px; color: rgba(236,234,226,0.7); }
    .post-title-link { color: #ECEAE2; text-decoration: none; font-weight: 500; }
    .post-title-link:hover { color: #E8320A; }
    .empty { font-size: 13px; padding: 24px 0; }
    .last-run { font-size: 12px; color: rgba(236,234,226,0.3); }
  </style>
</head>
<body>

  <div class="mobile-topbar">
    <div class="mobile-topbar-logo"><img src="/assets/logo.png" alt="Denny Pratama"/></div>
    <button class="mobile-burger" id="mobile-burger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo"><img src="/assets/logo.png" alt="Denny Pratama" style="height:28px;width:auto;opacity:0.85;"/></div>
    <nav class="sidebar-nav">
      <a class="sidebar-link" href="analytics.php">Dashboard</a>
      <a class="sidebar-link" href="index.php">Posts</a>
      <a class="sidebar-link active" href="auto-post.php">Auto Post</a>
      <a class="sidebar-link" href="ebooks.php">Ebooks</a>
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
      <h1>Auto Post</h1>
    </div>

    <?php if ($saved): ?>
      <div class="saved-banner">Settings saved.</div>
    <?php endif; ?>

    <!-- ── Enable toggle ── -->
    <div class="toggle-row">
      <div>
        <div class="toggle-label-text">Auto-publishing</div>
        <div class="toggle-sub">When enabled, the cron job will generate and publish a post automatically.</div>
      </div>
      <form method="POST" id="toggle-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
        <label class="toggle-switch" title="<?= $enabled ? 'Enabled' : 'Disabled' ?>">
          <input type="checkbox" name="enabled" <?= $enabled ? 'checked' : '' ?>
                 onchange="document.getElementById('toggle-form').submit()"/>
          <span class="toggle-track"></span>
        </label>
        <input type="hidden" name="action" value="save"/>
        <input type="hidden" name="model" value="<?= htmlspecialchars($model) ?>"/>
      </form>
    </div>

    <!-- ── Settings form ── -->
    <div class="section-heading">API Keys</div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
      <input type="hidden" name="action" value="save"/>

      <div class="field">
        <label>Anthropic API Key (Claude)</label>
        <input type="password" name="anthropic_api_key"
               placeholder="<?= $has_ant ? '••••••••••••••• (saved — leave blank to keep)' : 'sk-ant-...' ?>"/>
        <?php if ($has_ant): ?>
          <div class="key-set">✓ Key saved</div>
        <?php else: ?>
          <div class="hint">Required. Get yours at console.anthropic.com</div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>OpenAI API Key (DALL-E images)</label>
        <input type="password" name="openai_api_key"
               placeholder="<?= $has_oai ? '••••••••••••••• (saved — leave blank to keep)' : 'sk-...' ?>"/>
        <?php if ($has_oai): ?>
          <div class="key-set">✓ Key saved</div>
        <?php else: ?>
          <div class="hint">Required for featured image generation. Get yours at platform.openai.com</div>
        <?php endif; ?>
      </div>

      <div class="section-heading" style="margin-top:32px">Model</div>
      <div class="field">
        <label>Claude Model</label>
        <select name="model">
          <option value="claude-haiku-4-5-20251001" <?= $model === 'claude-haiku-4-5-20251001' ? 'selected' : '' ?>>
            Claude Haiku — Fast &amp; cost-effective
          </option>
          <option value="claude-sonnet-4-6" <?= $model === 'claude-sonnet-4-6' ? 'selected' : '' ?>>
            Claude Sonnet — Higher quality
          </option>
        </select>
      </div>

      <div class="btn-row">
        <button type="submit" class="btn-save">Save Settings →</button>
      </div>
    </form>

    <!-- ── Cron setup ── -->
    <div class="section-heading" style="margin-top:48px">Cron Setup (cPanel)</div>
    <div class="cron-box">
      <?php if ($token): ?>
        <label style="font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:rgba(236,234,226,0.3);margin-bottom:8px;display:block">Your cron URL</label>
        <div class="cron-url" id="cron-url"><?= $cron_url ?></div>
        <button class="cron-copy" onclick="copyUrl()">Copy URL</button>
      <?php else: ?>
        <div class="hint">Save your settings first to generate the cron URL.</div>
      <?php endif; ?>

      <div style="font-size:11px;letter-spacing:0.1em;text-transform:uppercase;color:rgba(236,234,226,0.3);margin-bottom:10px">Suggested schedules</div>
      <div class="cron-schedules">
        <div class="cron-row">
          <span class="cron-expr">0 8 * * 1</span>
          <span class="cron-desc">Every Monday at 8am</span>
        </div>
        <div class="cron-row">
          <span class="cron-expr">0 8 * * 1,4</span>
          <span class="cron-desc">Monday &amp; Thursday at 8am</span>
        </div>
        <div class="cron-row">
          <span class="cron-expr">0 8 * * *</span>
          <span class="cron-desc">Every day at 8am</span>
        </div>
      </div>

      <div class="hint" style="margin-top:16px">
        In cPanel → Cron Jobs, use the PHP CLI command (recommended — no HTTP timeout):<br>
        <code style="color:rgba(236,234,226,0.6)">php <?= htmlspecialchars(realpath(__DIR__ . '/../api/auto-post.php')) ?> <?= htmlspecialchars($token) ?></code><br><br>
        Or via wget (two requests, each under 30s):<br>
        <code style="color:rgba(236,234,226,0.6)">wget -q -O /dev/null "<?= $cron_url ?>&amp;phase=1" &amp;&amp; wget -q -O /dev/null "<?= $cron_url ?>&amp;phase=2"</code>
      </div>

      <?php if ($last_run): ?>
        <div class="last-run" style="margin-top:16px">Last run: <?= date('d M Y, H:i', strtotime($last_run)) ?></div>
      <?php endif; ?>
    </div>

    <!-- ── Regenerate token ── -->
    <form method="POST" style="margin-bottom:40px"
          onsubmit="return confirm('This will invalidate your current cron URL. Update it in cPanel after.')">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
      <input type="hidden" name="action" value="regenerate_token"/>
      <button type="submit" class="btn-secondary">Regenerate secret token</button>
    </form>

    <!-- ── Run now ── -->
    <div class="section-heading">Test</div>
    <div style="margin-bottom:40px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <button class="run-btn" id="run-btn" <?= !$token ? 'disabled' : '' ?>>
        Run Now
      </button>
      <span class="run-status" id="run-status"></span>
    </div>

    <!-- ── Recent auto-posts ── -->
    <div class="section-heading">Recent Auto-Posts</div>
    <?php if (empty($recent)): ?>
      <div class="empty">No auto-generated posts yet.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th>Title</th><th>Published</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td>
              <a class="post-title-link" href="/post?slug=<?= htmlspecialchars($r['slug']) ?>" target="_blank">
                <?= htmlspecialchars($r['title']) ?>
              </a>
            </td>
            <td><?= $r['published_at'] ? date('d M Y, H:i', strtotime($r['published_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  </main>

  <script>
    /* ── Copy cron URL ── */
    function copyUrl() {
      var url = document.getElementById('cron-url');
      if (!url) return;
      navigator.clipboard.writeText(url.textContent.trim()).then(function(){
        var btn = document.querySelector('.cron-copy');
        btn.textContent = 'Copied!';
        setTimeout(function(){ btn.textContent = 'Copy URL'; }, 2000);
      });
    }

    /* ── Run Now (two-phase) ── */
    var runBtn    = document.getElementById('run-btn');
    var runStatus = document.getElementById('run-status');
    var TOKEN     = '<?= addslashes($token) ?>';

    if (runBtn) {
      runBtn.addEventListener('click', function(){
        runBtn.disabled = true;
        runStatus.className = 'run-status';
        runStatus.textContent = 'Phase 1 — Generating content with Claude…';

        /* Phase 1: Claude generates post */
        fetch('/api/auto-post.php?token=' + encodeURIComponent(TOKEN) + '&phase=1')
          .then(function(r){ return r.json(); })
          .then(function(d1){
            if (!d1.ok) {
              runStatus.className = 'run-status err';
              runStatus.textContent = '✗ Phase 1 failed: ' + (d1.error || 'Unknown error');
              runBtn.disabled = false;
              return;
            }

            runStatus.textContent = 'Phase 2 — Generating featured image with DALL-E…';

            /* Phase 2: DALL-E generates image */
            var p2url = '/api/auto-post.php?token=' + encodeURIComponent(TOKEN)
              + '&phase=2'
              + '&post_id=' + d1.post_id
              + '&image_prompt=' + encodeURIComponent(d1.image_prompt || '');

            fetch(p2url)
              .then(function(r){ return r.json(); })
              .then(function(d2){
                runStatus.className = 'run-status ok';
                runStatus.textContent = '✓ Published: "' + d1.title + '"'
                  + (d2.image ? ' (with image)' : ' (no image)');
                setTimeout(function(){ location.reload(); }, 2500);
              })
              .catch(function(){
                /* Phase 2 failed but post was already created in phase 1 */
                runStatus.className = 'run-status ok';
                runStatus.textContent = '✓ Published: "' + d1.title + '" (image generation failed)';
                setTimeout(function(){ location.reload(); }, 2500);
              });
          })
          .catch(function(){
            runStatus.className = 'run-status err';
            runStatus.textContent = '✗ Request failed. Check your API keys and enable status.';
            runBtn.disabled = false;
          });
      });
    }
  </script>
  <script src="admin.js"></script>

</body>
</html>
