<?php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403); exit;
}
require_once __DIR__ . '/../api/showcase.php';

function showcaseValidateImage(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed. Choose the image again (maximum 5 MB).');
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Each image must be 5 MB or smaller.');
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $mime = getUploadMime($file['tmp_name']);
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime || !isAllowedImage($file['tmp_name'])) {
        throw new RuntimeException('Use a genuine JPG, PNG or WebP image.');
    }
    $size = @getimagesize($file['tmp_name']);
    if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] > 10000 || $size[1] > 10000 || $size[0] * $size[1] > 16000000) {
        throw new RuntimeException('Image dimensions are invalid or exceed 16 megapixels / 10,000 pixels per side. Export a smaller image.');
    }
    return $size;
}

function showcaseUpload(array $file): string {
    $size = showcaseValidateImage($file);
    if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Invalid upload. Choose the image again.');
    if (!function_exists('imagecreatefromstring') || (!function_exists('imagewebp') && !function_exists('imagejpeg'))) {
        throw new RuntimeException('Image processing is unavailable. Ask your host to enable PHP GD with WebP or JPEG support.');
    }
    $dir = __DIR__ . '/uploads/showcase';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('Image storage is unavailable.');
    // Only generated raster derivatives are kept; original bytes are never served.
    $source = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$source) throw new RuntimeException('This image could not be decoded. Try exporting it again.');
    $key = bin2hex(random_bytes(16));
    $variants = []; $gridVariants = [];
    $webp = function_exists('imagewebp');
    try {
        foreach (['detail'=>[480,800,1600], 'grid'=>[480,800]] as $kind=>$targets) {
          foreach ($targets as $target) {
            $cropWidth = $kind === 'grid' ? min($size[0], (int)floor($size[1] * 4 / 3)) : $size[0];
            $cropWidth = max(1, $cropWidth);
            $cropHeight = $kind === 'grid' ? max(1, min($size[1], (int)round($cropWidth * 3 / 4))) : $size[1];
            $width = min($target, $cropWidth);
            if (($kind === 'grid' && isset($gridVariants[$width])) || ($kind === 'detail' && isset($variants[$width]))) continue;
            $height = max(1, (int)round($cropHeight * $width / $cropWidth));
            $dest = imagecreatetruecolor($width, $height);
            if ($webp) {
                imagealphablending($dest, false);
                imagesavealpha($dest, true);
            } else {
                imagefill($dest, 0, 0, imagecolorallocate($dest, 255, 255, 255));
            }
            imagecopyresampled($dest, $source, 0, 0, (int)(($size[0]-$cropWidth)/2), (int)(($size[1]-$cropHeight)/2), $width, $height, $cropWidth, $cropHeight);
            $filename = $key . '-' . ($kind === 'grid' ? 'grid-' : '') . $width . ($webp ? '.webp' : '.jpg');
            $ok = $webp ? imagewebp($dest, $dir . '/' . $filename, 80) : imagejpeg($dest, $dir . '/' . $filename, 82);
            imagedestroy($dest);
            if (!$ok || !is_file($dir . '/' . $filename) || filesize($dir . '/' . $filename) === 0) throw new RuntimeException('Could not store the optimized image.');
            if ($kind === 'grid') $gridVariants[$width] = $filename;
            else $variants[$width] = $filename;
          }
        }
    } catch (Throwable $e) {
        foreach (glob($dir . '/' . $key . '-*') as $path) @unlink($path);
        throw $e;
    } finally { imagedestroy($source); }
    return json_encode(['key' => $key, 'width' => $size[0], 'height' => $size[1], 'variants' => $variants, 'grid_variants' => $gridVariants]);
}

function showcaseDeleteMedia(?string $json): void {
    $media = showcaseMedia($json);
    if (!$media) return;
    foreach (array_unique(array_merge(array_values($media['variants']), array_values($media['grid_variants'] ?? []))) as $filename) {
        $path = __DIR__ . '/uploads/showcase/' . $filename;
        if (is_file($path) && !@unlink($path)) error_log('Showcase: unable to remove an unused derivative.');
    }
}
