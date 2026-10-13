<?php
// api/playlist_list.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../konek.php';

if (!$conn || $conn->connect_error) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'DB connection failed']);
  exit;
}

$sql = "SELECT id, name FROM playlist ORDER BY created_at DESC, id DESC";
$res = $conn->query($sql);
$list = [];
if ($res && $res->num_rows) {
  while ($row = $res->fetch_assoc()) {
    $list[] = ['id' => (int)$row['id'], 'name' => $row['name']];
  }
}

echo json_encode(['success' => true, 'playlists' => $list]);
