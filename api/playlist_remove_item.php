<?php
while (ob_get_level()) ob_end_clean();
header_remove();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../konek.php';

$playlist_item_id = intval($_POST['playlist_item_id'] ?? 0);
if ($playlist_item_id <= 0) {
    echo json_encode(["success"=>false,"error"=>"Data tidak lengkap (playlist_item_id wajib)."]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM playlist_items WHERE id=?");
$stmt->bind_param("i", $playlist_item_id);

if ($stmt->execute()) {
    echo json_encode(["success"=>true]);
} else {
    echo json_encode(["success"=>false,"error"=>"Gagal menghapus video."]);
}
