<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

// Pastikan hanya admin
if (($_SESSION['role'] ?? '') !== 'admin') {
  echo "<div class='alert alert-danger'>Akses ditolak! Hanya admin yang dapat membuka halaman ini.</div>";
  exit;
}

$success = '';
$error = '';

// ====== Tambah User Baru ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $role = $_POST['role'] ?? 'viewer';

  if (strlen($username) < 3 || strlen($password) < 4) {
    $error = "Username minimal 3 karakter dan password minimal 4 karakter.";
  } else {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
      $error = "Username sudah digunakan.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt2 = $conn->prepare("INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, 1)");
      $stmt2->bind_param('sss', $username, $hash, $role);
      if ($stmt2->execute()) {
        $success = "User baru berhasil ditambahkan!";
      } else {
        $error = "Gagal menambahkan user baru: " . $conn->error;
      }
      $stmt2->close();
    }
    $stmt->close();
  }
}

// ====== Hapus User ======
if (isset($_GET['delete'])) {
  $deleteId = (int) $_GET['delete'];
  if ($deleteId !== ($_SESSION['user_id'] ?? 0)) { // tidak boleh hapus diri sendiri
    $conn->query("DELETE FROM users WHERE id=$deleteId");
    $success = "User berhasil dihapus.";
  } else {
    $error = "Tidak bisa menghapus akun yang sedang login.";
  }
}

// ====== Ambil Semua User ======
$users = [];
$res = $conn->query("SELECT id, username, role, is_active, created_at FROM users ORDER BY id ASC");
if ($res) $users = $res->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-4">
  <h2 class="mb-4 text-warning"><i class="bi bi-people"></i> Manajemen User</h2>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Form tambah user -->
  <div class="card bg-dark text-light mb-4">
    <div class="card-body">
      <h5 class="card-title"><i class="bi bi-person-plus"></i> Tambah User Baru</h5>
      <form id="formAddUser" class="mt-3">
        <div class="row g-2">
          <div class="col-md-4">
            <input type="text" class="form-control bg-secondary text-white" name="username" placeholder="Username" required>
          </div>
          <div class="col-md-4">
            <input type="password" class="form-control bg-secondary text-white" name="password" placeholder="Password" required>
          </div>
          <div class="col-md-3">
            <select name="role" class="form-select bg-secondary text-white">
              <option value="admin">Admin</option>
              <option value="editor">Teknik</option>
              <option value="news">Pemberitaan</option>
              <option value="host">Penyiar</option>
            </select>
          </div>
          <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i></button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabel daftar user -->
  <div class="card bg-dark text-light">
    <div class="card-body">
      <h5 class="card-title"><i class="bi bi-list"></i> Daftar Pengguna</h5>
      <table class="table table-dark table-striped align-middle mt-3">
        <thead>
          <tr>
            <th>#</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Dibuat</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="6" class="text-center text-secondary">Belum ada user.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $i => $u): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($u['role']) ?></span></td>
                <td>
                  <?php if ($u['is_active']): ?>
                    <span class="badge bg-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['created_at']) ?></td>
                <td>
                  <?php if ($u['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-user" data-id="<?= $u['id'] ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php else: ?>
                    <span class="text-muted small">Sedang login</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- === Script tunggal untuk event AJAX === -->
<script>
function initUserPage() {
  // Tambah user
  const form = document.querySelector("#formAddUser");
  if (form) {
    form.addEventListener("submit", async e => {
      e.preventDefault();
      const data = new FormData(form);
      const main = document.querySelector("main") || document.querySelector("#main-content");

      try {
        const res = await fetch("views/page_users.php", {
          method: "POST",
          body: data,
          headers: { "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin"
        });
        const html = await res.text();
        if (main) {
          main.innerHTML = html;
          initUserPage(); // reaktifkan tombol baru
        }
      } catch (err) {
        alert("Gagal menambah user: " + err.message);
      }
    });
  }

  // Hapus user
  document.querySelectorAll(".btn-delete-user").forEach(btn => {
    btn.addEventListener("click", async e => {
      const id = e.target.closest("button").dataset.id;
      if (!confirm("Yakin hapus user ini?")) return;
      const main = document.querySelector("main") || document.querySelector("#main-content");

      try {
        const res = await fetch("views/page_users.php?delete=" + id, {
          method: "GET",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin"
        });
        const html = await res.text();
        if (main) {
          main.innerHTML = html;
          initUserPage(); // reaktifkan tombol setelah reload
        }
      } catch (err) {
        alert("Gagal menghapus user: " + err.message);
      }
    });
  });
}

// Jalankan saat pertama kali dimuat
initUserPage();
</script>
