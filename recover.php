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
  <section class="recover-section">
    <div class="recover-wrap">
      <p class="recover-eyebrow">Ebook Access</p>
      <h1 class="recover-title">Lost your link?</h1>
      <p class="recover-sub">
        Enter the email you used to purchase and we'll resend
        your access links right away — no account needed.
      </p>

      <form class="recover-form" id="recover-form" novalidate>
        <div class="recover-field">
          <input
            type="email"
            id="recover-email"
            name="email"
            class="recover-input"
            placeholder="you@example.com"
            autocomplete="email"
            required
          />
        </div>
        <button type="submit" class="recover-btn" id="recover-btn">
          <span class="recover-btn-label">Resend My Links</span>
          <span class="recover-btn-spinner" aria-hidden="true"></span>
        </button>
      </form>

      <!-- State messages -->
      <div class="recover-msg recover-msg--success" id="msg-success" hidden>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
          <path d="M6 10l3 3 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <p>Check your email! We sent your access links.</p>
      </div>

      <div class="recover-msg recover-msg--error" id="msg-not-found" hidden>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
          <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p>No purchases found for this email. Double-check the address you used at checkout.</p>
      </div>

      <div class="recover-msg recover-msg--error" id="msg-rate" hidden>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
          <path d="M10 6v5M10 14h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p>Too many requests. Please wait an hour before trying again.</p>
      </div>

      <div class="recover-msg recover-msg--error" id="msg-error" hidden>
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
.recover-section {
  min-height: calc(100vh - var(--nav-h, 72px) - 240px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
}

.recover-wrap {
  width: 100%;
  max-width: 480px;
}

.recover-eyebrow {
  margin: 0 0 16px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--mid);
}

.recover-title {
  margin: 0 0 16px;
  font-size: clamp(32px, 6vw, 48px);
  font-weight: 600;
  letter-spacing: -0.03em;
  line-height: 1.1;
  color: var(--fg);
}

.recover-sub {
  margin: 0 0 40px;
  font-size: 15px;
  line-height: 1.65;
  color: var(--mid);
}

.recover-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.recover-field {
  position: relative;
}

.recover-input {
  width: 100%;
  padding: 14px 16px;
  font-size: 15px;
  font-family: inherit;
  color: var(--fg);
  background: transparent;
  border: 1px solid color-mix(in srgb, var(--fg) 18%, transparent);
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.recover-input::placeholder {
  color: var(--mid);
  opacity: 0.6;
}

.recover-input:focus {
  border-color: var(--accent, #E8320A);
}

.recover-btn {
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

.recover-btn:hover { opacity: 0.88; }
.recover-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.recover-btn-spinner {
  display: none;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: recover-spin 0.7s linear infinite;
}

.recover-btn.loading .recover-btn-spinner { display: block; }
.recover-btn.loading .recover-btn-label { opacity: 0.6; }

@keyframes recover-spin {
  to { transform: rotate(360deg); }
}

/* State messages */
.recover-msg {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 24px;
  padding: 16px 18px;
  font-size: 14px;
  line-height: 1.55;
}

.recover-msg[hidden] { display: none; }

.recover-msg p { margin: 0; }

.recover-msg svg { flex-shrink: 0; margin-top: 1px; }

.recover-msg--success {
  background: color-mix(in srgb, #22c55e 10%, transparent);
  border-left: 3px solid #22c55e;
  color: #166534;
}

[data-theme="dark"] .recover-msg--success {
  color: #86efac;
}

.recover-msg--error {
  background: color-mix(in srgb, #E8320A 10%, transparent);
  border-left: 3px solid #E8320A;
  color: #991b1b;
}

[data-theme="dark"] .recover-msg--error {
  color: #fca5a5;
}
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

      if (data.status === 'sent')         show('success');
      else if (data.status === 'not_found') show('not_found');
      else if (data.status === 'rate_limited') show('rate');
      else                                show('error');
    } catch {
      show('error');
    } finally {
      setLoading(false);
    }
  });
})();
</script>

  <script src="/script.js?v=6" defer></script>
  <script>var PAGE='recover',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
