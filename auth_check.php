<?php
// auth_check.php 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  endpoint API / AJAX
$isApi = (
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) ||
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
);

// Cek login
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized - please login first']);
        exit;
    } else {
        header("Location: /rri_music/index.php");
        exit;
    }
}

// Cegah user nonaktif
if (isset($_SESSION['is_active']) && !$_SESSION['is_active']) {
    session_destroy();

    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account deactivated']);
        exit;
    } else {
        header("Location: /rri_music/index.php");
        exit;
    }
}

// Tambahkan default role jika belum ada
if (empty($_SESSION['role'])) {
    $_SESSION['role'] = 'viewer';
}
?>
