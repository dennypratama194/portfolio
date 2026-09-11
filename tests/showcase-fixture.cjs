/* Isolated QA copy only. Requires an empty local MariaDB on 127.0.0.1:33317.
   Never points at the site's configured database or copies its secrets. */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const qa = path.join(os.tmpdir(), 'portfolio-showcase-qa');
const site = path.join(qa, 'site');
const php = process.env.PHP_BIN || 'C:/xampp/php/php.exe';
fs.mkdirSync(site, { recursive: true });
for (const folder of ['', 'admin', 'admin/partials', 'admin/css', 'api', 'partials', 'css', 'migrations']) {
  const target = path.join(site, folder);
  fs.mkdirSync(target, { recursive: true });
  for (const file of fs.readdirSync(path.join(root, folder), { withFileTypes: true })) {
    if (!file.isFile() || file.name.startsWith('.') || (folder === 'api' && file.name === 'db.php')) continue;
    if (!/\.(php|css|js|sql)$/.test(file.name)) continue;
    fs.copyFileSync(path.join(root, folder, file.name), path.join(target, file.name));
  }
}
fs.writeFileSync(path.join(site, 'api/db.php'), `<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=33317;dbname=showcase_qa;charset=utf8mb4','root','', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if (!defined('ADMIN_PASS_HASH')) define('ADMIN_PASS_HASH', password_hash('test-only', PASSWORD_DEFAULT));
if (!defined('ADMIN_USER')) define('ADMIN_USER','fixture');
`);
fs.mkdirSync(path.join(site, 'admin/uploads/showcase'), { recursive: true });
fs.mkdirSync(path.join(qa, 'sessions'), { recursive: true });
fs.copyFileSync(path.join(root, '.htaccess'), path.join(site, '.htaccess'));
fs.writeFileSync(path.join(site, '__qa_login.php'), "<?php session_start(); $_SESSION['authed']=true; $_SESSION['csrf_token']='fixture-csrf'; echo 'Fixture session ready';");
const slash = value => value.replace(/\\/g, '/');
fs.writeFileSync(path.join(qa, 'php.ini'), `[PHP]\nextension_dir="C:/xampp/php/ext"\nextension=pdo_mysql\nextension=mbstring\nextension=fileinfo\nextension=gd\nsession.save_path="${slash(path.join(qa, 'sessions'))}"\nupload_max_filesize=5M\npost_max_size=70M\ndisplay_errors=On\nlog_errors=On\n`);
fs.writeFileSync(path.join(qa, 'httpd.conf'), `ServerRoot "C:/xampp/apache"
Listen 127.0.0.1:8138
ServerName 127.0.0.1
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule alias_module modules/mod_alias.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadFile "C:/xampp/php/php8ts.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "${slash(qa)}"
PidFile "${slash(qa)}/apache.pid"
ErrorLog "${slash(qa)}/apache.err.log"
DocumentRoot "${slash(site)}"
DirectoryIndex index.php
<Directory "${slash(site)}">
  AllowOverride All
  Require local
</Directory>
Alias /assets "${slash(root)}/assets"
<Directory "${slash(root)}/assets">
  Require local
</Directory>
<FilesMatch "\\.php$">
  SetHandler application/x-httpd-php
</FilesMatch>
`);
fs.writeFileSync(path.join(site, 'router.php'), `<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === '/__qa_login') {
    session_start(); $_SESSION['authed']=true; $_SESSION['csrf_token']='fixture-csrf';
    echo 'Fixture session ready'; return;
}
if (strpos($uri, '/assets/') === 0) {
    $base = ${JSON.stringify(root.replace(/\\/g, '/'))};
    $file = realpath($base . $uri);
    if ($file && strpos(str_replace('\\\\','/',$file), $base . '/assets/') === 0 && is_file($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mime = ['webp'=>'image/webp','png'=>'image/png','jpg'=>'image/jpeg','svg'=>'image/svg+xml','mp4'=>'video/mp4','ico'=>'image/x-icon'];
        header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream')); readfile($file); return;
    }
}
if (is_file(__DIR__ . $uri)) return false;
$routes=['/'=>'index.php','/sitemap.xml'=>'sitemap.php'];
$file=$routes[$uri] ?? ltrim($uri,'/') . '.php';
foreach (['showcase'=>'showcase-item.php','blog'=>'post.php','case-studies'=>'case-study.php'] as $prefix=>$script) {
    if (preg_match('#^/' . $prefix . '/([^/]+)$#',$uri,$m)) { $file=$script; $_GET['slug']=$m[1]; }
}
if (!is_file(__DIR__ . '/' . $file) || strpos($file,'..') !== false) $file='404.php';
$_SERVER['SCRIPT_NAME']='/' . $file; $_SERVER['SCRIPT_FILENAME']=__DIR__ . '/' . $file;
chdir(dirname(__DIR__ . '/' . $file)); require __DIR__ . '/' . $file;
`);
fs.writeFileSync(path.join(site, 'seed.php'), `<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=33317','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS showcase_qa'); $pdo->exec('USE showcase_qa');
$pdo->exec(file_get_contents(__DIR__ . '/migrations/004_projects.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/migrations/005_showcase.sql'));
$pdo->exec(file_get_contents(__DIR__ . '/migrations/005_showcase.sql'));
$pdo->exec("CREATE TABLE IF NOT EXISTS posts (id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(255),slug VARCHAR(255),excerpt TEXT,body TEXT,featured_image VARCHAR(255),category VARCHAR(50),is_published TINYINT DEFAULT 0,scheduled_at DATETIME NULL,published_at DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("UPDATE projects SET excerpt=COALESCE(excerpt,''),cover_image=COALESCE(cover_image,''),client=COALESCE(client,''),year=COALESCE(year,2026),tools=COALESCE(tools,''),s1_body=COALESCE(s1_body,''),s2_body=COALESCE(s2_body,''),s3_body=COALESCE(s3_body,''),s4_body=COALESCE(s4_body,''),s5_body=COALESCE(s5_body,'')");
if ($pdo->query('SELECT COUNT(*) FROM showcase_projects')->fetchColumn()) exit('Fixture already populated');
$pdo->exec("INSERT INTO showcase_categories (name,slug) VALUES ('Web Design','web-design'),('Dashboard','dashboard'),('Mobile','mobile'),('Branding','branding')");
$pdo->exec("INSERT INTO projects (title,slug,is_published) VALUES ('Fixture case study','fixture-case',1),('Draft case study','draft-case',0)");
$pdo->exec("UPDATE projects SET excerpt='',cover_image='',client='',year=2026,tools='',s1_body='',s2_body='',s3_body='',s4_body='',s5_body=''");
$pdo->exec("INSERT INTO posts (title,slug,excerpt,body,category,is_published,published_at) VALUES ('Fixture blog post','fixture-post','A short test excerpt','<h2>Test heading</h2><p>Test content.</p>','uiux',1,NOW())");
$names=['Finance, at a glance','A quieter way to shop','Travel without the noise','A workspace that flows','Room to breathe','Built around the details','Small screen, big ideas','The everyday dashboard','A different perspective','Made for the morning','A fresh start','Focus on what matters','Explore the essentials','One more experiment'];
$media=[];
for ($i=0;$i<6;$i++) {
    $key=str_pad(dechex($i+1),32,'0',STR_PAD_LEFT); $variants=[];
    foreach ([480,800,1600] as $width) {
        $height=(int)($width*.75); $img=imagecreatetruecolor($width,$height);
        $bg=imagecolorallocate($img,220+$i*4,225-$i*6,232-$i*8); imagefill($img,0,0,$bg);
        $paper=imagecolorallocate($img,250,250,248); $ink=imagecolorallocate($img,35,40,42); $accent=imagecolorallocate($img,90+$i*20,100,120);
        imagefilledrectangle($img,(int)($width*.12),(int)($height*.14),(int)($width*.88),(int)($height*.86),$paper);
        imagefilledrectangle($img,(int)($width*.12),(int)($height*.14),(int)($width*.29),(int)($height*.86),$ink);
        for ($j=0;$j<3;$j++) imagefilledrectangle($img,(int)($width*(.34+$j*.17)),(int)($height*.3),(int)($width*(.48+$j*.17)),(int)($height*.48),$bg);
        for ($j=0;$j<8;$j++) imagefilledrectangle($img,(int)($width*(.35+$j*.055)),(int)($height*(.7-(($j+$i)%5)*.035)),(int)($width*(.38+$j*.055)),(int)($height*.77),$accent);
        $name=$key.'-'.$width.'.webp'; imagewebp($img,__DIR__.'/admin/uploads/showcase/'.$name,80); imagedestroy($img); $variants[$width]=$name;
    }
    $media[]=json_encode(['key'=>$key,'width'=>1600,'height'=>1200,'variants'=>$variants]);
}
$stmt=$pdo->prepare('INSERT INTO showcase_projects (title,slug,short_description,category_id,thumbnail,thumbnail_alt,year,tools,is_featured,is_published,sort_order,related_case_study_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ($names as $i=>$name) $stmt->execute([$name,'design-'.($i+1),'An interface exploration focused on clarity, rhythm and the details that make everyday tasks feel effortless.',($i%3)+1,$media[$i%6],'A dashboard with a dark sidebar, summary cards and a weekly activity chart.',2026,'Figma',($i<6?1:0),1,$i,($i===0?1:null)]);
$stmt->execute(['Private draft','private-draft','Not public',1,null,'',2026,'',1,0,0,null]);
$stmt=$pdo->prepare('INSERT INTO showcase_images (showcase_id,media,alt_text,caption) VALUES (?,?,?,?)');
$stmt->execute([1,$media[1],'A close-up of the activity dashboard.','A closer look at the interface.']);
copy(__DIR__.'/admin/uploads/showcase/'.json_decode($media[0],true)['variants'][1600],__DIR__.'/upload.webp');
echo 'Fixture ready';
`);
console.log(execFileSync(php, ['-d', 'extension=gd', path.join(site, 'seed.php')], { encoding: 'utf8', windowsHide: true }));
console.log(site);
