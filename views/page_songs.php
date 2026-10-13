<?php
// Lindungi agar hanya dimuat via AJAX (SPA)
if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
  $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
  header("Location: {$base}/app.php?page=songs");
  exit;
}

require_once __DIR__ . '/../konek.php';
// ================================
// CEK SESSION ROLE (admin only delete)
// ================================
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';


// ================================
// AMBIL DATA LAGU
// ================================
$songs = [];
if (isset($conn) && !$conn->connect_error) {
  $res = $conn->query("SELECT id, title, artist, file_path, cover FROM music ORDER BY id DESC");
  if ($res) {
    $songs = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
  }
}

// ================================
// PATH DASAR & URL ABSOLUT
// ================================
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$ABS_BASE = $scheme . '://' . $host . $basePath;

// ================================
// FUNGSI COVER DEFAULT
// ================================
function cover_or_default(?string $cover, string $absBase): string
{
  $cover = trim((string) $cover);
  if ($cover === '' || strtolower($cover) === 'null') {
    return $absBase . '/uploads/default_cover.png';
  }
  if (preg_match('#^https?://#i', $cover)) {
    return $cover;
  }
  if ($cover[0] === '/') {
    return $absBase . $cover;
  }
  return $absBase . '/' . ltrim($cover, '/');
}
?>

<style>
  .btn-xxs {
    padding: .15rem .4rem;
    font-size: .75rem;
    line-height: 1;
    border-radius: .35rem;
  }

  .col-aksi {
    width: 140px;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><i class="bi bi-music-note-list text-info"></i> All Songs</h2>
</div>

<div class="putar d-flex justify-content-between align-items-center mb-0 mt-10" style="padding-bottom: 1rem;">
  <h5 class="mb-0 text-light">Daftar Lagu</h5>
  <button id="btnPlayAll" class="btn btn-success btn-sm" style="border:0;">
    <i class="bi bi-play-fill"></i> Putar Semua
  </button>
</div>

<table class="table table-dark table-hover song-table align-middle">
  <thead>
    <tr>
      <th style="width:56px;">No</th>
      <th>Title</th>
      <th>Artist</th>
      <th class="col-aksi text-center">Aksi</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($songs)): ?>
      <tr>
        <td colspan="4" class="text-muted">Belum ada lagu di tabel <code>music</code>.</td>
      </tr>
    <?php else: ?>
      <?php foreach ($songs as $i => $s):
        $id = (int) ($s['id'] ?? 0);
        $title = htmlspecialchars($s['title'] ?? 'Unknown', ENT_QUOTES);
        $artist = htmlspecialchars($s['artist'] ?? 'Unknown', ENT_QUOTES);
        $coverU = htmlspecialchars(cover_or_default($s['cover'] ?? '', $ABS_BASE), ENT_QUOTES);
        $src = htmlspecialchars($ABS_BASE . '/api/stream.php?id=' . $id, ENT_QUOTES);
        ?>
        <tr class="song-row" data-id="<?= $id ?>" data-index="<?= $i ?>" data-src="<?= $src ?>" data-cover="<?= $coverU ?>"
          data-title="<?= $title ?>" data-artist="<?= $artist ?>">
          <td><?= $i + 1 ?></td>
          <td>
            <img src="<?= $coverU ?>" alt="cover"
              style="width:40px;height:40px;object-fit:cover;border-radius:6px;margin-right:.5rem;">
            <?= $title ?>
          </td>
          <td><?= $artist ?></td>
          <td class="text-center">
            <div class="d-inline-flex gap-2">
              <button class="btn btn-xxs btn-outline-light play-btn-row" title="Putar">
                <i class="bi bi-play-fill"></i> Play
              </button>

              <?php if ($is_admin): ?>
                <button class="btn btn-xxs btn-danger btn-delete" title="Hapus lagu ini">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              <?php endif; ?>
            </div>
          </td>

        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<script>
  const API_DELETE = '<?= addslashes($basePath) ?>/api/delete_music.php';

  // =======================================
  // PLAY ALL
  // =======================================
  function playAllSongs() {
    const rows = Array.from(document.querySelectorAll('.song-row'));
    if (rows.length === 0) {
      alert('Tidak ada lagu untuk diputar.');
      return;
    }

    let current = 0;

    function playNext() {
      if (current >= rows.length) {
        console.log('Semua lagu telah selesai diputar.');
        return;
      }
      const tr = rows[current];
      const detail = {
        src: tr.dataset.src,
        title: tr.dataset.title,
        artist: tr.dataset.artist,
        cover: tr.dataset.cover
      };

      if (typeof window.playSong === 'function') {
        window.playSong(detail);
      } else {
        document.dispatchEvent(new CustomEvent('play-song', { detail }));
      }
    }

    document.removeEventListener('ended-song', handleEnded);
    function handleEnded() {
      current++;
      playNext();
    }
    document.addEventListener('ended-song', handleEnded);
    playNext();
  }

  document.getElementById('btnPlayAll')?.addEventListener('click', e => {
    e.preventDefault();
    playAllSongs();
  });

  // =======================================
  // PLAY SATU LAGU
  // =======================================
  document.querySelectorAll('.play-btn-row').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      const tr = btn.closest('tr.song-row');
      if (!tr) return;
      const detail = {
        src: tr.dataset.src,
        title: tr.dataset.title,
        artist: tr.dataset.artist,
        cover: tr.dataset.cover
      };
      if (typeof window.playSong === 'function') {
        window.playSong(detail);
      } else {
        document.dispatchEvent(new CustomEvent('play-song', { detail }));
      }
    });
  });

  // =======================================
  // HAPUS LAGU (delegated handler — SPA safe)
  // =======================================
  document.removeEventListener('click', handleDeleteClick); // hapus jika ada handler lama

  async function handleDeleteClick(e) {
    const btn = e.target.closest('.btn-delete');
    if (!btn) return; // bukan klik hapus

    e.preventDefault();
    e.stopPropagation();

    const tr = btn.closest('tr.song-row');
    if (!tr) return;
    const id = tr.dataset.id;
    const title = tr.dataset.title || 'lagu ini';

    if (!confirm(`Yakin hapus "${title}"?`)) return;

    try {
      const res = await fetch(API_DELETE, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: new URLSearchParams({ id })
      });

      const data = await res.json().catch(() => ({}));

      if (data && data.success) {
        // Jika baris masih ada, hapus dan update nomor urut
        if (tr.parentElement) tr.remove();

        document.querySelectorAll('.song-table tbody tr').forEach((row, i) => {
          const td = row.querySelector('td:first-child');
          if (td) td.textContent = i + 1;
        });

        // Jika ada mekanisme "putar semua" yang sedang berjalan, pastikan indeks disesuaikan
        if (window._playAllState && window._playAllState.playing) {
          const idxRemoved = Number(tr.dataset.index);
          if (!Number.isNaN(idxRemoved)) {
            if (idxRemoved <= window._playAllState.current && window._playAllState.current > 0) {
              window._playAllState.current--;
            }
            // juga update rows array (jika disimpan)
            if (Array.isArray(window._playAllState.rows)) {
              window._playAllState.rows = Array.from(document.querySelectorAll('.song-row'));
            }
          }
        }

      } else {
        alert((data && data.error) ? data.error : 'Gagal menghapus lagu.');
      }
    } catch (err) {
      console.error('Delete error:', err);
      alert('Terjadi kesalahan: ' + (err && err.message ? err.message : err));
    }
  }

  document.addEventListener('click', handleDeleteClick);
  // Deteksi apakah player global sudah ada
  function ensurePlayerReady() {
    if (typeof window.playSong !== 'function') {
      console.warn('Player global belum siap. Menunggu...');
      setTimeout(ensurePlayerReady, 500);
      return;
    }
    console.log(' Player siap digunakan di halaman Songs.');
  }
  ensurePlayerReady();

  // Pastikan event "play-all" tetap bisa dijalankan setelah load AJAX
  document.removeEventListener('click', handlePlayAllClick);

  function handlePlayAllClick(e) {
    if (e.target.closest('#btnPlayAll')) {
      e.preventDefault();
      playAllSongs();
    }
  }

  document.addEventListener('click', handlePlayAllClick);

</script>