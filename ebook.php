<?php
/* ════════════════════════════════════════════════════════════
   /ebook/SLUG — Public ebook sales page
   Routed by .htaccess: /ebook/SLUG → ebook.php?slug=SLUG
════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/api/db.php';

$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    http_response_code(404);
    exit('Not found.');
}

/* ── Load product ── */
$stmt = $pdo->prepare('SELECT * FROM ebook_products WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Ebook not found.');
}

/* ── Load published chapters ── */
$ch_stmt = $pdo->prepare(
    'SELECT sort_order, title FROM ebook_chapters
     WHERE product_id = ? AND is_published = 1
     ORDER BY sort_order ASC'
);
$ch_stmt->execute([$product['id']]);
$chapters = $ch_stmt->fetchAll();

/* ── State from query params ── */
$purchased = isset($_GET['purchased']) && $_GET['purchased'] === '1';
$failed    = isset($_GET['failed'])    && $_GET['failed']    === '1';
$owned     = isset($_GET['owned'])     && $_GET['owned']     === '1';
$error     = $_GET['error'] ?? '';

/* ── Formatted price ── */
$price_fmt = 'IDR ' . number_format((int)$product['price'], 0, ',', '.');

/* ── Cover image URL ── */
$cover_url = $product['cover_image']
    ? '/admin/uploads/' . $product['cover_image']
    : null;

/* ── head.php variables ── */
$title       = htmlspecialchars($product['title']) . ' — Denny Pratama';
$description = htmlspecialchars($product['tagline'] ?: $product['title']);
$og_image    = $cover_url
    ? 'https://dennypratama.com' . $cover_url
    : 'https://dennypratama.com/assets/og-image.png';
$og_type     = 'product';
$canonical   = 'https://dennypratama.com/ebook/' . rawurlencode($slug);

/* ── JSON-LD Product schema ── */
$jsonld = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['title'],
    'description' => $product['tagline'] ?: $product['title'],
    'image'       => $og_image,
    'url'         => $canonical,
    'offers'      => [
        '@type'         => 'Offer',
        'price'         => (string)(int)$product['price'],
        'priceCurrency' => 'IDR',
        'availability'  => 'https://schema.org/InStock',
        'seller'        => ['@type' => 'Person', 'name' => 'Denny Pratama'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
<style>
  /* ── Ebook page — scoped styles ──────────────────────────── */

  /* Banner */
  .eb-banner {
    padding: 14px var(--pad-x);
    font-size: 14px; line-height: 1.5;
    display: flex; align-items: center; justify-content: center;
    gap: 10px; text-align: center;
  }
  .eb-banner-ok  { background: rgba(76,175,80,0.1);  border-bottom: 1px solid rgba(76,175,80,0.25);  color: #2e7d32; }
  .eb-banner-err { background: rgba(232,50,10,0.08); border-bottom: 1px solid rgba(232,50,10,0.2);  color: #c62828; }
  .eb-banner-own { background: rgba(30,100,200,0.07); border-bottom: 1px solid rgba(30,100,200,0.18); color: #1a55a0; }
  .eb-banner a   { color: inherit; font-weight: 600; }

  /* Shared section */
  .eb-section { padding: clamp(80px, 10vw, 140px) var(--pad-x); }
  .eb-section + .eb-section { border-top: 1px solid var(--border); }
  .eb-eyebrow {
    font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase;
    color: var(--ink-3); margin-bottom: 20px; display: flex; align-items: center; gap: 12px;
  }
  .eb-eyebrow-line { flex: 1; max-width: 40px; height: 1px; background: var(--border); }
  .eb-h2 {
    font-family: 'Instrument Serif', serif; font-weight: 400;
    font-size: clamp(38px, 6vw, 72px); line-height: 1.08;
    letter-spacing: -0.02em; color: var(--ink); margin-bottom: 20px;
  }
  .eb-lead {
    font-size: clamp(15px, 2vw, 18px); color: var(--ink-2);
    line-height: 1.65; max-width: 560px;
  }

  /* ── Hero ── */
  #eb-hero {
    padding-top: clamp(120px, 18vh, 180px);
    padding-bottom: clamp(80px, 10vw, 120px);
    padding-left: var(--pad-x);
    padding-right: var(--pad-x);
    display: grid;
    grid-template-columns: 1fr auto;
    gap: clamp(48px, 8vw, 120px);
    align-items: center;
    min-height: 90vh;
  }
  .eb-hero-label {
    font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase;
    color: var(--red); margin-bottom: 24px;
  }
  .eb-hero-title {
    font-family: 'Instrument Serif', serif; font-weight: 400;
    font-size: clamp(48px, 8vw, 96px); line-height: 1.0;
    letter-spacing: -0.03em; color: var(--ink);
    margin-bottom: 20px;
  }
  .eb-tagline {
    font-size: clamp(16px, 2.2vw, 20px); color: var(--ink-2);
    line-height: 1.55; max-width: 500px; margin-bottom: 36px;
  }
  .eb-price-row { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
  .eb-price {
    font-size: 22px; font-weight: 700; letter-spacing: -0.02em; color: var(--ink);
  }
  .eb-chapter-count {
    font-size: 13px; color: var(--ink-3);
    padding: 5px 12px; border: 1px solid var(--border);
  }

  /* Hero buy form */
  .eb-form { display: flex; gap: 0; flex-wrap: nowrap; max-width: 520px; }
  .eb-email {
    flex: 1; min-width: 0;
    background: #fff; border: 1.5px solid var(--ink);
    border-right: none;
    font-family: 'Inter', sans-serif; font-size: 14px;
    padding: 14px 18px; color: var(--ink); outline: none;
    transition: border-color 0.2s;
  }
  .eb-email::placeholder { color: var(--ink-3); }
  .eb-email:focus { border-color: var(--red); }
  .eb-btn-buy {
    background: var(--ink); color: var(--paper);
    border: 1.5px solid var(--ink);
    font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 700;
    letter-spacing: 0.08em; text-transform: uppercase;
    padding: 14px 24px; cursor: none; white-space: nowrap;
    transition: background 0.22s, color 0.22s;
  }
  .eb-btn-buy:hover { background: var(--red); border-color: var(--red); }
  .eb-form-note {
    font-size: 12px; color: var(--ink-3); margin-top: 10px; line-height: 1.5;
  }

  /* Cover image */
  .eb-cover-wrap { flex-shrink: 0; }
  .eb-cover {
    width: clamp(200px, 22vw, 320px);
    aspect-ratio: 3 / 4;
    object-fit: cover;
    display: block;
    box-shadow: 0 32px 80px rgba(13,12,9,0.15);
  }
  .eb-cover-placeholder {
    width: clamp(200px, 22vw, 320px);
    aspect-ratio: 3 / 4;
    background: rgba(13,12,9,0.05);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 48px; color: var(--border);
  }

  /* ── Chapters ── */
  .eb-chapter-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-top: 48px; }
  .eb-chapter-item {
    display: flex; align-items: baseline; gap: 16px;
    padding: 18px 0; border-top: 1px solid var(--border);
    font-size: 15px; color: var(--ink-2); line-height: 1.4;
  }
  .eb-chapter-item:nth-child(odd)  { padding-right: 48px; }
  .eb-chapter-item:nth-child(even) { padding-left: 48px; border-left: 1px solid var(--border); }
  .eb-ch-num {
    font-size: 11px; letter-spacing: 0.08em; color: var(--ink-3);
    flex-shrink: 0; width: 28px;
  }

  /* ── Author ── */
  .eb-author-grid {
    display: grid; grid-template-columns: auto 1fr;
    gap: clamp(40px, 6vw, 80px); align-items: start; margin-top: 48px;
  }
  .eb-author-photo {
    width: clamp(140px, 16vw, 200px); aspect-ratio: 1;
    object-fit: cover; display: block;
    filter: grayscale(20%);
  }
  .eb-author-photo-placeholder {
    width: clamp(140px, 16vw, 200px); aspect-ratio: 1;
    background: rgba(13,12,9,0.06); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 48px;
  }
  .eb-author-name {
    font-size: 20px; font-weight: 600; letter-spacing: -0.02em;
    margin-bottom: 6px; color: var(--ink);
  }
  .eb-author-role {
    font-size: 13px; color: var(--ink-3); letter-spacing: 0.06em;
    text-transform: uppercase; margin-bottom: 20px;
  }
  .eb-author-bio {
    font-size: 15px; color: var(--ink-2); line-height: 1.7;
    max-width: 520px; margin-bottom: 32px;
  }
  .eb-stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;
  }
  .eb-stat { border-top: 2px solid var(--red); padding-top: 14px; }
  .eb-stat-num {
    font-size: 22px; font-weight: 700; letter-spacing: -0.02em;
    color: var(--ink); margin-bottom: 4px;
  }
  .eb-stat-desc { font-size: 12px; color: var(--ink-3); line-height: 1.4; }

  /* ── Bottom CTA ── */
  #eb-cta {
    background: var(--ink); color: var(--paper);
    padding: clamp(80px, 10vw, 130px) var(--pad-x);
  }
  #eb-cta .eb-eyebrow { color: rgba(236,234,226,0.4); }
  #eb-cta .eb-eyebrow-line { background: rgba(236,234,226,0.12); }
  #eb-cta .eb-h2 { color: var(--paper); }
  #eb-cta .eb-lead { color: rgba(236,234,226,0.6); margin-bottom: 40px; }
  #eb-cta .eb-email {
    background: rgba(236,234,226,0.06);
    border-color: rgba(236,234,226,0.2);
    color: var(--paper);
  }
  #eb-cta .eb-email::placeholder { color: rgba(236,234,226,0.35); }
  #eb-cta .eb-email:focus { border-color: var(--red); }
  #eb-cta .eb-btn-buy {
    background: var(--red); border-color: var(--red); color: #fff;
  }
  #eb-cta .eb-btn-buy:hover { background: #c9290a; border-color: #c9290a; }
  #eb-cta .eb-form-note { color: rgba(236,234,226,0.35); }
  #eb-cta .eb-price { color: rgba(236,234,226,0.5); font-weight: 400; font-size: 14px; margin-top: 16px; }

  /* ── FAQ ── */
  .eb-faq-list { margin-top: 48px; }
  details {
    border-top: 1px solid var(--border);
  }
  details:last-child { border-bottom: 1px solid var(--border); }
  summary {
    list-style: none; padding: 22px 0;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    cursor: none; font-size: 16px; font-weight: 500; color: var(--ink);
    transition: color 0.2s;
  }
  summary::-webkit-details-marker { display: none; }
  summary:hover { color: var(--red); }
  .faq-icon {
    font-size: 18px; color: var(--ink-3); flex-shrink: 0;
    transition: transform 0.25s cubic-bezier(0.23,1,0.32,1);
  }
  details[open] .faq-icon { transform: rotate(45deg); color: var(--red); }
  .faq-body {
    font-size: 15px; color: var(--ink-2); line-height: 1.7;
    padding-bottom: 22px; max-width: 640px;
  }
  .faq-body a { color: var(--red); text-decoration: none; }
  .faq-body a:hover { text-decoration: underline; }

  /* ── Responsive ── */
  @media (max-width: 800px) {
    #eb-hero {
      grid-template-columns: 1fr;
      min-height: auto;
    }
    .eb-cover-wrap { order: -1; }
    .eb-cover, .eb-cover-placeholder { width: 100%; max-width: 260px; margin: 0 auto; }
    .eb-chapter-grid { grid-template-columns: 1fr; }
    .eb-chapter-item:nth-child(even) { padding-left: 0; border-left: none; }
    .eb-chapter-item:nth-child(odd)  { padding-right: 0; }
    .eb-author-grid { grid-template-columns: 1fr; }
    .eb-stats-grid  { grid-template-columns: repeat(2, 1fr); }
    .eb-form { flex-wrap: wrap; }
    .eb-email { border-right: 1.5px solid var(--ink); min-width: 100%; }
    .eb-btn-buy { width: 100%; justify-content: center; }
    #eb-cta .eb-email { border-color: rgba(236,234,226,0.2); }
  }
  @media (max-width: 480px) {
    .eb-stats-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<!-- ── Status banners ── -->
<?php if ($purchased): ?>
  <div class="eb-banner eb-banner-ok">
    ✓ &nbsp;Payment confirmed! <strong>Check your email</strong> for the access link.
  </div>
<?php elseif ($owned): ?>
  <div class="eb-banner eb-banner-own">
    You already own this ebook.
    <a href="/ebook/recover">Recover your access link →</a>
  </div>
<?php elseif ($failed): ?>
  <div class="eb-banner eb-banner-err">
    Payment didn't go through. Try again below, or contact me if the issue persists.
  </div>
<?php elseif ($error === 'invalid_email'): ?>
  <div class="eb-banner eb-banner-err">
    Please enter a valid email address.
  </div>
<?php elseif ($error === 'payment'): ?>
  <div class="eb-banner eb-banner-err">
    Payment processor error. Please try again — your card was not charged.
  </div>
<?php endif; ?>

<main>

  <!-- ══════════════════════════════════════
       HERO
  ══════════════════════════════════════ -->
  <section id="eb-hero">
    <div class="eb-hero-content">
      <div class="eb-hero-label">Ebook</div>
      <h1 class="eb-hero-title"><?= htmlspecialchars($product['title']) ?></h1>
      <?php if ($product['tagline']): ?>
        <p class="eb-tagline"><?= htmlspecialchars($product['tagline']) ?></p>
      <?php endif; ?>

      <div class="eb-price-row">
        <span class="eb-price"><?= $price_fmt ?></span>
        <?php if ($chapters): ?>
          <span class="eb-chapter-count">
            <?= count($chapters) ?> chapter<?= count($chapters) !== 1 ? 's' : '' ?>
          </span>
        <?php endif; ?>
      </div>

      <form class="eb-form" method="POST" action="/api/ebook-checkout.php">
        <input type="hidden" name="product_slug" value="<?= htmlspecialchars($slug) ?>"/>
        <input class="eb-email" type="email" name="email"
               placeholder="Your email address" required
               autocomplete="email"/>
        <button class="eb-btn-buy" type="submit">
          Get Access — <?= $price_fmt ?>
        </button>
      </form>
      <p class="eb-form-note">Instant delivery · Magic link via email · No account needed</p>
    </div>

    <div class="eb-cover-wrap">
      <?php if ($cover_url): ?>
        <img class="eb-cover" src="<?= htmlspecialchars($cover_url) ?>"
             alt="<?= htmlspecialchars($product['title']) ?> cover" loading="eager"/>
      <?php else: ?>
        <div class="eb-cover-placeholder" aria-hidden="true">◆</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ══════════════════════════════════════
       WHAT'S INSIDE
  ══════════════════════════════════════ -->
  <?php if ($chapters): ?>
  <section class="eb-section" id="eb-chapters">
    <div class="eb-eyebrow">
      <span>01</span>
      <div class="eb-eyebrow-line"></div>
      What's inside
    </div>
    <h2 class="eb-h2 eb-reveal">
      <?= count($chapters) ?> chapter<?= count($chapters) !== 1 ? 's' : '' ?> of<br>
      actionable content.
    </h2>
    <?php if ($product['description']): ?>
      <p class="eb-lead eb-reveal"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
    <?php endif; ?>
    <div class="eb-chapter-grid">
      <?php foreach ($chapters as $ch): ?>
        <div class="eb-chapter-item">
          <span class="eb-ch-num"><?= str_pad((int)$ch['sort_order'], 2, '0', STR_PAD_LEFT) ?></span>
          <?= htmlspecialchars($ch['title']) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ══════════════════════════════════════
       ABOUT THE AUTHOR
  ══════════════════════════════════════ -->
  <section class="eb-section" id="eb-author">
    <div class="eb-eyebrow">
      <span>02</span>
      <div class="eb-eyebrow-line"></div>
      About the author
    </div>

    <div class="eb-author-grid">
      <div>
        <?php if (file_exists(__DIR__ . '/assets/author.jpg')): ?>
          <img class="eb-author-photo" src="/assets/author.jpg"
               alt="Denny Pratama" loading="lazy"/>
        <?php else: ?>
          <div class="eb-author-photo-placeholder" aria-hidden="true">◆</div>
        <?php endif; ?>
      </div>
      <div class="eb-reveal">
        <div class="eb-author-name">Denny Pratama</div>
        <div class="eb-author-role">UI/UX Designer &amp; Developer</div>
        <p class="eb-author-bio">
          I'm a UI/UX designer and developer based in Indonesia with 7+ years of
          experience building digital products across agencies, startups, and global
          freelance platforms. I've delivered 60+ projects and earned Top Rated status
          on Upwork with a 100% Job Success Score — the strategies in this ebook are
          what actually moved the needle.
        </p>
        <div class="eb-stats-grid">
          <div class="eb-stat">
            <div class="eb-stat-num">$30K<span style="color:var(--red)">+</span></div>
            <div class="eb-stat-desc">Earned on Upwork</div>
          </div>
          <div class="eb-stat">
            <div class="eb-stat-num">100<span style="color:var(--red)">%</span></div>
            <div class="eb-stat-desc">Job Success Score</div>
          </div>
          <div class="eb-stat">
            <div class="eb-stat-num">7<span style="color:var(--red)">+</span></div>
            <div class="eb-stat-desc">Years in the field</div>
          </div>
          <div class="eb-stat">
            <div class="eb-stat-num">60<span style="color:var(--red)">+</span></div>
            <div class="eb-stat-desc">Projects delivered</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ══════════════════════════════════════
       FAQ
  ══════════════════════════════════════ -->
  <section class="eb-section" id="eb-faq">
    <div class="eb-eyebrow">
      <span>03</span>
      <div class="eb-eyebrow-line"></div>
      Common questions
    </div>
    <h2 class="eb-h2 eb-reveal">FAQ.</h2>

    <div class="eb-faq-list eb-reveal">
      <details>
        <summary>
          How do I access the ebook after purchase?
          <span class="faq-icon">+</span>
        </summary>
        <p class="faq-body">
          Instantly — right after payment, you'll receive an email with a personal magic link.
          Click it and you're reading. No account, no password, no app to download.
        </p>
      </details>

      <details>
        <summary>
          Can I read it on my phone?
          <span class="faq-icon">+</span>
        </summary>
        <p class="faq-body">
          Yes. The reading experience is fully responsive and designed to work on any device —
          phone, tablet, or desktop.
        </p>
      </details>

      <details>
        <summary>
          What if I lose the email?
          <span class="faq-icon">+</span>
        </summary>
        <p class="faq-body">
          No problem. Go to <a href="/ebook/recover">/ebook/recover</a>, enter your email,
          and a fresh magic link will be sent to you immediately.
        </p>
      </details>

      <details>
        <summary>
          Is this a PDF?
          <span class="faq-icon">+</span>
        </summary>
        <p class="faq-body">
          No. It's a web-based reading experience — cleaner, faster, and readable on any screen
          without downloading a file. You access it through your browser via the magic link.
        </p>
      </details>
    </div>
  </section>

  <!-- ══════════════════════════════════════
       BOTTOM CTA (dark)
  ══════════════════════════════════════ -->
  <section id="eb-cta">
    <div class="eb-eyebrow">
      <div class="eb-eyebrow-line"></div>
      Get your copy
    </div>
    <h2 class="eb-h2 eb-reveal-dark">
      Ready to start?<br/>Get it now.
    </h2>
    <p class="eb-lead eb-reveal-dark">
      One payment, permanent access. No subscriptions, no DRM, no expiry.
    </p>

    <form class="eb-form" method="POST" action="/api/ebook-checkout.php">
      <input type="hidden" name="product_slug" value="<?= htmlspecialchars($slug) ?>"/>
      <input class="eb-email" type="email" name="email"
             placeholder="Your email address" required
             autocomplete="email"/>
      <button class="eb-btn-buy" type="submit">
        Get Access — <?= $price_fmt ?>
      </button>
    </form>
    <p class="eb-form-note">Instant delivery · Magic link via email · No account needed</p>
    <p class="eb-price"><?= $price_fmt ?> · One-time payment</p>
  </section>

</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<script src="/script.js?v=6" defer></script>
<script>
  /* ── Ebook page GSAP animations ── */
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof gsap === 'undefined') return;
    if (typeof ScrollTrigger !== 'undefined') gsap.registerPlugin(ScrollTrigger);

    /* Hero entrance */
    var heroEls = [
      document.querySelector('.eb-hero-label'),
      document.querySelector('.eb-hero-title'),
      document.querySelector('.eb-tagline'),
      document.querySelector('.eb-price-row'),
      document.querySelector('.eb-form'),
      document.querySelector('.eb-form-note'),
    ].filter(Boolean);

    if (heroEls.length) {
      gsap.from(heroEls, {
        y: 40, opacity: 0, stagger: 0.1, duration: 0.85, ease: 'power3.out', delay: 0.1,
      });
    }

    var coverWrap = document.querySelector('.eb-cover-wrap');
    if (coverWrap) {
      gsap.from(coverWrap, {
        y: 30, opacity: 0, duration: 1, ease: 'power3.out', delay: 0.35,
      });
    }

    if (typeof ScrollTrigger === 'undefined') return;

    /* Generic section reveals (light bg) */
    document.querySelectorAll('.eb-reveal').forEach(function (el) {
      gsap.from(el, {
        y: 40, opacity: 0, duration: 0.8, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 85%' },
      });
    });

    /* Dark CTA section reveals */
    document.querySelectorAll('.eb-reveal-dark').forEach(function (el) {
      gsap.from(el, {
        y: 30, opacity: 0, duration: 0.8, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 85%' },
      });
    });

    /* Chapter list stagger */
    var chapterEls = document.querySelectorAll('.eb-chapter-item');
    if (chapterEls.length) {
      gsap.from(chapterEls, {
        y: 24, opacity: 0, stagger: 0.06, duration: 0.6, ease: 'power2.out',
        scrollTrigger: { trigger: '.eb-chapter-grid', start: 'top 82%' },
      });
    }

    /* Author stats stagger */
    var statEls = document.querySelectorAll('.eb-stat');
    if (statEls.length) {
      gsap.from(statEls, {
        y: 20, opacity: 0, stagger: 0.1, duration: 0.65, ease: 'power2.out',
        scrollTrigger: { trigger: '.eb-stats-grid', start: 'top 85%' },
      });
    }

    /* FAQ items */
    var faqEls = document.querySelectorAll('details');
    if (faqEls.length) {
      gsap.from(faqEls, {
        y: 16, opacity: 0, stagger: 0.08, duration: 0.55, ease: 'power2.out',
        scrollTrigger: { trigger: '.eb-faq-list', start: 'top 85%' },
      });
    }

    /* Cursor hover on interactive ebook elements */
    document.querySelectorAll('.eb-btn-buy, .eb-email, summary').forEach(function (el) {
      el.addEventListener('mouseenter', function () { document.body.classList.add('cursor-hover'); });
      el.addEventListener('mouseleave', function () { document.body.classList.remove('cursor-hover'); });
    });
  });
</script>
</body>
</html>
