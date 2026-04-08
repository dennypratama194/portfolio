/* ── Mobile sidebar burger ── */
(function () {
  var burger  = document.getElementById('mobile-burger');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if (!burger || !sidebar || !overlay) return;

  function open() {
    sidebar.classList.add('open');
    overlay.classList.add('visible');
    burger.classList.add('open');
    burger.setAttribute('aria-expanded', 'true');
  }

  function close() {
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
    burger.classList.remove('open');
    burger.setAttribute('aria-expanded', 'false');
  }

  burger.addEventListener('click', function () {
    sidebar.classList.contains('open') ? close() : open();
  });

  overlay.addEventListener('click', close);

  /* Close when a nav link is tapped on mobile */
  sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
    link.addEventListener('click', close);
  });
})();

/* ── Theme toggle ── */
(function () {
  var btn = document.getElementById('theme-toggle');
  if (!btn) return;
  function update() {
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    btn.textContent = dark ? '◑ Light mode' : '◐ Dark mode';
  }
  update();
  btn.addEventListener('click', function () {
    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('admin-theme', next);
    update();
  });
})();
