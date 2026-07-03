<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$config_file = __DIR__ . '/../api/.auto_post_config.json';
$config_raw  = file_exists($config_file) ? file_get_contents($config_file) : '';
/* BOM-strip: a UTF-8 BOM breaks json_decode, which would empty the token
   and silently disable the Run Now button with no visible error. */
$config = json_decode(ltrim($config_raw, "\xEF\xBB\xBF"), true);
$config_broken = $config_raw !== '' && !is_array($config);
if (!is_array($config)) $config = [];

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
    "SELECT id, title, slug, published_at, featured_image FROM posts
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
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css?v=5"/>
  <style>
    .main { max-width: 1120px; }
    input[type=text], input[type=password] { font-size: 14px; padding: 11px 14px; }

    /* ── Two-column layout ── */
    .auto-layout { display: grid; grid-template-columns: 1fr 380px; gap: 48px; align-items: start; }
    .auto-main { min-width: 0; }
    .auto-sidebar { position: sticky; top: 24px; }
    .auto-sidebar-card {
      background: rgba(var(--text-rgb),0.03);
      border: 1px solid rgba(var(--text-rgb),0.08);
      padding: 24px;
    }
    @media (max-width: 960px) {
      .auto-layout { grid-template-columns: 1fr; }
      .auto-sidebar { position: static; }
    }

    .toggle-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border: 1px solid rgba(var(--text-rgb),0.08);
      background: rgba(var(--text-rgb),0.03); margin-bottom: 28px;
    }
    .toggle-label-text { font-size: 14px; color: rgba(var(--text-rgb),0.8); }
    .toggle-sub { font-size: 14px; color: rgba(var(--text-rgb),0.3); margin-top: 4px; }
    .toggle-switch { position: relative; width: 44px; height: 24px; cursor: pointer; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-track {
      position: absolute; inset: 0;
      background: rgba(var(--text-rgb),0.1); transition: background 0.2s;
      border-radius: 24px;
    }
    .toggle-track::after {
      content: ''; position: absolute; top: 3px; left: 3px;
      width: 18px; height: 18px; background: rgba(var(--text-rgb),0.4);
      border-radius: 50%; transition: transform 0.2s, background 0.2s;
    }
    .toggle-switch input:checked + .toggle-track { background: rgba(var(--red-rgb),0.3); }
    .toggle-switch input:checked + .toggle-track::after {
      transform: translateX(20px); background: var(--red);
    }

    .key-set { font-size: 14px; color: var(--red); margin-top: 8px; }

    .cron-box {
      background: rgba(var(--text-rgb),0.03); border: 1px solid rgba(var(--text-rgb),0.08);
      padding: 24px; margin-bottom: 40px;
    }
    .cron-url {
      font-family: monospace; font-size: 12px; color: rgba(var(--text-rgb),0.7);
      background: rgba(var(--text-rgb),0.05); padding: 10px 14px;
      word-break: break-all; margin-bottom: 16px;
      border: 1px solid rgba(var(--text-rgb),0.08);
    }
    .cron-copy {
      font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(var(--text-rgb),0.4); background: none;
      border: 1px solid rgba(var(--text-rgb),0.1);
      padding: 6px 14px; cursor: pointer; font-family: inherit;
      transition: color 0.2s, border-color 0.2s; margin-bottom: 20px;
    }
    .cron-copy:hover { color: var(--text); border-color: rgba(var(--text-rgb),0.3); }
    .cron-schedules { display: flex; flex-direction: column; gap: 8px; }
    .cron-row { display: flex; align-items: center; gap: 16px; }
    .cron-expr { font-family: monospace; font-size: 12px; color: var(--red); min-width: 100px; }
    .cron-desc { font-size: 12px; color: rgba(var(--text-rgb),0.4); }

    .run-btn {
      background: var(--red); color: var(--text); border: none;
      font-family: var(--font-sans); font-size: 12px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 13px 28px; cursor: pointer; transition: opacity 0.2s;
      display: block; width: 100%;
    }
    .run-btn:hover { opacity: 0.85; }
    .run-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .run-status {
      font-size: 14px; color: rgba(var(--text-rgb),0.5);
      display: block; margin-top: 10px; line-height: 1.5;
    }
    .run-status.ok  { color: #4ade80; }
    .run-status.err { color: var(--red); }

    .sidebar-rule { border: none; border-top: 1px solid rgba(var(--text-rgb),0.08); margin: 20px 0; }

    .btn-secondary {
      font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(var(--text-rgb),0.35); background: none;
      border: 1px solid rgba(var(--text-rgb),0.1);
      padding: 8px 16px; cursor: pointer; font-family: inherit;
      transition: color 0.2s, border-color 0.2s;
    }
    .btn-secondary:hover { color: var(--text); border-color: rgba(var(--text-rgb),0.25); }
    .btn-row { gap: 12px; margin-top: 32px; }

    .saved-banner {
      background: rgba(74,222,128,0.08); border: 1px solid rgba(74,222,128,0.2);
      padding: 12px 20px; font-size: 14px; color: #4ade80; margin-bottom: 28px;
    }

    /* Sidebar table — compact */
    .sidebar-table { width: 100%; border-collapse: collapse; }
    .sidebar-table th {
      font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(var(--text-rgb),0.3); padding: 0 0 10px; text-align: left; font-weight: 500;
    }
    .sidebar-table td { padding: 12px 8px 12px 0; font-size: 14px; color: rgba(var(--text-rgb),0.7); border-top: 1px solid rgba(var(--text-rgb),0.06); vertical-align: middle; }
    .sidebar-table td:last-child { white-space: nowrap; }
    .post-title-link { color: var(--text); text-decoration: none; font-weight: 500; font-size: 14px; line-height: 1.4; display: block; }
    .post-title-link:hover { color: var(--red); }
    .pub-date { font-size: 12px; color: rgba(var(--text-rgb),0.4); white-space: nowrap; }
    .empty-sidebar { font-size: 14px; color: rgba(var(--text-rgb),0.3); padding: 16px 0 4px; }
    .last-run { font-size: 12px; color: rgba(var(--text-rgb),0.3); margin-top: 4px; display: block; }

    .img-ok      { color: #4ade80; margin-right: 6px; }
    .img-missing { color: rgba(var(--text-rgb),0.3); margin-right: 6px; }
    .regen-btn {
      font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase;
      color: rgba(var(--text-rgb),0.5); background: none;
      border: 1px solid rgba(var(--text-rgb),0.12);
      padding: 3px 10px; cursor: pointer; font-family: inherit;
      transition: color 0.2s, border-color 0.2s;
    }
    .regen-btn:hover:not(:disabled) { color: var(--text); border-color: rgba(var(--text-rgb),0.3); }
    .regen-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .regen-status { font-size: 14px; color: rgba(var(--text-rgb),0.5); display: block; margin-top: 4px; }
    .regen-status.ok  { color: #4ade80; }
    .regen-status.err { color: var(--red); }
  </style>
</head>
<body>

  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main main--wide">
    <div class="top-bar">
      <h1>Auto Post</h1>
    </div>

    <?php if ($saved): ?>
      <div class="saved-banner">Settings saved.</div>
    <?php endif; ?>

    <?php if ($config_broken): ?>
      <div class="saved-banner" style="background:rgba(var(--red-rgb),0.08);border-color:rgba(var(--red-rgb),0.25);color:var(--red)">
        ⚠ api/.auto_post_config.json is corrupted (invalid JSON) — Run Now and the cron are disabled.
        Click “Save Settings” below to rewrite it (this generates a new token; update your cron URL in cPanel after).
      </div>
    <?php endif; ?>

    <?php if (!$token && !$config_broken): ?>
      <div class="saved-banner" style="background:rgba(var(--red-rgb),0.08);border-color:rgba(var(--red-rgb),0.25);color:var(--red)">
        ⚠ No secret token yet — Run Now is disabled. Click “Save Settings” to generate one.
      </div>
    <?php endif; ?>

    <div class="auto-layout">

      <!-- ── Left: config & cron settings ── -->
      <div class="auto-main">

        <!-- Enable toggle -->
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

        <!-- Settings form -->
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
            <label>OpenAI API Key (gpt-image-2 images)</label>
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

        <!-- Cron setup -->
        <div class="section-heading" style="margin-top:48px">Cron Setup (cPanel)</div>
        <div class="cron-box">
          <?php if ($token): ?>
            <label style="font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:rgba(var(--text-rgb),0.3);margin-bottom:8px;display:block">Your cron URL</label>
            <div class="cron-url" id="cron-url"><?= $cron_url ?></div>
            <button class="cron-copy" onclick="copyUrl()">Copy URL</button>
          <?php else: ?>
            <div class="hint">Save your settings first to generate the cron URL.</div>
          <?php endif; ?>

          <div style="font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:rgba(var(--text-rgb),0.3);margin-bottom:10px">Suggested schedules</div>
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
            <code style="color:rgba(var(--text-rgb),0.6)">php <?= htmlspecialchars(realpath(__DIR__ . '/../api/auto-post.php')) ?> <?= htmlspecialchars($token) ?></code><br><br>
            Or via wget (two requests, each under 30s):<br>
            <code style="color:rgba(var(--text-rgb),0.6)">wget -q -O /dev/null "<?= $cron_url ?>&amp;phase=1" &amp;&amp; wget -q -O /dev/null "<?= $cron_url ?>&amp;phase=2"</code>
          </div>

          <?php if ($last_run): ?>
            <div class="last-run" style="margin-top:16px">Last run: <?= date('d M Y, H:i', strtotime($last_run)) ?></div>
          <?php endif; ?>
        </div>

        <!-- Regenerate token -->
        <form method="POST" style="margin-bottom:40px"
              onsubmit="return confirm('This will invalidate your current cron URL. Update it in cPanel after.')">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
          <input type="hidden" name="action" value="regenerate_token"/>
          <button type="submit" class="btn-secondary">Regenerate secret token</button>
        </form>

      </div><!-- /.auto-main -->

      <!-- ── Right: sticky run panel ── -->
      <div class="auto-sidebar">
        <div class="auto-sidebar-card">

          <div class="section-heading" style="margin-top:0;margin-bottom:16px">Test Run</div>
          <button class="run-btn" id="run-btn" <?= !$token ? 'disabled' : '' ?>>Run Now</button>
          <span class="run-status" id="run-status"></span>

          <hr class="sidebar-rule"/>

          <div class="section-heading" style="margin-top:0;margin-bottom:16px">Recent Auto-Posts</div>
          <?php if (empty($recent)): ?>
            <div class="empty-sidebar">No auto-generated posts yet.</div>
          <?php else: ?>
            <table class="sidebar-table">
              <thead>
                <tr><th>Title</th><th>Image</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                  <td>
                    <a class="post-title-link" href="/blog/<?= htmlspecialchars($r['slug']) ?>" target="_blank" rel="noopener noreferrer">
                      <?= htmlspecialchars($r['title']) ?>
                    </a>
                    <span class="pub-date"><?= $r['published_at'] ? date('d M Y', strtotime($r['published_at'])) : '—' ?></span>
                  </td>
                  <td>
                    <?php if ($r['featured_image']): ?>
                      <span class="img-ok" title="Image present">✓</span>
                    <?php else: ?>
                      <span class="img-missing" title="No image">—</span>
                    <?php endif; ?>
                    <button class="regen-btn" data-id="<?= (int)$r['id'] ?>" <?= $token ? '' : 'disabled' ?>>
                      <?= $r['featured_image'] ? 'Regen' : 'Generate' ?>
                    </button>
                    <span class="regen-status" data-status-for="<?= (int)$r['id'] ?>"></span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

        </div>
      </div><!-- /.auto-sidebar -->

    </div><!-- /.auto-layout -->

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

    /* ── Animated-dots loading indicator (CSS in theme.css → .loading-dots) ── */
    function loadingHtml(verb, suffix) {
      return verb + '<span class="loading-dots"><span>.</span><span>.</span><span>.</span></span>' + (suffix || '');
    }

    /* ── Run Now (two-phase) ── */
    var runBtn    = document.getElementById('run-btn');
    var runStatus = document.getElementById('run-status');
    var TOKEN     = '<?= addslashes($token) ?>';

    /* Fetch wrapper: returns parsed JSON or throws with the raw body as the message.
       Cache-buster + no-store: the run URL is identical every click, so any cache
       layer (browser/Cloudflare) could replay a stale result instead of running. */
    function fetchJSON(url) {
      url += (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
      return fetch(url, { cache: 'no-store' }).then(function(r) {
        return r.text().then(function(txt) {
          try {
            var d = JSON.parse(txt);
            return d;
          } catch(e) {
            throw new Error('Server returned non-JSON (HTTP ' + r.status + '): ' + txt.slice(0, 300));
          }
        });
      });
    }

    /* ── Run Now: start a BACKGROUND run, then poll its status. No browser
       request ever waits on the minutes-long Claude call, so HTTP timeouts
       can no longer kill a paid run or hide its outcome. ── */
    if (runBtn) {
      var base      = '/api/auto-post.php?token=' + encodeURIComponent(TOKEN);
      var pollTimer = null;

      function stopPoll() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
      }

      function showRunState(run) {
        if (!run) return;
        if (run.state === 'done') {
          stopPoll();
          if (run.image) {
            runStatus.className = 'run-status ok';
            runStatus.textContent = '✓ Published: "' + (run.title || '') + '" (with image)';
          } else {
            runStatus.className = 'run-status err';
            runStatus.textContent = '⚠ Published: "' + (run.title || '') + '" — image failed ('
              + (run.image_error || 'unknown reason') + '). Use the Generate button next to the post to add one.';
          }
          setTimeout(function(){ location.reload(); }, 5000);
          return;
        }
        if (run.state === 'error') {
          stopPoll();
          runStatus.className = 'run-status err';
          if (run.post_id) {
            /* The post was created before the failure — this is a partial
               success, not a failed run. The image can be regenerated. */
            runStatus.textContent = '⚠ Published: "' + (run.title || '') + '" — a later step failed: '
              + (run.error || 'unknown') + '. Use the Generate button next to the post to add an image.';
            setTimeout(function(){ location.reload(); }, 7000);
          } else {
            runStatus.textContent = '✗ ' + (run.error || 'Run failed');
            runBtn.disabled = false;
          }
          return;
        }
        /* Still working — but if the status stopped updating, the host
           likely killed the background process. */
        if (run.age > 300) {
          stopPoll();
          runStatus.className = 'run-status err';
          runStatus.textContent = '⚠ The run stopped reporting — check the posts list and api/logs/auto-post.log.';
          runBtn.disabled = false;
          return;
        }
        if (run.state === 'starting')     runStatus.innerHTML = loadingHtml('Starting background run');
        else if (run.state === 'claude')  runStatus.innerHTML = loadingHtml('Phase 1 — Claude is writing the post');
        else                              runStatus.innerHTML = loadingHtml('Phase 2 — Generating featured image');
      }

      runBtn.addEventListener('click', function(){
        runBtn.disabled = true;
        runStatus.className = 'run-status';
        runStatus.innerHTML = loadingHtml('Checking connection');

        /* Preflight (~30 tokens): verifies deployed file + API key before
           any generation is paid for. */
        fetchJSON(base + '&phase=test')
          .then(function(t){
            if (!t.ok) throw new Error(t.error || 'Connection test failed');
            if (!t.v)  throw new Error('Server is running an outdated api/auto-post.php — re-upload it, then retry.');
            runStatus.innerHTML = loadingHtml('Starting background run');
            return fetchJSON(base + '&phase=start');
          })
          .then(function(s){
            if (!s.ok) throw new Error(s.error || 'Could not start the run');
            var polls = 0;
            pollTimer = setInterval(function(){
              if (++polls > 160) { // ~8 minutes
                stopPoll();
                runStatus.className = 'run-status err';
                runStatus.textContent = '⚠ Still running after 8 minutes — check the posts list and api/logs/auto-post.log.';
                runBtn.disabled = false;
                return;
              }
              fetchJSON(base + '&phase=status')
                .then(function(d){ if (d.ok) showRunState(d.run); })
                .catch(function(){ /* transient poll failure — keep polling */ });
            }, 3000);
          })
          .catch(function(err){
            runStatus.className = 'run-status err';
            runStatus.textContent = '✗ ' + ((err && err.message) ? err.message : 'Request failed');
            runBtn.disabled = false;
          });
      });
    }
  </script>
  <script>
    /* ── Per-post image regenerate ── */
    document.querySelectorAll('.regen-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!TOKEN) return;
        var id     = btn.dataset.id;
        var status = document.querySelector('.regen-status[data-status-for="' + id + '"]');
        btn.disabled = true;
        status.className = 'regen-status';
        status.innerHTML = loadingHtml('Generating', ' (30–60s)');

        fetchJSON('/api/auto-post.php?phase=regen&token=' + encodeURIComponent(TOKEN) + '&post_id=' + encodeURIComponent(id))
          .then(function (d) {
            if (d.image) {
              status.className   = 'regen-status ok';
              status.textContent = '✓ Saved — reloading…';
              setTimeout(function () { location.reload(); }, 1500);
            } else {
              status.className   = 'regen-status err';
              status.textContent = '✗ ' + (d.image_error || d.error || 'Failed');
              btn.disabled = false;
            }
          })
          .catch(function (err) {
            status.className   = 'regen-status err';
            status.textContent = '✗ ' + (err && err.message ? err.message : 'Request failed');
            console.error('Regen error:', err);
            btn.disabled = false;
          });
      });
    });
  </script>
  <script src="admin.js"></script>

</body>
</html>
