<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'viewer';
?>

<div class="container py-4">
  <h2 class="mb-4 text-warning"><i class="bi bi-gear"></i> Pengaturan Akun</h2>

  <div class="card bg-dark text-light mb-4">
    <div class="card-body">
      <h5 class="card-title"><i class="bi bi-person-circle"></i> Informasi Akun</h5>
      <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
      <p><strong>Role:</strong> <span class="badge bg-info text-dark"><?= htmlspecialchars($role) ?></span></p>
    </div>
  </div>

  <div class="card bg-dark text-light">
    <div class="card-body">
      <h5 class="card-title"><i class="bi bi-box-arrow-right"></i> Keluar</h5>
      <p class="text-secondary">Klik tombol di bawah untuk keluar dari akun Anda dengan aman.</p>
      <button id="btnLogout" class="btn btn-danger">
        <i class="bi bi-door-open"></i> Logout
      </button>
    </div>
  </div>
</div>

<script>
document.querySelector("#btnLogout")?.addEventListener("click", async () => {
  if (!confirm("Yakin ingin logout?")) return;

  try {
    const res = await fetch("views/logout.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      credentials: "same-origin"
    });

    const data = await res.json();

    if (data.success) {
      alert("Anda telah logout.");
      window.location.href = "index.php";
    } else {
      alert("Gagal logout: " + (data.error || "Terjadi kesalahan"));
    }
  } catch (err) {
    alert("Error logout: " + err.message);
  }
});
</script>
