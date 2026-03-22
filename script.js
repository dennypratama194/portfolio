/* ── CURSOR ── */
const ring = document.getElementById('cursor-ring');
const dot  = document.getElementById('cursor-dot');
let rX = 0, rY = 0, dX = 0, dY = 0;
document.addEventListener('mousemove', e => {
  dX = e.clientX; dY = e.clientY;
  dot.style.left = dX + 'px'; dot.style.top = dY + 'px';
});
function lerpCursor() {
  rX += (dX - rX) * 0.12; rY += (dY - rY) * 0.12;
  ring.style.left = rX + 'px'; ring.style.top = rY + 'px';
  requestAnimationFrame(lerpCursor);
}
lerpCursor();
document.querySelectorAll('a, button, .project-panel, .cap-item, .stat-cell').forEach(el => {
  el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
  el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
});

/* ── MOBILE NAV BURGER ── */
const burger     = document.getElementById('nav-burger');
const navMobile  = document.getElementById('nav-mobile');
if (burger) {
  burger.addEventListener('click', () => document.body.classList.toggle('nav-open'));
  /* Close menu when a link is clicked */
  document.querySelectorAll('.nav-mobile-link, .nav-mobile-cta').forEach(link => {
    link.addEventListener('click', () => document.body.classList.remove('nav-open'));
  });
  /* Close on outside click */
  document.addEventListener('click', e => {
    if (document.body.classList.contains('nav-open') &&
        !burger.contains(e.target) && !navMobile.contains(e.target)) {
      document.body.classList.remove('nav-open');
    }
  });
}

/* ── NAV SCROLL BLUR ── */
const navEl = document.querySelector('nav');
window.addEventListener('scroll', () => {
  navEl.classList.toggle('scrolled', window.scrollY > 40);
}, { passive: true });

document.querySelectorAll('#cta, footer').forEach(el => {
  el.addEventListener('mouseenter', () => document.body.classList.add('on-dark'));
  el.addEventListener('mouseleave', () => document.body.classList.remove('on-dark'));
});

/* ── INTERSECTION OBSERVER ── */
const io = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.querySelectorAll('.reveal-word').forEach((w, i) =>
        setTimeout(() => w.classList.add('visible'), i * 80)
      );
      e.target.classList.add('section-visible');
    }
  });
}, { threshold: 0.15 });
document.querySelectorAll('section').forEach(s => io.observe(s));

/* ── HORIZONTAL SCROLL DRAG ── */
const hs = document.getElementById('hscroll');
if (hs) {
  let isDown = false, startX, scrollLeft, dragged = false;
  hs.addEventListener('mousedown', e => {
    isDown = true; dragged = false; hs.classList.add('grabbing');
    startX = e.pageX - hs.offsetLeft; scrollLeft = hs.scrollLeft;
  });
  hs.addEventListener('mouseleave', () => { isDown = false; hs.classList.remove('grabbing'); });
  hs.addEventListener('mouseup',    () => { isDown = false; hs.classList.remove('grabbing'); });
  hs.addEventListener('mousemove', e => {
    if (!isDown) return;
    e.preventDefault();
    dragged = true;
    hs.scrollLeft = scrollLeft - (e.pageX - hs.offsetLeft - startX) * 1.8;
  });
  hs.addEventListener('click', e => {
    if (dragged) e.preventDefault();
  }, true);
}

/* ── MAGNETIC BUTTONS ── */
document.querySelectorAll('.btn-hero-primary, .btn-cta-main').forEach(btn => {
  btn.addEventListener('mousemove', e => {
    const r = btn.getBoundingClientRect();
    const x = (e.clientX - r.left - r.width  / 2) * 0.22;
    const y = (e.clientY - r.top  - r.height / 2) * 0.22;
    btn.style.transform = 'translate(' + x + 'px,' + y + 'px)';
  });
  btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
});

/* ── BLOG PREVIEW ── */
(function () {
  const grid = document.getElementById('bp-grid');
  if (!grid) return;

  function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  fetch('/api/posts.php')
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(posts => {
      if (!posts.length) { grid.innerHTML = ''; return; }
      const latest = posts.slice(0, 3);
      grid.innerHTML = latest.map(p => `
        <a class="bp-card" href="/post?slug=${encodeURIComponent(p.slug)}">
          ${p.featured_image
            ? `<img class="bp-card-img" src="${p.featured_image}" alt="${p.title}" loading="lazy"/>`
            : `<div class="bp-card-img"></div>`}
          <div class="bp-card-meta">${formatDate(p.published_at)}</div>
          <div class="bp-card-title">${p.title}</div>
          ${p.excerpt ? `<div class="bp-card-excerpt">${p.excerpt}</div>` : ''}
          <div class="bp-card-read">Read →</div>
        </a>
      `).join('');
      grid.querySelectorAll('.bp-card').forEach(el => {
        el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
        el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
      });
    })
    .catch(() => { grid.innerHTML = ''; });
})();

/* ── PROJECT MODAL ── */
(function () {
  const modal       = document.getElementById('project-modal');
  if (!modal) return;
  const closeBtn    = document.getElementById('pm-close');
  const sendBtn     = document.getElementById('pm-send');
  const successBack = document.getElementById('pm-success-back');
  const formEl      = document.getElementById('pm-form');
  const successEl   = document.getElementById('pm-success');

  const RECAPTCHA_SITE_KEY = '6LdhaJMsAAAAAAJb5MDygyGZks49IXEDUNvrUZgQ';
  let recaptchaLoaded = false;

  function loadRecaptcha() {
    if (recaptchaLoaded || typeof grecaptcha !== 'undefined') { recaptchaLoaded = true; return; }
    const s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?render=' + RECAPTCHA_SITE_KEY;
    s.async = true;
    document.head.appendChild(s);
    recaptchaLoaded = true;
  }

  function openModal() {
    loadRecaptcha();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    formEl.style.display    = 'flex';
    successEl.style.display = 'none';
    modal.querySelectorAll('.pm-input').forEach(i => i.value = '');
    setTimeout(() => document.getElementById('pm-name').focus(), 400);
  }
  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }
  function shake(el) {
    el.classList.remove('shake'); void el.offsetWidth;
    el.classList.add('shake');
    el.addEventListener('animationend', () => el.classList.remove('shake'), { once: true });
  }

  sendBtn.addEventListener('click', async () => {
    const name    = document.getElementById('pm-name').value.trim();
    const email   = document.getElementById('pm-email').value.trim();
    const enquiry = document.getElementById('pm-enquiry').value.trim();

    if (!name)  { shake(document.getElementById('pm-name'));  return; }
    if (!email || !email.includes('@')) { shake(document.getElementById('pm-email')); return; }

    /* ── loading state ── */
    sendBtn.disabled    = true;
    sendBtn.textContent = 'Sending…';

    try {
      /* Get reCAPTCHA v3 token */
      const recaptcha_token = await new Promise((resolve, reject) => {
        if (typeof grecaptcha === 'undefined') { reject(new Error('reCAPTCHA not loaded')); return; }
        grecaptcha.ready(() =>
          grecaptcha.execute('YOUR_SITE_KEY', { action: 'contact' }).then(resolve).catch(reject)
        );
      });

      const res = await fetch('/api/contact.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, enquiry, recaptcha_token })
      });

      const json = await res.json();
      if (res.ok && json.success) {
        formEl.style.display    = 'none';
        successEl.style.display = 'flex';
      } else {
        throw new Error(json.message || 'Server error');
      }
    } catch {
      sendBtn.textContent = 'Failed — try again';
      sendBtn.disabled    = false;
    }
  });

  document.querySelectorAll('.js-open-modal').forEach(el =>
    el.addEventListener('click', e => { e.preventDefault(); openModal(); })
  );
  closeBtn.addEventListener('click', closeModal);
  successBack.addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });
  modal.querySelectorAll('button, input, textarea').forEach(el => {
    el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
    el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
  });
})();
