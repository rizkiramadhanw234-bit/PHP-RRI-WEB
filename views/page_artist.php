<?php
// views/page_artist.php

// Jika diakses langsung (bukan via AJAX), redirect ke app shell agar player tetap hidup
if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // contoh: /webmusic
    $qs   = $_SERVER['QUERY_STRING'] ? ('?' . $_SERVER['QUERY_STRING']) : '';
    header("Location: {$base}/app.php?page=artist{$qs}");
    exit;
}

// Koneksi dari root proyek (bukan relatif ke views/)
require_once __DIR__ . '/../konek.php';

// Base absolut proyek (mis: http://localhost/webmusic)
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$ABS_BASE = $scheme . '://' . $host . $basePath;

// Param: jika ada ?artist=... maka tampilkan lagu milik artist tsb
$artistParam = trim($_GET['artist'] ?? '');

// Helper cover default
function cover_or_default(?string $cover, string $absBase): string {
  $cover = trim((string)$cover);
  if ($cover === '' || strtolower($cover) === 'null') {
    return $absBase . '/uploads/default_cover.png';
  }
  // Jika cover sudah absolut (http/https), langsung pakai
  if (preg_match('#^https?://#i', $cover)) return $cover;
  // Jika relatif, jadikan absolut
  if ($cover[0] === '/') {
    return $absBase . $cover; // mis: /webmusic/uploads/xxx.png
  }
  return $absBase . '/' . ltrim($cover, '/'); // mis: uploads/xxx.png
}

?>
<?php if ($artistParam === ''): ?>
  <!-- ===== Mode 1: Daftar Artist ===== -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-person text-warning"></i> Artists</h2>
  </div>

  <?php
  $artists = [];
  if (isset($conn) && !$conn->connect_error) {
      $sql = "
          SELECT 
              TRIM(COALESCE(artist, 'Unknown')) AS artist,
              COUNT(*) AS total,
              MAX(cover) AS cover
          FROM music
          GROUP BY TRIM(COALESCE(artist, 'Unknown'))
          ORDER BY artist ASC
      ";
      if ($res = $conn->query($sql)) {
          $artists = $res->fetch_all(MYSQLI_ASSOC);
      }
  }
  ?>

  <?php if (empty($artists)): ?>
    <div class="text-muted">Belum ada data artist. Tambahkan lagu dulu di tabel <code>music</code>.</div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
      <?php foreach ($artists as $a):
        $name   = $a['artist'] ?: 'Unknown';
        $total  = (int)$a['total'];
        $coverU = cover_or_default($a['cover'] ?? '', $ABS_BASE);
        // Tautan ke detail artist (tetap di page=artist)
        $detailUrl = $ABS_BASE . '/views/page_artist.php?page=artist&artist=' . urlencode($name);
      ?>
        <div class="col">
          <div class="card bg-dark text-white h-100 shadow-sm">
            <div class="card-body d-flex gap-3">
              <img src="<?= htmlspecialchars($coverU) ?>" alt=""
                   style="width:64px;height:64px;object-fit:cover;border-radius:8px;">
              <div class="flex-grow-1">
                <h5 class="mb-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= htmlspecialchars($name) ?>
                </h5>
                <small class="text-muted"><?= $total ?> lagu</small>
                <div class="mt-3">
                  <!-- ajax-link agar tidak reload penuh -->
                  <a href="<?= htmlspecialchars($detailUrl) ?>" class="btn btn-sm btn-outline-light ajax-link">
                    <i class="bi bi-music-note-list"></i> Lihat Lagu
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <!-- ===== Mode 2: Detail Artist (daftar lagu milik artist tsb) ===== -->
  <?php
  $songs = [];
  if (isset($conn) && !$conn->connect_error) {
      // Match artist persis setelah di-TRIM (konsisten dengan listing)
      $sql = "
  SELECT id, title, COALESCE(artist,'Unknown') AS artist, cover, file_path
  FROM music
  WHERE TRIM(COALESCE(artist,'')) = ?
  ORDER BY id DESC
";

      if ($stmt = $conn->prepare($sql)) {
          $stmt->bind_param('s', $artistParam);
          if ($stmt->execute()) {
              $res = $stmt->get_result();
              $songs = $res->fetch_all(MYSQLI_ASSOC);
          }
          $stmt->close();
      }
  }
  ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <a href="<?= htmlspecialchars($ABS_BASE . '/views/page_artist.php?page=artist') ?>" 
         class="btn btn-sm btn-outline-light ajax-link">
        <i class="bi bi-arrow-left"></i> Kembali
      </a>
      <h2 class="mb-0">
        <i class="bi bi-person text-warning"></i>
        <?= htmlspecialchars($artistParam) ?>
      </h2>
    </div>
    <span class="text-muted small"><?= count($songs) ?> lagu</span>
  </div>

  <?php if (empty($songs)): ?>
    <div class="alert alert-secondary">Belum ada lagu untuk artist ini.</div>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($songs as $s):
        $title   = $s['title'] ?: '(Untitled)';
        $artist  = $s['artist'] ?: 'Unknown';
        $coverU  = cover_or_default($s['cover'] ?? '', $ABS_BASE);
        $src     = $s['file_path']; // diasumsikan sudah bisa diakses oleh audio player
        // Normalisasi src jadi absolut jika relatif:
        if ($src && !preg_match('#^https?://#i', $src)) {
            $src = $ABS_BASE . '/' . ltrim($src, '/');
        }
      ?>
        <div class="list-group-item list-group-item-action bg-dark text-white">
          <div class="d-flex align-items-center gap-3">
            <img src="<?= htmlspecialchars($coverU) ?>" alt="" 
                 style="width:52px;height:52px;border-radius:8px;object-fit:cover;">
            <div class="flex-grow-1">
              <div class="fw-semibold" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($title) ?>
              </div>
              <small class="text-muted"><?= htmlspecialchars($artist) ?></small>
            </div>
            <div class="d-flex gap-2">
              <!-- Tombol Play (opsional: sesuaikan dengan player kamu) -->
              <button 
                class="btn btn-sm btn-outline-light btn-play"
                data-src="<?= htmlspecialchars($src) ?>"
                data-title="<?= htmlspecialchars($title) ?>"
                data-artist="<?= htmlspecialchars($artist) ?>"
                data-cover="<?= htmlspecialchars($coverU) ?>"
              >
                <i class="bi bi-play-fill"></i>
              </button>
              <!-- Jika butuh aksi lain (tambahkan ke playlist, dsb) bisa taruh di sini -->
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <script>
  // Integrasi minimal dengan audio player:
  // - Jika app shell punya window.playSong(detail), kita panggil itu.
  // - Jika tidak ada, kita broadcast CustomEvent 'play-song' yang bisa ditangkap global.
  (function(){
    document.querySelectorAll('.btn-play').forEach(function(btn){
      btn.addEventListener('click', function(){
        const detail = {
          src:    this.dataset.src,
          title:  this.dataset.title,
          artist: this.dataset.artist,
          cover:  this.dataset.cover
        };
        if (typeof window.playSong === 'function') {
          window.playSong(detail);
        } else {
          document.dispatchEvent(new CustomEvent('play-song', { detail }));
        }
      });
    });
  })();
  </script>

<?php endif; ?>
