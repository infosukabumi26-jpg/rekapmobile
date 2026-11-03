<?php
require 'inc/app.php';

$gasUrl = gas_get_url();
$msg = '';
$msgType = 'info';

// Hitung data MySQL saat ini
$cntHarian = 0; $cntAbsensi = 0; $cntCetak = 0;
if ($dbMode === 'mysql') {
  try {
    $cntHarian = (int)$pdo->query('SELECT COUNT(*) FROM rekap_harian')->fetchColumn();
    $cntAbsensi = (int)$pdo->query('SELECT COUNT(*) FROM rekap_absensi')->fetchColumn();
    $cntCetak = (int)$pdo->query('SELECT COUNT(*) FROM sudah_dicetak')->fetchColumn();
  } catch (Exception $e) {}
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $action = $_POST['action'] ?? '';

  // 1. Simpan URL GAS
  if ($action === 'save_url') {
    $url = trim($_POST['gas_url'] ?? '');
    gas_save_url($url);
    $gasUrl = $url;
    flash('success', 'URL Google Apps Script berhasil disimpan.');
    header('Location: integrasi_sheets.php');
    exit;
  }

  // 2. Export / Migrasi seluruh data MySQL ke Google Sheets
  if ($action === 'export_all_to_sheets') {
    try {
      if (empty($gasUrl)) throw new Exception('URL Google Apps Script belum diisi.');
      
      // Ambil data dari database lokal
      $harian = $pdo->query('SELECT * FROM rekap_harian ORDER BY id ASC')->fetchAll();
      $absensi = $pdo->query('SELECT * FROM rekap_absensi ORDER BY id ASC')->fetchAll();
      $cetak = $pdo->query('SELECT * FROM sudah_dicetak ORDER BY id ASC')->fetchAll();

      // Kirim via batch import ke Google Apps Script
      $payload = [
        'rekap_harian' => $harian,
        'rekap_absensi' => $absensi,
        'sudah_dicetak' => $cetak
      ];

      $res = gas_call('batchImportAll', 'POST', $payload, 180);
      if (!$res || empty($res['success'])) {
        throw new Exception('Gagal export: ' . ($res['error'] ?? 'Unknown error'));
      }

      gas_invalidate_cache();
      flash('success', 'Berhasil mengekspor semua data ke Google Sheets! (' . count($harian) . ' harian, ' . count($absensi) . ' absensi, ' . count($cetak) . ' sudah dicetak).');
    } catch (Exception $ex) {
      flash('danger', 'Terjadi kesalahan saat ekspor: ' . $ex->getMessage());
    }
    header('Location: integrasi_sheets.php');
    exit;
  }

  // 3. Refresh Cache SQLite
  if ($action === 'refresh_cache') {
    gas_invalidate_cache();
    flash('success', 'Cache SQLite berhasil dibersihkan dan akan diperbarui dari Google Sheets.');
    header('Location: integrasi_sheets.php');
    exit;
  }
}

// Cek koneksi ke GAS jika URL sudah diisi
$ping = null;
if (!empty($gasUrl)) {
  $ping = gas_call('ping', 'GET', null, 10);
}

head('Integrasi Google Sheets & Vercel', 'integrasi_sheets.php');
?>

<div class="row g-3">
  <!-- Status Mode & Koneksi -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-link-45deg fs-4 text-primary"></i> Konfigurasi Google Apps Script
        </h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label small text-muted">Mode Database Aktif Saat Ini</label>
          <div>
            <?php if ($dbMode === 'sheets'): ?>
              <span class="badge text-bg-success fs-6"><i class="bi bi-file-spreadsheet"></i> Google Sheets (Cloud API)</span>
            <?php else: ?>
              <span class="badge text-bg-primary fs-6"><i class="bi bi-database"></i> MySQL Localhost (`rekapmobile`)</span>
            <?php endif; ?>
          </div>
          <small class="text-muted d-block mt-1">
            * Saat di-deploy ke Vercel, aplikasi otomatis berjalan dalam mode <b>Google Sheets</b>.
          </small>
        </div>

        <form method="post">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="save_url">
          <div class="mb-3">
            <label class="form-label fw-bold">URL Web App Google Apps Script</label>
            <input type="url" name="gas_url" class="form-control" value="<?=e($gasUrl)?>" placeholder="https://script.google.com/macros/s/.../exec" required>
            <div class="form-text">Dapatkan URL ini setelah menerapkan (Deploy) skrip di Google Spreadsheet Anda.</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan URL</button>
            <?php if (!empty($gasUrl)): ?>
              <a href="integrasi_sheets.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Cek Koneksi</a>
            <?php endif; ?>
          </div>
        </form>

        <hr class="my-4">

        <h6 class="fw-bold mb-2">Status Koneksi:</h6>
        <?php if (empty($gasUrl)): ?>
          <div class="alert alert-warning py-2 mb-0">
            <i class="bi bi-exclamation-triangle"></i> URL Web App belum dikonfigurasi. Silakan ikuti panduan di bawah.
          </div>
        <?php elseif ($ping && !empty($ping['success'])): ?>
          <div class="alert alert-success py-2 mb-2">
            <i class="bi bi-check-circle-fill"></i> <b>Terhubung ke Google Spreadsheet!</b><br>
            <small>Nama Spreadsheet: <b><?=e($ping['name']??'Google Sheet')?></b></small>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light"><tr><th>Sheet / Tabel</th><th class="text-center">Jumlah Baris di Sheets</th></tr></thead>
              <tbody>
                <tr><td>rekap_harian</td><td class="text-center fw-bold"><?=number_format($ping['counts']['rekap_harian']??0)?></td></tr>
                <tr><td>rekap_absensi</td><td class="text-center fw-bold"><?=number_format($ping['counts']['rekap_absensi']??0)?></td></tr>
                <tr><td>sudah_dicetak</td><td class="text-center fw-bold"><?=number_format($ping['counts']['sudah_dicetak']??0)?></td></tr>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-danger py-2 mb-0">
            <i class="bi bi-x-circle-fill"></i> <b>Gagal terhubung:</b><br>
            <small><?=e($ping['error'] ?? 'Pastikan URL benar dan izin akses "Anyone" (Siapa saja).')?></small>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Migrasi Data MySQL ke Sheets -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-cloud-arrow-up fs-4 text-success"></i> Migrasi Data MySQL ke Google Sheets
        </h5>
      </div>
      <div class="card-body">
        <p class="text-muted">
          Fitur ini akan menyalin seluruh data dari database MySQL lokal <b>`rekapmobile`</b> ke dalam Google Spreadsheet yang terhubung.
        </p>

        <div class="row g-2 mb-3">
          <div class="col-4">
            <div class="border rounded p-2 text-center bg-light">
              <div class="fs-4 fw-bold text-primary"><?=number_format($cntHarian)?></div>
              <small class="text-muted">Rekap Harian</small>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2 text-center bg-light">
              <div class="fs-4 fw-bold text-info"><?=number_format($cntAbsensi)?></div>
              <small class="text-muted">Rekap Absensi</small>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded p-2 text-center bg-light">
              <div class="fs-4 fw-bold text-success"><?=number_format($cntCetak)?></div>
              <small class="text-muted">Sudah Dicetak</small>
            </div>
          </div>
        </div>

        <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin mengekspor seluruh data MySQL ke Google Spreadsheet? Data di Google Sheets akan diperbarui sesuai data ini.');">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="export_all_to_sheets">
          <button class="btn btn-success w-100 py-2 fw-bold" <?=empty($gasUrl)?'disabled':''?>>
            <i class="bi bi-upload"></i> Ekspor Seluruh Data ke Google Sheets
          </button>
          <?php if (empty($gasUrl)): ?>
            <div class="form-text text-danger text-center">Isi dan simpan URL Web App terlebih dahulu untuk mengaktifkan tombol ekspor.</div>
          <?php endif; ?>
        </form>

        <hr class="my-4">

        <h6 class="fw-bold mb-2">Manajemen Cache SQLite Cloud:</h6>
        <p class="small text-muted mb-2">
          Pada mode Google Sheets, data di-cache sementara agar akses halaman tetap super cepat. Klik di bawah untuk menyegarkan cache.
        </p>
        <form method="post" class="d-inline">
          <?=csrf_field()?>
          <input type="hidden" name="action" value="refresh_cache">
          <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-repeat"></i> Segarkan Cache SQLite</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Panduan Setup Google Sheets & Vercel -->
  <div class="col-12">
    <div class="card">
      <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-journal-text fs-4 text-warning"></i> Panduan Lengkap: Google Spreadsheet, GitHub &amp; Vercel
        </h5>
      </div>
      <div class="card-body">
        <div class="row g-4">
          <!-- Step 1 & 2 -->
          <div class="col-md-6">
            <h6 class="fw-bold text-primary"><i class="bi bi-1-circle-fill"></i> Setup Google Spreadsheet &amp; Apps Script</h6>
            <ol class="small ps-3 mb-3">
              <li>Buka <a href="https://sheets.new" target="_blank">sheets.new</a> untuk membuat Google Spreadsheet baru. Beri judul (misal: <code>Database Rekap Mobile</code>).</li>
              <li>Klik menu <b>Ekstensi (Extensions) &gt; Apps Script</b>.</li>
              <li>Hapus semua isi kode default di editor, lalu salin dan tempelkan kode skrip dari tab di bawah ini (atau buka file <code>google_apps_script/Code.gs</code>).</li>
              <li>Pilih fungsi <code>setupDatabase</code> di dropdown atas, lalu klik <b>Run (Jalankan)</b> sekali untuk membuat sheet &amp; header otomatis.</li>
              <li>Klik tombol biru <b>Deploy (Terapkan) &gt; New deployment (Deployment baru)</b>.</li>
              <li>Pilih tipe <b>Web App (Aplikasi Web)</b>:
                <ul>
                  <li>Execute as (Jalankan sebagai): <b>Me (Saya)</b></li>
                  <li>Who has access (Akses): <b>Anyone (Siapa saja)</b></li>
                </ul>
              </li>
              <li>Klik <b>Deploy</b>, lalu salin <b>Web App URL</b> yang muncul dan tempelkan ke form di halaman ini.</li>
            </ol>

            <h6 class="fw-bold text-success mt-4"><i class="bi bi-2-circle-fill"></i> Migrasi Data MySQL</h6>
            <p class="small text-muted mb-0">
              Setelah URL disimpan dan terhubung, klik tombol hijau <b>"Ekspor Seluruh Data ke Google Sheets"</b> di atas. Seluruh 687 data harian, 37 absensi, dan 4.727 data sudah dicetak akan otomatis masuk ke Google Spreadsheet Anda!
            </p>
          </div>

          <!-- Step 3 & 4 -->
          <div class="col-md-6">
            <h6 class="fw-bold text-primary"><i class="bi bi-3-circle-fill"></i> Upload ke GitHub</h6>
            <ol class="small ps-3 mb-3">
              <li>Buka terminal pada folder <code>C:\xampp\htdocs\rekapmobile</code>.</li>
              <li>Inisialisasi git dan commit:
                <pre class="bg-light p-2 rounded mt-1"><code>git init
git add .
git commit -m "Initial commit RekapMobile dengan Google Sheets &amp; Vercel"</code></pre>
              </li>
              <li>Buat repository baru di GitHub (misal <code>rekapmobile</code>), lalu hubungkan dan push:
                <pre class="bg-light p-2 rounded mt-1"><code>git remote add origin https://github.com/USERNAME/rekapmobile.git
git branch -M main
git push -u origin main</code></pre>
              </li>
            </ol>

            <h6 class="fw-bold text-success mt-4"><i class="bi bi-4-circle-fill"></i> Deploy ke Vercel (Gratis)</h6>
            <ol class="small ps-3 mb-0">
              <li>Buka <a href="https://vercel.com" target="_blank">vercel.com</a> dan login dengan akun GitHub Anda.</li>
              <li>Klik <b>"Add New..." &gt; "Project"</b>, lalu pilih repository <code>rekapmobile</code>.</li>
              <li>Pada bagian <b>Environment Variables</b>, tambahkan variable berikut:
                <ul>
                  <li>Key: <code>GAS_URL</code>, Value: <i>(URL Web App Google Apps Script Anda)</i></li>
                  <li>Key: <code>DB_MODE</code>, Value: <code>sheets</code></li>
                </ul>
              </li>
              <li>Klik <b>Deploy</b>. Dalam 1-2 menit, aplikasi Anda sudah online dan dapat diakses dari mana saja (HP/Komputer) melalui link <code>https://rekapmobile.vercel.app</code>!</li>
            </ol>
          </div>
        </div>

        <hr class="my-4">

        <h6 class="fw-bold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-code-slash text-danger"></i> Kode Google Apps Script (Code.gs)</span>
          <button class="btn btn-outline-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('gasCode').innerText); alert('Kode Google Apps Script berhasil disalin ke clipboard!');">
            <i class="bi bi-clipboard"></i> Salin Kode
          </button>
        </h6>
        <pre id="gasCode" class="bg-dark text-white p-3 rounded" style="max-height: 250px; overflow-y: auto; font-size: 12px;"><?=e(file_get_contents(__DIR__ . '/google_apps_script/Code.gs'))?></pre>
      </div>
    </div>
  </div>
</div>

<?php foot(); ?>

