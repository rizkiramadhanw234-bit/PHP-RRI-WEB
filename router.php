<?php
// ===== CORS fallback jika .htaccess tidak aktif =====
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page = $_GET['page'] ?? 'songs';
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/* =====================================================
    NORMALISASI ROOT PROJECT
   ===================================================== */
$baseDir = realpath(__DIR__);

// Jika router dijalankan dari dalam .vscode, naik ke parent folder project
if (str_ends_with($baseDir, '.vscode') || basename($baseDir) === '.vscode') {
    $baseDir = dirname($baseDir);
}

/* =====================================================
   DETEKSI SUBFOLDER PROJECT (misal: /rri_music)
   ===================================================== */
$subFolder = '';
if (is_dir($baseDir . '/rri_music')) {
    $subFolder = '/rri_music';
}

/* =====================================================
   BYPASS UNTUK API (FULL FIXED)
   ===================================================== */
if (strpos($uri, '/api/') !== false) {
    // Pastikan kita benar-benar tembak ke lokasi api sebenarnya
    $possible = [
        $baseDir . $uri,
        $baseDir . $subFolder . $uri,
        dirname($baseDir) . $uri,
        dirname($baseDir) . '/rri_music' . $uri
    ];

    $found = null;
    foreach ($possible as $p) {
        if (file_exists($p)) {
            $found = $p;
            break;
        }
    }

    if ($found) {
        // Pastikan output JSON tetap valid
        header('Content-Type: application/json; charset=utf-8');
        include $found;
        exit;
    }

    // Jika tidak ketemu, beri info debugging JSON
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'API not found',
        'checked' => $possible
    ]);
    exit;
}

/* =====================================================
   DEFINISI HALAMAN VIEW
   ===================================================== */
$pages = [
    'songs'           => 'page_songs.php',
    'artist'          => 'page_artist.php',
    'playlist'        => 'page_playlist.php',
    'playlist_detail' => 'playlist_detail.php',
    'profil'          => 'page_profil.php',
    'users'           => 'page_users.php',
    'upload'          => 'upload.php',
    'upload_news'     => 'page_upload_news.php',
    'news_archive'    => 'page_news_archive.php',
    'settings'        => 'page_settings.php',
];

/* =====================================================
   NORMALISASI FOLDER /views
   ===================================================== */
$viewBase = realpath($baseDir . $subFolder . '/views');
if (!$viewBase || !is_dir($viewBase)) {
    http_response_code(500);
    echo "❌ Folder /views tidak ditemukan.<br>Base: <code>" . htmlspecialchars($baseDir . $subFolder) . "</code>";
    exit;
}

/* =====================================================
   VALIDASI PAGE
   ===================================================== */
if (!isset($pages[$page])) {
    http_response_code(404);
    echo "<div class='alert alert-danger text-center p-3'>
            ❌ Halaman tidak dikenal: <code>" . htmlspecialchars($page) . "</code>
          </div>";
    exit;
}

/* =====================================================
   CEK FILE VIEW
   ===================================================== */
$path = realpath($viewBase . DIRECTORY_SEPARATOR . $pages[$page]);
if (!$path || !is_file($path)) {
    http_response_code(404);
    echo "<div class='alert alert-danger text-center p-3'>
            ❌ File tidak ditemukan: <code>" . htmlspecialchars($pages[$page]) . "</code><br>
            Dicari di: <code>" . htmlspecialchars($viewBase) . "</code>
          </div>";
    exit;
}

/* =====================================================
   LOAD FILE VIEW
   ===================================================== */
include $path;
