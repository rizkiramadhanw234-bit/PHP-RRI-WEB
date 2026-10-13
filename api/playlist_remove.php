<?php
// Matikan output sampingan (mencegah script injected dari server)
while (ob_get_level()) ob_end_clean();
header_remove();
ini_set('output_buffering', 0);
ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);
ob_implicit_flush(true);

require_once __DIR__ . '/../konek.php';
header('Content-Type: application/json; charset=utf-8');

// Pastikan koneksi valid
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit;
}

// Ambil ID
$id = intval($_POST['playlist_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID playlist tidak valid']);
    exit;
}

// Cek apakah playlist masih ada
$check = $conn->prepare("SELECT id FROM playlist WHERE id = ?");
$check->bind_param('i', $id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Playlist tidak ditemukan']);
    $check->close();
    exit;
}
$check->close();

// Hapus playlist
$stmt = $conn->prepare("DELETE FROM playlist WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Gagal menyiapkan query: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Gagal menghapus playlist: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
exit;
