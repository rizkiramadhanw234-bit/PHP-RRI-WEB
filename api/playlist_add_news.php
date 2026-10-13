<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

header('Content-Type: application/json; charset=utf-8');

// Pastikan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

// Ambil data dari request
$playlist_id = intval($_POST['playlist_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$artist      = trim($_POST['artist'] ?? '');
$acara       = trim($_POST['acara'] ?? '');
$file_path   = trim($_POST['file_path'] ?? '');
$cover       = trim($_POST['cover'] ?? '');
$user_id     = $_SESSION['user_id'] ?? null;

// Validasi dasar
if ($playlist_id <= 0 || $title === '' || $file_path === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Data tidak lengkap.']);
  exit;
}

// Pastikan tabel playlist_songs ada
$conn->query("
CREATE TABLE IF NOT EXISTS playlist_songs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  playlist_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  artist VARCHAR(255) DEFAULT NULL,
  acara VARCHAR(100) DEFAULT NULL,
  file_path VARCHAR(255) NOT NULL,
  cover VARCHAR(255) DEFAULT NULL,
  added_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Cek apakah file sudah ada dalam playlist (hindari duplikat)
$check = $conn->prepare("SELECT id FROM playlist_songs WHERE playlist_id=? AND file_path=?");
$check->bind_param("is", $playlist_id, $file_path);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
  echo json_encode(['success' => false, 'error' => 'Lagu ini sudah ada di playlist.']);
  exit;
}
$check->close();

// Masukkan ke playlist
$stmt = $conn->prepare("
  INSERT INTO playlist_songs (playlist_id, title, artist, acara, file_path, cover, added_by)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isssssi", $playlist_id, $title, $artist, $acara, $file_path, $cover, $user_id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
  echo json_encode(['success' => true, 'message' => 'Berhasil ditambahkan ke playlist.']);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal menambah ke playlist.']);
}
