<?php
// app.php (debug-on + SPA + player) — versi lengkap + dropdown playlist dinamis

// ====== DEBUG ON  ======
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ========================================

header("Content-Type: text/html; charset=utf-8");

// Koneksi DB
require_once __DIR__ . "/konek.php";

// (Load config_api.php 
$cfg = __DIR__ . "/config_api.php";
if (is_file($cfg)) {
  require_once $cfg; // define('YOUTUBE_API_KEY','...')
}
if (!defined('YOUTUBE_API_KEY')) {
  define('YOUTUBE_API_KEY', ''); // fallback aman
}

// Ambil lagu & playlist 
$songs = [];
$playlists = [];
if (isset($conn) && !$conn->connect_error) {
  // Lagu
  $res = $conn->query("SELECT * FROM music ORDER BY id DESC");
  if ($res && $res->num_rows > 0) $songs = $res->fetch_all(MYSQLI_ASSOC);

  // Daftar playlist
  $rp = $conn->query("SELECT id, name FROM playlist ORDER BY created_at DESC, id DESC");
  if ($rp && $rp->num_rows > 0) while ($row = $rp->fetch_assoc()) $playlists[] = $row;
} else {
  $db_error = isset($conn) ? $conn->connect_error : 'Unknown DB error';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RRI Music App</title>

  <base href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/') ?>/">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body { background:#121212; color:#fff; font-family:Roboto, system-ui, -apple-system, Segoe UI, sans-serif; margin:0; }
    .debug-banner { background:#121212; color:#ddd; padding:6px 12px; font-size:12px; border-bottom:1px solid #222; }
    .sidebar { width:220px; background:#000; position:fixed; top:0; left:0; bottom:0; padding:1.2rem 0; overflow:auto; }
    .brand { font-size:1.4rem; font-weight:700; padding:0 1rem 1rem; color:#fff; }
    .brand span { color:#ff0000; }
    .nav-link { color:#aaa; display:flex; gap:.6rem; align-items:center; padding:.6rem 1rem; text-decoration:none; }
    .nav-link.active, .nav-link:hover { color:#fff; background:#181818; border-left:3px solid #ff0000; }
    .main-content { margin-left:240px; padding:1.5rem; min-height:100vh; box-sizing:border-box; padding-bottom:120px; }
    .song-table img { width:40px; height:40px; object-fit:cover; border-radius:6px; margin-right:.5rem; }
    .music-player { position:fixed; bottom:0; left:240px; right:0; background:#202020; padding:12px 18px; display:flex; gap:16px; align-items:center; border-top:1px solid #333; z-index:999; }
    .music-cover img { width:60px; height:60px; object-fit:cover; border-radius:8px; }
    .music-info { flex:1; min-width:0; }
    .music-info h5 { margin:0; font-size:16px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
    .music-info small { color:#bbb; display:block; }
    #progress { width:100%; -webkit-appearance:none; height:4px; background:#444; border-radius:2px; }
    #progress::-webkit-slider-thumb { -webkit-appearance:none; width:12px; height:12px; border-radius:50%; background:#ff0000; }
    .music-controls { display:flex; gap:12px; align-items:center; font-size:20px; cursor:pointer; }
    .music-controls i { opacity:.9; }
    .music-controls i:hover { opacity:1; }
    .play-btn-row { cursor:pointer; color:#ff4d4d; font-size:1.4rem; }
    .table-dark.table-hover tbody tr:hover { background:#1a1a1a; }
    .placeholder-cover { background:#2a2a2a; display:inline-block; width:40px; height:40px; border-radius:6px; margin-right:.5rem; }
    .empty { color:#aaa; font-style:italic; }

    /* ===== Search bar ===== */
    .top-search { margin-left:240px; padding:10px 16px 0; }
    .top-search .form-control, .top-search .form-select { background:#181818; color:#fff; border-color:#2a2a2a; }
    .top-search .form-control::placeholder { color:#888; }
    .top-search .btn { white-space:nowrap; }

    /* ===== YouTube list ===== */
    #ytResults .list-group-item { background:transparent; color:#eee; border-color:#333; }
    #ytResults .list-group-item:hover { background:#181818; }
    #ytResults img { width:120px; height:68px; object-fit:cover; border-radius:6px; }
    .badge-soft { background:#222; color:#aaa; border:1px solid #333; }

/* ===== Modal YouTube Minimize (FIXED + VIDEO SHOW) ===== */
#ytModal .modal-dialog {
  transition: all 0.3s ease;
}

/*  diminimize jadi mini-player */
#ytModal.minimized {
  position: fixed !important;
  bottom: 1rem;
  right: 1rem;
  width: 320px !important;
  z-index: 9999;
  margin: 0 !important;
  pointer-events: auto;
  transform: scale(0.95);
  opacity: 0.98;
}

#ytModal.minimized .modal-dialog {
  width: 100% !important;
  margin: 0;
}

#ytModal.minimized .modal-content {
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
}


/* Sembunyikan list & elemen lain kecuali video */
#ytModal.minimized .col-lg-5 { 
  display: none !important; 
}

/* Tampilkan kolom video */
#ytModal.minimized .col-lg-7 {
  display: block !important;
  width: 100% !important;
}

/* Biar video kecil */
#ytModal.minimized .ratio {
  width: 100%;
  height: 180px; /* tinggi mini player */
  border-bottom: 1px solid #333;
}

/* Sembunyikan teks “Now Playing” */
#ytModal.minimized #ytNowPlaying {
  display: none;
}

/* Header disederhanakan */
#ytModal.minimized .modal-header {
  background: #222;
  border: none;
  cursor: pointer;
  padding: 0.35rem 0.6rem;
}

#ytModal.minimized .modal-title {
  font-size: 0.9rem;
  color: #ccc;
}
  </style>
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Banner debug -->
  <div class="debug-banner">
    app.php rendered • <?= date('H:i:s') ?>
    <?php if (!empty($db_error)): ?>
      • <b>DB ERROR:</b> <?= htmlspecialchars($db_error) ?>
    <?php endif; ?>
  </div>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="brand">RRI<span>Music</span></div>
    <a href="views/page_songs.php?page=songs" class="nav-link ajax-link active"><i class="bi bi-music-note-list"></i> All Songs</a>
    <a href="views/page_artist.php?page=artist" class="nav-link ajax-link"><i class="bi bi-person"></i> Artists</a>
    <a href="views/page_playlist.php?page=playlist" class="nav-link ajax-link"><i class="bi bi-music-note-beamed"></i> Playlists</a>
    <hr style="border-color:#222">
    <a href="views/page_profil.php?page=profil" class="nav-link ajax-link"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="views/page_users.php?page=users" class="nav-link ajax-link"><i class="bi bi-people"></i> Manajemen User</a>
    <a href="views/upload.php?page=upload" class="nav-link ajax-link"><i class="bi bi-upload"></i> Upload Song</a>
    <a href="views/page_upload_news.php?page=upload_news" class="nav-link ajax-link"><i class="bi bi-upload"></i> Upload Berita</a>
    <a href="views/page_news_archive.php?page=news_archive" class="nav-link ajax-link"><i class="bi bi-archive"></i> Arsip Berita</a>
    <a href="views/page_settings.php?page=settings" class="nav-link ajax-link"><i class="bi bi-gear"></i> Settings</a>
  </div>

 <!-- Global search bar + Pilih playlist -->
<div class="top-search my-3">
  <div class="row g-2 align-items-center">
    
    <!-- Search Database -->
    <div class="col-lg-4 col-md-6">
      <form id="dbSearchForm" class="input-group">
        <input id="dbSearchInput" type="text" class="form-control" placeholder="Cari lagu (judul/artist) di database…">
        <button class="btn btn-outline-light" type="submit">
          <i class="bi bi-search"></i> Cari DB
        </button>
      </form>
    </div>

    <!-- Search YouTube -->
    <div class="col-lg-4 col-md-6">
      <form id="ytSearchForm" class="input-group">
        <input id="ytSearchInput" type="text" class="form-control" placeholder="Cari musik di YouTube…">
        <button class="btn btn-outline-danger" type="submit">
          <i class="bi bi-youtube"></i> Cari YouTube
        </button>
      </form>
    </div>

    <!-- Pilih Playlist -->
    <div class="col-lg-4 col-md-12" style="margin-top:-6px;">
      <div class="d-flex flex-column" style="margin-bottom:-2px;">
        <small class="text-secondary d-block" style="font-size: 0.75rem; margin-bottom:-6px; position:relative; top:-10px;">
          Semua tombol <b>+ Playlist</b> akan masuk ke playlist
          <b id="playlistNameActive">
            <?= !empty($playlists) ? htmlspecialchars($playlists[0]['name']) : 'Default' ?>
          </b>.
        </small>

        <div class="input-group" style="margin-top:-6px;">
          <label class="input-group-text bg-dark text-white" for="playlistSelect">
            <i class="bi bi-music-note-beamed"></i>
          </label>
          <select id="playlistSelect" class="form-select bg-dark text-white border-secondary">
            <?php if (!empty($playlists)): ?>
              <?php foreach ($playlists as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <option value="1">Playlist Default</option>
            <?php endif; ?>
          </select>
          <button id="btnRefreshPlaylists" class="btn btn-outline-light" type="button" title="Refresh daftar playlist">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// update teks "playlistNameActive" saat user ganti dropdown
document.getElementById('playlistSelect')?.addEventListener('change', function() {
  const sel = this.options[this.selectedIndex];
  const nameEl = document.getElementById('playlistNameActive');
  if (nameEl) nameEl.textContent = sel.textContent.trim();
});
</script>

  <!-- Modal YouTube (LIST + PLAYER) -->
  <div class="modal fade" id="ytModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-youtube text-danger"></i> Hasil Pencarian YouTube</h5>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-light" id="ytMinimizeBtn" title="Minimize">
              <i class="bi bi-dash-lg"></i>
            </button>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>
        <div class="modal-body" style="padding:0;">
          <div class="row g-0" style="min-height:65vh;">
            <!-- Daftar hasil -->
            <div class="col-12 col-lg-5 border-end border-secondary" style="max-height:65vh; overflow:auto;">
              <div class="p-3">
                <!-- Kolom pencarian di dalam modal -->
                <form id="ytModalSearchForm" class="input-group mb-2">
                  <input id="ytModalSearchInput" type="text" class="form-control" placeholder="Cari lagi di YouTube…">
                  <button class="btn btn-outline-danger" type="submit"><i class="bi bi-search"></i></button>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong id="ytResultCount">Hasil</strong>
                  <small id="ytQueryLabel" class="text-secondary"></small>
                </div>
                <div id="ytResults" class="list-group list-group-flush"></div>
                <div id="ytLoadMoreWrap" class="p-3 d-none">
                  <button id="ytLoadMore" class="btn btn-outline-light w-100">Muat lebih banyak</button>
                </div>
              </div>
            </div>

            <!-- Player -->
            <div class="col-12 col-lg-7">
              <div class="ratio ratio-16x9">
                <iframe id="ytFrame" src="" title="YouTube" allow="autoplay; encrypted-media" allowfullscreen></iframe>
              </div>
              <div id="ytNowPlaying" class="p-3 small text-secondary"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <div id="main-content" class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>All Songs</h2>
    </div>
    <table class="table table-dark table-hover song-table align-middle">
      <thead><tr><th>#</th><th>Title</th><th>Artist</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($songs)): ?>
        <tr><td colspan="4" class="empty">Belum ada lagu di tabel <code>music</code>.</td></tr>
      <?php else: ?>
        <?php foreach ($songs as $i=>$s):
          $filePath = $s['file_path'] ?? '';
          // Jika app sudah pakai stream by id, sesuaikan:
          $src = !empty($s['id']) ? ('api/stream.php?id='.(int)$s['id']) : ($filePath ? 'api/stream.php?file=' . rawurlencode(basename($filePath)) : '');
        ?>
        <tr class="song-row"
            data-index="<?= $i ?>"
            data-src="<?= htmlspecialchars($src) ?>"
            data-cover="<?= htmlspecialchars($s['cover'] ?: '') ?>"
            data-title="<?= htmlspecialchars($s['title']) ?>"
            data-artist="<?= htmlspecialchars($s['artist']) ?>">
          <td><?= $i+1 ?></td>
          <td>
            <?php if(!empty($s['cover'])): ?>
              <img src="<?= htmlspecialchars($s['cover']) ?>" alt="">
            <?php else: ?>
              <span class="placeholder-cover"></span>
            <?php endif; ?>
            <?= htmlspecialchars($s['title']) ?>
          </td>
          <td><?= htmlspecialchars($s['artist']) ?></td>
          <td><i class="bi bi-play-circle-fill play-btn-row" title="Putar"></i></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Player -->
  <div class="music-player">
    <div class="music-cover"><img id="player-cover" src="https://via.placeholder.com/60?text=No+Cover" alt="Cover"></div>
    <div class="music-info">
      <h5 id="player-title">Tidak ada lagu</h5>
      <small id="player-artist">Unknown</small>
      <div class="d-flex align-items-center mt-1">
        <small id="current-time">0:00</small>
        <input type="range" id="progress" value="0" min="0" step="1" class="mx-2">
        <small id="duration">0:00</small>
      </div>
    </div>
    <div class="music-controls">
      <i class="bi bi-shuffle" id="shuffle" title="Shuffle"></i>
      <i class="bi bi-skip-backward-fill" id="prev" title="Sebelumnya"></i>
      <i class="bi bi-play-fill" id="play" title="Play/Pause"></i>
      <i class="bi bi-skip-forward-fill" id="next" title="Berikutnya"></i>
    </div>
  </div>

<!-- Expose API key ke JS + seed playlists ke JS (opsional) -->
<script>
  window.YT_API_KEY = window.YT_API_KEY || "<?= addslashes(YOUTUBE_API_KEY) ?>";
  const SEED_PLAYLISTS = <?= json_encode($playlists, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
// ---------- Helper ----------
const BASE_DIR = (()=>{ const b=document.querySelector('base')?.getAttribute('href'); return b?b:window.location.pathname.replace(/[^/]+$/, '');})();
const API_BASE = BASE_DIR + 'api/';
function apiEndpoint(name){ return API_BASE + name; }
// (BARU) default cover dipakai saat sumber eksternal (arsip berita, dst.)
const DEFAULT_COVER = BASE_DIR + 'uploads/default_cover.png';

function getActivePlaylistId(){
  const sel = document.getElementById('playlistSelect');
  const val = sel?.value || '1';
  const id = parseInt(val, 10);
  return Number.isFinite(id) && id > 0 ? id : 1;
}

// (BARU) Ambil ulang playlist dari API
async function fetchPlaylistsFromAPI() {
  const url = apiEndpoint('playlist_list.php');
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
  const data = await res.json();
  if (!data.success) throw new Error(data.error || 'Gagal memuat playlist');
  return Array.isArray(data.playlists) ? data.playlists : [];
}

// (BARU) Set label nama playlist aktif sesuai dropdown
function setActivePlaylistLabelFromSelect() {
  const sel = document.getElementById('playlistSelect');
  const label = document.getElementById('playlistNameActive');
  if (sel && label) {
    const name = sel.options[sel.selectedIndex]?.textContent?.trim() || 'Default';
    label.textContent = name;
  }
}

// (BARU) Render ulang dropdown playlist dari list baru
function refreshPlaylistDropdown(list) {
  const sel = document.getElementById('playlistSelect');
  if (!sel) return;
  const prev = sel.value;
  if (!list || !list.length) {
    sel.innerHTML = `<option value="1">Playlist Default</option>`;
    setActivePlaylistLabelFromSelect();
    return;
  }
  sel.innerHTML = list.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
  if ([...sel.options].some(o => o.value === prev)) sel.value = prev;
  setActivePlaylistLabelFromSelect();
}

// Eksekusi ulang semua <script> dari partial (agar inline script jalan)
function runScripts(container){
  const scripts = container.querySelectorAll('script');
  scripts.forEach(old => {
    const s = document.createElement('script');
    for (const attr of old.attributes) s.setAttribute(attr.name, attr.value);
    if (old.src) s.src = old.src; else s.textContent = old.textContent;
    old.parentNode.replaceChild(s, old);
  });
}

// ===== Global handler: tombol Play dari halaman mana pun =====
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-play');      // tombol play di page lain (arsip berita, dsb)
  const icon = e.target.closest('.play-btn-row'); // icon play di tabel lagu
  if (!btn && !icon) return;

  // Jangan biarkan router SPA jalan
  e.preventDefault();
  e.stopPropagation();

  // Jika klik icon di tabel lagu → gunakan mekanisme baris (auto-next ON)
  if (icon) {
    const row = icon.closest('.song-row');
    if (row) row.click();
    return;
  }

  // Jika tombol .btn-play (eksternal) → panggil bridge global (auto-next OFF sekali)
  const detail = {
    src:    btn?.dataset.src || '',
    title:  btn?.dataset.title || '',
    artist: btn?.dataset.artist || '',
    cover:  btn?.dataset.cover || DEFAULT_COVER
  };
  if (typeof window.playSong === 'function') {
    window.playSong(detail);   // mode eksternal → sekali putar
  } else {
    document.dispatchEvent(new CustomEvent('play-song', { detail }));
  }
}, { passive:false });

// ====== Search handlers (DB + YouTube) ======
(function(){
  const dbForm   = document.getElementById('dbSearchForm');
  const dbInput  = document.getElementById('dbSearchInput');
  const ytForm   = document.getElementById('ytSearchForm');
  const ytInput  = document.getElementById('ytSearchInput');
  const ytModalEl= document.getElementById('ytModal');
  const ytModal  = new bootstrap.Modal(ytModalEl);
  const ytFrame  = document.getElementById('ytFrame');
  const btnRefreshPlaylists = document.getElementById('btnRefreshPlaylists');

  // set label awal sesuai option yang sudah di-render server-side
  setActivePlaylistLabelFromSelect();

  // klik refresh → fetch API dan render dropdown
  btnRefreshPlaylists?.addEventListener('click', async () => {
    const old = btnRefreshPlaylists.innerHTML;
    btnRefreshPlaylists.disabled = true;
    btnRefreshPlaylists.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memuat`;
    try {
      const list = await fetchPlaylistsFromAPI();
      refreshPlaylistDropdown(list);
    } catch (e) {
      // fallback: gunakan seed agar UI tetap jalan
      if (Array.isArray(SEED_PLAYLISTS) && SEED_PLAYLISTS.length) {
        refreshPlaylistDropdown(SEED_PLAYLISTS);
      }
      alert(e.message || 'Gagal memuat playlist.');
    } finally {
      btnRefreshPlaylists.disabled = false;
      btnRefreshPlaylists.innerHTML = old;
    }
  });

  // Render hasil pencarian DB ke tabel All Songs
  function renderSongsInTable(songs){
    const tbody = document.querySelector('.song-table tbody');
    if (!tbody) return;

    if (!songs.length) {
      tbody.innerHTML = `<tr><td colspan="4" class="empty">Tidak ada hasil.</td></tr>`;
      if (typeof bindSongEvents === 'function') bindSongEvents();
      return;
    }

    const rows = songs.map((s, i) => `
      <tr class="song-row"
          data-index="${i}"
          data-src="api/stream.php?id=${s.id}"
          data-cover="${(s.cover||'').replace(/"/g,'&quot;')}"
          data-title="${(s.title||'').replace(/"/g,'&quot;')}"
          data-artist="${(s.artist||'').replace(/"/g,'&quot;')}">
        <td>${i+1}</td>
        <td>
          ${s.cover ? `<img src="${s.cover}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;margin-right:.5rem;">` : `<span class="placeholder-cover"></span>`}
          ${s.title||''}
        </td>
        <td>${s.artist||''}</td>
        <td><i class="bi bi-play-circle-fill play-btn-row" title="Putar"></i></td>
      </tr>
    `).join('');

    tbody.innerHTML = rows;
    if (typeof bindSongEvents === 'function') bindSongEvents();
  }

  // Submit pencarian DB
  dbForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = dbInput.value.trim();
    try {
      if (typeof window.loadPage === 'function') {
        await window.loadPage('views/page_songs.php?page=songs');
      }
      const res = await fetch(`api/search_music.php?q=${encodeURIComponent(q)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.success) renderSongsInTable(data.songs || []);
      else alert('Gagal mencari lagu.');
    } catch (err) {
      console.error(err);
      alert('Terjadi kesalahan saat mencari.');
    }
  });

  // ====== YouTube search ======
  let ytNextPageToken = null;
  let ytLastQuery = "";

  function parseISODuration(iso) {
    const m = /PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/.exec(iso) || [];
    const h = parseInt(m[1]||0,10), mi=parseInt(m[2]||0,10), s=parseInt(m[3]||0,10);
    const total = h*3600 + mi*60 + s;
    const mm = Math.floor(total/60), ss = total % 60;
    return (h? (h+":"+String(mi).padStart(2,"0")) : mm) + ":" + String(ss).padStart(2,"0");
  }

  function playInModal(video) {
    const id = video.id?.videoId || video.id;
    if (!id) return;
    ytFrame.src = `https://www.youtube.com/embed/${id}?autoplay=1`;
    const t = video.snippet?.title || "(Tanpa judul)";
    const ch= video.snippet?.channelTitle || "";
    document.getElementById('ytNowPlaying').innerHTML =
      `<span class="text-white">${t}</span><br><span>${ch}</span>`;
  }

  function renderYtResults(items, meta = {}) {
    const list = document.getElementById('ytResults');
    const qLabel = document.getElementById('ytQueryLabel');
    const countEl = document.getElementById('ytResultCount');
    const loadMoreWrap = document.getElementById('ytLoadMoreWrap');

    qLabel.textContent = ytLastQuery ? `"${ytLastQuery}"` : "";
    countEl.textContent = `Hasil (${items.length}+ )`;

    list.innerHTML = items.map(v => {
      const id = v.id?.videoId || v.id;
      const sn = v.snippet || {};
      const thumb = sn.thumbnails?.medium?.url || sn.thumbnails?.default?.url || "";
      const title = sn.title || "(Tanpa judul)";
      const ch = sn.channelTitle || "";
      const published = sn.publishedAt ? new Date(sn.publishedAt).toLocaleDateString() : "";
      const dur = v.contentDetails?.duration ? parseISODuration(v.contentDetails.duration) : "";
      return `
        <div class="list-group-item d-flex gap-3 align-items-start" data-video-id="${id}">
          <img src="${thumb}" alt="">
          <div class="flex-fill">
            <div class="fw-semibold">${title}</div>
            <div class="small text-secondary">${ch} • ${published} ${dur?`• ${dur}`:""}</div>
            <div class="mt-1"><span class="badge badge-soft">YouTube</span></div>
          </div>
          <div class="d-flex flex-column gap-2">
            <button class="btn btn-sm btn-outline-light play-video"><i class="bi bi-play-circle"></i> Play</button>
            <button class="btn btn-sm btn-outline-success add-to-playlist"><i class="bi bi-plus-circle"></i> + Playlist</button>
          </div>
        </div>
      `;
    }).join('');

    // Bind tombol Play & +Playlist (pakai playlist_id dari dropdown)
    list.querySelectorAll('.list-group-item').forEach((item, idx) => {
      const btnPlay = item.querySelector('.play-video');
      btnPlay?.addEventListener('click', (e) => {
        e.stopPropagation();
        playInModal(items[idx]);
      });

      const btnAdd = item.querySelector('.add-to-playlist');
      btnAdd?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const v = items[idx];
        const id = v.id?.videoId || v.id;
        const sn = v.snippet || {};

        const pid = getActivePlaylistId();
        if (!pid) { alert('Pilih playlist terlebih dahulu.'); return; }

        const payload = new URLSearchParams({
          playlist_id: pid,
          source: 'youtube',
          video_id: id,
          url: `https://www.youtube.com/watch?v=${id}`,
          title: sn.title || '',
          artist: sn.channelTitle || '',
          cover: (sn.thumbnails?.medium?.url || sn.thumbnails?.default?.url || '')
        });

        try {
          const res = await fetch('api/playlist_add_song.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: payload,
            credentials: 'same-origin'
          });
          const data = await res.json().catch(()=>({success:false}));
          if (data.success) {
            btnAdd.classList.remove('btn-outline-success');
            btnAdd.classList.add('btn-success');
            btnAdd.innerHTML = '<i class="bi bi-check2-circle"></i> Ditambahkan';
          } else {
            alert((data.error || data.message) ?? 'Gagal menambahkan ke playlist.');
          }
        } catch (err) {
          console.error(err);
          alert('Terjadi kesalahan saat menambahkan ke playlist.');
        }
      });
    });

    if (meta.nextPageToken) {
      ytNextPageToken = meta.nextPageToken;
      loadMoreWrap.classList.remove('d-none');
    } else {
      ytNextPageToken = null;
      loadMoreWrap.classList.add('d-none');
    }
  }

async function fetchYouTubeList(q, pageToken = null) {
    const YT_API_KEY = "AIzaSyDD4E65adltA9dB3bI2rcQjwJ4qrXYjpew";
    const BASE = "https://www.googleapis.com/youtube/v3";

    // === SEARCH ===
    const sUrl = new URL(BASE + "/search");
    sUrl.searchParams.set("part", "snippet");
    sUrl.searchParams.set("type", "video");
    sUrl.searchParams.set("maxResults", "12");
    sUrl.searchParams.set("q", q);
    if (pageToken) sUrl.searchParams.set("pageToken", pageToken);
    sUrl.searchParams.set("key", YT_API_KEY);

    const sRes = await fetch(sUrl.toString());
    const sData = await sRes.json();

    const items = sData.items || [];
    const ids = items.map(v => v.id?.videoId).filter(Boolean);

    // === GET VIDEO DETAILS (duration) ===
    let detailsMap = {};

    if (ids.length) {
        const vUrl = new URL(BASE + "/videos");
        vUrl.searchParams.set("part", "contentDetails,snippet");
        vUrl.searchParams.set("id", ids.join(","));
        vUrl.searchParams.set("key", YT_API_KEY);

        const vRes = await fetch(vUrl.toString());
        const vData = await vRes.json();

        (vData.items || []).forEach(v => detailsMap[v.id] = v);
    }

    // === MERGE video snippet + duration ===
    const merged = items.map(s => {
        const vid = s.id?.videoId;
        return detailsMap[vid] ? detailsMap[vid] : s;
    });

    return {
        items: merged,
        nextPageToken: sData.nextPageToken || null
    };
}
  // Submit pencarian YouTube (TANPA menghentikan video yang sedang diputar)
ytForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const q = ytInput.value.trim();
    if (!q) return;

    ytLastQuery = q;

    try {
        const { items, nextPageToken } = await fetchYouTubeList(q);
        renderYtResults(items, { nextPageToken });
        ytModal.show(); // video TIDAK direset
    } catch (err) {
        console.error(err);
        alert("Gagal mencari video YouTube.");
    }
});

// Pencarian ulang dari dalam modal (TANPA menghentikan video yang sedang diputar)
  const ytModalSearchForm = document.getElementById('ytModalSearchForm');
  const ytModalSearchInput = document.getElementById('ytModalSearchInput');

ytModalSearchForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const q = ytModalSearchInput.value.trim();
    if (!q) return;

    ytLastQuery = q;

    try {
        const { items, nextPageToken } = await fetchYouTubeList(q);
        renderYtResults(items, { nextPageToken });
    } catch (err) {
        console.error(err);
        alert("Gagal mencari video YouTube.");
    }
});

  // Load more (pakai playlist_id aktif saat + ditekan)
  document.getElementById('ytLoadMore').addEventListener('click', async () => {
    if (!ytNextPageToken || !YT_API_KEY || !ytLastQuery) return;
    try {
      const { items, nextPageToken } = await fetchYouTubeList(ytLastQuery, ytNextPageToken);

      const curList = document.getElementById('ytResults');
      const fragment = document.createElement('div');
      fragment.innerHTML = items.map(v => {
        const id = v.id?.videoId || v.id;
        const sn = v.snippet || {};
        const thumb = sn.thumbnails?.medium?.url || sn.thumbnails?.default?.url || "";
        const title = sn.title || "(Tanpa judul)";
        const ch = sn.channelTitle || "";
        const published = sn.publishedAt ? new Date(sn.publishedAt).toLocaleDateString() : "";
        const dur = v.contentDetails?.duration ? parseISODuration(v.contentDetails.duration) : "";
        return `
          <div class="list-group-item d-flex gap-3 align-items-start" data-video-id="${id}">
            <img src="${thumb}" alt="">
            <div class="flex-fill">
              <div class="fw-semibold">${title}</div>
              <div class="small text-secondary">${ch} • ${published} ${dur?`• ${dur}`:""}</div>
              <div class="mt-1"><span class="badge badge-soft">YouTube</span></div>
            </div>
            <div class="d-flex flex-column gap-2">
              <button class="btn btn-sm btn-outline-light play-video"><i class="bi bi-play-circle"></i> Play</button>
              <button class="btn btn-sm btn-outline-success add-to-playlist"><i class="bi bi-plus-circle"></i> + Playlist</button>
            </div>
          </div>
        `;
      }).join('');

      Array.from(fragment.children).forEach((node, idx) => {
        curList.appendChild(node);

        const btnPlay = node.querySelector('.play-video');
        btnPlay?.addEventListener('click', (e) => {
          e.stopPropagation();
          playInModal(items[idx]);
        });

        const btnAdd = node.querySelector('.add-to-playlist');
        btnAdd?.addEventListener('click', async (e) => {
          e.stopPropagation();
          const v = items[idx];
          const id = v.id?.videoId || v.id;
          const sn = v.snippet || {};

          const pid = getActivePlaylistId();
          if (!pid) { alert('Pilih playlist terlebih dahulu.'); return; }

          const payload = new URLSearchParams({
            playlist_id: pid,
            source: 'youtube',
            video_id: id,
            url: `https://www.youtube.com/watch?v=${id}`,
            title: sn.title || '',
            artist: sn.channelTitle || '',
            cover: (sn.thumbnails?.medium?.url || sn.thumbnails?.default?.url || '')
          });

          try {
            const res = await fetch('api/playlist_add_song.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
              body: payload,
              credentials: 'same-origin'
            });
            const data = await res.json().catch(()=>({success:false}));
            if (data.success) {
              btnAdd.classList.remove('btn-outline-success');
              btnAdd.classList.add('btn-success');
              btnAdd.innerHTML = '<i class="bi bi-check2-circle"></i> Ditambahkan';
            } else {
              alert((data.error || data.message) ?? 'Gagal menambahkan ke playlist.');
            }
          } catch (err) {
            console.error(err);
            alert('Terjadi kesalahan saat menambahkan ke playlist.');
          }
        });
      });

      ytNextPageToken = nextPageToken || null;
      document.getElementById('ytLoadMoreWrap').classList.toggle('d-none', !ytNextPageToken);
    } catch (err) {
      console.error(err);
      alert('Gagal memuat hasil berikutnya.');
    }
  });

  // Stop video saat modal ditutup & reset state
  ytModalEl.addEventListener('hidden.bs.modal', () => {
    ytFrame.src = '';
    document.getElementById('ytResults').innerHTML = '';
    document.getElementById('ytLoadMoreWrap').classList.add('d-none');
    document.getElementById('ytNowPlaying').textContent = '';
    ytNextPageToken = null;
  });
})();

// ---------- AJAX Router ----------
(function(){
  const main = document.getElementById('main-content');
    
    // Fungsi untuk mendapatkan URL fetch yang benar dari query string bersih
    function getFetchUrl(urlQuery) {
  const params = new URLSearchParams(urlQuery);
  const page = params.get('page');
  if (!page) return null;

  let filename = page;

  // Tambahkan prefix "page_" untuk SEMUA halaman
  // kecuali beberapa pengecualian yang benar-benar tidak pakai prefix
  const tanpaPrefix = ['upload', 'playlist_detail'];

  if (!tanpaPrefix.includes(page)) {
    filename = `page_${page}`;
  }

  return `views/${filename}.php?${params.toString()}`;
}

  async function loadPage(urlQuery, push=true){
        // 💡 GUARD PUSHSTATE: Pastikan urlQuery tidak kosong
        if (!urlQuery) {
            console.warn("loadPage dipanggil dengan urlQuery kosong. Menggunakan default 'page=songs'.");
            urlQuery = 'page=songs'; 
        }

        // Dapatkan URL yang akan di-fetch (misal: 'views/playlist_detail.php?id=100')
        const fetchPath = getFetchUrl(urlQuery);
        if (!fetchPath) {
            main.innerHTML = `<div class="alert alert-danger">URL tidak valid atau query 'page' hilang.</div>`;
            return;
        }

        // Buat URL Absolut untuk fetch
        const fetchUrl = window.location.protocol + '//' + window.location.host + '/' + fetchPath;

    try {
      // FETCH LANGSUNG KE FILE VIEW, MENGIRIM HEADER AJAX (MENGHINDARI REDIRECT 302)
      const res = await fetch(fetchUrl, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const html = await res.text();
      
      if (!res.ok) {
        main.innerHTML = `<pre class="text-danger p-3 bg-dark rounded-2" style="white-space:pre-wrap;">${html}</pre>`;
        return;
      }
      main.innerHTML = html;

      runScripts(main);

      if (typeof bindSongEvents === 'function') bindSongEvents();
      // Ini akan berjalan HANYA jika playlist_detail.php dimuat dengan sukses
      if (typeof bindPlaylistHandlers === 'function') bindPlaylistHandlers();

      // PUSHSTATE: Selalu gunakan format '?page=songs' yang valid
      if (push) history.pushState({urlQuery}, "", `?${urlQuery}`);
      window.scrollTo(0,0);
      setActiveLink(`?${urlQuery}`); 
    } catch(err) {
      console.error('Failed loadPage', err);
      main.innerHTML = `<div class="alert alert-danger">Gagal memuat halaman: ${err?.message||err}</div>`;
    }
  }

  // Perbaiki setActiveLink (membandingkan query string yang bersih)
  function setActiveLink(urlWithQuery){
        // urlWithQuery adalah string seperti '?page=songs'
    document.querySelectorAll('.sidebar .nav-link').forEach(a=>a.classList.remove('active'));
    
    // Ambil query string dari urlWithQuery
    const activeQuery = (new URLSearchParams(urlWithQuery)).toString(); 

    // Cari link yang query stringnya cocok
    const found = Array.from(document.querySelectorAll('.sidebar .nav-link'))
      .find(a => {
            const linkHref = a.getAttribute('href');
            const linkQuery = linkHref.includes('?') ? linkHref.split('?')[1] : '';
            return linkQuery === activeQuery; 
        });

    if (found) found.classList.add('active');
  }

  // event listener untuk link navigasi
document.addEventListener('click', e => {
  // 🎵 Cegah router SPA dari event Play
  if (e.target.closest('.btn-play') || e.target.closest('.play-btn-row')) {
    return; 
  }

  const a = e.target.closest('a.ajax-link');
  if (a) {
    e.preventDefault();
    
    // Ambil hanya query string dari href (e.g. 'page=songs')
    const href = a.getAttribute('href');
    // Jika tidak ada '?', kita tetap kirim string kosong yang akan diubah menjadi 'page=songs' oleh guard di loadPage
    const query = href.includes('?') ? href.split('?')[1] : ''; 
    loadPage(query); 
  }
});


  window.addEventListener('popstate', e=>{
    // Pastikan urlQuery dari state ada sebelum memuat
    if(e.state && e.state.urlQuery) loadPage(e.state.urlQuery,false); 
  });

  // Inisialisasi awal
  (function(){
    const params = new URLSearchParams(location.search);
    const page = params.get('page') || 'songs';
    
    // Ambil seluruh query string dari URL browser, default ke 'page=songs'
    const urlQuery = location.search.substring(1) || `page=${page}`;
    
    loadPage(urlQuery, true);
    setActiveLink(`?${urlQuery}`);
  })();

  window.loadPage = loadPage;
})();
// ---------- Music Player ----------
(function(){
  let playlist = [], currentIndex=-1, isPlaying=false;
  let useAutoNext = true; // (BARU) kendali auto-next
  const audio = new Audio();
  const playBtn=document.getElementById('play'), prevBtn=document.getElementById('prev'),
        nextBtn=document.getElementById('next'), shuffleBtn=document.getElementById('shuffle');
  const playerTitle=document.getElementById('player-title'),
        playerArtist=document.getElementById('player-artist'),
        playerCover=document.getElementById('player-cover');
  const progress=document.getElementById('progress'),
        currentTimeEl=document.getElementById('current-time'),
        durationEl=document.getElementById('duration');
  audio.addEventListener('ended', () => {
  document.dispatchEvent(new Event('ended-song'));
});


  function fmt(s){ if(!s || !isFinite(s)) return "0:00"; s=Math.floor(s); const m=Math.floor(s/60), sec=s%60; return m+":"+(sec<10?"0"+sec:sec); }

  function initPlaylist(){
    playlist = [];
    document.querySelectorAll('.song-row').forEach(r=>{
      playlist.push({src:r.dataset.src,title:r.dataset.title||'Unknown',artist:r.dataset.artist||'Unknown',cover:r.dataset.cover||'https://via.placeholder.com/60?text=No+Cover'});
    });
  }

  window.bindSongEvents = function(){
  initPlaylist();
  document.querySelectorAll('.song-row').forEach((row,i)=>{
    if (row.dataset.bound) return; 
    row.dataset.bound = '1';

    // ✅ Hanya ikon Play yang memutar lagu
    const icon = row.querySelector('.play-btn-row');
    if (icon) {
      icon.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        currentIndex = i;
        loadSong(i);
        useAutoNext = true;
        playSong();
      });
    }
  });
};


  function loadSong(i){
    const s=playlist[i]; if(!s) return;
    audio.src=s.src; playerTitle.textContent=s.title; playerArtist.textContent=s.artist; playerCover.src=(s.cover||'<?= htmlspecialchars("uploads/default_cover.png") ?>');
  }
  function playSong(){ audio.play().catch(()=>{}); isPlaying=true; playBtn.classList.replace('bi-play-fill','bi-pause-fill'); }
  function pauseSong(){ audio.pause(); isPlaying=false; playBtn.classList.replace('bi-pause-fill','bi-play-fill'); }
  function nextSong(){ if(!playlist.length) return; currentIndex=(currentIndex+1)%playlist.length; loadSong(currentIndex); playSong(); }
  function prevSong(){ if(!playlist.length) return; currentIndex=(currentIndex-1+playlist.length)%playlist.length; loadSong(currentIndex); playSong(); }
  function shuffleSong(){ if(!playlist.length) return; currentIndex=Math.floor(Math.random()*playlist.length); loadSong(currentIndex); playSong(); }

  playBtn.onclick=()=>{ if(isPlaying) pauseSong(); else { if(currentIndex<0&&playlist.length) { currentIndex=0; loadSong(0);} playSong(); } };
  nextBtn.onclick=nextSong; prevBtn.onclick=prevSong; shuffleBtn.onclick=shuffleSong;

  audio.ontimeupdate=()=>{ if(isFinite(audio.duration)){progress.max=Math.floor(audio.duration);progress.value=Math.floor(audio.currentTime);} currentTimeEl.textContent=fmt(audio.currentTime); durationEl.textContent=fmt(audio.duration); };
  progress.oninput=()=>{ if(isFinite(audio.duration)) audio.currentTime=progress.value; };
  audio.onended=()=>{ if(useAutoNext) nextSong(); };

  // Bridge eksternal
  window.playSong = function(detail){
    if (!detail || !detail.src) return;
    useAutoNext = false; 
    playerTitle.textContent = detail.title || 'Unknown';
    playerArtist.textContent = detail.artist || 'Unknown';
    playerCover.src = detail.cover || '<?= htmlspecialchars("uploads/default_cover.png") ?>';
    audio.src = detail.src;
    playSong();
  };

  bindSongEvents();
})();

</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const ytModal = document.getElementById("ytModal");
  const minimizeBtn = document.getElementById("ytMinimizeBtn");
  const header = ytModal.querySelector(".modal-header");

  // Handler minimize
  minimizeBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    e.preventDefault();

    ytModal.classList.toggle("minimized");

    if (ytModal.classList.contains("minimized")) {
      // Hapus backdrop dan biarkan modal tetap tampil
      document.querySelectorAll(".modal-backdrop").forEach(el => el.remove());
      document.body.classList.remove("modal-open");
      ytModal.style.display = "block";
    } else {
      // Restore ke modal normal
      const modal = bootstrap.Modal.getOrCreateInstance(ytModal);
      modal.show();
    }
  });

  // Klik header saat minimized → restore
  header.addEventListener("click", (e) => {
    // Abaikan klik pada tombol minimize/close
    if (e.target.closest("button")) return;

    if (ytModal.classList.contains("minimized")) {
      ytModal.classList.remove("minimized");
      const modal = bootstrap.Modal.getOrCreateInstance(ytModal);
      modal.show();
    }
  });

  // Pastikan tidak auto-dismiss saat klik luar
  ytModal.addEventListener("hide.bs.modal", (e) => {
    if (ytModal.classList.contains("minimized")) {
      e.preventDefault();
    }
  });
});
</script>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</body>
</html>


