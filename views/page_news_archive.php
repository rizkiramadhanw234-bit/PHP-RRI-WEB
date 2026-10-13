<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../konek.php';

// ============ BASE URL otomatis =============
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$root = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$ABS_BASE = $scheme . '://' . $host . $root;

// Ambil filter
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$acara = $_GET['acara'] ?? '';

$sql = "SELECT n.id, n.judul, n.acara, n.reporter, n.file_path, n.aired_date, n.created_at,
               u.username AS uploader
        FROM news n
        LEFT JOIN users u ON u.id = n.uploaded_by
        WHERE 1=1";
$params = [];
~
  $types = '';

if ($acara !== '') {
  $sql .= " AND n.acara = ?";
  $params[] = $acara;
  $types .= 's';
}
if ($from !== '') {
  $sql .= " AND DATE(COALESCE(n.aired_date, n.created_at)) >= ?";
  $params[] = $from;
  $types .= 's';
}
if ($to !== '') {
  $sql .= " AND DATE(COALESCE(n.aired_date, n.created_at)) <= ?";
  $params[] = $to;
  $types .= 's';
}

$sql .= " ORDER BY COALESCE(n.aired_date, n.created_at) DESC";
$stmt = $conn->prepare($sql);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
?>

<div class="container py-4">
  <h2 class="mb-4 text-warning"><i class="bi bi-archive"></i> Arsip Berita</h2>

  <!-- Filter -->
  <form id="archiveFilter" class="row g-2 mb-4" onsubmit="return false;">
    <input type="hidden" name="page" value="news_archive">
    <div class="col-md-3">
      <label class="form-label">Dari Tanggal</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
        class="form-control bg-secondary text-white">
    </div>
    <div class="col-md-3">
      <label class="form-label">Sampai Tanggal</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control bg-secondary text-white">
    </div>
    <div class="col-md-3">
      <label class="form-label">Acara</label>
      <select name="acara" class="form-select bg-secondary text-white">
        <option value="">Semua Acara</option>
        <option value="Berita Daerah Pagi" <?= $acara === 'Berita Daerah Pagi' ? 'selected' : ''; ?>>Berita Daerah Pagi
        </option>
        <option value="Berita Daerah Siang" <?= $acara === 'Berita Daerah Siang' ? 'selected' : ''; ?>>Berita Daerah Siang
        </option>
        <option value="Berita Sepekan" <?= $acara === 'Berita Sepekan' ? 'selected' : ''; ?>>Berita Sepekan</option>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-warning w-100" id="btnFilter">
        <i class="bi bi-funnel"></i> Tampilkan
      </button>
    </div>
  </form>

  <a id="spaLink" class="ajax-link d-none"></a>

  <!-- Tabel arsip -->
  <div class="card bg-dark text-light">
    <div class="card-body">
      <h5 class="card-title mb-3"><i class="bi bi-clock-history"></i> Riwayat Upload</h5>
      <div class="table-responsive">
        <table class="table table-dark table-striped align-middle text-nowrap">
          <thead>
            <tr>
              <th>#</th>
              <th>Judul</th>
              <th>Acara</th>
              <th>Reporter</th>
              <th>Uploader</th>
              <th>Tanggal Siar</th>
              <th>Tanggal Upload</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="8" class="text-center text-secondary">Belum ada data berita.</td>
              </tr>
            <?php else:
              foreach ($rows as $i => $r): ?>
                <?php
                $title = $r['judul'] ?: '(Tanpa Judul)';
                $absSrc = $ABS_BASE . '/' . ltrim($r['file_path'], '/');
                ?>
                <tr data-id="<?= (int) $r['id'] ?>">
                  <td><?= $i + 1 ?></td>
                  <td><?= htmlspecialchars($title) ?></td>
                  <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['acara']) ?></span></td>
                  <td><?= htmlspecialchars($r['reporter']) ?></td>
                  <td><?= htmlspecialchars($r['uploader'] ?? '-') ?></td>
                  <td><?= $r['aired_date'] ? htmlspecialchars(date('d M Y', strtotime($r['aired_date']))) : '-' ?></td>
                  <td><?= htmlspecialchars(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
                  <td class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light btn-play"
                      data-src="<?= htmlspecialchars($absSrc) ?>" data-title="<?= htmlspecialchars($title) ?>"
                      data-artist="<?= htmlspecialchars($r['reporter']) ?>">
                      <i class="bi bi-play-circle"></i> Play
                    </button>

                    <button type="button" class="btn btn-sm btn-outline-success btn-add-playlist"
                      data-src="<?= htmlspecialchars($absSrc) ?>" data-title="<?= htmlspecialchars($title) ?>"
                      data-artist="<?= htmlspecialchars($r['reporter']) ?>"
                      data-acara="<?= htmlspecialchars($r['acara']) ?>"
                      data-cover="<?= $ABS_BASE ?>/uploads/default_cover.png">
                      <i class="bi bi-plus-circle"></i> + Playlist
                    </button>

                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-news"
                      data-id="<?= (int) $r['id'] ?>" data-title="<?= htmlspecialchars($title) ?>">
                      <i class="bi bi-trash"></i> Hapus
                    </button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  (() => {
    const BASE = "<?= $ABS_BASE ?>";
    const API_ADD = BASE + "/api/playlist_add_news.php";
    const API_DEL = BASE + "/api/delete_news.php";

    // 🔊 Play
    document.querySelectorAll('.btn-play').forEach(btn => {
      btn.onclick = () => {
        const detail = {
          src: btn.dataset.src,
          title: btn.dataset.title,
          artist: btn.dataset.artist,
          cover: btn.dataset.cover || (BASE + "/uploads/default_cover.png")
        };
        if (window.playSong) window.playSong(detail);
      };
    });

    // ➕ Tambah ke playlist aktif
    document.querySelectorAll('.btn-add-playlist').forEach(btn => {
      btn.onclick = async () => {
        const sel = document.getElementById('playlistSelect');
        const pid = sel ? sel.value : 1;

        const row = btn.closest('tr');
        const newsId = row?.dataset.id || 0;

        const params = new URLSearchParams({
          playlist_id: pid,
          news_id: newsId,
          acara: btn.dataset.acara,
          title: btn.dataset.title,
          artist: btn.dataset.artist,
          cover: btn.dataset.cover,
          file_path: btn.dataset.src
        });


        try {
          const res = await fetch(API_ADD, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: params.toString()
          });
          const data = await res.json();
          alert(data.success ? "✅ Ditambahkan ke playlist!" : "❌ " + (data.error || "Gagal menambah."));
        } catch (e) {
          alert("⚠️ Gagal menghubungi server.\n" + e.message);
        }
      };
    });

    // 🗑️ Hapus berita
    document.querySelectorAll('.btn-delete-news').forEach(btn => {
      btn.onclick = async () => {
        const id = btn.dataset.id;
        const title = btn.dataset.title;
        if (!confirm(`Yakin ingin menghapus "${title}"?`)) return;
        try {
          const res = await fetch(API_DEL, {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
              "X-Requested-With": "XMLHttpRequest" 
            },
            body: new URLSearchParams({ id })
          });
          const data = await res.json();
          if (data.success) {
            btn.closest('tr').remove();
            alert("🗑️ Berita dihapus.");
          } else {
            alert("❌ " + (data.error || "Gagal hapus."));
          }
        } catch (e) {
          alert("⚠️ Tidak bisa menghubungi API.\n" + e.message);
        }
      };
    });

    // 🔄 Filter
    document.getElementById("archiveFilter")?.addEventListener("submit", e => {
      e.preventDefault();
      const q = new URLSearchParams(new FormData(e.target)).toString();
      document.getElementById("spaLink").href = "views/page_news_archive.php?page=news_archive&" + q;
      document.getElementById("spaLink").click();
    });
  })();
</script>