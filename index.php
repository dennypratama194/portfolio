<?php
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/helpers.php';

$bp_stmt = $pdo->query(
    'SELECT title, slug, excerpt, featured_image,
            COALESCE(published_at, scheduled_at) AS published_at
     FROM posts
     WHERE (is_published = 1
        OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW()))
       AND slug != \'empty-states-ux-design-losing-users\'
     ORDER BY COALESCE(published_at, scheduled_at) DESC
     LIMIT 3'
);
$bp_posts = $bp_stmt->fetchAll();

$title         = 'Denny Pratama — UI/UX Designer & Developer for Startups';
$description   = 'UI/UX designer and developer based in Indonesia. I help startups and founders ship products that look sharp, work flawlessly, and actually convert. Available for new projects.';
$canonical     = 'https://dennypratama.com/';
$og_image      = 'https://dennypratama.com/assets/og-image.png';
$needs_gsap    = true;
$meta_keywords = 'UI/UX designer Indonesia, freelance UI/UX designer, UX designer and developer, startup product design, web designer Indonesia, UI designer for hire';
$jsonld        = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Person',
    'name'        => 'Denny Pratama',
    'url'         => 'https://dennypratama.com',
    'jobTitle'    => 'UI/UX Designer & Developer',
    'description' => 'UI/UX designer and developer based in Indonesia. I help startups and founders ship products that look sharp, work flawlessly, and actually convert.',
    'image'       => 'https://dennypratama.com/assets/denny-pratama-portrait.jpg',
    'email'       => 'dennypratama194@gmail.com',
    'knowsAbout'  => ['UI/UX Design', 'Web Development', 'Brand Identity', 'Design Systems', 'AI'],
    'sameAs'      => [
        'https://dribbble.com/dennypratama',
        'https://www.linkedin.com/in/denny-pratama-740a14151/',
        'https://instagram.com/dennypratama',
    ],
]);
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
  var pre = document.getElementById('preloader');
  if (!pre) return;

  // Skip if user prefers reduced motion
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    pre.parentNode && pre.parentNode.removeChild(pre);
    return;
  }

  // Show once every 4 hours via localStorage cooldown
  var COOLDOWN = 4 * 60 * 60 * 1000;
  try {
    var last = parseInt(localStorage.getItem('preloaderTs') || '0', 10);
    if (Date.now() - last < COOLDOWN) {
      pre.parentNode && pre.parentNode.removeChild(pre);
      return;
    }
    localStorage.setItem('preloaderTs', String(Date.now()));
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
      <span class="hero-overline-text">Available for new projects · Indonesia-based, worldwide</span>
    </div>
    <div class="hero-year">2026</div>

    <h1 class="hero-type">
      <span class="hero-line-1">Your product</span>
      <span class="hero-line-1">deserves</span>
      <span class="hero-line-1">UI/UX design</span>
      <div class="hero-line-2">
        <span class="outline-word">that converts.</span>
      </div>
    </h1>

    <div class="hero-bottom">
      <div class="hero-bottom-left">
        <p class="hero-desc">
          I design and build — so there are no handoff gaps, no agency overhead, and no lost context between Figma and the browser. One person. Full stack. Shipped.
        </p>
        <div class="hero-ctas">
          <a class="btn-hero-primary" href="#work">See the work</a>
          <a class="btn-hero-ghost js-open-modal" href="#">
            <span class="arrow">↗</span>
            Book a 15-min call →
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
      <div class="section-meta">
        <span class="section-num">01</span>
        <div class="section-line"></div>
        <span class="section-title-sm">Selected Work</span>
      </div>
      <h2 class="work-title">The work.</h2>
      <p class="work-subtitle">Projects where the design decision directly moved the business metric.</p>
    </div>

    <div class="work-grid">

      <a class="wc wc-1" href="https://xertra.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-top">
          <span class="wc-num">01 / 05</span>
          <svg class="wc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </div>
        <div class="wc-body">
          <h3 class="wc-name">Xertra</h3>
          <p class="wc-desc">SaaS platform — dashboard redesign for a faster, clearer product workflow.</p>
          <div class="wc-divider"></div>
          <span class="wc-url">xertra.com</span>
        </div>
      </a>

      <a class="wc wc-2" href="https://wordsburg.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-top">
          <span class="wc-num">02 / 05</span>
          <svg class="wc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </div>
        <div class="wc-body">
          <h3 class="wc-name">Wordsburg</h3>
          <p class="wc-desc">Content platform — end-to-end UI/UX design and front-end development.</p>
          <div class="wc-divider"></div>
          <span class="wc-url">wordsburg.com</span>
        </div>
      </a>

      <a class="wc wc-3" href="https://brenom-systems-3.vercel.app/" target="_blank" rel="noopener noreferrer">
        <div class="wc-top">
          <span class="wc-num">03 / 05</span>
          <svg class="wc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </div>
        <div class="wc-body">
          <h3 class="wc-name">Brenom Systems</h3>
          <p class="wc-desc">Enterprise software — full interface overhaul for complex internal tooling.</p>
          <div class="wc-divider"></div>
          <span class="wc-url">brenom-systems.com</span>
        </div>
      </a>

      <a class="wc wc-4" href="https://digitalrevo.id" target="_blank" rel="noopener noreferrer">
        <div class="wc-top">
          <span class="wc-num">04 / 05</span>
          <svg class="wc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </div>
        <div class="wc-body">
          <h3 class="wc-name">Digital Revo</h3>
          <p class="wc-desc">Digital agency — website design and build from brand to launch.</p>
          <div class="wc-divider"></div>
          <span class="wc-url">digitalrevo.id</span>
        </div>
      </a>

      <a class="wc wc-5" href="https://morecreativeagency.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-top">
          <span class="wc-num">05 / 05</span>
          <svg class="wc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
        </div>
        <div class="wc-body">
          <h3 class="wc-name">MoRe</h3>
          <p class="wc-desc">Creative agency — brand identity paired with a web presence to match.</p>
          <div class="wc-divider"></div>
          <span class="wc-url">morecreativeagency.com</span>
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
          "The best design work I've ever done started with someone saying 'I don't know what I need yet.'"
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
            <div class="stat-num">5<span class="red">+</span></div>
            <div class="stat-desc">Years shipping digital products</div>
          </div>
          <div class="stat-cell">
            <div class="stat-num">100<span class="red">%</span></div>
            <div class="stat-desc">Client satisfaction rate</div>
          </div>
          <div class="stat-cell">
            <div class="stat-num">5<span class="red">★</span></div>
            <div class="stat-desc">Average client rating</div>
          </div>
        </div>

        <div class="bio-block">
          <div class="bio-label">Background</div>
          <p class="bio-text">
            I'm Denny Pratama — a <strong>freelance UI/UX designer and developer</strong> based in Indonesia.
            I work at the intersection where visual craft meets technical execution.
            Most people do one or the other. I do both, because the gap between them is where the best work lives.
          </p>
        </div>

        <div class="bio-block">
          <div class="bio-label">Philosophy</div>
          <p class="bio-text">
            Design isn't decoration — it's the difference between a product that <strong>sells itself</strong> and one that needs a sales team to explain it.
            I build products that speak for themselves. Every element that remains is there because it earns its place.
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
    <div class="approach-eyebrow testi-eyebrow">03 — What Clients Say</div>
    <div class="bento-grid">

      <!-- Stats -->
      <div class="bento-cell bc-stats">
        <div class="bento-stat">
          <span class="bs-num">6<span class="bs-red">+</span></span>
          <span class="bs-label">Years of experience</span>
        </div>
        <div class="bento-stat">
          <span class="bs-num">60<span class="bs-red">+</span></span>
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
          <blockquote class="bt-quote">&ldquo;It&rsquo;s rare to find someone who combines both high-quality design work and very fast delivery at the same time. The collaboration felt effortless from start to finish &mdash; a genuine 10/10 experience.&rdquo;</blockquote>
          <span class="testimonial-result">Result: First version delivered within hours</span>
          <div class="bt-author">
            <span class="bt-name">Krystof</span>
          </div>
        </article>

        <article class="testi-slide" data-index="1">
          <div class="bento-dots">
            <span class="bento-dot"></span>
            <span class="bento-dot is-active"></span>
            <span class="bento-dot"></span>
          </div>
          <blockquote class="bt-quote">&ldquo;Fast, flexible, and very easy to communicate with. He understood the direction quickly and made great improvements without overcomplicating things &mdash; the final design was exactly what I needed.&rdquo;</blockquote>
          <span class="testimonial-result">Result: Final design aligned exactly with the brief</span>
          <div class="bt-author">
            <span class="bt-name">Serhii</span>
          </div>
        </article>

        <article class="testi-slide" data-index="2">
          <div class="bento-dots">
            <span class="bento-dot"></span>
            <span class="bento-dot"></span>
            <span class="bento-dot is-active"></span>
          </div>
          <blockquote class="bt-quote">&ldquo;Denny created our Figma website mock-ups exactly how we wanted and right on schedule. He addressed all requested changes promptly and communicated clearly throughout. We&rsquo;ll definitely work with him again.&rdquo;</blockquote>
          <span class="testimonial-result">Result: Figma mock-ups delivered on schedule</span>
          <div class="bt-author">
            <span class="bt-name">Heloise</span>
          </div>
        </article>
      </div>

      <!-- Brand panel -->
      <div class="bento-cell bc-clients">
        <img src="/assets/logo.png" alt="Denny Pratama" class="bc-brand-logo" width="120" height="40"/>
      </div>

      <!-- CTA -->
      <div class="bento-cell bc-cta">
        <p class="bc-cta-text">Most projects start with a 15-minute call.</p>
        <button class="bc-cta-btn js-open-modal">Let's talk &rarr;</button>
      </div>

    </div>
  </section>

  <section id="clients">
    <div class="cl-head">
      <span class="cl-eyebrow">04 — Clients</span>
      <h2 class="cl-headline">Brands I've partnered with.</h2>
    </div>
    <div class="cl-grid">
      <div class="cl-cell">
        <img src="/assets/client-wordsburg.webp" alt="Wordsburg" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-xertra.webp" alt="Xertra" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-mariwisata.webp" alt="Mariwisata" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-ortex.webp" alt="Ortex" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-more.webp" alt="MoRe Creative Agency" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-labme.webp" alt="LAB.ME" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-socialbee.webp" alt="Socialbee" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-edufarmers.webp" alt="Edufarmers" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-ispapp.webp" alt="ISPApp" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
      <div class="cl-cell">
        <img src="/assets/client-isohorns.webp" alt="IsoHorns" class="cl-logo" loading="lazy" width="160" height="60"/>
      </div>
    </div>
  </section>

  <section id="approach">
    <div class="approach-head">
      <div class="approach-head-main">
        <div class="approach-eyebrow">05 — Process</div>
        <h2 class="approach-title">From a vague idea to a live product.</h2>
      </div>
      <p class="approach-intro">Four phases, no guesswork — a clear path from the first conversation to a product that's live and accountable. No assumptions, no disappearing acts.</p>
    </div>

    <div class="approach-grid">
      <article class="approach-card">
        <span class="approach-card-num">01</span>
        <h3 class="approach-card-tag">Discover</h3>
        <p class="approach-card-title">No assumptions.</p>
        <p class="approach-card-desc">Deep research into your users, market, and goals before a single pixel is touched.</p>
      </article>

      <article class="approach-card">
        <span class="approach-card-num">02</span>
        <h3 class="approach-card-tag">Define</h3>
        <p class="approach-card-title">Clarity first.</p>
        <p class="approach-card-desc">Strategy, IA, and wireframes locked before visual design begins. Prevents expensive rework.</p>
      </article>

      <article class="approach-card">
        <span class="approach-card-num">03</span>
        <h3 class="approach-card-tag">Design</h3>
        <p class="approach-card-title">Craft, not trend.</p>
        <p class="approach-card-desc">High-fidelity UI with motion, systems, and visual language built to endure — not just impress.</p>
      </article>

      <article class="approach-card">
        <span class="approach-card-num">04</span>
        <h3 class="approach-card-tag">Deliver</h3>
        <p class="approach-card-title">Ship it. Own it.</p>
        <p class="approach-card-desc">Full build or clean handoff. Your product, live and accountable. No disappearing acts.</p>
      </article>
    </div>
  </section>

  <section id="blog-preview">
    <div class="bp-header">
      <div class="bp-eyebrow">06 — Writing</div>
      <a class="bp-view-all" href="/blog">View all →</a>
    </div>
    <h2 class="bp-title">The thinking behind better products.</h2>
    <div class="bp-grid" id="bp-grid">
      <?php foreach ($bp_posts as $bp):
        $bp_img = $bp['featured_image'] ? '/admin/uploads/' . $bp['featured_image'] : null;
      ?>
        <a class="bp-card" href="/blog/<?= rawurlencode($bp['slug']) ?>">
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
          I take on 2–3 projects per quarter so each gets my full focus. If you're building something worth shipping, let's talk before the next slot fills.
        </p>
        <div class="cta-btns">
          <a class="btn-cta-main js-open-modal" href="#">Start the conversation →</a>
          <a class="btn-cta-outline" href="https://dribbble.com/dennypratama">See the portfolio</a>
        </div>
      </div>
    </div>
  </section>

</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

  <script src="/script.js?v=24" defer></script>
  <script>var PAGE='home',SLUG=null;</script>
  <script src="/api/tracker.js?v=1" defer></script>
</body>
</html>
