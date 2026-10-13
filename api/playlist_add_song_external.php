<?php
// api/playlist_add_song_external.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../konek.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false, 'error'=>'Method not allowed']); exit;
}

$playlist_id = intval($_POST['playlist_id'] ?? 0);
$title  = trim($_POST['title']  ?? '');
$artist = trim($_POST['artist'] ?? '');
$url    = trim($_POST['src']    ?? $_POST['url'] ?? ''); // terima "src" atau "url"
$cover  = trim($_POST['cover']  ?? '');

if ($playlist_id <= 0 || $url === '') {
  echo json_encode(['success'=>false, 'error'=>'playlist_id dan src/url wajib diisi']); exit;
}

// Amankan cover kosong
if ($cover === '') {
  // fallback ke default_cover relatif dari root project (opsional)
  $base = rtrim(dirname(__DIR__), '/\\'); // .. (folder webmusic)
  $cover = (isset($_SERVER['HTTP_HOST']))
    ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'], 2) . '/uploads/default_cover.png'
    : '/uploads/default_cover.png';
}

// Simpan ke playlist_items sebagai "external"
$stmt = $conn->prepare("
  INSERT INTO playlist_items (playlist_id, source, video_id, url, title, artist, cover, sort_order, created_at)
  VALUES (?, 'external', NULL, ?, ?, ?, ?, NULL, NOW())
");
$stmt->bind_param('issss', $playlist_id, $url, $title, $artist, $cover);

if (!$stmt->execute()) {
  echo json_encode(['success'=>false, 'error'=>'DB error: '.$conn->error]); exit;
}

$item_id = $stmt->insert_id;
$stmt->close();

echo json_encode([
  'success'      => true,
  'mode'         => 'external',
  'playlist_id'  => $playlist_id,
  'item_id'      => $item_id,
  'title'        => $title,
  'artist'       => $artist,
  'cover'        => $cover,
  'url'          => $url,
]);
