<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

// Base path untuk endpoint (root-relative)
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // contoh: /webmusic
$API_UPLOAD = $basePath . '/api/news_upload.php';
?>
<div class="container py-4">
  <h2 class="mb-4 text-warning"><i class="bi bi-upload"></i> Upload Berita</h2>

  <div class="card bg-dark text-light">
    <div class="card-body">
      <form id="newsForm" autocomplete="off" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Acara (Jenis Berita)</label>
          <select name="acara" class="form-select bg-secondary text-white" required>
            <option value="">-- Pilih Acara --</option>
            <option value="Berita Daerah Pagi">Berita Daerah Pagi</option>
            <option value="Berita Daerah Siang">Berita Daerah Siang</option>
            <option value="Berita Sepekan">Berita Sepekan</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Judul Berita (Opsional)</label>
          <input type="text" name="judul" class="form-control bg-secondary text-white"
                 placeholder="Contoh: Laporan Cuaca Hari Ini">
        </div>

        <div class="mb-3">
          <label class="form-label">Laporan Dari</label>
          <input type="text" name="reporter" class="form-control bg-secondary text-white"
                 placeholder="Contoh: Rini / Reporter RRI Palu" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Tanggal Siar (Opsional)</label>
          <input type="date" name="aired_date" class="form-control bg-secondary text-white">
        </div>

        <div class="mb-3">
          <label class="form-label">File Berita (MP3)</label>
          <input type="file" name="audio" accept=".mp3,audio/mpeg" class="form-control bg-secondary text-white" required>
        </div>

        <button type="submit" id="btnUpload" class="btn btn-warning">
          <span class="label"><i class="bi bi-cloud-upload"></i> Upload Berita</span>
          <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Toast container (popup sukses/gagal) -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:1080">
  <div id="toastNews" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastBody">…</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<script>
(() => {
  const form = document.getElementById('newsForm');
  const btn  = document.getElementById('btnUpload');
  const spinner = btn.querySelector('.spinner-border');
  const toastEl = document.getElementById('toastNews');
  const toastBody = document.getElementById('toastBody');
  const API_UPLOAD = '<?= $API_UPLOAD ?>';

  function showToast(msg, ok=true) {
    toastBody.textContent = msg;
    toastEl.classList.toggle('text-bg-success', ok);
    toastEl.classList.toggle('text-bg-danger', !ok);
    const t = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2500 });
    t.show();
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault(); // Jangan reload halaman

    const fd = new FormData(form);
    if (!fd.get('acara') || !fd.get('reporter') || !fd.get('audio').name) {
      showToast('Lengkapi acara, reporter, dan file MP3.', false);
      return;
    }

    // Set status default "draft" otomatis
    fd.append('status', 'draft');

    // Loading UI
    btn.disabled = true;
    spinner.classList.remove('d-none');

    try {
      const res = await fetch(API_UPLOAD, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const data = await res.json().catch(() => ({}));
      if (res.ok && data.success) {
        showToast('Berita berhasil diunggah!');
        form.reset(); // tanpa reload
      } else {
        showToast(data.error || 'Gagal mengunggah berita.', false);
      }
    } catch (err) {
      console.error(err);
      showToast('Kesalahan jaringan.', false);
    } finally {
      btn.disabled = false;
      spinner.classList.add('d-none');
    }
  });
})();
</script>
