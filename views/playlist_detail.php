<?php
// views/playlist_detail.php (gabungan lokal + YouTube + search)

// 🚫 Guard: Hanya izinkan akses AJAX
if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
  $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
  header("Location: {$base}/app.php?page=playlist");
  exit;
}

require_once __DIR__ . '/../konek.php';

$playlist_id = intval($_GET['id'] ?? 0);
if ($playlist_id <= 0) {
  echo "<div class='alert alert-danger'>Playlist tidak ditemukan.</div>";
  exit;
}

// Ambil playlist
$q = $conn->prepare("SELECT id, name, created_at FROM playlist WHERE id=?");
$q->bind_param("i", $playlist_id);
$q->execute();
$res = $q->get_result();
if (!$res || !$res->num_rows) {
  echo "<div class='alert alert-warning'>Playlist tidak ditemukan.</div>";
  exit;
}
$playlist = $res->fetch_assoc();

// ABS base untuk default cover (dipindahkan ke atas agar bisa digunakan segera)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$ABS_BASE = $scheme . '://' . $host . $basePath;
$DEFAULT_COVER = $ABS_BASE . '/uploads/default_cover.png';

// --- PENGAMBILAN SEMUA ITEM PLAYLIST ---

// 1. Lagu lokal (playlist_songs)
$stmt = $conn->prepare("
    SELECT
        ps.id    AS ps_id,
        m.id     AS music_id,
        m.title, m.artist, m.cover, m.file_path,
      ps.sort_order
    FROM playlist_songs ps
    JOIN music m ON ps.music_id = m.id
    WHERE ps.playlist_id = ?
    ORDER BY ps.sort_order DESC
");
$stmt->bind_param("i", $playlist_id);
$stmt->execute();
$resSongs = $stmt->get_result();
$songs = $resSongs ? $resSongs->fetch_all(MYSQLI_ASSOC) : [];

// 2. Item YouTube (playlist_items)
$yt = $conn->prepare("
    SELECT id AS item_id, video_id, url, title, artist, cover,
    sort_order
    FROM playlist_items
    WHERE playlist_id=? AND source='youtube'
    ORDER BY sort_order DESC
");
$yt->bind_param("i", $playlist_id);
$yt->execute();
$resYt = $yt->get_result();
$ytItems = $resYt ? $resYt->fetch_all(MYSQLI_ASSOC) : [];

// 3. Item News (playlist_news)
$news = $conn->prepare("
    SELECT
        pn.id AS pn_id,
        n.id AS news_id,
        n.judul AS title,
        n.reporter AS artist,
        n.file_path,
        n.acara,
        pn.sort_order
    FROM playlist_news pn
    JOIN news n ON pn.news_id = n.id
    WHERE pn.playlist_id = ?
    ORDER BY pn.sort_order DESC
");
$news->bind_param("i", $playlist_id);
$news->execute();
$resNews = $news->get_result();
$newsItems = $resNews ? $resNews->fetch_all(MYSQLI_ASSOC) : [];

// --- GABUNGKAN SEMUA ITEM KE SATU ARRAY ---
$playlistItems = [];

// Tambahkan lagu lokal
foreach ($songs as $s) {
  $playlistItems[] = [
    'row_id' => (int) $s['ps_id'],     // ID di tabel junction (ps.id)
    'type' => 'lokal',
    'content_id' => (int) $s['music_id'],  // ID konten (music.id)
    'title' => $s['title'],
    'artist' => $s['artist'],
    'cover' => $s['cover'],
    'file_path' => $s['file_path'],
    'sort_order' => $s['sort_order'],

  ];
}

// Tambahkan YouTube
foreach ($ytItems as $y) {
  $playlistItems[] = [
    'row_id' => (int) $y['item_id'],  // ID di tabel junction (item.id)
    'type' => 'youtube',
    'content_id' => (int) $y['item_id'],  // ID konten (item.id)
    'video_id' => $y['video_id'],
    'title' => $y['title'],
    'artist' => $y['artist'],
    'cover' => $y['cover'], // Ini biasanya URL YouTube
    'sort_order' => $y['sort_order'],

  ];
}

// Tambahkan News
foreach ($newsItems as $n) {
  $playlistItems[] = [
    'row_id' => (int) $n['pn_id'],    // ID di tabel junction (pn.id)
    'type' => 'news',
    'content_id' => (int) $n['news_id'],  // ID konten (news.id)
    'title' => $n['title'],
    'artist' => $n['artist'] ?? $n['acara'], // Gunakan acara jika reporter kosong
    'acara' => $n['acara'],
    'file_path' => $n['file_path'],
    'sort_order' => $n['sort_order'],
  ];
}
// echo '<pre>';

// var_dump($playlistItems);
// echo '</pre>';
// Sortir item sesuai urutan pengambilan (DESC)
usort($playlistItems, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);


// Semua lagu untuk dropdown tambah (lokal)
$all = $conn->query("SELECT id, title, artist FROM music ORDER BY title ASC");
$allSongs = $all ? $all->fetch_all(MYSQLI_ASSOC) : [];

?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0"><i class="bi bi-folder2-open text-warning"></i> <?= htmlspecialchars($playlist['name']) ?></h2>
  <a href="?page=playlist" class="btn btn-secondary ajax-link">
    <i class="bi bi-arrow-left"></i> Kembali
  </a>
</div>

<form id="addSongForm" class="mb-3" method="post" action="api/playlist_add_song.php">
  <input type="hidden" name="playlist_id" value="<?= $playlist_id ?>">
  <div class="input-group">
    <select name="music_id" class="form-select" required>
      <option value="">-- Pilih lagu (lokal) --</option>
      <?php foreach ($allSongs as $s): ?>
        <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['title'] . ' - ' . $s['artist']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-success" type="submit"><i class="bi bi-plus-circle"></i> Tambah</button>
  </div>
  <div class="form-text text-secondary">Untuk menambah video YouTube/Berita, gunakan tombol <b>+ Playlist</b> dari hasil
    pencarian.</div>
</form>

<div class="card bg-dark text-light mb-3">
  <div class="card-body py-3">
    <form id="playlistSearchForm" class="row g-2 align-items-center" onsubmit="return false;">
      <div class="col-md-6">
        <div class="input-group">
          <span class="input-group-text bg-secondary border-0 text-white"><i class="bi bi-search"></i></span>
          <input id="playlistSearchInput" type="text" class="form-control bg-secondary text-white border-0"
            placeholder="Cari judul / artis / sumber (YouTube/Lokal/News)…">
          <button id="playlistSearchClear" type="button" class="btn btn-outline-light">Bersihkan</button>
        </div>
      </div>
      <div class="col-md-6 text-end small">
        <span id="playlistSearchInfo" class="text-secondary">Tampilkan semua item</span>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-0 mt-10" style="margin-top: 1rem;">
        <h5 class="mb-0 text-light">Daftar Lagu</h5>
        <button id="btnPlayAll" class="btn btn-primary btn-sm" style="background-color: green; border: 0;">
          <i class="bi bi-play-fill"></i> Putar Semua
        </button>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-dark table-hover" id="playlist-table">
    <thead>
      <tr>
        <th style="width: 40px;" class="drag-handle-header"></th>
        <th style="width: 40px;">#</th>
        <th style="width: 50px;">Cover</th>
        <th>Title & Artist</th>
        <th>Sumber</th>
        <th style="width: 200px;">Action</th>
      </tr>
    </thead>
    <tbody id="playlistItemContainer">
      <?php if (empty($playlistItems)): ?>
        <tr class="row-empty">
          <td colspan="6" class="text-white text-center">Belum ada item dalam playlist ini.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($playlistItems as $index => $item):
          $isLocal = $item['type'] === 'lokal';
          $isYt = $item['type'] === 'youtube';
          $isNews = $item['type'] === 'news';

          $rowClass = "playlist-item " . ($isLocal ? 'song-row' : ($isYt ? 'yt-row' : 'news-row'));

          // Menentukan path untuk data-cover dan data-src
          $coverPath = $DEFAULT_COVER;
          if ($isLocal && $item['cover']) {
            $coverPath = $item['cover'];
          } elseif ($isYt && $item['cover']) {
            $coverPath = $item['cover']; // URL YouTube sudah full
          }

          $filePath = '';
          if ($isLocal) {
            $filePath = $ABS_BASE . '/api/stream.php?id=' . $item['content_id'];
          } elseif ($isNews && $item['file_path']) {
            $filePath = $ABS_BASE . '/' . $item['file_path'];
          }

          // Data-Attributes untuk JS Player/Filter/Hapus
          $dataAttributes = [
            'row-id' => (int) $item['row_id'],     // ID Baris di tabel junction (untuk Hapus)
            'content-id' => (int) $item['content_id'], // ID Konten (music.id/news.id/item.id)
            'source' => $item['type'],
            'title' => htmlspecialchars($item['title'] ?? ''),
            'artist' => htmlspecialchars($item['artist'] ?? ''),
            'cover' => $coverPath,
            'src' => $filePath, // URL Streaming (Lokal/News)
            'video-id' => $isYt ? ($item['video_id'] ?? '') : '', // ID Video YouTube
          ];
          $dataString = implode(' ', array_map(fn($k, $v) => "data-{$k}=\"{$v}\"", array_keys($dataAttributes), array_values($dataAttributes)));

          $badgeClass = $isLocal ? 'bg-success' : ($isYt ? 'bg-danger' : 'bg-info');
          $badgeText = $isLocal ? 'Lokal' : ($isYt ? 'YouTube' : 'News');
          ?>
          <tr class="<?= $rowClass ?>" <?= $dataString ?>>
            <td class="drag-handle" style="cursor: move;">☰</td>
            <td class="row-number"><?= $index + 1 ?></td>
            <td>
              <img src="<?= $coverPath ?>" alt="Cover" width="40" height="40" style="object-fit:cover;border-radius:6px;">
            </td>
            <td>
              <b><?= htmlspecialchars($item['title'] ?? 'Tanpa Judul') ?></b><br>
              <span class="text-secondary"><?= htmlspecialchars($item['artist'] ?? '') ?></span>
            </td>
            <td>
              <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            </td>
            <td class="d-flex gap-2">
              <?php if ($isLocal): ?>
                <button type="button" class="btn btn-sm btn-outline-light btn-play-local" title="Putar">
                  <i class="bi bi-play-circle"></i> Play
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-local"
                  data-row-id="<?= $item['row_id'] ?>" title="Hapus dari playlist">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              <?php elseif ($isYt): ?>
                <button type="button" class="btn btn-sm btn-outline-light btn-play-yt" title="Putar">
                  <i class="bi bi-youtube"></i> Play
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-yt" data-row-id="<?= $item['row_id'] ?>"
                  title="Hapus dari playlist">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              <?php elseif ($isNews): ?>
                <button type="button" class="btn btn-sm btn-outline-light btn-play-news" title="Putar">
                  <i class="bi bi-mic"></i> Play
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-news"
                  data-row-id="<?= $item['row_id'] ?>" title="Hapus dari playlist">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              <?php endif; ?>

              <?php if ($isLocal || $isNews): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= $filePath ?>" target="_blank" title="Buka sumber">
                  <i class="bi bi-box-arrow-up-right"></i>
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
  <button onclick="saveNewOrder()">Simpan</button>
  <script>
    // === AUTO NEXT SONG (lokal) ===
    (function () {
      let currentIndex = 0;
      const container = document.getElementById('playlistItemContainer');

      // Cari semua lagu lokal yang bisa diputar
      function getPlaylistRows() {
        return Array.from(container.querySelectorAll('tr.song-row'));
      }

      // Mainkan lagu ke-n
      function playAt(index) {
        const rows = getPlaylistRows();
        if (rows.length === 0 || index >= rows.length) {
          console.log("🎧 Playlist selesai diputar");
          return;
        }
        currentIndex = index;
        const tr = rows[index];
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

      // Saat user klik Play manual → update index aktif
      container.addEventListener('click', (e) => {
        const tr = e.target.closest('tr.song-row');
        if (tr && e.target.closest('.btn-play-local')) {
          const rows = getPlaylistRows();
          currentIndex = rows.indexOf(tr);
        }
      });

      // Pantau kapan lagu selesai diputar
      document.addEventListener('ended-song', () => {
        playAt(currentIndex + 1);
      });

      // Buat event helper agar mudah dipanggil dari player utama
      window.playNext = () => playAt(currentIndex + 1);
    })();
    async function saveNewOrder() {
      const items = document.querySelectorAll('#playlist-table tbody tr');
      const newOrder = Array.from(items).map((el, index) => ({
        row_id: el.dataset.rowId,
        type: 'lokal',
        sort_order: index + 1
      }));
      console.log(newOrder);

      const playlistId = <?= (int) $playlist_id ?>;

      const res = await fetch('api/playlist_reorder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist_id: playlistId,
          new_order: newOrder
        })
      });

      const result = await res.json();

      if (result.success) {
        alert('Urutan playlist berhasil disimpan!');
      } else {
        alert(' Gagal menyimpan: ' + result.error);
      }
    }
  </script>
</div>

<script>
  (() => {
    // 💡 PENTING: Container sekarang menunjuk ke <tbody>
    const container = document.getElementById('playlistItemContainer');
    const form = document.getElementById('addSongForm');
    const playlistId = <?= (int) $playlist_id ?>;
    const DEFAULT_COVER = "<?= htmlspecialchars($DEFAULT_COVER) ?>";
    const ABS_BASE = "<?= $ABS_BASE ?>";
    const API = n => `${ABS_BASE}/api/${n}`;

    // ==== Helper YouTube modal (dibiarkan seperti semula, diasumsikan Modal ada di app.php) ====
    function openYouTubeModal(videoId, title, channel) {
      try {
        const frame = document.getElementById('ytFrame');
        const modalEl = document.getElementById('ytModal');
        const nowEl = document.getElementById('ytNowPlaying');
        if (!frame || !modalEl) return alert('YouTube modal tidak tersedia di halaman ini.');
        frame.src = `https://www.youtube.com/embed/${encodeURIComponent(videoId)}?autoplay=1&playsinline=1`;
        if (nowEl) nowEl.innerHTML = `<span class="text-white">${title || ''}</span><br><span>${channel || ''}</span>`;
        if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
          throw new Error('Bootstrap JS tidak ditemukan.');
        }
        const m = bootstrap.Modal.getOrCreateInstance(modalEl);
        m.show();
      } catch (e) {
        console.error(e);
        alert('Gagal membuka pemutar YouTube.');
      }
    }

    // --- Fungsi Bind Play (Unified Logic) ---

    // Play lokal 
    function bindPlayButtonsLocal() {
      container.querySelectorAll('.btn-play-local').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const tr = btn.closest('tr.song-row');
          if (!tr) return;
          const detail = {
            src: tr.dataset.src,
            title: tr.dataset.title || 'Unknown',
            artist: tr.dataset.artist || 'Unknown',
            cover: tr.dataset.cover || DEFAULT_COVER
          };
          if (typeof window.playSong === 'function') {
            window.playSong(detail);
          } else {
            document.dispatchEvent(new CustomEvent('play-song', { detail }));
          }
        });
      });
    }

    // ▶️ Play YouTube via tombol
    function bindPlayButtonsYt() {
      container.querySelectorAll('.btn-play-yt').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const tr = btn.closest('tr.yt-row');
          if (!tr) return;
          openYouTubeModal(tr.dataset.videoId, tr.dataset.title || '', tr.dataset.artist || '');
        });
      });
    }

    // ▶️ Play News
    function bindPlayButtonsNews() {
      container.querySelectorAll('.btn-play-news').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const tr = btn.closest('tr.news-row');
          if (!tr) return;
          const detail = {
            src: tr.dataset.src, // 💡 Data ini sekarang dijamin ABSOLUT dari PHP
            title: tr.dataset.title || 'Berita',
            artist: tr.dataset.artist || '',
            cover: tr.dataset.cover || DEFAULT_COVER
          };
          if (typeof window.playSong === 'function') {
            window.playSong(detail);
          } else {
            document.dispatchEvent(new CustomEvent('play-song', { detail }));
          }
        });
      });
    }

    // --- Fungsi Bind Remove (Unified Logic) ---

    // Hapus lokal (music_id diganti row-id/ps_id)
    function bindRemoveLocal() {
      container.querySelectorAll('.btn-remove-local').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.onclick = async (e) => {
          e.preventDefault();
          e.stopPropagation();
          // Mengambil ID baris di tabel playlist_songs (row-id)
          const rowId = btn.dataset.rowId;
          if (!confirm('Hapus lagu ini dari playlist?')) return;
          const old = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';
          try {
            const res = await fetch(API('playlist_remove_song.php'), {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: new URLSearchParams({
                playlist_id: playlistId,
                playlist_song_id: rowId
              })
            }); const data = await res.json();
            if (data.success) {
              btn.closest('tr')?.remove();
              renumberRows();
              emptyCheck();
            } else {
              alert(data.error || 'Gagal menghapus lagu.');
              btn.disabled = false; btn.innerHTML = old;
            }
          } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false; btn.innerHTML = old;
          }
        };
      });
    }

    // Hapus YouTube (item_id diganti row-id)
    function bindRemoveYt() {
      container.querySelectorAll('.btn-remove-yt').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.onclick = async (e) => {
          e.preventDefault();
          e.stopPropagation();
          // 💡 Mengambil ID baris di tabel playlist_items (row-id)
          const rowId = btn.dataset.rowId;
          if (!confirm('Hapus video ini dari playlist?')) return;
          const old = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';
          try {
            const res = await fetch(API('playlist_remove_item.php'), {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
              //  Asumsi endpoint API menerima playlist_item_id
              body: new URLSearchParams({ playlist_item_id: rowId })
            });
            const data = await res.json();
            if (data.success) {
              btn.closest('tr')?.remove();
              renumberRows();
              emptyCheck();
            } else {
              alert(data.error || 'Gagal menghapus video.');
              btn.disabled = false; btn.innerHTML = old;
            }
          } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false; btn.innerHTML = old;
          }
        };
      });
    }

    // 🗑️ Hapus News (item_id diganti row-id/pn_id)
    function bindRemoveNews() {
      container.querySelectorAll('.btn-remove-news').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.onclick = async (e) => {
          e.preventDefault();
          const rowId = btn.dataset.rowId;
          if (!confirm('Hapus berita ini dari playlist?')) return;
          const old = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';
          try {
            const res = await fetch(API('playlist_remove_news.php'), {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
              // 💡 Asumsi endpoint API menerima playlist_news_id
              body: new URLSearchParams({ playlist_news_id: rowId })
            });
            const data = await res.json();
            if (data.success) {
              btn.closest('tr')?.remove();
              renumberRows();
              emptyCheck();
            } else {
              alert(data.error || 'Gagal menghapus berita.');
              btn.disabled = false; btn.innerHTML = old;
            }
          } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false; btn.innerHTML = old;
          }
        };
      });
    }


    // ➕ Tambah lokal (AJAX)
    form.onsubmit = async e => {
      e.preventDefault();
      const fd = new FormData(form);
      const btn = form.querySelector('button[type="submit"]');
      const old = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menambah...';
      try {
        const res = await fetch(API('playlist_add_song.php'), {
          method: 'POST',
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        // 💡 Asumsi API mengembalikan: success, playlist_song_id (row_id), music_id (content_id), title, artist, cover.
        if (data.success) {
          const title = data.title || '';
          const artist = data.artist || '';
          const cover = data.cover ? `${ABS_BASE}/${data.cover}` : DEFAULT_COVER;
          const psId = data.playlist_song_id;
          const musicId = data.music_id;
          const src = `${ABS_BASE}/api/stream.php?id=${musicId}`;

          const tr = document.createElement('tr');
          tr.className = 'playlist-item song-row';

          // 💡 Set data attributes baru
          tr.dataset.rowId = psId;
          tr.dataset.contentId = musicId;
          tr.dataset.source = 'lokal';
          tr.dataset.src = src;
          tr.dataset.title = title;
          tr.dataset.artist = artist;
          tr.dataset.cover = cover;

          tr.innerHTML = `
          <td class="drag-handle" style="cursor: move;">☰</td>
          <td class="row-number">•</td>
          <td><img src="${cover}" alt="Cover" width="40" height="40" style="object-fit:cover;border-radius:6px;"></td>
          <td><b>${title}</b><br><span class="text-secondary">${artist}</span></td>
          <td><span class="badge bg-success">Lokal</span></td>
          <td class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-light btn-play-local" title="Putar">
              <i class="bi bi-play-circle"></i> Play
            </button>
            <a class="btn btn-sm btn-outline-primary" href="${src}" target="_blank" title="Buka sumber">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-local" data-row-id="${psId}" title="Hapus dari playlist">
              <i class="bi bi-trash"></i> Hapus
            </button>
          </td>`;

          const empty = container.querySelector('.row-empty');
          if (empty) empty.remove();
          container.prepend(tr);
          renumberRows();

          // 💡 Panggil bind untuk baris baru
          bindRemoveLocal();
          bindPlayButtonsLocal();

          applyPlaylistFilter(document.getElementById('playlistSearchInput')?.value || '');
          form.reset();
        } else {
          alert(data.error || 'Gagal menambah lagu.');
        }
      } catch (err) {
        alert('Error: ' + err.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = old;
      }
    };

    // === 🔍 Filter client-side ===
    const searchInput = document.getElementById('playlistSearchInput');
    const searchInfo = document.getElementById('playlistSearchInfo');
    const searchClear = document.getElementById('playlistSearchClear');

    function applyPlaylistFilter(q) {
      const rows = container.querySelectorAll('tr.playlist-item');
      const query = (q || '').toLowerCase().trim();
      let shown = 0;
      rows.forEach(tr => {
        const title = (tr.dataset.title || '').toLowerCase();
        const artist = (tr.dataset.artist || '').toLowerCase();
        const source = (tr.dataset.source || '').toLowerCase();

        const hit = !query || title.includes(query) || artist.includes(query) || source.includes(query);
        tr.style.display = hit ? '' : 'none';
        if (hit) shown++;
      });
      const empty = container.querySelector('.row-empty');
      if (empty) empty.style.display = shown === 0 ? '' : 'none';

      if (!query) {
        searchInfo.textContent = `Tampilkan semua ${rows.length} item`;
      } else {
        searchInfo.textContent = `Menampilkan ${shown} item untuk: "${q}"`;
      }
      renumberRows(); // Panggil ulang penomoran setelah filter
    }

    let tDeb = null;
    searchInput?.addEventListener('input', () => {
      clearTimeout(tDeb);
      tDeb = setTimeout(() => applyPlaylistFilter(searchInput.value), 120);
    });
    searchClear?.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      applyPlaylistFilter('');
      searchInput?.focus();
    });

    // Utility kecil untuk penomoran ulang (hanya untuk baris yang terlihat)
    function renumberRows() {
      let n = 1;
      container.querySelectorAll('tr.playlist-item').forEach(tr => {
        if (tr.style.display !== 'none') {
          const cell = tr.querySelector('.row-number');
          if (cell) cell.textContent = n++;
        }
      });
    }

    // Utility cek baris kosong
    function emptyCheck() {
      if (container.querySelectorAll('tr.playlist-item').length === 0) {
        container.innerHTML = `<tr class="row-empty"><td colspan="6" class="text-white text-center">Belum ada item dalam playlist ini.</td></tr>`;
      }
    }

    // Bind semua handler saat script dijalankan pertama kali
    window.bindPlaylistHandlers = () => {
      bindRemoveLocal();
      bindRemoveYt();
      bindRemoveNews();
      bindPlayButtonsLocal(); // Ini yang memanggil window.playSong untuk lagu lokal
      bindPlayButtonsYt();
      bindPlayButtonsNews();

      // Inisialisasi ulang filter dan penomoran (penting untuk AJAX)
      applyPlaylistFilter(document.getElementById('playlistSearchInput')?.value || '');
      renumberRows();
    };

    // Panggil fungsi binding hanya jika halaman ini dimuat secara standalone (non-AJAX)
    // Jika dimuat via AJAX, app.php akan memanggil window.bindPlaylistHandlers() secara otomatis
    if (typeof window.loadPage !== 'function') {
      window.bindPlaylistHandlers();
    }
    // === 🔀 DRAG & DROP REORDER ===
    let dragSrc = null;

    function handleDragStart(e) {
      dragSrc = this;
      this.style.opacity = '0.5';
    }

    function handleDragOver(e) {
      e.preventDefault();
      this.style.borderTop = '2px solid #0d6efd';
    }

    function handleDragLeave(e) {
      this.style.borderTop = '';
    }

    function handleDrop(e) {
      e.preventDefault();
      if (dragSrc === this) return;
      this.style.borderTop = '';

      const rows = Array.from(container.querySelectorAll('tr.playlist-item'));
      const srcIndex = rows.indexOf(dragSrc);
      const destIndex = rows.indexOf(this);

      if (srcIndex < destIndex) {
        this.after(dragSrc);
      } else {
        this.before(dragSrc);
      }

      renumberRows();
      saveNewOrder();
    }

    function handleDragEnd() {
      this.style.opacity = '1';
      container.querySelectorAll('tr.playlist-item').forEach(row => row.style.borderTop = '');
    }

    function enableDragDrop() {
      container.querySelectorAll('tr.playlist-item').forEach(row => {
        row.draggable = true;
        row.addEventListener('dragstart', handleDragStart);
        row.addEventListener('dragover', handleDragOver);
        row.addEventListener('dragleave', handleDragLeave);
        row.addEventListener('drop', handleDrop);
        row.addEventListener('dragend', handleDragEnd);
      });
    }

    async function saveNewOrder() {
      const rows = Array.from(container.querySelectorAll('tr.playlist-item'));
      const newOrder = rows.map(r => ({
        row_id: r.dataset.rowId,
        type: r.dataset.source
      }));

      try {
        const res = await fetch(API('playlist_reorder.php'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            playlist_id: playlistId,
            new_order: newOrder
          })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Gagal menyimpan urutan');
      } catch (err) {
        console.error(err);
        alert('Gagal menyimpan urutan playlist!');
      }
    }
    enableDragDrop();
    // === ▶️ PLAY ALL FUNCTION ===
    function playAllSongs() {
      const rows = Array.from(document.querySelectorAll('#playlistItemContainer tr.song-row'));
      if (rows.length === 0) {
        alert('Tidak ada lagu lokal untuk diputar.');
        return;
      }

      let current = 0;

      function playNext() {
        if (current >= rows.length) {
          console.log('🎵 Semua lagu telah selesai diputar.');
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

      // Saat satu lagu selesai, lanjut ke berikutnya
      document.addEventListener('ended-song', () => {
        current++;
        playNext();
      });

      playNext(); // mulai dari lagu pertama
    }

    // === Hubungkan ke tombol ===
    document.getElementById('btnPlayAll')?.addEventListener('click', (e) => {
      e.preventDefault();
      playAllSongs();
    });

  })();

</script>