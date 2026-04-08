<?php
$title       = 'My Library — Denny Pratama';
$description = 'Access all your purchased ebooks from Denny Pratama in one place.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main>
  <section class="lib-section">
    <div class="lib-wrap">

      <!-- ── Form state ── -->
      <div id="lib-form-state">
        <p class="lib-eyebrow">My Library</p>
        <h1 class="lib-title">Access your<br>purchases.</h1>
        <p class="lib-sub">
          Enter the email you used at checkout and your ebooks
          will appear right here — no email required.
        </p>

        <form class="lib-form" id="lib-form" novalidate>
          <input type="email" id="lib-email" name="email" class="lib-input"
                 placeholder="you@example.com" autocomplete="email" required/>
          <button type="submit" class="lib-btn" id="lib-btn">
            <span class="lib-btn-label">View My Library</span>
            <span class="lib-btn-spinner" aria-hidden="true"></span>
          </button>
        </form>

        <div class="lib-msg lib-msg--error" id="lib-msg-not-found" hidden>
          No purchases found for that email. Double-check the address you used at checkout,
          or <a href="/ebook/recover">request an access link by email →</a>
        </div>
        <div class="lib-msg lib-msg--error" id="lib-msg-rate" hidden>
          Too many requests. Please wait a bit and try again.
        </div>
        <div class="lib-msg lib-msg--error" id="lib-msg-error" hidden>
          Something went wrong. Please try again.
        </div>
      </div>

      <!-- ── Results state ── -->
      <div id="lib-results-state" hidden>
        <p class="lib-eyebrow">My Library</p>
        <h1 class="lib-title">Your purchases.</h1>
        <button class="lib-switch-btn" id="lib-switch-btn">← Use a different email</button>
        <div class="lib-grid" id="lib-grid"></div>
      </div>

    </div>
  </section>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<style>
body { min-height: 100vh; display: flex; flex-direction: column; }
main { flex: 1; display: flex; align-items: center; }

.lib-section {
  width: 100%;
  display: flex;
  justify-content: center;
  padding: 80px 24px;
}

.lib-wrap {
  width: 100%;
  max-width: 560px;
}

.lib-eyebrow {
  margin: 0 0 16px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.lib-title {
  margin: 0 0 16px;
  font-family: 'Instrument Serif', serif;
  font-weight: 400;
  font-style: italic;
  font-size: clamp(36px, 7vw, 56px);
  letter-spacing: -0.03em;
  line-height: 1.1;
  color: var(--ink);
}

.lib-sub {
  margin: 0 0 36px;
  font-size: 15px;
  line-height: 1.65;
  color: var(--ink-2);
}

/* ── Form ── */
.lib-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.lib-input {
  width: 100%;
  padding: 14px 16px;
  font-size: 15px;
  font-family: inherit;
  color: var(--ink);
  background: var(--paper);
  border: 1px solid color-mix(in srgb, var(--ink) 18%, transparent);
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.lib-input::placeholder { color: var(--ink-3); opacity: 0.7; }
.lib-input:focus { border-color: var(--red, #E8320A); }

.lib-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 28px;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #fff;
  background: #E8320A;
  border: none;
  cursor: pointer;
  transition: opacity 0.2s;
  font-family: inherit;
}
.lib-btn:hover { opacity: 0.88; }
.lib-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.lib-btn-spinner {
  display: none;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: lib-spin 0.7s linear infinite;
}
.lib-btn.loading .lib-btn-spinner { display: block; }
.lib-btn.loading .lib-btn-label  { opacity: 0.6; }
@keyframes lib-spin { to { transform: rotate(360deg); } }

/* ── Messages ── */
.lib-msg {
  margin-top: 20px;
  padding: 14px 18px;
  font-size: 14px;
  line-height: 1.55;
}
.lib-msg[hidden] { display: none; }
.lib-msg a { color: #E8320A; text-decoration: none; }
.lib-msg a:hover { text-decoration: underline; }

.lib-msg--error {
  background: color-mix(in srgb, #E8320A 8%, transparent);
  border-left: 3px solid #E8320A;
  color: #991b1b;
}
[data-theme="dark"] .lib-msg--error { color: #fca5a5; }

/* ── Switch email button ── */
.lib-switch-btn {
  background: none; border: none; padding: 0;
  font-size: 12px; font-family: inherit;
  letter-spacing: 0.06em; text-transform: uppercase;
  color: var(--ink-3); cursor: pointer;
  transition: color 0.2s;
  margin-bottom: 40px;
  display: inline-block;
}
.lib-switch-btn:hover { color: var(--ink); }

/* ── Ebook cards ── */
.lib-grid { display: flex; flex-direction: column; gap: 0; }

.lib-card {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 24px 0;
  border-top: 1px solid var(--border);
  text-decoration: none;
}
.lib-card:last-child { border-bottom: 1px solid var(--border); }

.lib-card-cover {
  flex-shrink: 0;
  width: 64px;
  aspect-ratio: 3 / 4;
  object-fit: cover;
  display: block;
  background: var(--border);
}
.lib-card-cover-placeholder {
  flex-shrink: 0;
  width: 64px;
  aspect-ratio: 3 / 4;
  background: var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: var(--ink-3);
}

.lib-card-body { flex: 1; min-width: 0; }
.lib-card-title {
  font-size: 16px;
  font-weight: 500;
  letter-spacing: -0.01em;
  color: var(--ink);
  margin: 0 0 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lib-card-date {
  font-size: 12px;
  color: var(--ink-3);
  letter-spacing: 0.04em;
}

.lib-card-cta {
  flex-shrink: 0;
  display: inline-block;
  padding: 10px 20px;
  background: #E8320A;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-decoration: none;
  transition: opacity 0.2s;
  white-space: nowrap;
}
.lib-card-cta:hover { opacity: 0.85; }
</style>

<script>
(function () {
  var STORAGE_KEY = 'lib_email';

  var formState    = document.getElementById('lib-form-state');
  var resultsState = document.getElementById('lib-results-state');
  var form         = document.getElementById('lib-form');
  var emailEl      = document.getElementById('lib-email');
  var btn          = document.getElementById('lib-btn');
  var grid         = document.getElementById('lib-grid');
  var switchBtn    = document.getElementById('lib-switch-btn');

  var msgNotFound = document.getElementById('lib-msg-not-found');
  var msgRate     = document.getElementById('lib-msg-rate');
  var msgError    = document.getElementById('lib-msg-error');

  /* Pre-fill email from localStorage */
  var saved = localStorage.getItem(STORAGE_KEY);
  if (saved) emailEl.value = saved;

  function hideMessages() {
    msgNotFound.hidden = true;
    msgRate.hidden     = true;
    msgError.hidden    = true;
  }

  function setLoading(on) {
    btn.disabled = on;
    btn.classList.toggle('loading', on);
  }

  function showResults(purchases) {
    grid.innerHTML = '';
    purchases.forEach(function (p) {
      var date = p.paid_at
        ? new Date(p.paid_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        : '';

      var coverHtml = p.cover_image
        ? '<img class="lib-card-cover" src="' + p.cover_image + '" alt="' + escHtml(p.title) + '" loading="lazy"/>'
        : '<div class="lib-card-cover-placeholder" aria-hidden="true">◆</div>';

      var card = document.createElement('div');
      card.className = 'lib-card';
      card.innerHTML =
        coverHtml +
        '<div class="lib-card-body">' +
          '<div class="lib-card-title">' + escHtml(p.title) + '</div>' +
          (date ? '<div class="lib-card-date">Purchased ' + date + '</div>' : '') +
        '</div>' +
        '<a class="lib-card-cta" href="' + escHtml(p.read_url) + '">Read Now →</a>';
      grid.appendChild(card);
    });

    formState.hidden    = true;
    resultsState.hidden = false;
  }

  function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    var email = emailEl.value.trim();
    if (!email) { emailEl.focus(); return; }

    hideMessages();
    setLoading(true);

    try {
      var res  = await fetch('/api/ebook-library', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email: email }),
      });
      var data = await res.json().catch(function(){ return {}; });

      if (data.status === 'found') {
        localStorage.setItem(STORAGE_KEY, email);
        showResults(data.purchases);
      } else if (data.status === 'not_found') {
        msgNotFound.hidden = false;
      } else if (data.status === 'rate_limited') {
        msgRate.hidden = false;
      } else {
        msgError.hidden = false;
      }
    } catch (err) {
      msgError.hidden = false;
    } finally {
      setLoading(false);
    }
  });

  switchBtn.addEventListener('click', function () {
    resultsState.hidden = true;
    formState.hidden    = false;
    hideMessages();
    emailEl.focus();
  });
})();
</script>

  <script src="/script.js?v=6" defer></script>
  <script>var PAGE='my-library',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
