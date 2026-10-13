<?php
// ====== DEBUG ON (wajib saat testing) ======
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ==========================================

require_once __DIR__ . '/../konek.php';
header('Content-Type: application/json');

try {
    // Baca input JSON dari frontend
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $playlist_id = intval($input['playlist_id'] ?? 0);
    $new_order = $input['new_order'] ?? [];

    if ($playlist_id <= 0 || !is_array($new_order) || empty($new_order)) {
        throw new Exception('Invalid parameters');
    }

    // Mulai transaksi
    $conn->begin_transaction();

    $position = 1;
    foreach ($new_order as $item) {
        $row_id = intval($item['row_id'] ?? 0);
        $type = $item['type'] ?? '';

        if ($row_id <= 0 || !$type) continue;

        switch ($type) {
            case 'lokal':
                $query = "UPDATE playlist_songs SET sort_order=? WHERE id=? AND playlist_id=?";
                break;
            case 'youtube':
                $query = "UPDATE playlist_items SET sort_order=? WHERE id=? AND playlist_id=?";
                break;
            case 'news':
                $query = "UPDATE playlist_news SET sort_order=? WHERE id=? AND playlist_id=?";
                break;
            default:
                continue 2; // skip kalau tipe tidak dikenal
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $position, $row_id, $playlist_id);
        $stmt->execute();

        // Debug log ke server (cek di error_log)
        error_log("Updated type=$type id=$row_id playlist=$playlist_id sort=$position affected={$stmt->affected_rows}");

        $stmt->close();
        $position++;
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Playlist order updated successfully!'
    ]);
} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
