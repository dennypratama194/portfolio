/* Run only against tests/showcase-fixture.cjs's isolated loopback fixture. */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const base = process.env.SHOWCASE_TEST_BASE || 'http://127.0.0.1:8137';
if (!/^http:\/\/127\.0\.0\.1:813[78]$/.test(base)) throw Error('Tests are restricted to the isolated loopback fixture.');
let cookie = '';
async function get(url, auth = false) {
  const res = await fetch(base + url, { headers: { 'X-Forwarded-Proto': 'https', ...(auth ? { Cookie: cookie } : {}) }, redirect: 'manual' });
  return { res, html: await res.text() };
}
async function post(url, fields, file) {
  const body = new FormData();
  for (const [key, value] of Object.entries(fields)) body.set(key, String(value));
  if (file) body.set(file.field || 'thumbnail', new Blob([file.data], { type: file.mime }), file.name);
  const res = await fetch(base + url, { method: 'POST', body, headers: { Cookie: cookie, 'X-Forwarded-Proto': 'https' }, redirect: 'manual' });
  return { res, html: await res.text() };
}
const clean = html => assert.doesNotMatch(html, /(?:Fatal error|Warning:|Notice:|SQLSTATE\[)/);
const fields = { csrf: 'fixture-csrf', title: 'Integration test', slug: 'integration-test', short_description: 'Test description', thumbnail_alt: 'A dashboard with summary cards and a bar chart', year: 2026, tools: 'Figma', client: '', category_id: 1, related_case_study_id: 1, sort_order: 100 };
const upload = fs.readFileSync(path.join(os.tmpdir(), 'portfolio-showcase-qa/site/upload.webp'));

test('public archive, metadata, filters, pagination, drafts and regressions', async () => {
  const list = await get('/showcase'); clean(list.html);
  assert.equal(list.res.status, 200);
  assert.equal((list.html.match(/class="sc-card"/g) || []).length, 12);
  assert.doesNotMatch(list.html, /private-draft|-1600.webp/);
  assert.match(list.html, /srcset=/); assert.match(list.html, /aria-label="Filter designs/);
  const second = await get('/showcase?page=2');
  assert.equal((second.html.match(/class="sc-card"/g) || []).length, 2);
  assert.match(second.html, /rel="canonical" href="https:\/\/dennypratama.com\/showcase\?page=2"/);
  const filtered = await get('/showcase?category=dashboard'); clean(filtered.html);
  assert.match(filtered.html, /dashboard" aria-current="page"/);
  assert.equal((filtered.html.match(/class="sc-card"/g) || []).length, 5);
  const empty = await get('/showcase?category=branding');
  assert.equal(empty.res.status, 200); assert.match(empty.html, /No designs in this category/);
  for (const url of ['/showcase/missing','/showcase/private-draft','/showcase?page=999','/showcase?category=unknown','/showcase?category=%27%20OR%201=1','/showcase-item?slug[]=evil','/showcase/invalid_slug']) {
    const result = await get(url); assert.equal(result.res.status, 404, url); clean(result.html);
  }
  const detail = await get('/showcase/design-1'); clean(detail.html);
  assert.match(detail.html, /View full case study/); assert.match(detail.html, /A closer look at the interface/);
  assert.match(detail.html, /property="og:image" content="https:\/\/dennypratama.com\/admin\/uploads\/showcase\//);
  assert.match(detail.html, /CreativeWork/);
  const withoutCase = await get('/showcase/design-2');
  assert.doesNotMatch(withoutCase.html, /View full case study/);
  const sitemap = await get('/sitemap.xml'); clean(sitemap.html);
  assert.match(sitemap.html, /showcase\/design-1/); assert.doesNotMatch(sitemap.html, /private-draft/);
  for (const url of ['/','/blog','/blog/fixture-post','/case-studies','/case-studies/fixture-case','/admin/login']) {
    const page = await get(url); assert.equal(page.res.status, 200, url); clean(page.html);
  }
  const home = await get('/');
  assert.equal((home.html.match(/class="sc-card"/g) || []).length, 6);
  assert.match(home.html, /pm-form/); // Existing contact modal still renders.
});

test('admin authorization, CSRF, CRUD, uploads, escaping and deletion', async () => {
  for (const url of ['/admin/showcase','/admin/showcase-edit','/admin/showcase-categories']) {
    const result = await get(url); assert.equal(result.res.status, 302); assert.equal(result.res.headers.get('location'), '/admin/login');
  }
  const login = await get('/__qa_login'); cookie = login.res.headers.get('set-cookie').split(';')[0];
  for (const url of ['/admin/showcase','/admin/showcase-edit','/admin/showcase-categories','/admin/index','/admin/projects','/admin/edit?id=1','/admin/project-edit?id=1']) {
    const result = await get(url, true); assert.equal(result.res.status, 200, url); clean(result.html);
  }
  const legacyUpload = await post('/admin/upload-image.php', { csrf: 'fixture-csrf' }, { field: 'image', name: 'legacy.webp', mime: 'image/webp', data: upload });
  const legacyUrl = JSON.parse(legacyUpload.html).url;
  assert.match(legacyUrl, /^\/admin\/uploads\/img_[a-z0-9.]+\.webp$/);
  fs.unlinkSync(path.join(os.tmpdir(), 'portfolio-showcase-qa/site', legacyUrl));
  for (const csrf of ['', 'incorrect']) {
    const result = await post('/admin/showcase', { csrf, id: 1, action: 'delete' }); assert.equal(result.res.status, 403);
  }
  const noTitle = await post('/admin/showcase-edit', { ...fields, title: '' });
  assert.match(noTitle.html, /Enter a title/); clean(noTitle.html);
  // Slug is server-generated from the title, not user input — verify it auto-suffixes
  // instead of colliding with the seeded "design-1" (title "Finance, at a glance").
  const duplicateTitle = await post('/admin/showcase-edit', { ...fields, title: 'Design 1', is_published: 1 }, { name: 'dup.webp', mime: 'image/webp', data: upload });
  assert.equal(duplicateTitle.res.status, 302, duplicateTitle.html.slice(-300));
  assert.equal((await get('/showcase/design-1-2')).res.status, 200);
  for (const file of [
    { name: 'fake.webp', mime: 'image/webp', data: '<?php echo "bad"; ?>' },
    { name: 'image.php', mime: 'image/webp', data: upload },
    { name: 'image.jpg', mime: 'image/jpeg', data: upload },
    { name: 'large.webp', mime: 'image/webp', data: Buffer.alloc(6 * 1024 * 1024) }
  ]) {
    const result = await post('/admin/showcase-edit', fields, file);
    assert.equal(result.res.status, 200); assert.match(result.html, /class="errors"/); clean(result.html);
  }
  // XSS payload goes in short_description (not title) so the resulting slug stays
  // the predictable "integration-test" the rest of this test relies on.
  const created = await post('/admin/showcase-edit', { ...fields, short_description: '</script><script>alert(1)</script>', is_published: 1 }, { name: 'real.webp', mime: 'image/webp', data: upload });
  assert.equal(created.res.status, 302, created.html.slice(-300));
  const location = created.res.headers.get('location');
  const id = new URL(base + location).searchParams.get('id');
  const detail = await get('/showcase/integration-test'); clean(detail.html);
  assert.equal(detail.res.status, 200);
  assert.doesNotMatch(detail.html, /<script>alert\(1\)<\/script>/);
  assert.match(detail.html, /&lt;script&gt;/);
  const files = [...detail.html.matchAll(/\/admin\/uploads\/showcase\/([a-f0-9]{32}-\d+.webp)/g)].map(m => m[1]);
  assert.ok(files.length > 0);
  const galleryUpload = await post('/admin/showcase-edit?id=' + id, { ...fields, is_published: 1 }, { field: 'images[]', name: 'gallery.webp', mime: 'image/webp', data: upload });
  assert.equal(galleryUpload.res.status, 302);
  assert.equal((await get('/showcase/integration-test')).res.status, 404);
  const edit = await get('/admin/showcase-edit?id=' + id, true);
  for (const tag of edit.html.matchAll(/<script([^>]*)>([\s\S]*?)<\/script>/g)) {
    if (!tag[1].includes('application/ld+json')) new (require('node:vm').Script)(tag[2]);
  }
  const imageId = edit.html.match(/name="gallery\[(\d+)\]\[alt_text\]"/)[1];
  const publishBlocked = await post('/admin/showcase', { csrf: 'fixture-csrf', id, action: 'publish' });
  assert.match(publishBlocked.html, /Add alt text/);
  const published = await post('/admin/showcase-edit?id=' + id, { ...fields, is_published: 1, [`gallery[${imageId}][alt_text]`]: 'A second view', [`gallery[${imageId}][caption]`]: '<script>caption</script>', [`gallery[${imageId}][sort_order]`]: 2 });
  assert.equal(published.res.status, 302);
  const withGallery = await get('/showcase/integration-test');
  assert.equal(withGallery.res.status, 200); assert.match(withGallery.html, /&lt;script&gt;caption/);
  const category = await post('/admin/showcase-categories', { csrf: 'fixture-csrf', id: 0, action: 'save', name: 'Temporary category', slug: 'temporary-category', sort_order: 0 });
  assert.equal(category.res.status, 302);
  const categories = await get('/admin/showcase-categories', true);
  const categoryId = categories.html.match(/id="cat-name-(\d+)"[^>]*value="Temporary category"/)[1];
  const assigned = await post('/admin/showcase-edit?id=' + id, { ...fields, category_id: categoryId, is_published: 1, related_case_study_id: 2, [`gallery[${imageId}][alt_text]`]: 'A second view', [`gallery[${imageId}][sort_order]`]: 1 });
  assert.equal(assigned.res.status, 302);
  assert.doesNotMatch((await get('/showcase/integration-test')).html, /View full case study/); // Draft case study stays hidden.
  assert.match((await get('/showcase?category=temporary-category')).html, /Integration test/);
  const categoryRemoved = await post('/admin/showcase-categories', { csrf: 'fixture-csrf', id: categoryId, action: 'delete' });
  assert.equal(categoryRemoved.res.status, 302);
  assert.equal((await get('/showcase/integration-test')).res.status, 200); // SET NULL keeps the design.
  const unpublished = await post('/admin/showcase', { csrf: 'fixture-csrf', id, action: 'unpublish' });
  assert.equal(unpublished.res.status, 302); assert.equal((await get('/showcase/integration-test')).res.status, 404);
  const removed = await post('/admin/showcase', { csrf: 'fixture-csrf', id, action: 'delete' });
  assert.equal(removed.res.status, 302);
  for (const filename of new Set(files.filter(f => !f.startsWith('000000')))) {
    assert.equal(fs.existsSync(path.join(os.tmpdir(), 'portfolio-showcase-qa/site/admin/uploads/showcase', filename)), false);
  }
});

test('Apache clean routes and generated-media restrictions', { skip: !base.endsWith(':8138') }, async () => {
  const route = await get('/showcase/design-1?slug=private-draft');
  assert.equal(route.res.status, 200); assert.match(route.html, /Finance, at a glance/);
  for (const url of ['/admin/uploads/showcase/evil.php','/admin/uploads/showcase/image.svg','/admin/uploads/showcase/folder/image.jpg']) {
    assert.equal((await get(url)).res.status, 403, url);
  }
  assert.equal((await get('/admin/uploads/showcase/00000000000000000000000000000001-480.webp')).res.status, 200);
});
