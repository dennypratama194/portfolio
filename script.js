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
let isDown = false, startX, scrollLeft;
hs.addEventListener('mousedown', e => {
  isDown = true; hs.classList.add('grabbing');
  startX = e.pageX - hs.offsetLeft; scrollLeft = hs.scrollLeft;
});
hs.addEventListener('mouseleave', () => { isDown = false; hs.classList.remove('grabbing'); });
hs.addEventListener('mouseup',    () => { isDown = false; hs.classList.remove('grabbing'); });
hs.addEventListener('mousemove', e => {
  if (!isDown) return;
  e.preventDefault();
  hs.scrollLeft = scrollLeft - (e.pageX - hs.offsetLeft - startX) * 1.8;
});

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

/* ── PROJECT MODAL ── */
(function () {
  const modal       = document.getElementById('project-modal');
  const closeBtn    = document.getElementById('pm-close');
  const sendBtn     = document.getElementById('pm-send');
  const successBack = document.getElementById('pm-success-back');
  const formEl      = document.getElementById('pm-form');
  const successEl   = document.getElementById('pm-success');

  function openModal() {
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
      const res = await fetch('https://api.web3forms.com/submit', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          access_key: '89b01a8a-31ae-4672-a5de-53c2c8d834bd',
          subject: 'New project enquiry from ' + name,
          name, email, enquiry
        })
      });

      if (res.ok) {
        formEl.style.display    = 'none';
        successEl.style.display = 'flex';
      } else {
        throw new Error('Server error');
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
