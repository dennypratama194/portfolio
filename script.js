/* ── PAGE TRANSITION (top-to-bottom curtain) ── */
(function () {
  const html = document.documentElement;
  const LEAVE_MS  = 550;
  const REVEAL_MS = 700;

  // Inbound: if arriving from a transition, slide curtain off bottom on next frame
  if (html.classList.contains('pt-arriving')) {
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        html.classList.add('pt-revealing');
        setTimeout(function () {
          html.classList.remove('pt-arriving', 'pt-revealing');
        }, REVEAL_MS + 50);
      });
    });
  }

  function shouldIntercept(a, e) {
    if (e.defaultPrevented) return false;
    if (e.button !== 0) return false;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
    if (a.target && a.target !== '' && a.target !== '_self') return false;
    if (a.hasAttribute('download')) return false;
    if (a.classList.contains('js-open-modal')) return false;
    const href = a.getAttribute('href');
    if (!href) return false;
    if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return false;
    let url;
    try { url = new URL(a.href, location.href); } catch (err) { return false; }
    if (url.origin !== location.origin) return false;
    // Same path + only a hash change → let browser handle scroll
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return false;
    return true;
  }

  document.addEventListener('click', function (e) {
    const a = e.target.closest('a');
    if (!a) return;
    if (!shouldIntercept(a, e)) return;
    e.preventDefault();
    try { sessionStorage.setItem('pt', '1'); } catch (err) {}
    html.classList.add('pt-leaving');
    setTimeout(function () { window.location.href = a.href; }, LEAVE_MS);
  });

  // Restore state if user comes back via bfcache
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      html.classList.remove('pt-leaving', 'pt-arriving', 'pt-revealing');
    }
  });
})();

/* ── CURSOR ── */
const ring = document.getElementById('cursor-ring');
const dot  = document.getElementById('cursor-dot');
let rX = 0, rY = 0, dX = 0, dY = 0;
document.addEventListener('mousemove', e => {
  dX = e.clientX; dY = e.clientY;
  dot.style.left = dX + 'px'; dot.style.top = dY + 'px';
});
function lerpCursor() {
  const nx = rX + (dX - rX) * 0.12;
  const ny = rY + (dY - rY) * 0.12;
  if (Math.abs(nx - rX) > 0.1 || Math.abs(ny - rY) > 0.1) {
    rX = nx; rY = ny;
    ring.style.left = rX + 'px'; ring.style.top = rY + 'px';
  } else {
    rX = nx; rY = ny;
  }
  requestAnimationFrame(lerpCursor);
}
lerpCursor();
/* Single delegated listener — avoids attaching handlers to 100+ elements */
document.addEventListener('mouseover', e => {
  document.body.classList.toggle('cursor-hover', !!e.target.closest('a, button, .wc, .cap-item, .stat-cell'));
});

/* ── NAV BURGER / OVERLAY ── */
const burger     = document.getElementById('nav-burger');
const navOverlay = document.getElementById('nav-overlay');
if (burger && navOverlay) {
  const overlayLinks = navOverlay.querySelectorAll('.nav-overlay-link');
  const overlayCtas  = navOverlay.querySelectorAll('.nav-overlay-cta');

  let scrollLockY = 0;
  function openNav() {
    scrollLockY = window.scrollY;
    document.body.classList.add('nav-open');
    document.documentElement.classList.add('nav-open');
    document.body.style.top = -scrollLockY + 'px';
    burger.setAttribute('aria-expanded', 'true');
    navOverlay.setAttribute('aria-hidden', 'false');
  }
  function closeNav() {
    document.body.classList.remove('nav-open');
    document.documentElement.classList.remove('nav-open');
    document.body.style.top = '';
    window.scrollTo(0, scrollLockY);
    burger.setAttribute('aria-expanded', 'false');
    navOverlay.setAttribute('aria-hidden', 'true');
  }

  burger.addEventListener('click', function() {
    document.body.classList.contains('nav-open') ? closeNav() : openNav();
  });
  overlayLinks.forEach(function(link) { link.addEventListener('click', closeNav); });
  overlayCtas.forEach(function(cta) { cta.addEventListener('click', closeNav); });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.body.classList.contains('nav-open')) closeNav();
  });
}

/* ── NAV SCROLL BLUR ── */
const navEl = document.querySelector('nav');
const navDarkEls = document.querySelectorAll('#about, #testimonials, #clients, #cta, footer');

/* Cache section offsets on load+resize to avoid getBoundingClientRect on every scroll */
let navH = navEl ? navEl.offsetHeight : 60;
let navSectionCache = [];
function cacheNavSections() {
  navH = navEl ? navEl.offsetHeight : 60;
  const scrollY = window.scrollY;
  navSectionCache = Array.from(navDarkEls).map(el => ({
    top: el.offsetTop,
    bottom: el.offsetTop + el.offsetHeight,
  }));
}
cacheNavSections();
window.addEventListener('resize', cacheNavSections, { passive: true });

function updateNavDark() {
  const scrollY = window.scrollY;
  navEl.classList.toggle('scrolled', scrollY > 40);
  const onDark = navSectionCache.some(({ top, bottom }) => {
    const rTop = top - scrollY;
    return rTop < navH && (bottom - scrollY) > 0;
  });
  document.body.classList.toggle('nav-on-dark', onDark);
}
window.addEventListener('scroll', updateNavDark, { passive: true });
updateNavDark();

/* ── SCROLL FADE BOTTOM THEME ── */
const fadEl = document.querySelector('.scroll-fade-bottom');
if (fadEl) {
  let fadeSectionCache = [];
  function cacheFadeSections() {
    fadeSectionCache = Array.from(document.querySelectorAll('#cta, footer')).map(el => ({
      top: el.offsetTop,
      bottom: el.offsetTop + el.offsetHeight,
    }));
  }
  cacheFadeSections();
  window.addEventListener('resize', cacheFadeSections, { passive: true });

  function updateFade() {
    const scrollY = window.scrollY;
    const vh = window.innerHeight;
    const atBottom = scrollY + vh >= document.body.scrollHeight - 40;
    fadEl.style.opacity = atBottom ? '0' : '1';
    const isDark = fadeSectionCache.some(({ top, bottom }) => {
      const rTop = top - scrollY;
      return rTop < vh && (bottom - scrollY) > vh * 0.5;
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

/* ── MAGNETIC BUTTONS ── */
document.querySelectorAll('.btn-hero-primary, .btn-cta-main').forEach(btn => {
  let r = { left: 0, top: 0, width: 0, height: 0 };
  /* Cache rect on hover start — not on every mousemove */
  btn.addEventListener('mouseenter', () => { r = btn.getBoundingClientRect(); });
  btn.addEventListener('mousemove', e => {
    const x = (e.clientX - r.left - r.width  / 2) * 0.22;
    const y = (e.clientY - r.top  - r.height / 2) * 0.22;
    btn.style.transform = 'translate(' + x + 'px,' + y + 'px)';
  });
  btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
});

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
        y: 100,
        opacity: 0,
        stagger: 0.14,
        duration: 1.5,
        ease: 'expo.out',
      });
    }

    // Subheadline + CTAs reveal after headline words finish
    const followEls = [heroDesc, heroCtas].filter(Boolean);
    if (followEls.length) {
      gsap.from(followEls, {
        y: 50,
        opacity: 0,
        stagger: 0.12,
        duration: 1.2,
        ease: 'expo.out',
        delay: wordEls.length * 0.14 + 0.3,
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

  // ── 5. CINEMATIC SCROLL REVEALS ─────────────────────────────────────────────
  (function () {
    if (typeof ScrollTrigger === 'undefined') return;

    const EASE = 'expo.out';
    const D    = 1.4;   // standard duration
    const DS   = 1.1;   // short duration

    function st(trigger, extra) {
      return Object.assign({ trigger: trigger, start: 'top 88%' }, extra || {});
    }

    // Work — section header
    const workHeaderEls = document.querySelectorAll('.work-header .section-meta, .work-title, .work-subtitle');
    if (workHeaderEls.length) {
      gsap.from(workHeaderEls, {
        y: 64, opacity: 0, stagger: 0.12, duration: D, ease: EASE,
        scrollTrigger: st(workHeaderEls[0]),
      });
    }

    // Work — scattered cards
    const wcCards = document.querySelectorAll('.wc');
    if (wcCards.length) {
      gsap.from(wcCards, {
        y: 80, opacity: 0, stagger: 0.1, duration: D, ease: EASE,
        scrollTrigger: st(wcCards[0], { start: 'top 90%' }),
      });
    }

    // Work — scroll-linked parallax (skipped on reduced-motion + small screens)
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduceMotion) {
      gsap.matchMedia().add('(min-width: 768px)', function () {
        // Negative = floats up faster than scroll, positive = sticky/heavier
        const wcSpeeds = { 'wc-1': -40, 'wc-2': -110, 'wc-3': 50, 'wc-4': -20, 'wc-5': -130 };
        wcCards.forEach(function (card) {
          const inner = card.querySelector('.wc-card');
          if (!inner) return;
          const speedKey = Array.from(card.classList).find(function (c) { return wcSpeeds[c] !== undefined; });
          const speed = speedKey ? wcSpeeds[speedKey] : 0;
          if (speed === 0) return;
          gsap.to(inner, {
            y: speed,
            ease: 'none',
            scrollTrigger: {
              trigger: card,
              start: 'top bottom',
              end: 'bottom top',
              scrub: 0.6,
            },
          });
        });
      });
    }

    // About — manifesto
    const manifesto = document.querySelector('.manifesto-text');
    if (manifesto) {
      gsap.from(manifesto, {
        y: 96, opacity: 0, duration: 1.6, ease: EASE,
        scrollTrigger: st(manifesto, { start: 'top 85%' }),
      });
    }

    // About — label + quote
    const manifestoLabel = document.querySelector('.manifesto-label');
    const aboutQuote     = document.querySelector('.about-quote');
    [manifestoLabel, aboutQuote].filter(Boolean).forEach(function (el, i) {
      gsap.from(el, {
        y: 40, opacity: 0, duration: DS, ease: EASE, delay: i * 0.12,
        scrollTrigger: st(el),
      });
    });

    // About — stat cells
    const statCells = document.querySelectorAll('.stat-cell');
    if (statCells.length) {
      gsap.from(statCells, {
        y: 60, opacity: 0, stagger: 0.12, duration: D, ease: EASE,
        scrollTrigger: st(statCells[0]),
      });
    }

    // About — bio blocks
    const bioBlocks = document.querySelectorAll('.bio-block');
    if (bioBlocks.length) {
      gsap.from(bioBlocks, {
        y: 48, opacity: 0, stagger: 0.1, duration: DS, ease: EASE,
        scrollTrigger: st(bioBlocks[0]),
      });
    }

    // About — capability items
    const capItems = document.querySelectorAll('.cap-item');
    if (capItems.length) {
      gsap.from(capItems, {
        x: -20, opacity: 0, stagger: 0.07, duration: 1.0, ease: EASE,
        scrollTrigger: st(capItems[0], { start: 'top 90%' }),
      });
    }

    // ── Trigger-based wash: paper → ink when #about hits 75% in view ─────────
    // No scrub — GPU-composited CSS transitions handle the interpolation, which
    // is frame-rate independent and immune to wheel/trackpad jitter. The class
    // toggles once on enter and reverses on scroll-back.
    const aboutEl = document.getElementById('about');
    if (aboutEl) {
      ScrollTrigger.create({
        trigger: aboutEl,
        start: 'top 25%',
        onEnter:     function () { aboutEl.classList.add('in-dark'); },
        onLeaveBack: function () { aboutEl.classList.remove('in-dark'); },
      });
    }

    // Approach — sticky scroll switcher
    (function () {
      var section = document.getElementById('approach');
      if (!section) return;

      var steps = section.querySelectorAll('.approach-step[data-step]');
      var imgs  = section.querySelectorAll('.approach-img[data-step]');
      var dots  = section.querySelectorAll('.approach-dot[data-step]');
      var TOTAL = 4;
      var current = 0;

      function setStep(i) {
        if (i === current) return;
        current = i;
        steps.forEach(function(el) { el.classList.toggle('is-active', +el.dataset.step === i); });
        imgs.forEach(function(el)  { el.classList.toggle('is-active', +el.dataset.step === i); });
        dots.forEach(function(el)  { el.classList.toggle('is-active', +el.dataset.step === i); });
      }

      // Mobile: skip scroll logic, all steps visible
      if (window.innerWidth <= 768) return;

      ScrollTrigger.create({
        trigger: section,
        start: 'top top',
        end: 'bottom bottom',
        onUpdate: function(self) {
          var step = Math.min(TOTAL - 1, Math.floor(self.progress * TOTAL));
          setStep(step);
        },
      });
    }());

    // Clients — header text
    const clientsHeader = document.querySelector('.clients-header');
    if (clientsHeader) {
      gsap.from(Array.from(clientsHeader.children), {
        y: 40, opacity: 0, stagger: 0.1, duration: DS, ease: EASE,
        scrollTrigger: st(clientsHeader),
      });
    }

    // Clients — logo cells
    const clientCells = document.querySelectorAll('.client-cell');
    if (clientCells.length) {
      gsap.from(clientCells, {
        scale: 0.9, opacity: 0, stagger: { amount: 0.8 },
        duration: DS, ease: EASE,
        scrollTrigger: st(clientCells[0]),
      });
    }

    // Blog preview — eyebrow + title
    const bpEyebrow = document.querySelector('.bp-eyebrow');
    const bpTitle   = document.querySelector('.bp-title');
    if (bpTitle) {
      gsap.from([bpEyebrow, bpTitle].filter(Boolean), {
        y: 60, opacity: 0, stagger: 0.12, duration: D, ease: EASE,
        scrollTrigger: st(bpTitle),
      });
    }

    // CTA — bg text parallax
    const ctaBgType = document.querySelector('.cta-bg-type');
    if (ctaBgType) {
      gsap.to(ctaBgType, {
        y: -80, ease: 'none',
        scrollTrigger: { trigger: '#cta', start: 'top bottom', end: 'bottom top', scrub: 1.2 },
      });
    }

    // CTA — content stagger
    const ctaEls = ['#cta .cta-label', '#cta .cta-title', '#cta .cta-row']
      .map(function (s) { return document.querySelector(s); }).filter(Boolean);
    if (ctaEls.length) {
      gsap.from(ctaEls, {
        y: 80, opacity: 0, stagger: 0.2, duration: 1.6, ease: EASE,
        scrollTrigger: st(ctaEls[0], { start: 'top 85%' }),
      });
    }

  }());
});

/* ── TESTIMONIAL SLIDER ── */
(function () {
  const stage = document.getElementById('testi-stage');
  if (!stage) return;
  const slides = Array.from(stage.querySelectorAll('.testi-slide'));
  if (slides.length <= 1) return;
  const arrows  = document.querySelectorAll('.bt-prev, .bt-next');
  let idx = 0;

  function go(next) {
    idx = (next + slides.length) % slides.length;
    slides.forEach(function (s, i) {
      s.classList.toggle('is-active', i === idx);
      s.querySelectorAll('.bento-dot').forEach(function (d, di) {
        d.classList.toggle('is-active', di === idx);
      });
    });
  }

  arrows.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const dir = btn.getAttribute('data-dir');
      go(idx + (dir === 'next' ? 1 : -1));
    });
  });

  document.addEventListener('keydown', function (e) {
    const tag = (e.target && e.target.tagName) || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA' || (e.target && e.target.isContentEditable)) return;
    const r = stage.getBoundingClientRect();
    const inView = r.top < window.innerHeight && r.bottom > 0;
    if (!inView) return;
    if (e.key === 'ArrowLeft')  go(idx - 1);
    if (e.key === 'ArrowRight') go(idx + 1);
  });
}());
