<?php
// api/news_upload.php — FIX FINAL
// Endpoint murni JSON untuk upload berita

// Nonaktifkan output buffering dari router atau layout
if (ob_get_level()) ob_end_clean();

// Tidak boleh ada output HTML
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

// Pastikan tabel news ada
$conn->query("
CREATE TABLE IF NOT EXISTS news (
  id INT AUTO_INCREMENT PRIMARY KEY,
  acara ENUM('Berita Daerah Pagi','Berita Daerah Siang','Berita Sepekan') NOT NULL,
  judul VARCHAR(255) DEFAULT NULL,
  reporter VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  duration_seconds INT UNSIGNED DEFAULT NULL,
  aired_date DATE DEFAULT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  notes TEXT DEFAULT NULL,
  uploaded_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Ambil input
$acara    = $_POST['acara'] ?? '';
$judul    = trim($_POST['judul'] ?? '');
$reporter = trim($_POST['reporter'] ?? '');
$status   = $_POST['status'] ?? 'draft';
$aired    = $_POST['aired_date'] ?? null;

// Validasi input
if ($acara === '' || $reporter === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Harap isi acara dan reporter.']);
  exit;
}
if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'File MP3 belum dipilih atau gagal diunggah.']);
  exit;
}

// Validasi file
$file = $_FILES['audio'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$mime = @mime_content_type($file['tmp_name']) ?: '';

$isMp3 = ($ext === 'mp3') || stripos($mime, 'audio/mpeg') !== false || stripos($mime, 'audio/mp3') !== false;
if (!$isMp3) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Hanya file MP3 yang diperbolehkan.']);
  exit;
}

// Simpan file
$uploadDir = __DIR__ . '/../uploads/news';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

$newName  = time() . '_' . bin2hex(random_bytes(3)) . '.mp3';
$destPath = $uploadDir . '/' . $newName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
  $err = error_get_last();
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => 'Gagal menyimpan file ke server: ' . ($err['message'] ?? 'tidak diketahui')
  ]);
  exit;
}

$relPath = 'uploads/news/' . $newName;

// Simpan ke DB
$userId = $_SESSION['user_id'] ?? null;
$stmt = $conn->prepare("
  INSERT INTO news (acara, judul, reporter, file_path, aired_date, status, uploaded_by)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param('ssssssi', $acara, $judul, $reporter, $relPath, $aired, $status, $userId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal menyimpan ke database.']);
  exit;
}

// Output JSON murni
echo json_encode([
  'success' => true,
  'message' => 'Berita berhasil diunggah!',
  'item' => [
    'id'       => $conn->insert_id,
    'acara'    => $acara,
    'judul'    => $judul,
    'reporter' => $reporter,
    'file'     => $relPath,
    'status'   => $status,
    'aired'    => $aired,
  ]
]);
exit;
