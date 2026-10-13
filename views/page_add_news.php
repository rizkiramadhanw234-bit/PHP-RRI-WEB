<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

// Base absolut proyek (mis: http://localhost/webmusic)
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // /webmusic
$ABS_BASE = $scheme . '://' . $host . $basePath;
?>

<div class="container py-4">
  <h2 class="mb-4 text-warning"><i class="bi bi-upload"></i> Upload Berita Baru</h2>

  <form id="newsUploadForm" enctype="multipart/form-data" class="card bg-dark text-light p-4">
    <div class="mb-3">
      <label class="form-label">Judul Berita</label>
      <input type="text" name="judul" class="form-control bg-secondary text-white" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Reporter</label>
      <input type="text" name="reporter" class="form-control bg-secondary text-white" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Acara</label>
      <select name="acara" class="form-select bg-secondary text-white" required>
        <option value="Berita Daerah Pagi">Berita Daerah Pagi</option>
        <option value="Berita Daerah Siang">Berita Daerah Siang</option>
        <option value="Berita Sepekan">Berita Sepekan</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Tanggal Siar</label>
      <input type="date" name="aired_date" class="form-control bg-secondary text-white" required>
    </div>

    <div class="mb-3">
      <label class="form-label">File Audio (MP3)</label>
      <input type="file" name="file_audio" accept=".mp3,audio/*" class="form-control bg-secondary text-white" required>
      <div class="form-text text-muted">Format yang diperbolehkan: MP3</div>
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-success"><i class="bi bi-cloud-arrow-up"></i> Upload</button>
    </div>
  </form>
</div>

<script>
const API_UPLOAD_NEWS = '<?= $ABS_BASE ?>/api/upload_news.php';

document.getElementById('newsUploadForm').addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Mengunggah...';

  try {
    const res = await fetch(API_UPLOAD_NEWS, { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      alert('✅ Berita berhasil diunggah!');
      form.reset();
    } else {
      alert('❌ Gagal upload: ' + (data.error || 'Terjadi kesalahan tak dikenal.'));
    }
  } catch (err) {
    alert('⚠️ Gagal menghubungi server.\n' + err.message);
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-cloud-arrow-up"></i> Upload';
});
</script>
