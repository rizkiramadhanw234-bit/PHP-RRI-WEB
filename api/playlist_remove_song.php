<?php
// Bersihkan semua output
while (ob_get_level()) ob_end_clean();
header_remove();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../konek.php';

$playlist_id = intval($_POST['playlist_id'] ?? 0);
$playlist_song_id = intval($_POST['playlist_song_id'] ?? $_POST['music_id'] ?? 0);

if ($playlist_id <= 0 || $playlist_song_id <= 0) {
    echo json_encode(["success"=>false,"error"=>"Data tidak lengkap (playlist_id & row_id wajib)."]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM playlist_songs WHERE playlist_id=? AND id=?");
$stmt->bind_param("ii", $playlist_id, $playlist_song_id);

if ($stmt->execute()) {
    echo json_encode(["success"=>true]);
} else {
    echo json_encode(["success"=>false,"error"=>"Gagal menghapus lagu."]);
}
