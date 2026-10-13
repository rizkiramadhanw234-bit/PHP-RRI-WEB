<?php
// playlist_actions.php
header('Content-Type: application/json; charset=utf-8');
include "konek.php";

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function render_playlists_html($conn){
    $res = $conn->query("SELECT * FROM playlist ORDER BY id DESC");
    $html = '';
    while($row = $res->fetch_assoc()){
        $id = (int)$row['id'];
        $name = htmlspecialchars($row['name']);
        $html .= "<div class=\"col\" id=\"playlist-{$id}\">";
        $html .= "<div class=\"card bg-dark text-white shadow-sm h-100\"><div class=\"card-body d-flex flex-column\">";
        $html .= "<h5 class=\"card-title\">{$name}</h5>";
        $html .= "<div class=\"mt-auto d-flex justify-content-between\">";
        $html .= "<a href=\"playlist_detail.php?id={$id}\" class=\"btn btn-sm btn-outline-light ajax-link\"><i class=\"bi bi-folder2-open\"></i> Buka</a>";
        $html .= "<button type=\"button\" class=\"btn btn-sm btn-outline-danger hapusBtn\" data-id=\"{$id}\"><i class=\"bi bi-trash\"></i> Hapus</button>";
        $html .= "</div></div></div></div>";
    }
    return $html;
}

if ($action === 'create') {
    $name = trim($_POST['nama_playlist'] ?? '');
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Nama playlist kosong.']); exit;
    }
    $stmt = $conn->prepare("INSERT INTO playlist (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();
    $html = render_playlists_html($conn);
    echo json_encode(['success' => true, 'html' => $html]); exit;
}

if ($action === 'delete') {
    $id = intval($_POST['playlist_id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalid.']); exit;
    }
    $stmt = $conn->prepare("DELETE FROM playlist WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $html = render_playlists_html($conn);
    echo json_encode(['success' => true, 'html' => $html]); exit;
}

// default: return current list
$html = render_playlists_html($conn);
echo json_encode(['success' => true, 'html' => $html]);
exit;
