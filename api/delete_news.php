<?php
// api/delete_news.php — final clean version
if (ob_get_level()) ob_end_clean();
header_remove("Content-Type");
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

// Hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'ID tidak valid.']);
  exit;
}

// Ambil file path
$stmt = $conn->prepare("SELECT file_path FROM news WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan.']);
  exit;
}

// Hapus file di server
$file = __DIR__ . '/../' . ltrim($row['file_path'], '/');
if (file_exists($file)) @unlink($file);

// Hapus dari database
$stmt = $conn->prepare("DELETE FROM news WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal menghapus dari database.']);
  exit;
}

echo json_encode(['success' => true, 'message' => 'Berita berhasil dihapus.']);
exit;
