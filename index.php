<?php
session_start();
require_once __DIR__ . '/konek.php'; 


function ensure_users_table_and_seed(mysqli $conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','editor','viewer') DEFAULT 'admin',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    
    $res = $conn->query("SELECT COUNT(*) AS total FROM users");
    $row = $res->fetch_assoc();
    if ((int)$row['total'] === 0) {
        $username = 'admin';
        $password = 'admin123'; 
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
        $stmt->bind_param('ss', $username, $hash);
        $stmt->execute();
        $stmt->close();
    }
}

if (!isset($conn) || $conn->connect_error) {
    die("Gagal koneksi ke database.");
}
ensure_users_table_and_seed($conn);

// ============================
// Token CSRF dan limit login
// ============================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

if (!isset($_SESSION['login_try'])) $_SESSION['login_try'] = 0;
$lockUntil = $_SESSION['lock_until'] ?? 0;
$now = time();
$locked = $now < $lockUntil;

// ============================
// 3️⃣  Proses login
// ============================
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_in  = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf, $csrf_in)) {
        $error = "Sesi kedaluwarsa. Muat ulang halaman.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username=? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if ($user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['user_role'] = $user['role']; 
            $_SESSION['login_try'] = 0;
            unset($_SESSION['lock_until']);
            header("Location: app.php");
            exit;
        } else {
            $_SESSION['login_try']++;
            if ($_SESSION['login_try'] >= 5) {
                $_SESSION['lock_until'] = time() + 120;
                $locked = true;
            }
            $error = "Username atau password salah!";
        }
    }
} elseif ($locked) {
    $sisa = $lockUntil - $now;
    $error = "Terlalu banyak percobaan. Coba lagi dalam {$sisa} detik.";
}

// Sudah login → arahkan langsung
if (!empty($_SESSION['logged_in'])) {
    header("Location: app.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - RRI WEB MUSIC</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #121212;
      color: #fff;
      font-family: 'Roboto', sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .login-card {
      background-color: #1e1e1e;
      padding: 2rem;
      border-radius: 10px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 0 20px rgba(0,0,0,0.5);
    }
    .login-card h2 {
      margin-bottom: 1.5rem;
      text-align: center;
      font-weight: bold;
    }
    .form-control {
      background-color: #2a2a2a;
      border: none;
      color: #fff;
    }
    .form-control:focus {
      background-color: #2a2a2a;
      border: 1px solid #ff0000;
      box-shadow: none;
      color: #fff;
    }
    .btn-login {
      background-color: #ff0000;
      border: none;
      font-weight: 500;
    }
    .btn-login:hover {
      background-color: #e60000;
    }
    .error {
      color: #ff6b6b;
      font-size: 0.9rem;
      text-align: center;
      margin-top: 1rem;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <h2><i class="bi bi-music-note-beamed"></i> RRI WEB MUSIC</h2>
    <form method="post" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required autofocus>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-login w-100" <?= $locked ? 'disabled' : '' ?>>Login</button>
      <?php if ($error): ?>
        <div class="error mt-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (!$locked && isset($_SESSION['login_try']) && $_SESSION['login_try'] > 0): ?>
        <div class="text-center text-secondary small mt-2">Percobaan: <?= (int)$_SESSION['login_try'] ?>/5</div>
      <?php endif; ?>
      <?php if ($locked): ?>
        <div class="text-center text-warning small mt-2">Akun terkunci sementara.</div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
