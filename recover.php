<?php
$title       = 'Recover Access — Denny Pratama';
$description = 'Lost your ebook access link? Enter your email and we\'ll resend all your purchase links instantly.';
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
      <p class="form-page-eyebrow">Ebook Access</p>
      <h1 class="form-page-title">Lost your link?</h1>
      <p class="form-page-sub">
        Enter the email you used to purchase and we'll resend
        your access links right away — no account needed.
      </p>

      <form class="form-stack" id="recover-form" novalidate>
        <input
          type="email"
          id="recover-email"
          name="email"
          class="form-input"
          placeholder="you@example.com"
          autocomplete="email"
          required
        />
        <button type="submit" class="form-btn" id="recover-btn">
          <span class="form-btn-label">Resend My Links</span>
          <span class="form-btn-spinner" aria-hidden="true"></span>
        </button>
      </form>

      <!-- State messages -->
      <div class="form-msg form-msg--success" id="msg-success" hidden>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
          <path d="M6 10l3 3 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p>Check your email! We sent your access links.</p>
      </div>

      <div class="form-msg form-msg--error" id="msg-not-found" hidden>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
          <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p>No purchases found for this email. Double-check the address you used at checkout.</p>
      </div>

      <div class="form-msg form-msg--error" id="msg-rate" hidden>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
          <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p>Too many requests. Please wait an hour before trying again.</p>
      </div>

      <div class="form-msg form-msg--error" id="msg-error" hidden>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
          <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p>Something went wrong. Please try again in a moment.</p>
      </div>
    </div>
  </section>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<style>
body { min-height: 100vh; display: flex; flex-direction: column; }
main { flex: 1; display: flex; align-items: center; }
</style>

<script>
(function () {
  const form    = document.getElementById('recover-form');
  const emailEl = document.getElementById('recover-email');
  const btn     = document.getElementById('recover-btn');

  const msgs = {
    success:   document.getElementById('msg-success'),
    not_found: document.getElementById('msg-not-found'),
    rate:      document.getElementById('msg-rate'),
    error:     document.getElementById('msg-error'),
  };

  function hideAll() {
    Object.values(msgs).forEach(el => el.hidden = true);
  }

  function show(key) {
    hideAll();
    if (msgs[key]) msgs[key].hidden = false;
  }

  function setLoading(on) {
    btn.disabled = on;
    btn.classList.toggle('loading', on);
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const email = emailEl.value.trim();
    if (!email) { emailEl.focus(); return; }

    hideAll();
    setLoading(true);

    try {
      const res  = await fetch('/api/ebook-recover', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email }),
      });
      const data = await res.json().catch(() => ({}));

      if (data.status === 'sent')              show('success');
      else if (data.status === 'not_found')    show('not_found');
      else if (data.status === 'rate_limited') show('rate');
      else                                     show('error');
    } catch {
      show('error');
    } finally {
      setLoading(false);
    }
  });
})();
</script>

  <script src="/script.js?v=11" defer></script>
  <script>var PAGE='recover',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
