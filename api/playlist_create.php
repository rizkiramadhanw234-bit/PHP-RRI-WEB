<?php

// Hapus semua output tambahan (termasuk injected script bawaan server)
while (ob_get_level()) ob_end_clean();
header_remove();

// Pastikan tidak ada HTML disisipkan oleh extension atau server
ini_set('output_buffering', 0);
ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);
ob_implicit_flush(true);

require_once __DIR__ . '/../konek.php';
header('Content-Type: application/json; charset=utf-8');


// Pastikan koneksi OK
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit;
}

// Ambil input
$name = trim($_POST['new_playlist_name'] ?? '');
if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Nama playlist tidak boleh kosong']);
    exit;
}

// Cegah duplikasi playlist dengan nama sama
$check = $conn->prepare("SELECT id FROM playlist WHERE name = ?");
$check->bind_param('s', $name);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Playlist dengan nama ini sudah ada']);
    $check->close();
    exit;
}
$check->close();

// Insert playlist baru
$stmt = $conn->prepare("INSERT INTO playlist (name) VALUES (?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Query gagal dipersiapkan: ' . $conn->error]);
    exit;
}
$stmt->bind_param('s', $name);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'id' => $stmt->insert_id,
        'name' => $name
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Gagal menambah playlist: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
