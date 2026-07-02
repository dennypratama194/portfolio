  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <!-- Page transition: set arriving state before paint to prevent flash -->
  <script>
    try {
      if (sessionStorage.getItem('pt')) {
        document.documentElement.classList.add('pt-arriving');
        sessionStorage.removeItem('pt');
        // Safety net: if script.js never runs the reveal (e.g. not loaded on
        // this page), drop the curtain so it can't leave a blank screen.
        setTimeout(function () {
          document.documentElement.classList.remove('pt-arriving', 'pt-revealing');
        }, 1500);
      }
    } catch (e) {}
  </script>
  <title><?= htmlspecialchars($title ?? 'Denny Pratama — Design is Conviction') ?></title>
  <meta name="description" content="<?= htmlspecialchars($description ?? 'Denny Pratama — UI/UX Designer & Developer based in Indonesia. Building digital products where aesthetics and function refuse to compromise.') ?>"/>
  <?php if (!empty($meta_keywords)): ?>
  <meta name="keywords" content="<?= htmlspecialchars($meta_keywords) ?>"/>
  <?php endif; ?>

  <!-- Open Graph -->
  <meta property="og:title" content="<?= htmlspecialchars($title ?? 'Denny Pratama — Design is Conviction') ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($description ?? 'UI/UX Designer & Developer building digital products where aesthetics and function refuse to compromise.') ?>"/>
  <meta property="og:image" content="<?= htmlspecialchars($og_image ?? 'https://dennypratama.com/assets/logo.png') ?>"/>
  <meta property="og:url" content="https://dennypratama.com<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>"/>
  <meta property="og:type" content="<?= htmlspecialchars($og_type ?? 'website') ?>"/>
  <meta property="og:site_name" content="Denny Pratama"/>
  <meta name="twitter:card" content="summary_large_image"/>
  <meta name="twitter:title" content="<?= htmlspecialchars($title ?? 'Denny Pratama — Design is Conviction') ?>"/>
  <meta name="twitter:description" content="<?= htmlspecialchars($description ?? 'UI/UX Designer & Developer building digital products where aesthetics and function refuse to compromise.') ?>"/>
  <meta name="twitter:image" content="<?= htmlspecialchars($og_image ?? 'https://dennypratama.com/assets/logo.png') ?>"/>
  <meta name="twitter:creator" content="@dennypratama"/>

  <!-- Canonical -->
  <link rel="canonical" href="<?= htmlspecialchars($canonical ?? 'https://dennypratama.com' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>"/>

  <!-- JSON-LD -->
  <script type="application/ld+json">
  <?= $jsonld ?? json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Person',
    'name'        => 'Denny Pratama',
    'url'         => 'https://dennypratama.com',
    'jobTitle'    => 'UI/UX Designer & Developer',
    'description' => 'UI/UX designer and developer based in Indonesia. I help startups and founders ship products that look sharp, work flawlessly, and actually convert.',
    'image'       => 'https://dennypratama.com/assets/logo.png',
    'email'       => 'dennypratama194@gmail.com',
    'knowsAbout'  => ['UI/UX Design', 'Web Development', 'Brand Identity', 'Design Systems', 'AI'],
    'sameAs'      => [
      'https://dribbble.com/dennypratama',
      'https://www.linkedin.com/in/denny-pratama-740a14151/',
      'https://instagram.com/dennypratama',
    ],
  ]) ?>
  </script>

  <!-- DNS prefetch for external resources -->
  <link rel="dns-prefetch" href="//fonts.googleapis.com"/>
  <link rel="dns-prefetch" href="//fonts.gstatic.com"/>

  <!-- Preconnect (faster font load) -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>

  <!-- Fonts — non-blocking via print media swap trick -->
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap"/>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'"/>
  <noscript><link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/></noscript>

  <link rel="icon" type="image/x-icon" href="/favicon.ico"/>
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png"/>
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png"/>
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png"/>
  <link rel="stylesheet" href="/style.css?v=99"/>
  <?php if (!empty($page_css)): /* page-specific stylesheet, loaded after the global one */ ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($page_css) ?>"/>
  <?php endif; ?>

  <!-- reCAPTCHA v3 site key (public; consumed by script.js) -->
  <meta name="recaptcha-site-key" content="6LdhaJMsAAAAAAJb5MDygyGZks49IXEDUNvrUZgQ"/>

  <!-- GSAP + ScrollTrigger via CDN — only when the page declares it needs them -->
  <?php if (!empty($needs_gsap)): ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" integrity="sha512-7eHRwcbYkK4d9g/6tD/mhkf++eoTHwpNM9woBxtPUBWm67zeAfFC+HrdoE2GanKeocly/VxeLvIqwvCdk7qScg==" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" integrity="sha512-onMTRKJBKz8M1TnqqDuGBlowlH0ohFzMXYRNebz+yOcc5TQr/zAKsthzhuv0hiyUKEiQEQXEynnXCvNTOk50dg==" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
  <?php endif; ?>
