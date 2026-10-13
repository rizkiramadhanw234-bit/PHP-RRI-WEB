<?php
// api/delete_music.php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'Method not allowed']);
  exit;
}

// Ambil id: dukung JSON & x-www-form-urlencoded
$id = 0;
if (!empty($_POST['id'])) {
  $id = (int)$_POST['id'];
} else {
  $raw = file_get_contents('php://input');
  if ($raw) {
    $j = json_decode($raw, true);
    if (isset($j['id'])) $id = (int)$j['id'];
  }
}

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Bad request: id tidak valid']);
  exit;
}

// Ambil data lagu (untuk hapus file)
$stmt = $conn->prepare("SELECT file_path, cover FROM music WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res  = $stmt->get_result();
if (!$res || !$res->num_rows) {
  echo json_encode(['success'=>false,'error'=>'Lagu tidak ditemukan']);
  exit;
}
$music = $res->fetch_assoc();
$stmt->close();

// Hapus relasi dari playlist_songs
$stmt = $conn->prepare("DELETE FROM playlist_songs WHERE music_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Hapus record utama
$stmt = $conn->prepare("DELETE FROM music WHERE id=?");
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  echo json_encode(['success'=>false,'error'=>'Gagal menghapus data di database']);
  exit;
}

// (Opsional) Hapus file fisik jika berada di dalam project
function safe_unlink($path) {
  $path = trim((string)$path);
  if ($path === '' || preg_match('#^https?://#i', $path)) return; // jangan hapus URL eksternal
  $rel = ltrim(str_replace('\\','/',$path), '/');
  $full = realpath(__DIR__ . '/../' . $rel);
  $root = realpath(__DIR__ . '/..');

  if ($full && $root && strpos($full, $root) === 0 && is_file($full)) {
    @unlink($full);
  }
}

safe_unlink($music['file_path'] ?? '');
safe_unlink($music['cover'] ?? '');

echo json_encode(['success'=>true]);
