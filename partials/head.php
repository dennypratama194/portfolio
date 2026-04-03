  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($title ?? 'Denny Pratama — Design is Conviction') ?></title>
  <meta name="description" content="<?= htmlspecialchars($description ?? 'Denny Pratama — UI/UX Designer & Developer based in Indonesia. Building digital products where aesthetics and function refuse to compromise.') ?>"/>

  <!-- Open Graph -->
  <meta property="og:title" content="<?= htmlspecialchars($title ?? 'Denny Pratama — Design is Conviction') ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($description ?? 'UI/UX Designer & Developer building digital products where aesthetics and function refuse to compromise.') ?>"/>
  <meta property="og:image" content="<?= htmlspecialchars($og_image ?? 'https://dennypratama.com/assets/og-image.png') ?>"/>
  <meta property="og:url" content="https://dennypratama.com<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>"/>
  <meta property="og:type" content="<?= htmlspecialchars($og_type ?? 'website') ?>"/>
  <meta property="og:site_name" content="Denny Pratama"/>
  <meta name="twitter:card" content="summary_large_image"/>

  <!-- Canonical -->
  <link rel="canonical" href="<?= htmlspecialchars($canonical ?? 'https://dennypratama.com' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>"/>

  <!-- JSON-LD -->
  <script type="application/ld+json">
  <?= $jsonld ?? json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => 'Denny Pratama',
    'url'      => 'https://dennypratama.com',
    'jobTitle' => 'UI/UX Designer & Developer',
    'image'    => 'https://dennypratama.com/assets/logo.png',
    'sameAs'   => [
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

  <!-- Preload critical CSS -->
  <link rel="preload" href="/style.css?v=16" as="style"/>

  <!-- Fonts — non-blocking via print media swap trick -->
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@300;400;500;600;700;800;900&display=swap"/>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" media="print" onload="this.media='all'"/>
  <noscript><link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/></noscript>

  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="stylesheet" href="/style.css?v=16"/>

  <!-- GSAP + ScrollTrigger via CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
