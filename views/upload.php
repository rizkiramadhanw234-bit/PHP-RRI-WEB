<?php
// views/upload.php  (SPA partial + cover opsional)
if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {

  $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
  header("Location: {$base}/app.php?page=upload");
  exit;
}

require_once __DIR__ . '/../konek.php';
// ==========================
// akses (Admin Only)
// ==========================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger py-2">
      <i class="bi bi-shield-lock"></i> Akses ditolak. Hanya admin yang bisa mengupload lagu.
    </div>';
    exit;
}


// ---- Handle submit (AJAX form atau normal post dari partial) ----
$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title  = trim($_POST['title'] ?? '');
  $artist = trim($_POST['artist'] ?? '');

  if ($title === '' || $artist === '') {
    $err = 'Judul dan Artist wajib diisi.';
  } else {
    // Pastikan folder uploads ada
    $uploadDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

    // Base URL (otomatis dari host) — kalau mau hardcode, silakan
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseURL = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';

    // Cover (opsional)

    // Cover (opsional) + validasi ukuran/ekstensi
    $coverPath = null;
    if (!empty($_FILES['cover']['name'])) {
      if ($_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['cover']['error'] === UPLOAD_ERR_OK) {
          $maxCover = 2 * 1024 * 1024; // 2MB
          $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
          $tmp = $_FILES['cover']['tmp_name'];
          $mime = @mime_content_type($tmp) ?: '';
          $okExt = in_array($ext, ['png','jpg','jpeg','webp'], true);
          $okMime = preg_match('#^image/(png|jpe?g|webp)$#', $mime);

          if ($_FILES['cover']['size'] > $maxCover) {
            $err = 'Ukuran cover melebihi 2MB.';
          } elseif (!$okExt || !$okMime) {
            $err = 'Cover harus PNG/JPG/WEBP.';
          } else {
            $file = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . '/' . $file;
            if (move_uploaded_file($tmp, $dest)) {
              $coverPath = $baseURL . 'uploads/' . $file;
            } else {
              $err = 'Gagal menyimpan file cover.';
            }
          }
        } else {
          // Map error bawaan PHP ke pesan ramah
          $map = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran cover melebihi batas server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran cover melebihi batas form.',
            UPLOAD_ERR_PARTIAL    => 'Upload cover tidak lengkap.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder tmp server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file cover.',
            UPLOAD_ERR_EXTENSION  => 'Upload cover diblokir ekstensi PHP.'
          ];
          $err = $map[$_FILES['cover']['error']] ?? 'Terjadi kesalahan saat upload cover.';
        }
      }
    }
// Jika tidak upload cover, pakai default
if (empty($coverPath)) {
    $defaultCover = $uploadDir . '/default_cover.png';
    if (file_exists($defaultCover)) {
        // gunakan URL ke default cover
        $coverPath = $baseURL . 'uploads/default_cover.png';
    } else {
        // fallback: tetap null tapi tulis log
        error_log("Default cover tidak ditemukan di: " . $defaultCover);
        $coverPath = null;
    }
}

    // Lagu (wajib MP3) + tangani berbagai error umum
    $songPath = null;
    if (empty($err)) { // hanya lanjut jika tidak ada error cover
      if (!empty($_FILES['song']['name'])) {
        if ($_FILES['song']['error'] === UPLOAD_ERR_OK) {
          $tmp = $_FILES['song']['tmp_name'];
          $name = $_FILES['song']['name'];
          $size = (int)$_FILES['song']['size'];
          $maxSong = 50 * 1024 * 1024; // 50MB, atur sesuai kebutuhan

          if ($size > $maxSong) {
            $err = 'Ukuran lagu melebihi 50MB.';
          } else {
            $mime = @mime_content_type($tmp) ?: '';
            $isMp3ByMime = stripos($mime, 'audio/mpeg') !== false or stripos($mime, 'audio/mp3') !== false;
            $isMp3ByExt  = preg_match('/\\.mp3$/i', $name);
            // Beberapa server mengembalikan application/octet-stream → fallback ke ekstensi
            if ($isMp3ByMime or $isMp3ByExt) {
              $ext  = 'mp3';
              $file = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
              $dest = $uploadDir . '/' . $file;
              if (move_uploaded_file($tmp, $dest)) {
                $songPath = $baseURL . 'uploads/' . $file;
              } else {
                $err = 'Gagal memindahkan file lagu (cek permission folder uploads).';
              }
            } else {
              $err = 'Hanya file MP3 yang diperbolehkan.';
            }
          }
        } else {
          $map = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran lagu melebihi batas server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran lagu melebihi batas form (MAX_FILE_SIZE).',
            UPLOAD_ERR_PARTIAL    => 'Upload lagu tidak lengkap.',
            UPLOAD_ERR_NO_FILE    => 'File lagu tidak terkirim.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder tmp server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file lagu.',
            UPLOAD_ERR_EXTENSION  => 'Upload diblokir ekstensi PHP.'
          ];
          $err = $map[$_FILES['song']['error']] ?? 'Terjadi kesalahan saat upload lagu.';
        }
      } else {
        $err = 'File lagu (MP3) wajib diupload.';
      }
    }

    // Jika baseURL gagal terdeteksi (mis. saat CLI), fallback relatif
    if (!$err) {
      if (!$baseURL or !preg_match('#^https?://#i', $baseURL)) {
        $coverPath = $coverPath ? ('uploads/' . basename($coverPath)) : null;
        $songPath  = $songPath  ? ('uploads/' . basename($songPath))  : null;
      }
    }

    // Simpan DB
    if (!$err) {
      $stmt = $conn->prepare("INSERT INTO music (title, artist, cover, file_path) VALUES (?,?,?,?)");
      $stmt->bind_param("ssss", $title, $artist, $coverPath, $songPath);
      if ($stmt->execute()) {
        $msg = 'Lagu berhasil diupload.';
      } else {
        $err = 'Gagal menyimpan ke database.';
      }
      $stmt->close();
    }
  }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><i class="bi bi-upload text-danger"></i> Upload Lagu</h2>
  <a href="views/page_songs.php?page=songs" class="btn btn-secondary ajax-link">
    <i class="bi bi-arrow-left"></i> Kembali ke All Songs
  </a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<form id="uploadForm" method="post" enctype="multipart/form-data" class="mt-3">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Judul Lagu</label>
      <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Artist</label>
      <input type="text" name="artist" class="form-control bg-dark text-white border-secondary" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Cover (opsional)</label>
      <input type="file" name="cover" accept="image/*" class="form-control bg-dark text-white border-secondary">
      <div class="form-text text-secondary">Boleh dikosongkan.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">File Lagu (MP3) <span class="text-danger">*</span></label>
      <input type="file" name="song" accept="audio/mp3,audio/mpeg" class="form-control bg-dark text-white border-secondary" required>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-danger">
      <i class="bi bi-cloud-arrow-up"></i> Upload
    </button>
    <button type="reset" class="btn btn-outline-light">Reset</button>
  </div>
</form>

<script>
// (opsional) submit via AJAX agar tetap di halaman ini tanpa reload
document.getElementById('uploadForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.currentTarget;
  const fd = new FormData(form);
  const btn = form.querySelector('button[type="submit"]');
  const old = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengunggah...';

  try {
    const res = await fetch(window.location.protocol + '//' + window.location.host + '/' + 'views/upload.php?page=upload', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With':'XMLHttpRequest' }, // penting!
      credentials: 'same-origin'
    });
    const html = await res.text();
    // render ulang partial (biar pesan sukses/error muncul)
    const main = document.getElementById('main-content');
    if (main) {
      main.innerHTML = html;
      // jalankan kembali script di partial (jika ada)
      if (typeof runScripts === 'function') runScripts(main);
    }
    // refresh daftar lagu di All Songs (opsional)
    if (typeof window.loadPage === 'function') {
      // muat ulang halaman songs secara diam-diam bila kamu mau
      // window.loadPage('views/page_songs.php?page=songs', true);
    }
  } catch (err) {
    alert('Gagal upload: ' + err.message);
  } finally {
    btn.disabled = false; btn.innerHTML = old;
  }
});
</script>
