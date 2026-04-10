# SEO Rules

## Every new public page needs all of these

- [ ] `$title` — unique, primary keyword near front, under 60 chars
- [ ] `$description` — unique, 150–160 chars
- [ ] `$canonical` — full URL, no trailing slash (`https://dennypratama.com/slug`)
- [ ] `$og_image` — absolute URL; fallback: `https://dennypratama.com/assets/og-default.jpg`
- [ ] One `<h1>` — in PHP/HTML, never injected by JavaScript
- [ ] JSON-LD schema (see patterns below)
- [ ] Add URL to `sitemap.xml`

## Admin pages
```html
<meta name="robots" content="noindex, nofollow"/>
```

## JSON-LD schema patterns

```php
// Homepage — Person
$jsonld = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => 'Denny Pratama',
    'url'      => 'https://dennypratama.com',
    'jobTitle' => 'Product Designer & Developer',
]);

// Blog post — BlogPosting
$jsonld = json_encode([
    '@context'      => 'https://schema.org',
    '@type'         => 'BlogPosting',
    'headline'      => $post['title'],
    'description'   => $post['excerpt'],
    'author'        => ['@type' => 'Person', 'name' => 'Denny Pratama'],
    'datePublished' => $post['published_at'],
    'url'           => $canonical,
    'image'         => $og_image,
]);

// Ebook — Product
$jsonld = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['title'],
    'description' => $product['excerpt'],
    'offers'      => [
        '@type'         => 'Offer',
        'price'         => $product['price'],
        'priceCurrency' => 'IDR',
        'availability'  => 'https://schema.org/InStock',
    ],
]);
```
