<?php
// api/stream.php — stream audio dengan dukungan HTTP Range + debug path

require_once __DIR__ . '/../konek.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$debug = isset($_GET['debug']);
if ($id <= 0) { http_response_code(400); exit('Invalid ID'); }

$stmt = $conn->prepare("SELECT file_path FROM music WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) { http_response_code(404); exit('Song not found'); }
$row = $res->fetch_assoc();
$stmt->close();

$root = realpath(__DIR__ . '/..'); // C:\xampp\htdocs\webmusic
$rel  = str_replace(['..','\\'], ['','/'], trim((string)$row['file_path'])); // normalisasi

// Kumpulan kandidat lokasi file
$candidates = [];

// 1) Jika rel sudah absolute (mis. C:\path\file.mp3 atau /var/...)
if (preg_match('~^[A-Za-z]:[\\\\/]~', $rel) || str_starts_with($rel, '/')) {
    $candidates[] = $rel;
}

// 2) Relatif ke root project
$candidates[] = $root . DIRECTORY_SEPARATOR . $rel;

// 3) Coba di uploads/ + basename
$candidates[] = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rel);

// 4) Coba di uploads/ + rel (kalau rel mengandung subfolder)
$candidates[] = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $rel;

// 5) Coba di uploads/music/
$candidates[] = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'music' . DIRECTORY_SEPARATOR . basename($rel);

// Cari kandidat yang benar
$found = null;
$tried = [];
foreach ($candidates as $p) {
    $real = realpath($p);
    $tried[] = $p . ($real ? " -> $real" : '');
    if ($real && is_file($real)) { $found = $real; break; }
}

if (!$found) {
    http_response_code(404);
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "File not found on server\n";
        echo "id        : $id\n";
        echo "file_path : " . $row['file_path'] . "\n";
        echo "root      : $root\n";
        echo "Tried paths:\n- " . implode("\n- ", $tried);
    } else {
        exit('File not found on server');
    }
    exit;
}

// ====== Streaming dengan HTTP Range ======
$size = filesize($found);
$mime = function_exists('mime_content_type') ? mime_content_type($found) : 'audio/mpeg';

$start = 0;
$end   = $size - 1;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $start = max(0, (int)$m[1]);
    if ($m[2] !== '') $end   = min($end, (int)$m[2]);
    if ($start > $end) { header('HTTP/1.1 416 Requested Range Not Satisfiable'); exit; }
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
    $length = $end - $start + 1;
} else {
    header('HTTP/1.1 200 OK');
    $length = $size;
}
header('Content-Length: ' . $length);
@ob_end_clean();

$fp = fopen($found, 'rb');
fseek($fp, $start);
$chunk = 8192;
$left  = $length;
while ($left > 0 && !feof($fp)) {
    $read = ($left > $chunk) ? $chunk : $left;
    $buf  = fread($fp, $read);
    echo $buf;
    $left -= strlen($buf);
    flush();
}
fclose($fp);
exit;
