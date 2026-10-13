<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../auth_check.php';
    require_once __DIR__ . '/../konek.php';

    // Validasi data
    $judul    = trim($_POST['judul'] ?? '');
    $acara    = trim($_POST['acara'] ?? '');
    $reporter = trim($_POST['reporter'] ?? '');
    $status   = trim($_POST['status'] ?? '');
    $aired_in = trim($_POST['aired_date'] ?? '');

    if ($judul === '' || $acara === '' || $status === '') {
        throw new Exception('Kolom wajib belum lengkap.');
    }

    // Normalisasi status
    $statusAllowed = ['published','draft','archived'];
    if (!in_array($status, $statusAllowed, true)) $status = 'draft';

    // Tanggal siar
    $aired_date = null;
    if ($aired_in !== '') {
        $ts = strtotime($aired_in);
        if ($ts === false) throw new Exception('Tanggal siar tidak valid.');
        $aired_date = date('Y-m-d', $ts);
    }

    // Validasi file
    if (!isset($_FILES['audio'])) throw new Exception('File audio belum dipilih.');
    $f = $_FILES['audio'];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error kode: ' . $f['error']);

    // Pastikan folder upload tersedia
    $root = dirname(__DIR__);
    $uploadDir = $root . '/uploads/news';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Nama file unik
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) ?: 'mp3';
    $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . '/' . $fname;
    if (!move_uploaded_file($f['tmp_name'], $target)) throw new Exception('Gagal menyimpan file audio.');

    $filePath = 'uploads/news/' . $fname;
    $now = date('Y-m-d H:i:s');
    $uploader = $_SESSION['user_id'] ?? null;

    // Simpan ke DB
    $sql = "INSERT INTO news (judul, acara, reporter, file_path, status, aired_date, created_at, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssi', $judul, $acara, $reporter, $filePath, $status, $aired_date, $now, $uploader);
    if (!$stmt->execute()) throw new Exception('DB error: ' . $stmt->error);

    echo json_encode([
        'success' => true,
        'id' => $stmt->insert_id,
        'title' => $judul,
        'file' => $filePath
    ]);
    exit;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
