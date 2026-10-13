<?php
while (ob_get_level()) ob_end_clean();
header_remove();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../konek.php';

$playlist_news_id = intval($_POST['playlist_news_id'] ?? 0);
if ($playlist_news_id <= 0) {
    echo json_encode(["success"=>false,"error"=>"Data tidak lengkap (playlist_news_id wajib)."]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM playlist_news WHERE id=?");
$stmt->bind_param("i", $playlist_news_id);

if ($stmt->execute()) {
    echo json_encode(["success"=>true]);
} else {
    echo json_encode(["success"=>false,"error"=>"Gagal menghapus berita."]);
}
