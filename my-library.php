<?php
$title       = 'My Library — Denny Pratama';
$description = 'Access all your purchased ebooks from Denny Pratama. Enter your email to receive your library links — instant delivery, no account required, no expiry.';
$canonical   = 'https://dennypratama.com/my-library';
$og_image    = 'https://dennypratama.com/assets/logo.png';
$page_css    = '/css/my-library.css?v=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main id="main-content">
  <section class="form-page-section">
    <div class="form-page-wrap">

      <!-- ── Form state ── -->
      <div id="lib-form-state">
        <p class="form-page-eyebrow">My Library</p>
        <h1 class="form-page-title">Access your<br>purchases.</h1>
        <p class="form-page-sub">
          Enter the email you used at checkout and we'll send your
          access links straight to your inbox.
        </p>

        <form class="form-stack" id="lib-form" novalidate>
          <label for="lib-email" class="sr-only">Email address</label>
          <input type="email" id="lib-email" name="email" class="form-input"
                 placeholder="you@example.com" autocomplete="email" required/>
          <button type="submit" class="form-btn" id="lib-btn">
            <span class="form-btn-label">Email my links</span>
            <span class="form-btn-spinner" aria-hidden="true"></span>
          </button>
        </form>

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

      <!-- ── Sent confirmation state ── -->
      <div id="lib-sent-state" hidden>
        <p class="form-page-eyebrow">Check your inbox</p>
        <h2 class="form-page-title">Links sent.</h2>
        <p class="form-page-sub">
          If that email has any purchases, we've just sent the access links to it.
          Delivery can take a minute — remember to check your spam folder.
        </p>
        <button class="lib-switch-btn" id="lib-switch-btn">← Use a different email</button>
      </div>

    </div>
  </section>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>


<script>
(function () {
  var STORAGE_KEY = 'lib_email';

  var formState = document.getElementById('lib-form-state');
  var sentState = document.getElementById('lib-sent-state');
  var form      = document.getElementById('lib-form');
  var emailEl   = document.getElementById('lib-email');
  var btn       = document.getElementById('lib-btn');
  var switchBtn = document.getElementById('lib-switch-btn');

  var msgRate  = document.getElementById('lib-msg-rate');
  var msgError = document.getElementById('lib-msg-error');

  /* Pre-fill email from localStorage */
  var saved = localStorage.getItem(STORAGE_KEY);
  if (saved) emailEl.value = saved;

  function hideMessages() {
    msgRate.hidden  = true;
    msgError.hidden = true;
  }

  function setLoading(on) {
    btn.disabled = on;
    btn.classList.toggle('loading', on);
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    var email = emailEl.value.trim();
    if (!email) { emailEl.focus(); return; }

    hideMessages();
    setLoading(true);

    try {
      /* Secure flow: links are emailed to the address (only the inbox owner
         can use them). The response is intentionally generic. */
      var res  = await fetch('/api/ebook-recover', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email: email }),
      });
      var data = await res.json().catch(function(){ return {}; });

      if (data.status === 'sent') {
        localStorage.setItem(STORAGE_KEY, email);
        formState.hidden = true;
        sentState.hidden = false;
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
    sentState.hidden = true;
    formState.hidden = false;
    hideMessages();
    emailEl.focus();
  });
})();
</script>

  <script src="/script.js?v=24" defer></script>
  <script>var PAGE='my-library',SLUG=null;</script>
  <script src="/api/tracker.js?v=1" defer></script>
</body>
</html>
