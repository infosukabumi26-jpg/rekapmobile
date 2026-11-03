<?php require 'inc/app.php';
$c=fn($sql)=>(int)$pdo->query($sql)->fetchColumn();
$box=[['Rekap Harian',$c('SELECT COUNT(*) FROM rekap_harian'),'primary','calendar-check'],['Rekap Absensi',$c('SELECT COUNT(*) FROM rekap_absensi'),'info','person-check'],
['Sudah Dicetak',$c("SELECT COUNT(*) FROM v_harian WHERE status_cetak='Sudah Dicetak'"),'success','printer'],['Belum Dicetak',$c("SELECT COUNT(*) FROM v_harian WHERE status_cetak='Belum Dicetak'"),'warning','hourglass-split']];
head('Dashboard','index.php'); ?>
<?php if ($dbMode === 'sheets'): ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-file-spreadsheet fs-4"></i>
  <div>Aplikasi terhubung ke <b>Google Spreadsheet (Cloud API)</b>. Data tersinkronisasi otomatis.</div>
</div>
<?php endif; ?>
<div class="row"><?php foreach($box as $b): ?>
<div class="col-6 col-lg-3"><div class="card text-bg-<?=$b[2]?> mb-3"><div class="card-body d-flex justify-content-between align-items-center">
  <div><div class="fs-2 fw-bold"><?=number_format($b[1])?></div><div><?=$b[0]?></div></div><i class="bi bi-<?=$b[3]?> fs-1 opacity-50"></i></div></div></div>
<?php endforeach; ?></div>
<div class="card"><div class="card-body"><h5>Alur penggunaan</h5><ol class="mb-0">
<li>Isi <b>Rekap Harian</b> dan <b>Rekap Absensi</b> (tambah manual atau import CSV).</li>
<li>Import <b>Data Sudah Dicetak</b>: status cetak otomatis tersinkron berdasarkan No. Permohonan.</li>
<li>Cek kecocokan nama &amp; keterangan di <b>Hasil Pengecekan</b>.</li>
<li>Cetak <b>Laporan Detail</b> per periode.</li>
<li>Kelola cloud database &amp; deploy di <b><a href="integrasi_sheets.php">Google Sheets &amp; Vercel</a></b>.</li></ol></div></div>
<?php foot(); ?>
