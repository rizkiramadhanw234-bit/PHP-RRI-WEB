<?php
// Lindungi agar hanya dimuat via AJAX (SPA)
if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
  $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
  header("Location: {$base}/app.php?page=profil");
  exit;
}

require_once __DIR__ . '/../auth_check.php'; // pastikan login
require_once __DIR__ . '/../konek.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$userId = $_SESSION['user_id'] ?? 0;
$user = null;
$success = '';
$error = '';

// ================================================
// Ambil Data Pengguna
// ================================================
if ($userId > 0 && isset($conn) && !$conn->connect_error) {
  $stmt = $conn->prepare("SELECT id, username, role, created_at FROM users WHERE id=?");
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
} else {
  $error = "Koneksi database gagal atau user belum login.";
}

// ================================================
// Proses Update Profil
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0) {
  $newUser = trim($_POST['username'] ?? '');
  $newPass = $_POST['password'] ?? '';

  if (strlen($newUser) < 3) {
    $error = "Username minimal 3 karakter.";
  } else {
    // Update username + password (jika diisi)
    if (!empty($newPass)) {
      $hash = password_hash($newPass, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE users SET username=?, password_hash=? WHERE id=?");
      $stmt->bind_param('ssi', $newUser, $hash, $userId);
    } else {
      $stmt = $conn->prepare("UPDATE users SET username=? WHERE id=?");
      $stmt->bind_param('si', $newUser, $userId);
    }

    if ($stmt->execute()) {
      $_SESSION['username'] = $newUser;
      $success = "Profil berhasil diperbarui!";
      $user['username'] = $newUser;
    } else {
      $error = "Gagal menyimpan perubahan. " . htmlspecialchars($stmt->error);
    }
    $stmt->close();
  }
}
?>

<div class="container py-4">
  <h2 class="mb-4 text-warning">
    <i class="bi bi-person-circle"></i> Profil Pengguna
  </h2>

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($user): ?>
    <div class="card bg-dark text-light mb-4 shadow">
      <div class="card-body">
        <h5 class="card-title mb-3">
          <i class="bi bi-info-circle"></i> Informasi Akun
        </h5>
        <p><b>Username Saat Ini:</b> <?= htmlspecialchars($user['username']) ?></p>
        <p><b>Role:</b> <span class="badge bg-info text-dark"><?= htmlspecialchars($user['role']) ?></span></p>
        <p><b>Dibuat Pada:</b> <?= htmlspecialchars($user['created_at']) ?></p>
      </div>
    </div>

    <div class="card bg-dark text-light shadow">
      <div class="card-body">
        <h5 class="card-title mb-3">
          <i class="bi bi-pencil-square"></i> Ubah Username / Password
        </h5>
        <form method="post" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">Username Baru</label>
            <input type="text" class="form-control bg-secondary text-white" name="username"
              value="<?= htmlspecialchars($user['username']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password Baru</label>
            <input type="password" class="form-control bg-secondary text-white" name="password"
              placeholder="Biarkan kosong jika tidak ingin mengubah">
          </div>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-save"></i> Simpan Perubahan
          </button>
        </form>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-danger mt-4">Data pengguna tidak ditemukan.</div>
  <?php endif; ?>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    console.log('Profil pengguna siap.');
  });
</script>