<?php
// CLI-only tests; no database, production credentials or network access.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../admin/showcase-media.php';
$checks = 0;
function check($condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$key = str_repeat('a', 32);
check(showcaseMedia(json_encode(['key'=>[]])) === [], 'Reject invalid media key');
check(showcaseMedia(json_encode(['key'=>$key,'width'=>480,'height'=>360,'variants'=>[480=>'../../api/.secrets.php']])) === [], 'Reject traversal path');
check(showcaseMedia('invalid') === [], 'Reject broken JSON');
$missing = json_encode(['key'=>$key,'width'=>480,'height'=>360,'variants'=>[480=>$key.'-480.webp']]);
check(strpos(showcaseImage($missing,'Example'), 'Image unavailable') !== false, 'Missing image fallback');
check(showcaseUrl('dashboard',2) === '/showcase?category=dashboard&page=2', 'Shareable category pagination');
check(strpos(showcaseImage(null,'Example'), 'Image coming soon') !== false, 'Absent media fallback');
$tmp = tempnam(sys_get_temp_dir(), 'showcase-test-');
try {
    file_put_contents($tmp, '<?php echo "not an image";');
    try { showcaseValidateImage(['error'=>0,'size'=>filesize($tmp),'name'=>'fake.webp','tmp_name'=>$tmp]); check(false,'Invalid MIME accepted'); }
    catch (RuntimeException $e) { check(strpos($e->getMessage(),'genuine') !== false,'Invalid MIME rejected'); }
    if (function_exists('imagecreatetruecolor')) {
        $image = imagecreatetruecolor(10001,1); imagepng($image,$tmp); imagedestroy($image);
        try { showcaseValidateImage(['error'=>0,'size'=>filesize($tmp),'name'=>'huge.png','tmp_name'=>$tmp]); check(false,'Oversized dimensions accepted'); }
        catch (RuntimeException $e) { check(strpos($e->getMessage(),'dimensions') !== false,'Dimension limit enforced'); }
        $image = imagecreatetruecolor(64,48); imagepng($image,$tmp); imagedestroy($image);
        $size = showcaseValidateImage(['error'=>0,'size'=>filesize($tmp),'name'=>'../../safe.png','tmp_name'=>$tmp]);
        check($size[0] === 64 && $size[1] === 48,'Valid MIME and dimensions; filename is not a storage path');
    }
} finally { unlink($tmp); }
echo "$checks Showcase media checks passed\n";
