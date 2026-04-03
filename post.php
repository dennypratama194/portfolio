<?php
/* Title/description are overridden via JS once post data loads,
   but these defaults ensure a valid fallback for crawlers. */
$title       = 'Post — Denny Pratama';
$description = 'Read the latest articles on UI/UX design, development, and AI by Denny Pratama.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main>
  <!-- ── POST CONTENT (populated by JS) ── -->
  <article id="post-root">
    <div class="post-hero" style="display:flex;align-items:center;gap:8px;padding-top:200px;">
      <span class="blog-loading-dot"></span>
      <span class="blog-loading-dot"></span>
      <span class="blog-loading-dot"></span>
    </div>
  </article>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

  <script>
    const slug = new URLSearchParams(window.location.search).get('slug');
    if (!slug) { window.location.replace('/blog'); }

    function formatDate(iso) {
      if (!iso) return '';
      return new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    const CAT_LABELS = { uiux: 'UI/UX', development: 'Development', ai: 'AI' };

    function escHtml(s) {
      return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderPost(post) {
      document.title = escHtml(post.title) + ' — Denny Pratama';

      document.getElementById('post-root').innerHTML = `
        <div class="post-hero">
          <div class="post-hero-meta">
            <a class="post-hero-back" href="/blog">← Blog</a>
            ${post.category ? `<span class="cat-badge">${escHtml(CAT_LABELS[post.category] || post.category)}</span>` : ''}
            <span>${formatDate(post.published_at)}</span>
          </div>
          <h1 class="post-hero-title">${escHtml(post.title)}</h1>
        </div>

        ${post.featured_image
          ? `<img class="post-featured-img" src="${escHtml(post.featured_image)}" alt="${escHtml(post.title)}"/>`
          : ''
        }

        <div class="post-body">${post.body || ''}</div>

        <div class="post-footer">
          <a class="post-footer-back" href="/blog">← All posts</a>
        </div>
      `;
    }

    fetch('/api/post.php?slug=' + encodeURIComponent(slug))
      .then(r => {
        if (r.status === 404) { window.location.replace('/blog'); return null; }
        if (!r.ok) throw new Error(r.status);
        return r.json();
      })
      .then(data => { if (data) renderPost(data); })
      .catch(() => {
        document.getElementById('post-root').innerHTML = `
          <div class="post-hero" style="padding-top:200px;">
            <div class="post-hero-meta"><a class="post-hero-back" href="/blog">← Blog</a></div>
            <h1 class="post-hero-title" style="font-size:clamp(28px,4vw,48px)">Could not load this post.</h1>
          </div>`;
      });
  </script>
  <script src="/script.js?v=3" defer></script>
  <script>var PAGE='post',SLUG=slug;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
