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
    'SELECT sort_order, title, excerpt FROM ebook_chapters
     WHERE product_id = ? AND is_published = 1
     ORDER BY sort_order ASC'
);
$ch_stmt->execute([$product['id']]);
$chapters = $ch_stmt->fetchAll();

/* ── State from query params ── */
$purchased     = isset($_GET['purchased']) && $_GET['purchased'] === '1';
$failed        = isset($_GET['failed'])    && $_GET['failed']    === '1';
$owned         = isset($_GET['owned'])     && $_GET['owned']     === '1';
$access_denied = isset($_GET['access'])    && $_GET['access']    === 'denied';
$error         = $_GET['error'] ?? '';

/* When the user landed here from a denied /read token, return 403 + noindex.
   The sales page still renders (good UX — they can re-buy or recover their link),
   but Google won't flag this as a soft 404 and won't index the URL. */
if ($access_denied) {
    http_response_code(403);
    header('X-Robots-Tag: noindex, nofollow');
}

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
    : 'https://dennypratama.com/assets/logo.png';
$og_type     = 'product';
$canonical   = 'https://dennypratama.com/ebook/' . rawurlencode($slug);

/* ── JSON-LD: Product + FAQPage + BreadcrumbList ── */
$jsonld = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
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
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => [
                ['@type' => 'Question', 'name' => 'How do I access the ebook after purchase?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Instantly — right after payment, you\'ll receive an email with a personal magic link. Click it and you\'re reading. No account, no password, no app to download.']],
                ['@type' => 'Question', 'name' => 'Can I read it on my phone?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes. The reading experience is fully responsive and designed to work on any device — phone, tablet, or desktop.']],
                ['@type' => 'Question', 'name' => 'What if I lose the email?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No problem. Go to /ebook/recover, enter your email, and a fresh magic link will be sent to you immediately.']],
                ['@type' => 'Question', 'name' => 'Is this a PDF?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. It\'s a web-based reading experience — cleaner, faster, and readable on any screen without downloading a file.']],
            ],
        ],
        [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://dennypratama.com'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Ebooks', 'item' => 'https://dennypratama.com/ebooks'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $product['title'], 'item' => $canonical],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$needs_gsap = true;
$page_css   = '/css/ebook.css?v=2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<!-- ── Status banners ── -->
<?php if ($purchased): ?>
  <div class="eb-banner eb-banner-ok">
    ✓ &nbsp;Payment confirmed! Check your email for the access link — or
    <a href="/my-library">view your library →</a>
  </div>
<?php elseif ($owned): ?>
  <div class="eb-banner eb-banner-own">
    You already own this ebook.
    <a href="/ebook/recover">Recover your access link →</a>
  </div>
<?php elseif ($access_denied): ?>
  <div class="eb-banner eb-banner-err">
    That access link is invalid or has expired. <a href="/ebook/recover">Recover your link →</a>
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

<main id="main-content">

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

      <form class="eb-form" method="POST" action="/api/ebook-checkout">
        <input type="hidden" name="product_slug" value="<?= htmlspecialchars($slug) ?>"/>
        <label for="eb-email-hero" class="sr-only">Email address</label>
        <input class="eb-email" type="email" id="eb-email-hero" name="email"
               placeholder="Your email address" required
               autocomplete="email"/>
        <input type="hidden" name="recaptcha_token" class="eb-recaptcha-token" value=""/>
        <button class="eb-btn-buy" type="submit">
          <span class="form-btn-spinner" aria-hidden="true"></span>
          <span class="form-btn-label">Get Access — <?= $price_fmt ?></span>
        </button>
      </form>
      <p class="eb-form-note">Instant delivery · Magic link via email · No account needed</p>
      <p class="eb-form-note" style="margin-top:6px">Already purchased? <a href="/my-library" style="color:var(--red);text-decoration:none;font-weight:500">View your library →</a></p>
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
          <div class="eb-ch-title"><?= htmlspecialchars($ch['title']) ?></div>
          <?php if (!empty($ch['excerpt'])): ?>
            <div class="eb-ch-excerpt"><?= htmlspecialchars($ch['excerpt']) ?></div>
          <?php else: ?>
            <div></div>
          <?php endif; ?>
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

    <form class="eb-form" method="POST" action="/api/ebook-checkout">
      <input type="hidden" name="product_slug" value="<?= htmlspecialchars($slug) ?>"/>
      <label for="eb-email-cta" class="sr-only">Email address</label>
      <input class="eb-email" type="email" id="eb-email-cta" name="email"
             placeholder="Your email address" required
             autocomplete="email"/>
      <button class="eb-btn-buy" type="submit">
        <span class="form-btn-spinner" aria-hidden="true"></span>
        <span class="form-btn-label">Get Access — <?= $price_fmt ?></span>
      </button>
    </form>
    <p class="eb-form-note">Instant delivery · Magic link via email · No account needed</p>
    <p class="eb-form-note" style="margin-top:6px">Already purchased? <a href="/my-library" style="color:rgba(var(--light-text-rgb),0.55);text-decoration:none;font-weight:500">View your library →</a></p>
    <p class="eb-price"><?= $price_fmt ?> · One-time payment</p>
  </section>

</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<script src="/script.js?v=25" defer></script>
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

    /* Cursor turns white on dark sections — same pattern as homepage */
    document.querySelectorAll('#eb-cta, footer').forEach(function (el) {
      el.addEventListener('mouseenter', function () { document.body.classList.add('on-dark'); });
      el.addEventListener('mouseleave', function () { document.body.classList.remove('on-dark'); });
    });
  });
</script>

<script>
  /* ── reCAPTCHA v3 on the buy form ──
     Loads grecaptcha on-demand, intercepts each .eb-form submit, fetches a
     scored token, drops it into the hidden field, then lets the normal POST
     fly through to /api/ebook-checkout. Fails open (third-party outage must
     never block a real buyer) — the server still rejects only on a confident
     low-score verdict. Matches the pattern used in admin/login.php. ── */
  (function () {
    var KEY = (document.querySelector('meta[name="recaptcha-site-key"]') || {}).content || '';
    var forms = document.querySelectorAll('.eb-form');
    if (!KEY || !forms.length) return;

    if (typeof grecaptcha === 'undefined') {
      var s = document.createElement('script');
      s.src = 'https://www.google.com/recaptcha/api.js?render=' + KEY;
      s.async = true;
      document.head.appendChild(s);
    }

    forms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.dataset.ok) return;          // second pass: real submit
        e.preventDefault();

        var btn   = form.querySelector('.eb-btn-buy');
        var label = btn ? btn.querySelector('.form-btn-label') : null;
        if (btn) {
          btn.disabled = true;
          btn.classList.add('loading');
          if (label) label.textContent = 'Verifying…';
        }

        function go(token) {
          var input = form.querySelector('.eb-recaptcha-token');
          if (input) input.value = token || '';
          form.dataset.ok = '1';
          form.submit();
        }

        if (typeof grecaptcha === 'undefined') { go(''); return; }
        try {
          grecaptcha.ready(function () {
            grecaptcha.execute(KEY, { action: 'ebook_buy' })
              .then(go)
              .catch(function () { go(''); });
          });
        } catch (_) { go(''); }
      });
    });
  })();
</script>
</body>
</html>
