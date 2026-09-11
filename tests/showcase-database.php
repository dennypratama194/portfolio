<?php
// Only the disposable database created by showcase-fixture.cjs is accessed.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../api/showcase.php';
$pdo = new PDO('mysql:host=127.0.0.1;port=33317;dbname=showcase_qa;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo->beginTransaction();
try {
    showcaseQuery($pdo, 'INSERT INTO projects (title,slug) VALUES (?,?)', ['Disposable related case','disposable-related-case']);
    $case = (int)$pdo->lastInsertId();
    showcaseQuery($pdo, 'INSERT INTO showcase_projects (title,slug,related_case_study_id) VALUES (?,?,?)', ['Disposable design','disposable-design',$case]);
    $design = (int)$pdo->lastInsertId();
    showcaseQuery($pdo, 'DELETE FROM projects WHERE id=?', [$case]);
    $value = showcaseQuery($pdo, 'SELECT related_case_study_id FROM showcase_projects WHERE id=?', [$design])->fetchColumn();
    if ($value !== null) throw new RuntimeException('Related case deletion must set reference to NULL');
    foreach ([3,1,2] as $order) showcaseQuery($pdo, 'INSERT INTO showcase_images (showcase_id,media,sort_order) VALUES (?,?,?)', [$design,'{}',$order]);
    $orders = showcaseQuery($pdo, 'SELECT sort_order FROM showcase_images WHERE showcase_id=? ORDER BY sort_order,id', [$design])->fetchAll(PDO::FETCH_COLUMN);
    if (array_map('intval',$orders) !== [1,2,3]) throw new RuntimeException('Gallery ordering failed');
    showcaseQuery($pdo, 'DELETE FROM showcase_projects WHERE id=?', [$design]);
    if ((int)showcaseQuery($pdo, 'SELECT COUNT(*) FROM showcase_images WHERE showcase_id=?', [$design])->fetchColumn() !== 0) throw new RuntimeException('Gallery cascade failed');
    echo "3 database integrity checks passed\n";
} finally { $pdo->rollBack(); }
$plan = showcaseQuery($pdo, 'EXPLAIN SELECT id,title,slug,thumbnail FROM showcase_projects WHERE is_published=1 AND category_id=? ORDER BY sort_order,id LIMIT 12', [1])->fetchAll();
echo 'Category query EXPLAIN: ' . json_encode(array_map(function ($row) { return ['key'=>$row['key'],'rows'=>$row['rows'],'extra'=>$row['Extra']]; },$plan)) . "\n";
