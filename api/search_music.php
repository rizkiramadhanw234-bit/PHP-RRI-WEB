<?php
// api/search_music.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../konek.php';

$q = trim($_GET['q'] ?? '');
$songs = [];

if ($q === '') {
    // boleh kosong: kembalikan semua lagu (opsional)
    $stmt = $conn->prepare("SELECT id, title, artist, cover FROM music ORDER BY id DESC");
} else {
    $like = "%{$q}%";
    $stmt = $conn->prepare("SELECT id, title, artist, cover FROM music
                            WHERE title LIKE ? OR artist LIKE ?
                            ORDER BY id DESC");
    $stmt->bind_param('ss', $like, $like);
}

if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $songs[] = [
            'id'     => (int)$row['id'],
            'title'  => $row['title'] ?? '',
            'artist' => $row['artist'] ?? '',
            'cover'  => $row['cover'] ?? '',
        ];
    }
}

echo json_encode(['success' => true, 'songs' => $songs], JSON_UNESCAPED_UNICODE);
