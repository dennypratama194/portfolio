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
  <section class="form-page-section">
    <div class="form-page-wrap">

      <!-- ── Form state ── -->
      <div id="lib-form-state">
        <p class="form-page-eyebrow">My Library</p>
        <h1 class="form-page-title">Access your<br>purchases.</h1>
        <p class="form-page-sub">
          Enter the email you used at checkout and your ebooks
          will appear right here — no email trip needed.
        </p>

        <form class="form-stack" id="lib-form" novalidate>
          <input type="email" id="lib-email" name="email" class="form-input"
                 placeholder="you@example.com" autocomplete="email" required/>
          <button type="submit" class="form-btn" id="lib-btn">
            <span class="form-btn-label">View My Library</span>
            <span class="form-btn-spinner" aria-hidden="true"></span>
          </button>
        </form>

        <div class="form-msg form-msg--error" id="lib-msg-not-found" hidden>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <p>No purchases found for that email. Double-check the address you used at checkout,
          or <a href="/ebook/recover">resend your links by email →</a></p>
        </div>
        <div class="form-msg form-msg--error" id="lib-msg-rate" hidden>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <p>Too many requests. Please wait a bit and try again.</p>
        </div>
        <div class="form-msg form-msg--error" id="lib-msg-error" hidden>
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <p>Something went wrong. Please try again.</p>
        </div>
      </div>

      <!-- ── Results state ── -->
      <div id="lib-results-state" hidden>
        <p class="form-page-eyebrow">My Library</p>
        <h1 class="form-page-title">Your purchases.</h1>
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

/* ── Ebook result cards ── */
.lib-grid { display: flex; flex-direction: column; }

.lib-card {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 24px 0;
  border-top: 1px solid var(--border);
}
.lib-card:last-child { border-bottom: 1px solid var(--border); }

.lib-card-cover {
  flex-shrink: 0;
  width: 60px;
  aspect-ratio: 3 / 4;
  object-fit: cover;
  display: block;
}
.lib-card-cover-placeholder {
  flex-shrink: 0;
  width: 60px;
  aspect-ratio: 3 / 4;
  background: var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--ink-3);
}

.lib-card-body { flex: 1; min-width: 0; }
.lib-card-title {
  font-size: 15px;
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
  letter-spacing: 0.02em;
}

.lib-card-cta {
  flex-shrink: 0;
  display: inline-block;
  padding: 10px 20px;
  background: var(--ink);
  color: var(--paper);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-decoration: none;
  transition: background 0.2s;
  white-space: nowrap;
}
.lib-card-cta:hover { background: var(--red); }
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

  function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function showResults(purchases) {
    grid.innerHTML = '';
    purchases.forEach(function (p) {
      var date = p.paid_at
        ? new Date(p.paid_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        : '';

      var coverHtml = p.cover_image
        ? '<img class="lib-card-cover" src="' + escHtml(p.cover_image) + '" alt="' + escHtml(p.title) + '" loading="lazy"/>'
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

  <script src="/script.js?v=11" defer></script>
  <script>var PAGE='my-library',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
