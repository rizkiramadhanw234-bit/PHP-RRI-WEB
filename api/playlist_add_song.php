<?php
// 🧹 Bersihkan semua output sebelum kirim JSON
while (ob_get_level()) ob_end_clean();
header_remove();
header('Content-Type: application/json; charset=utf-8');

// api/playlist_add_song.php (gabungan: music lokal + YouTube) — versi fix + fallback playlist
require_once __DIR__ . '/../konek.php';
header('Content-Type: application/json; charset=utf-8');

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success"=>false, "error"=>"DB connection failed"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(["success"=>false, "error"=>"Method Not Allowed"]);
    exit;
}

// Ambil input (x-www-form-urlencoded / JSON)
$ct  = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input');
$in  = [];
if (stripos($ct, 'application/json') !== false) {
    $in = json_decode($raw, true) ?: [];
} else {
    $in = $_POST;
}

// --------- Playlist ID dengan fallback ---------
$playlist_id = intval($in['playlist_id'] ?? 0);
if ($playlist_id <= 0) {
    // fallback: ambil playlist terbaru (aman jika JS lupa kirim)
    $q = $conn->query("SELECT id FROM playlist ORDER BY created_at DESC, id DESC LIMIT 1");
    if ($q && $q->num_rows) {
        $playlist_id = (int)$q->fetch_assoc()['id'];
    }
}
if ($playlist_id <= 0) {
    echo json_encode(["success"=>false, "error"=>"playlist_id wajib diisi"]);
    exit;
}

// Pastikan playlist ada
$chkP = $conn->prepare("SELECT id FROM playlist WHERE id=? LIMIT 1");
$chkP->bind_param("i", $playlist_id);
$chkP->execute();
$chkP->store_result();
if ($chkP->num_rows === 0) {
    echo json_encode(["success"=>false, "error"=>"Playlist tidak ditemukan."]);
    exit;
}
$chkP->close();

/**
 * MODE A: Tambah lagu dari DB lokal (tabel music) ke tabel playlist_songs
 * Input minimal: playlist_id, music_id
 */
$music_id = intval($in['music_id'] ?? 0);

if ($music_id > 0) {
    // Pastikan lagu ada
    $chkM = $conn->prepare("SELECT id, title, artist, cover FROM music WHERE id=?");
    $chkM->bind_param("i", $music_id);
    $chkM->execute();
    $resM = $chkM->get_result();
    if (!$resM || $resM->num_rows === 0) {
        echo json_encode(["success"=>false, "error"=>"Lagu tidak ditemukan."]);
        exit;
    }
    $music = $resM->fetch_assoc(); // title, artist, cover
    $chkM->close();

    // Cek duplikat di playlist_songs
    $dup = $conn->prepare("SELECT 1 FROM playlist_songs WHERE playlist_id=? AND music_id=?");
    $dup->bind_param("ii", $playlist_id, $music_id);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        // idempotent: anggap sukses
        echo json_encode(["success"=>true, "mode"=>"local", "message"=>"Sudah ada di playlist"]);
        exit;
    }
    $dup->close();

    // Insert
    $ins = $conn->prepare("INSERT INTO playlist_songs (playlist_id, music_id) VALUES (?,?)");
    $ins->bind_param("ii", $playlist_id, $music_id);
    if ($ins->execute()) {
        echo json_encode([
            "success"      => true,
            "mode"         => "local",
            "playlist_id"  => $playlist_id,
            "music_id"     => $music_id,
            "title"        => $music['title'],
            "artist"       => $music['artist'],
            "cover"        => $music['cover'] ?? ""
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success"=>false,"error"=>"Gagal menambah lagu (DB)."]);
    }
    exit;
}

/**
 * MODE B: Tambah item YouTube ke tabel playlist_items
 * Input minimal:
 *   playlist_id, source='youtube', video_id, url, title
 *   (opsional: artist, cover)
 */
$source   = trim($in['source']  ?? '');
$video_id = trim($in['video_id']?? '');
$url      = trim($in['url']     ?? '');
$title    = trim($in['title']   ?? '');
$artist   = trim($in['artist']  ?? '');
$cover    = trim($in['cover']   ?? '');
$extra    = $in['extra'] ?? null;

if (strtolower($source) === 'youtube') {
    if ($video_id === '' || $url === '' || $title === '') {
        echo json_encode(["success"=>false, "error"=>"video_id, url, dan title wajib untuk sumber YouTube."]);
        exit;
    }

    // Cek duplikat di playlist_items (unique: playlist_id + video_id)
    $dup2 = $conn->prepare("SELECT 1 FROM playlist_items WHERE playlist_id=? AND video_id=?");
    $dup2->bind_param("is", $playlist_id, $video_id);
    $dup2->execute();
    $dup2->store_result();
    if ($dup2->num_rows > 0) {
        echo json_encode(["success"=>true, "mode"=>"youtube", "message"=>"Sudah ada di playlist"]);
        exit;
    }
    $dup2->close();

    $extraJson = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

    $ins2 = $conn->prepare("
        INSERT INTO playlist_items (playlist_id, source, video_id, url, title, artist, cover, extra)
        VALUES (?, 'youtube', ?, ?, ?, ?, ?, ?)
    ");
    $ins2->bind_param('issssss',
        $playlist_id, $video_id, $url, $title, $artist, $cover, $extraJson
    );

    if ($ins2->execute()) {
        echo json_encode([
            "success"      => true,
            "mode"         => "youtube",
            "playlist_id"  => $playlist_id,
            "item_id"      => $conn->insert_id,
            "video_id"     => $video_id,
            "title"        => $title,
            "artist"       => $artist,
            "cover"        => $cover
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success"=>false, "error"=>"Gagal menambah item YouTube: ".$conn->error]);
    }
    exit;
}

// Jika tidak masuk kedua mode
echo json_encode(["success"=>false,"error"=>"Data tidak lengkap. Kirim 'music_id' (lokal) atau 'source=youtube' beserta 'video_id,url,title'."]);
