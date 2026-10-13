<?php
// views/page_playlist.php

if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'xmlhttprequest') {
  $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // /webmusic
  header("Location: {$base}/app.php?page=playlist");
  exit;
}

require_once __DIR__ . '/../konek.php';
// ================================
// CEK SESSION ROLE ADMIN
// ================================
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';


// Ambil playlists
$playlists = [];
if (isset($conn) && !$conn->connect_error) {
  if ($res = $conn->query("SELECT id, name FROM playlist ORDER BY id DESC")) {
    $playlists = $res->fetch_all(MYSQLI_ASSOC);
  }
}

// ABS base utk API/link
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$ABS_BASE = $scheme . '://' . $host . $basePath;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2><i class="bi bi-music-note-beamed text-success"></i> Playlists</h2>
</div>

<!-- Form tambah -->
<form id="createPlaylistForm" class="mb-4" method="post" action="<?= htmlspecialchars($ABS_BASE) ?>/api/playlist_create.php">
  <div class="input-group">
    <input type="text" name="new_playlist_name" class="form-control" placeholder="Nama playlist baru..." required>
    <button class="btn btn-success" type="submit">
      <i class="bi bi-plus-circle"></i> Tambah
    </button>
  </div>
</form>

<!-- Daftar -->
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3" id="playlistContainer">
  <?php if (empty($playlists)): ?>
    <div class="text-muted">Belum ada playlist. Tambahkan dulu yuk!</div>
  <?php else: foreach ($playlists as $p): ?>
    <div class="col" id="playlist-<?= (int)$p['id'] ?>">
      <div class="card bg-dark text-white h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title mb-3" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></h5>
          <div class="mt-auto d-flex justify-content-between">
            <a href="?page=playlist_detail&id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-light ajax-link">
              <i class="bi bi-folder2-open"></i> Buka
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-playlist" data-id="<?= (int)$p['id'] ?>">
              <i class="bi bi-trash"></i> Hapus
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>
(() => {
  const ABS_BASE = <?= json_encode($ABS_BASE) ?>;
  const API = n => ABS_BASE + '/api/' + n;
  const esc = s => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  function makeCard(id, name){
    const div = document.createElement('div');
    div.className = 'col';
    div.id = 'playlist-'+id;
    div.innerHTML = `
      <div class="card bg-dark text-white h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title mb-3" title="${esc(name)}">${esc(name)}</h5>
          <div class="mt-auto d-flex justify-content-between">
            <a href="?page=playlist_detail&id=${id}" class="btn btn-sm btn-outline-light ajax-link">
              <i class="bi bi-folder2-open"></i> Buka
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-playlist" data-id="${id}">
              <i class="bi bi-trash"></i> Hapus
            </button>
          </div>
        </div>
      </div>`;
    return div;
  }

  // === CREATE ===
  async function onCreateSubmit(e){
    e.preventDefault();
    const form = e.currentTarget;
    const btn  = form.querySelector('button[type="submit"]');
    const old  = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Tambah...';
    try{
      const res = await fetch(API('playlist_create.php'), {
        method:'POST',
        body: new FormData(form),
        headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin'
      });
      const data = await res.json();
      if (data?.success || data?.status === 'ok' || data?.id) {
        const container = document.getElementById('playlistContainer');
        if (container.querySelector('.text-muted')) container.innerHTML = '';
        container.prepend(makeCard(data.id, data.name || form.new_playlist_name.value));
        form.reset();
        alert('Playlist berhasil dibuat!');
      } else {
        alert(data?.error || 'Gagal membuat playlist');
      }
    } catch(err){
      console.error(err);
      alert('Terjadi kesalahan saat membuat playlist');
    } finally {
      btn.disabled = false;
      btn.innerHTML = old;
    }
  }

  // === DELETE ===
  async function onContainerClick(e){
    const btn = e.target.closest('.btn-remove-playlist');
    if(!btn) return;
    const id = btn.dataset.id;
    if(!id) return;
    if(!confirm('Hapus playlist ini?')) return;

    const old = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Hapus...';
    try{
      const res = await fetch(API('playlist_remove.php'), {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: new URLSearchParams({playlist_id:id}),
        credentials:'same-origin'
      });
      const data = await res.json();
      if(data?.success || data?.status === 'ok'){
        document.getElementById('playlist-'+id)?.remove();
        const container = document.getElementById('playlistContainer');
        if(!container.querySelector('.col')){
          container.innerHTML = '<div class="text-muted">Belum ada playlist. Tambahkan dulu yuk!</div>';
        }
      }else{
        alert(data?.error || 'Gagal menghapus playlist');
      }
    }catch(err){
      console.error(err);
      alert('Terjadi kesalahan saat menghapus playlist');
    }finally{
      btn.disabled=false; btn.innerHTML=old;
    }
  }

  // === INIT ===
  function bindPlaylistHandlers(){
    document.getElementById('createPlaylistForm')?.addEventListener('submit', onCreateSubmit);
    document.getElementById('playlistContainer')?.addEventListener('click', onContainerClick);
  }

  window.bindPlaylistHandlers = bindPlaylistHandlers;
  bindPlaylistHandlers();
})();
</script>

