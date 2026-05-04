<?php
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
          <div class="vis-brand">
            <div class="vb-grid"></div>
            <div class="vb-mark"></div>
            <div class="vb-pal"></div>
          </div>
          <div class="wc-overlay">
            <span class="wc-num">01</span>
            <span class="wc-name">Xertra</span>
          </div>
        </div>
      </a>

      <a class="wc wc-2" href="https://wordsburg.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <div class="vis-uiux">
            <div class="vu-nav"></div>
            <div class="vu-card1"></div>
            <div class="vu-card2"></div>
          </div>
          <div class="wc-overlay">
            <span class="wc-num">02</span>
            <span class="wc-name">Wordsburg</span>
          </div>
        </div>
      </a>

      <a class="wc wc-3" href="https://brenom-systems-3.vercel.app/" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <div class="vis-dash">
            <div class="vh-sidebar"></div>
            <div class="vh-chart"></div>
            <div class="vh-dot"></div>
          </div>
          <div class="wc-overlay">
            <span class="wc-num">03</span>
            <span class="wc-name">Brenom Systems</span>
          </div>
        </div>
      </a>

      <a class="wc wc-4" href="https://digitalrevo.id" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <div class="vis-dev">
            <div class="vd-chrome"></div>
            <div class="vd-lines"></div>
            <div class="vd-cursor"></div>
          </div>
          <div class="wc-overlay">
            <span class="wc-num">04</span>
            <span class="wc-name">Digital Revo</span>
          </div>
        </div>
      </a>

      <a class="wc wc-5" href="https://morecreativeagency.com" target="_blank" rel="noopener noreferrer">
        <div class="wc-card">
          <div class="vis-dash">
            <div class="vh-sidebar"></div>
            <div class="vh-chart"></div>
            <div class="vh-dot"></div>
          </div>
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

        <div class="bio-block">
          <div class="bio-label">Capabilities</div>
          <div class="caps-list">
            <div class="cap-item">
              <span class="cap-name">UI/UX Design</span>
              <span class="cap-tag">Research → Pixels</span>
            </div>
            <div class="cap-item">
              <span class="cap-name">WordPress Development</span>
              <span class="cap-tag">Custom Themes · CMS</span>
            </div>
            <div class="cap-item">
              <span class="cap-name">Brand Identity</span>
              <span class="cap-tag">Visual Language</span>
            </div>
            <div class="cap-item">
              <span class="cap-name">Graphic Design</span>
              <span class="cap-tag">Print · Digital</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="approach">
    <h2 class="sr-only">My Process</h2>
    <div class="approach-eyebrow">03 — Process</div>
    <div class="approach-steps">
      <div class="approach-step">
        <div class="as-num">01 · Discover</div>
        <div class="as-title">No assumptions.</div>
        <div class="as-desc">Deep research into your users, market, and goals before a single pixel is touched.</div>
      </div>
      <div class="approach-step">
        <div class="as-num">02 · Define</div>
        <div class="as-title">Clarity first.</div>
        <div class="as-desc">Strategy, IA, and wireframes locked before visual design begins. Prevents expensive rework.</div>
      </div>
      <div class="approach-step">
        <div class="as-num">03 · Design</div>
        <div class="as-title">Craft, not trend.</div>
        <div class="as-desc">High-fidelity UI with motion, systems, and visual language built to endure — not just impress.</div>
      </div>
      <div class="approach-step">
        <div class="as-num">04 · Deliver</div>
        <div class="as-title">Ship it. Own it.</div>
        <div class="as-desc">Full build or clean handoff. Your product, live and accountable. No disappearing acts.</div>
      </div>
    </div>
  </section>

  <section id="clients">
    <div class="clients-header">
      <h2 class="sr-only">Clients</h2>
      <div class="clients-eyebrow">04 — Clients</div>
      <p class="clients-desc">Brands and teams I've had the privilege to work with.</p>
    </div>
    <div class="clients-grid">
      <div class="client-cell">
        <img src="/assets/client-wordsburg.png" alt="Wordsburg" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-xertra.png" alt="Xertra" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-mariwisata.png" alt="Mariwisata" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-ortex.png" alt="Ortex" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-more.png" alt="*MORe Creative Agency" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-labme.png" alt="LAB.ME" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-socialbee.png" alt="socialbee" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-edufarmers.png" alt="edufarmers" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-ispapp.png" alt="ISPApp" class="client-logo" loading="lazy"/>
      </div>
      <div class="client-cell">
        <img src="/assets/client-isohorns.png" alt="IsoHorns" class="client-logo" loading="lazy"/>
      </div>
    </div>
  </section>

  <section id="blog-preview">
    <div class="bp-header">
      <div class="bp-eyebrow">05 — Writing</div>
      <a class="bp-view-all" href="/blog">View all →</a>
    </div>
    <h2 class="bp-title">Thoughts &amp; ideas.</h2>
    <div class="bp-grid" id="bp-grid">
      <!-- populated by JS -->
      <div class="bp-loading">
        <span class="blog-loading-dot"></span>
        <span class="blog-loading-dot"></span>
        <span class="blog-loading-dot"></span>
      </div>
    </div>
  </section>

  <section id="cta">
    <div class="cta-bg-type">Let's go.</div>
    <div class="cta-inner">
      <div class="cta-label">06 — Let's Work</div>
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

  <script src="/script.js?v=12" defer></script>
  <script>var PAGE='home',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
