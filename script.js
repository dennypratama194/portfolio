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

/* ── NAV BURGER / OVERLAY ── */
const burger     = document.getElementById('nav-burger');
const navOverlay = document.getElementById('nav-overlay');
if (burger && navOverlay) {
  const overlayLinks = navOverlay.querySelectorAll('.nav-overlay-link');
  const navClose     = document.getElementById('nav-close');
  const overlayCtas  = navOverlay.querySelectorAll('.nav-overlay-cta');

  function openNav() {
    document.body.classList.add('nav-open');
    burger.setAttribute('aria-expanded', 'true');
    navOverlay.setAttribute('aria-hidden', 'false');
  }
  function closeNav() {
    document.body.classList.remove('nav-open');
    burger.setAttribute('aria-expanded', 'false');
    navOverlay.setAttribute('aria-hidden', 'true');
  }

  burger.addEventListener('click', function() {
    document.body.classList.contains('nav-open') ? closeNav() : openNav();
  });
  if (navClose) navClose.addEventListener('click', closeNav);
  overlayLinks.forEach(function(link) { link.addEventListener('click', closeNav); });
  overlayCtas.forEach(function(cta) { cta.addEventListener('click', closeNav); });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.body.classList.contains('nav-open')) closeNav();
  });
}

/* ── NAV SCROLL BLUR ── */
const navEl = document.querySelector('nav');
const navDarkEls = document.querySelectorAll('#cta, footer');
function updateNavDark() {
  navEl.classList.toggle('scrolled', window.scrollY > 40);
  const navH = navEl.offsetHeight;
  let onDark = false;
  navDarkEls.forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.top < navH && r.bottom > 0) onDark = true;
  });
  document.body.classList.toggle('nav-on-dark', onDark);
}
window.addEventListener('scroll', updateNavDark, { passive: true });
updateNavDark();

/* ── SCROLL FADE BOTTOM THEME ── */
const fadEl = document.querySelector('.scroll-fade-bottom');
if (fadEl) {
  const darkEls = document.querySelectorAll('#cta, footer');
  function updateFade() {
    const vh = window.innerHeight;
    const atBottom = window.scrollY + vh >= document.body.scrollHeight - 40;
    fadEl.style.opacity = atBottom ? '0' : '1';
    let isDark = false;
    darkEls.forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.top < vh && r.bottom > vh * 0.5) isDark = true;
    });
    fadEl.classList.toggle('is-dark', isDark);
  }
  window.addEventListener('scroll', updateFade, { passive: true });
  updateFade();
}

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
    modal.removeAttribute('inert');
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
    modal.setAttribute('inert', '');
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
          grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'contact' }).then(resolve).catch(reject)
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

// === GSAP ANIMATIONS ===
document.addEventListener('DOMContentLoaded', function () {
  if (typeof gsap === 'undefined') return;
  if (typeof ScrollTrigger !== 'undefined') gsap.registerPlugin(ScrollTrigger);

  // ── 1. HERO TEXT REVEAL ──────────────────────────────────────────────────────
  (function () {
    const heroLine1   = document.querySelector('.hero-line-1');
    const heroOutline = document.querySelector('.hero-line-2 .outline-word');
    const heroDesc    = document.querySelector('.hero-desc');
    const heroCtas    = document.querySelector('.hero-ctas');

    // Split .hero-line-1 text into individual word <span>s
    if (heroLine1) {
      const words = heroLine1.textContent.trim().split(/\s+/);
      heroLine1.innerHTML = words
        .map(w => '<span class="gsap-word" style="display:inline-block">' + w + '</span>')
        .join(' ');
    }

    // Collect headline word elements: split words + the outline word as one unit
    const wordEls = [];
    if (heroLine1)   wordEls.push(...heroLine1.querySelectorAll('.gsap-word'));
    if (heroOutline) wordEls.push(heroOutline);

    if (wordEls.length) {
      gsap.from(wordEls, {
        y: 60,
        opacity: 0,
        stagger: 0.12,
        duration: 0.9,
        ease: 'power3.out',
      });
    }

    // Subheadline + CTAs reveal after headline words finish
    const followEls = [heroDesc, heroCtas].filter(Boolean);
    if (followEls.length) {
      gsap.from(followEls, {
        y: 30,
        opacity: 0,
        stagger: 0.1,
        duration: 0.8,
        ease: 'power3.out',
        delay: wordEls.length * 0.12 + 0.3,
      });
    }
  }());

  // ── 2. WORK — GSAP HORIZONTAL SCROLL (desktop) / drag scroll (mobile) ───────
  (function () {
    if (typeof ScrollTrigger === 'undefined') return;
    const outer = document.getElementById('hscroll');
    const track = outer && outer.querySelector('.hscroll-track');
    if (!outer || !track) return;

    gsap.matchMedia().add('(min-width: 768px)', function () {
      // Take over from the CSS overflow scroll
      outer.style.overflow = 'hidden';

      // Update hint label to reflect the new interaction
      const hintLabel = document.querySelector('.drag-hint');
      if (hintLabel) hintLabel.innerHTML = '<span class="drag-hint-arrow">→</span> Scroll to explore';

      gsap.to(track, {
        x: function () { return -(track.scrollWidth - window.innerWidth); },
        ease: 'none',
        scrollTrigger: {
          trigger: '#work',
          pin: true,
          scrub: 1,
          start: 'top top',
          end: function () { return '+=' + (track.scrollWidth - window.innerWidth); },
          invalidateOnRefresh: true,
        },
      });
    });
  }());

  // ── 3. STATS COUNTER ────────────────────────────────────────────────────────
  (function () {
    if (typeof ScrollTrigger === 'undefined') return;
    document.querySelectorAll('.stat-num').forEach(function (el) {
      // The number is in the first text node; the suffix (+ or ×) is in a child <span>
      const textNode = el.firstChild;
      if (!textNode || textNode.nodeType !== Node.TEXT_NODE) return;
      const target = parseInt(textNode.textContent.trim(), 10);
      if (isNaN(target)) return; // skip ∞ and any non-numeric cells

      textNode.textContent = '0';
      const proxy = { val: 0 };
      gsap.to(proxy, {
        val: target,
        duration: 1.5,
        ease: 'power2.out',
        scrollTrigger: {
          trigger: '#about',
          start: 'top 80%',
          once: true,
        },
        onUpdate: function () {
          textNode.textContent = Math.round(proxy.val);
        },
      });
    });
  }());

  // ── 4. SKILLS TICKER — GSAP infinite loop ───────────────────────────────────
  (function () {
    const track = document.querySelector('.ticker-track');
    if (!track) return;

    // Disable the existing CSS keyframe animation
    track.style.animation = 'none';
    track.style.transform = 'translateX(0)';

    // Drive the loop with GSAP: content is already duplicated in HTML,
    // so moving -50% lands exactly at the start of the second copy → seamless
    const tween = gsap.to(track, {
      x: '-50%',
      duration: 20,
      repeat: -1,
      ease: 'none',
    });

    const wrap = document.querySelector('.ticker-wrap');
    if (wrap) {
      wrap.addEventListener('mouseenter', function () { tween.pause(); });
      wrap.addEventListener('mouseleave', function () { tween.resume(); });
    }
  }());
});
