<?php
$title       = 'Blog — Denny Pratama';
$description = 'Thoughts and ideas on UI/UX design, development, and AI from Denny Pratama.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main>
  <!-- ── BLOG HERO ── -->
  <section class="blog-hero">
    <div class="blog-hero-eyebrow">Blog</div>
    <h1 class="blog-hero-title">Thoughts &amp; ideas.</h1>
  </section>

  <!-- ── CATEGORY FILTER ── -->
  <div class="blog-filter">
    <button class="blog-filter-btn active" data-cat="">All</button>
    <button class="blog-filter-btn" data-cat="uiux">UI/UX</button>
    <button class="blog-filter-btn" data-cat="development">Development</button>
    <button class="blog-filter-btn" data-cat="ai">AI</button>
  </div>

  <!-- ── BLOG GRID (populated by JS) ── -->
  <div class="blog-grid" id="blog-grid">
    <div class="blog-loading">
      <span class="blog-loading-dot"></span>
      <span class="blog-loading-dot"></span>
      <span class="blog-loading-dot"></span>
    </div>
  </div>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

  <script>
    const CAT_LABELS = { uiux: 'UI/UX', development: 'Development', ai: 'AI' };

    function escHtml(s) {
      return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatDate(iso) {
      if (!iso) return '';
      return new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    let allPosts = [];

    function renderCards(posts) {
      const grid = document.getElementById('blog-grid');
      if (!posts.length) {
        grid.innerHTML = '<div class="blog-empty">No posts in this category yet.</div>';
        return;
      }
      grid.innerHTML = posts.map(post => `
        <a class="blog-card" href="/post?slug=${encodeURIComponent(post.slug)}">
          ${post.featured_image
            ? `<img class="blog-card-img" src="${escHtml(post.featured_image)}" alt="${escHtml(post.title)}" loading="lazy"/>`
            : `<div class="blog-card-img"></div>`
          }
          ${post.category ? `<span class="blog-card-cat">${escHtml(CAT_LABELS[post.category] || post.category)}</span>` : ''}
          <div class="blog-card-meta">${formatDate(post.published_at)}</div>
          <div class="blog-card-title">${escHtml(post.title)}</div>
          ${post.excerpt ? `<div class="blog-card-excerpt">${escHtml(post.excerpt)}</div>` : ''}
          <div class="blog-card-readmore">Read &rarr;</div>
        </a>
      `).join('');

      document.querySelectorAll('.blog-card').forEach(el => {
        el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
        el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
      });
    }

    document.querySelectorAll('.blog-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.blog-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.dataset.cat;
        renderCards(cat ? allPosts.filter(p => p.category === cat) : allPosts);
      });
    });

    fetch('/api/posts.php')
      .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(data => { allPosts = data; renderCards(allPosts); })
      .catch(() => {
        document.getElementById('blog-grid').innerHTML =
          '<div class="blog-empty">Could not load posts. Please try again later.</div>';
      });
  </script>
</main>

  <script src="/script.js?v=11" defer></script>
  <script>var PAGE='blog',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
