<?php
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/helpers.php';

$bp_stmt = $pdo->query(
    'SELECT title, slug, excerpt, featured_image,
            COALESCE(published_at, scheduled_at) AS published_at
     FROM posts
     WHERE is_published = 1
        OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW())
     ORDER BY COALESCE(published_at, scheduled_at) DESC
     LIMIT 3'
);
$bp_posts = $bp_stmt->fetchAll();

$title       = 'Denny Pratama — Design is Conviction';
$description = 'UI/UX Designer & Developer based in Indonesia. I build digital products where aesthetics and function refuse to compromise on each other.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<div class="preloader" id="preloader" aria-hidden="true">
  <div class="preloader-window"></div>
  <div class="preloader-label">UIUX Designer</div>
  <div class="preloader-count" id="preloader-count">0</div>
  <div class="preloader-year">2026</div>
</div>
<script>
(function(){
  /* TODO before production: re-enable 4h cooldown + prefers-reduced-motion check */
  var pre = document.getElementById('preloader');
  if (!pre) return;

  // Only show on first homepage visit per session — skip on return navigation
  try {
    if (sessionStorage.getItem('preloaderSeen') === '1') {
      pre.parentNode && pre.parentNode.removeChild(pre);
      return;
    }
    sessionStorage.setItem('preloaderSeen', '1');
  } catch (e) { /* private mode — fall through and play once */ }

  document.documentElement.classList.add('preload-lock');
  var countEl = document.getElementById('preloader-count');
  var start = null;
  var DURATION = 2200;

  function tick(t) {
    if (!start) start = t;
    var p = Math.min(1, (t - start) / DURATION);
    var eased = 1 - Math.pow(1 - p, 3);
    countEl.textContent = Math.floor(eased * 100);
    if (p < 1) {
      requestAnimationFrame(tick);
    } else {
      countEl.textContent = '100';
      setTimeout(reveal, 320);
    }
  }

  function reveal() {
    pre.classList.add('is-revealing');
    setTimeout(function () {
      pre.classList.add('is-done');
      document.documentElement.classList.remove('preload-lock');
    }, 1750);
  }

  requestAnimationFrame(tick);
})();
</script>

<?php include 'partials/nav.php'; ?>

<main>
  <section id="hero">
    <div class="hero-overline">
      <div class="hero-overline-dot"></div>
      <span class="hero-overline-text">Denny Pratama · UIUX Designer · AI Enthusiast</span>
    </div>
    <div class="hero-year">2026</div>

    <div class="hero-type">
      <span class="hero-line-1">Design is</span>
      <div class="hero-line-2">
        <span class="outline-word">conviction.</span>
      </div>
    </div>

    <div class="hero-bottom">
      <div class="hero-bottom-left">
        <p class="hero-desc">
          I bridge the gap between visual craft and technical execution to build 
          digital products that look exceptional and drive measurable results.
        </p>
        <div class="hero-ctas">
          <a class="btn-hero-primary" href="#work">View Selected Work</a>
          <a class="btn-hero-ghost js-open-modal" href="#">
            <span class="arrow">↗</span>
            Discuss your project
          </a>
        </div>
      </div>
      <div class="scroll-cue">
        <span class="scroll-cue-label">Scroll</span>
        <div class="scroll-cue-track"></div>
      </div>
    </div>
  </section>

  <div class="ticker-wrap">
    <div class="ticker-track">
      <span class="ticker-item">UI/UX Design<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">WordPress Development<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Brand Identity<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Design Systems<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Interaction Design<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Digital Products<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Visual Strategy<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Web Design<span class="ticker-sep">◆</span></span>
      <!-- duplicate -->
      <span class="ticker-item">UI/UX Design<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">WordPress Development<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Brand Identity<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Design Systems<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Interaction Design<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Digital Products<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Visual Strategy<span class="ticker-sep">◆</span></span>
      <span class="ticker-item">Web Design<span class="ticker-sep">◆</span></span>
    </div>
  </div>

  <section id="work">
    <div class="work-header">
      <div class="section-meta" style="margin-bottom:0;">
        <span class="section-num">01</span>
        <div class="section-line" style="max-width:40px;"></div>
        <span class="section-title-sm">Selected Work</span>
      </div>
      <h2 class="work-title">The work.</h2>
      <p class="work-subtitle">Cases where design became the deciding factor.</p>
    </div>

    <div class="work-scattered">

      <a class="wc wc-1" href="https://xertra.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <img class="wc-img" src="https://picsum.photos/seed/xertra/500/617" alt="Xertra mockup placeholder" loading="lazy"/>
          <div class="wc-overlay">
            <span class="wc-num">01</span>
            <span class="wc-name">Xertra</span>
          </div>
        </div>
      </a>

      <a class="wc wc-2" href="https://wordsburg.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <img class="wc-img" src="https://picsum.photos/seed/wordsburg/500/617" alt="Wordsburg mockup placeholder" loading="lazy"/>
          <div class="wc-overlay">
            <span class="wc-num">02</span>
            <span class="wc-name">Wordsburg</span>
          </div>
        </div>
      </a>

      <a class="wc wc-3" href="https://brenom-systems-3.vercel.app/" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <img class="wc-img" src="https://picsum.photos/seed/brenom/500/617" alt="Brenom Systems mockup placeholder" loading="lazy"/>
          <div class="wc-overlay">
            <span class="wc-num">03</span>
            <span class="wc-name">Brenom Systems</span>
          </div>
        </div>
      </a>

      <a class="wc wc-4" href="https://digitalrevo.id" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <img class="wc-img" src="https://picsum.photos/seed/digitalrevo/500/617" alt="Digital Revo mockup placeholder" loading="lazy"/>
          <div class="wc-overlay">
            <span class="wc-num">04</span>
            <span class="wc-name">Digital Revo</span>
          </div>
        </div>
      </a>

      <a class="wc wc-5" href="https://morecreativeagency.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <img class="wc-img" src="https://picsum.photos/seed/more/500/617" alt="MoRe mockup placeholder" loading="lazy"/>
          <div class="wc-overlay">
            <span class="wc-num">05</span>
            <span class="wc-name">MoRe</span>
          </div>
        </div>
      </a>

    </div>
  </section>

  <section id="about">
    <div class="about-grid">

      <!-- Left: manifesto -->
      <div class="about-left-sticky">
        <div class="manifesto-label">02 — About</div>
        <h2 class="manifesto-text">
          The designer<br>
          who builds.<br/>
          <span class="accent">Both ways.</span>
        </h2>
        <blockquote class="about-quote">
          "Life is full of learning; when you stop learning,<br/>you stop living."
        </blockquote>
      </div>

      <!-- Right: content -->
      <div class="about-right">
        <div class="stats-block">
          <div class="stat-cell">
            <div class="stat-num">60<span class="red">+</span></div>
            <div class="stat-desc">Projects delivered, end to end</div>
          </div>
          <div class="stat-cell">
            <div class="stat-num">3<span class="red">+</span></div>
            <div class="stat-desc">Industries — agency, product, brand</div>
          </div>
          <div class="stat-cell">
            <div class="stat-num">2<span class="red">×</span></div>
            <div class="stat-desc">Disciplines in one person: design + dev</div>
          </div>
          <div class="stat-cell">
            <div class="stat-num">∞</div>
            <div class="stat-desc">Commitment to craft over convenience</div>
          </div>
        </div>

        <div class="bio-block">
          <div class="bio-label">Background</div>
          <p class="bio-text">
            I'm Denny Pratama — a <strong>UI/UX designer and developer</strong> based in Indonesia.
            I work at the intersection where visual craft meets technical execution.
            Most people do one or the other. I do both, because the gap between them is where the best work lives.
          </p>
        </div>

        <div class="bio-block">
          <div class="bio-label">Philosophy</div>
          <p class="bio-text">
            Design isn't decoration — it's <strong>decision-making made visible</strong>.
            I build products that are minimal not from laziness, but from discipline.
            Every element that remains is there because it earns its place.
          </p>
        </div>

      </div>
    </div>

    <div class="capg-grid">

      <div class="capg-card capg-card--large">
        <div class="capg-card-header">
          <span class="capg-title">UI/UX Design</span>
          <span class="capg-arrow">→</span>
        </div>
        <div class="capg-marquee" aria-hidden="true">
          <div class="capg-marquee-track">
            <span>UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;</span>
            <span>UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;UI/UX Design&nbsp;&nbsp;·&nbsp;&nbsp;</span>
          </div>
        </div>
        <div class="capg-pills">
          <span class="capg-pill">Research</span>
          <span class="capg-pill">Wireframes</span>
          <span class="capg-pill">Prototyping</span>
          <span class="capg-pill">Design Systems</span>
          <span class="capg-pill">Strategy</span>
        </div>
      </div>

      <div class="capg-card">
        <div class="capg-card-header">
          <span class="capg-title">Brand Identity</span>
          <span class="capg-arrow">→</span>
        </div>
        <div class="capg-marquee" aria-hidden="true">
          <div class="capg-marquee-track">
            <span>Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;</span>
            <span>Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;Brand Identity&nbsp;&nbsp;·&nbsp;&nbsp;</span>
          </div>
        </div>
        <div class="capg-pills">
          <span class="capg-pill">Visual Language</span>
          <span class="capg-pill">Logo</span>
          <span class="capg-pill">Guidelines</span>
          <span class="capg-pill">Typography</span>
        </div>
      </div>

      <div class="capg-card">
        <div class="capg-card-header">
          <span class="capg-title">Web Development</span>
          <span class="capg-arrow">→</span>
        </div>
        <div class="capg-marquee" aria-hidden="true">
          <div class="capg-marquee-track">
            <span>Web Development&nbsp;&nbsp;·&nbsp;&nbsp;Web Development&nbsp;&nbsp;·&nbsp;&nbsp;Web Development&nbsp;&nbsp;·&nbsp;&nbsp;Web Development&nbsp;&nbsp;·&nbsp;&nbsp;</span>
            <span>Web Development&nbsp;&nbsp;·&nbsp;&nbsp;Web Development&nbsp;&nbsp;·&nbsp;&nbsp;Web Development&nbsp;&nbsp;·&nbsp;&nbsp;Web Development&nbsp;&nbsp;·&nbsp;&nbsp;</span>
          </div>
        </div>
        <div class="capg-pills">
          <span class="capg-pill">PHP</span>
          <span class="capg-pill">JavaScript</span>
          <span class="capg-pill">WordPress</span>
          <span class="capg-pill">MySQL</span>
        </div>
      </div>

    </div>
  </section><!-- /#about -->

  <section id="testimonials">
    <div class="bento-grid">

      <!-- Stats -->
      <div class="bento-cell bc-stats">
        <div class="bento-stat">
          <span class="bs-num">6<span class="bs-red">+</span></span>
          <span class="bs-label">Years of experience</span>
        </div>
        <div class="bento-stat">
          <span class="bs-num">40<span class="bs-red">+</span></span>
          <span class="bs-label">Projects delivered</span>
        </div>
        <div class="bento-stat">
          <span class="bs-num">10<span class="bs-red">+</span></span>
          <span class="bs-label">Clients worldwide</span>
        </div>
        <div class="bento-stat">
          <span class="bs-num">100<span class="bs-red">%</span></span>
          <span class="bs-label">Client satisfaction</span>
        </div>
      </div>

      <!-- Testimonial slider -->
      <div class="bento-cell bc-testi" id="testi-stage">
        <button class="bt-prev" data-dir="prev" aria-label="Previous testimonial"></button>
        <button class="bt-next" data-dir="next" aria-label="Next testimonial"></button>

        <article class="testi-slide is-active" data-index="0">
          <div class="bento-dots">
            <span class="bento-dot is-active"></span>
            <span class="bento-dot"></span>
            <span class="bento-dot"></span>
          </div>
          <blockquote class="bt-quote">&ldquo;Denny's ability to translate a vague brief into a polished, functional product is remarkable. He thinks in systems, not just screens &mdash; and the results speak for themselves.&rdquo;</blockquote>
          <div class="bt-author">
            <div class="bt-avatar" aria-hidden="true">AF</div>
            <div class="bt-author-info">
              <span class="bt-name">Ahmad Fauzi</span>
              <span class="bt-role">Founder, Xertra</span>
            </div>
          </div>
        </article>

        <article class="testi-slide" data-index="1">
          <div class="bento-dots">
            <span class="bento-dot"></span>
            <span class="bento-dot is-active"></span>
            <span class="bento-dot"></span>
          </div>
          <blockquote class="bt-quote">&ldquo;The redesign increased our user engagement significantly. Denny is more than a designer &mdash; he's a strategic partner who understands both sides of the product.&rdquo;</blockquote>
          <div class="bt-author">
            <div class="bt-avatar" aria-hidden="true">SC</div>
            <div class="bt-author-info">
              <span class="bt-name">Sarah Chen</span>
              <span class="bt-role">Product Lead, Wordsburg</span>
            </div>
          </div>
        </article>

        <article class="testi-slide" data-index="2">
          <div class="bento-dots">
            <span class="bento-dot"></span>
            <span class="bento-dot"></span>
            <span class="bento-dot is-active"></span>
          </div>
          <blockquote class="bt-quote">&ldquo;He delivered a brand identity that felt timeless from day one. Our team still references it as the gold standard for every new project we take on.&rdquo;</blockquote>
          <div class="bt-author">
            <div class="bt-avatar" aria-hidden="true">RA</div>
            <div class="bt-author-info">
              <span class="bt-name">Rizky Ananda</span>
              <span class="bt-role">Creative Director, MoRe</span>
            </div>
          </div>
        </article>
      </div>

      <!-- Image cell -->
      <div class="bento-cell bc-clients">
        <img src="https://picsum.photos/seed/denny/400/560" alt="Portfolio visual" class="bc-img" loading="lazy"/>
      </div>

      <!-- CTA -->
      <div class="bento-cell bc-cta">
        <p class="bc-cta-text">Have a project in&nbsp;mind?</p>
        <button class="bc-cta-btn js-open-modal">Start a project &rarr;</button>
      </div>

    </div>
  </section>

  <section id="clients">
    <div class="cl-grid">
      <div class="cl-cell cl-cell--text">
        <span class="cl-headline">Brands I've<br>partnered<br>with.</span>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-wordsburg.png" alt="Wordsburg" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-xertra.png" alt="Xertra" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-mariwisata.png" alt="Mariwisata" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-ortex.png" alt="Ortex" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-more.png" alt="MoRe Creative Agency" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-labme.png" alt="LAB.ME" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-socialbee.png" alt="Socialbee" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-edufarmers.png" alt="Edufarmers" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-ispapp.png" alt="ISPApp" class="cl-logo" loading="lazy"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-isohorns.png" alt="IsoHorns" class="cl-logo" loading="lazy"/>
      </div>
    </div>
  </section>

  <section id="approach">
    <h2 class="sr-only">My Process</h2>

    <div class="approach-layout" id="approach-layout">

      <!-- Left: sticky step info -->
      <div class="approach-left">
        <div class="approach-eyebrow">03 — Process</div>

        <div class="approach-steps-wrap">

          <div class="approach-step is-active" data-step="0">
            <div class="as-kicker">
              <span class="as-num">01</span>
              <span class="as-tag">Discover</span>
            </div>
            <div class="as-title">No assumptions.</div>
            <div class="as-desc">Deep research into your users, market, and goals before a single pixel is touched.</div>
          </div>

          <div class="approach-step" data-step="1">
            <div class="as-kicker">
              <span class="as-num">02</span>
              <span class="as-tag">Define</span>
            </div>
            <div class="as-title">Clarity first.</div>
            <div class="as-desc">Strategy, IA, and wireframes locked before visual design begins. Prevents expensive rework.</div>
          </div>

          <div class="approach-step" data-step="2">
            <div class="as-kicker">
              <span class="as-num">03</span>
              <span class="as-tag">Design</span>
            </div>
            <div class="as-title">Craft, not trend.</div>
            <div class="as-desc">High-fidelity UI with motion, systems, and visual language built to endure — not just impress.</div>
          </div>

          <div class="approach-step" data-step="3">
            <div class="as-kicker">
              <span class="as-num">04</span>
              <span class="as-tag">Deliver</span>
            </div>
            <div class="as-title">Ship it. Own it.</div>
            <div class="as-desc">Full build or clean handoff. Your product, live and accountable. No disappearing acts.</div>
          </div>

        </div>

        <div class="approach-dots" id="approach-dots">
          <span class="approach-dot is-active" data-step="0"></span>
          <span class="approach-dot" data-step="1"></span>
          <span class="approach-dot" data-step="2"></span>
          <span class="approach-dot" data-step="3"></span>
        </div>
      </div>

      <!-- Right: sticky dummy images -->
      <div class="approach-right">
        <div class="approach-img is-active" data-step="0">
          <img class="ai-img" src="https://picsum.photos/seed/discover/900/1100" alt="Discover step placeholder" loading="lazy"/>
        </div>
        <div class="approach-img" data-step="1">
          <img class="ai-img" src="https://picsum.photos/seed/define/900/1100" alt="Define step placeholder" loading="lazy"/>
        </div>
        <div class="approach-img" data-step="2">
          <img class="ai-img" src="https://picsum.photos/seed/design/900/1100" alt="Design step placeholder" loading="lazy"/>
        </div>
        <div class="approach-img" data-step="3">
          <img class="ai-img" src="https://picsum.photos/seed/deliver/900/1100" alt="Deliver step placeholder" loading="lazy"/>
        </div>
      </div>

    </div>
  </section>

  <section id="blog-preview">
    <div class="bp-header">
      <div class="bp-eyebrow">06 — Writing</div>
      <a class="bp-view-all" href="/blog">View all →</a>
    </div>
    <h2 class="bp-title">Thoughts &amp; ideas.</h2>
    <div class="bp-grid" id="bp-grid">
      <?php foreach ($bp_posts as $bp):
        $bp_img = $bp['featured_image'] ? '/admin/uploads/' . $bp['featured_image'] : null;
      ?>
        <a class="bp-card" href="/post?slug=<?= rawurlencode($bp['slug']) ?>">
          <?php if ($bp_img): ?>
            <img class="bp-card-img"
                 src="<?= escHtml($bp_img) ?>"
                 alt="<?= escHtml($bp['title']) ?>"
                 loading="lazy"/>
          <?php else: ?>
            <div class="bp-card-img"></div>
          <?php endif; ?>
          <div class="bp-card-meta"><?= escHtml(date('j M Y', strtotime($bp['published_at']))) ?></div>
          <div class="bp-card-title"><?= escHtml($bp['title']) ?></div>
          <?php if ($bp['excerpt']): ?>
            <div class="bp-card-excerpt"><?= escHtml($bp['excerpt']) ?></div>
          <?php endif; ?>
          <div class="bp-card-read">Read &rarr;</div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="cta">
    <div class="cta-bg-type">Let's go.</div>
    <div class="cta-inner">
      <div class="cta-label">07 — Let's Work</div>
      <h2 class="cta-title">
        Something<br/>
        worth<br/>
        <span class="cta-outline">building</span><span class="cta-accent">?</span>
      </h2>

      <div class="cta-row">
        <p class="cta-desc">
          If you have a product, brand, or idea that deserves design at this level —
          let's talk about what it could become.
        </p>
        <div class="cta-btns">
          <a class="btn-cta-main js-open-modal" href="#">Start a project →</a>
          <a class="btn-cta-outline" href="https://dribbble.com/dennypratama">View portfolio</a>
        </div>
      </div>
    </div>
  </section>

</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

  <script src="/script.js?v=15" defer></script>
  <script>var PAGE='home',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
