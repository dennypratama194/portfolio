<?php
/**
 * One-time seed script — inserts the Brenom Systems case study as a draft.
 * Visit /admin/seed-first-project in the browser (while logged in), then DELETE this file.
 */
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';

$slug = 'brenom-systems';

$existing = $pdo->prepare('SELECT id FROM projects WHERE slug = ?');
$existing->execute([$slug]);
if ($row = $existing->fetch()) {
    echo '<p>Already exists. <a href="/admin/project-edit?id=' . $row['id'] . '">Edit it</a> or <a href="/admin/projects">view all</a>.</p>';
    exit;
}

$s1 = '<p>Brenom Systems replaces 5–10 fragmented component suppliers with one accountable partner for automation and robotics OEMs. The challenge: make a complex B2B procurement service feel simple, credible, and instantly legible to a technical buyer who has been burned by supplier chaos before.</p><p>[Add the real brief here — what the client told you, their goals, timeline, and any constraints that shaped the project.]</p>';

$s2 = '<p>[Describe how you used Claude to analyze the brief. What did you prompt it with? What did it surface about B2B procurement buyers, industrial web conventions, or trust signals for high-stakes supply chain decisions?]</p><p>[Example: "I fed Claude the brief and asked it to identify the top 3 objections a procurement manager would have when landing on this site. It flagged X, Y, Z — which directly shaped the hero copy and the social proof section."]</p>';

$s3 = '<p>[Describe what Figma Make or Claude Design generated from your prompt. What was useful — layout ideas, component suggestions, colour direction? What missed the mark and why? Upload screenshots of the AI output below so visitors can see the raw generation vs. your refined version.]</p>';

$s4 = '<p>[Describe how you rebuilt the design on Figma using your own judgment. What did you change from the AI concepts and why? What design principles or best practices guided your decisions — hierarchy, whitespace, trust-building patterns for B2B, accessibility?]</p><p>[This is the heart of the case study — show your thinking, not just the output.]</p>';

$s5 = '<p>[Describe the final delivered design. How did it solve the original brief? How did the client respond? Upload your polished final screenshots below — ideally desktop and mobile views of the key pages.]</p>';

$stmt = $pdo->prepare(
    'INSERT INTO projects
     (title, slug, excerpt, client, role, year, tools,
      s1_body, s2_body, s3_body, s4_body, s5_body,
      s1_images, s2_images, s3_images, s4_images, s5_images,
      is_published, sort_order)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$stmt->execute([
    'Brenom Systems',
    $slug,
    'Designing the web presence for a B2B managed component supply platform — replacing 5–10 fragmented suppliers with one accountable partner for automation and robotics OEMs.',
    'Brenom Systems',
    'UI/UX Designer',
    2025,
    'Figma, Claude, Figma Make',
    $s1, $s2, $s3, $s4, $s5,
    '[]', '[]', '[]', '[]', '[]',
    0, /* draft — publish from admin when ready */
    1,
]);

$new_id = (int)$pdo->lastInsertId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Seed done</title>
  <style>
    body { font-family: system-ui; padding: 48px; background: #0D0C09; color: #ECEAE2; }
    a { color: #E8320A; }
    .box { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 24px 32px; max-width: 480px; }
    h2 { margin: 0 0 12px; }
    p  { margin: 0 0 16px; opacity: 0.7; font-size: 14px; line-height: 1.6; }
    .warn { color: #f87171; font-size: 13px; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Brenom Systems seeded.</h2>
    <p>The case study was created as a draft. Go to the editor to fill in your real content and upload screenshots for each section.</p>
    <p><a href="/admin/project-edit?id=<?= $new_id ?>">Open editor &rarr;</a></p>
    <p class="warn">Delete this file from the server once you're done:<br/><code>admin/seed-first-project.php</code></p>
  </div>
</body>
</html>
