<?php require 'inc/app.php';
$sql=<<<'S'
(SELECT h.id hid,h.tgl_permohonan tgl,h.no_permohonan,h.nama h_nama,h.jk h_jk,h.tp h_tp,h.tl h_tl,h.keterangan h_ket,h.status_cetak,
  a.id aid,a.nama a_nama,a.jk a_jk,a.tp a_tp,a.tl a_tl,a.keterangan a_ket,
  COALESCE(h.nama,a.nama) nama_sort,
  IF(a.id IS NULL,'Tidak Ada','Ada') absen,
  CASE WHEN a.id IS NULL THEN 'Tidak Ada di Absensi'
       WHEN LOWER(TRIM(COALESCE(h.keterangan,'')))=LOWER(TRIM(COALESCE(a.keterangan,''))) THEN 'Sesuai'
       ELSE 'Keterangan Beda' END hasil
 FROM v_harian h LEFT JOIN v_absensi_latest a ON a.nama_key=h.nama_key
 UNION ALL
 SELECT NULL,a.tgl_absen,NULL,NULL,NULL,NULL,NULL,NULL,'-',a.id,a.nama,a.jk,a.tp,a.tl,a.keterangan,a.nama,'-','Tidak Ada di Harian'
 FROM rekap_absensi a WHERE NOT EXISTS(SELECT 1 FROM rekap_harian h WHERE h.nama_key=a.nama_key)) x
S;
$L=listing($sql,['h_nama','a_nama','no_permohonan','h_ket','a_ket','hasil'],'tgl','tgl DESC,nama_sort ASC,hid ASC,aid ASC',['nama_sort','h_nama','a_nama','tgl','hasil']);
head('Hasil Pengecekan','hasil_cek.php'); ?>
<div class="card"><div class="card-body">
  <div class="text-muted mb-3">Data terurut abjad A-Z. Kiri: Rekap Harian · Kanan: Rekap Absensi. Pencocokan berdasarkan nama (tanpa beda huruf besar/kecil) dan keterangan.</div>
  <?php filter_bar($L); ?>
  <div class="table-responsive"><table class="table table-hover table-bordered align-middle mb-0">
    <thead class="text-center"><tr><th rowspan="2">No</th><th colspan="9" class="table-primary">Rekap Harian</th><th colspan="5" class="table-info">Rekap Absensi</th><th colspan="2" class="table-success">Hasil</th></tr>
    <tr><th>Tgl.</th><th>No. Permohonan</th><th>Nama</th><th>L/P</th><th>TP</th><th>TL</th><th>Keterangan</th><th>Status Cetak</th><th>Absen</th>
    <th>Nama</th><th>L/P</th><th>TP</th><th>TL</th><th>Keterangan</th><th>Pengecekan</th><th></th></tr></thead><tbody>
  <?php if(!$L['rows'])echo '<tr><td colspan="16" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
  foreach($L['rows'] as $i=>$r): $h=$r['hid']!==null; $a=$r['aid']!==null; ?>
    <tr><td><?=$L['off']+$i+1?></td>
    <td><?=dmy($r['tgl'])?></td><td><?=e($r['no_permohonan'])?:'-'?></td><td><?=e($r['h_nama'])?:'-'?></td><td class="text-center"><?=$h?$r['h_jk']:'-'?></td>
    <td class="text-center"><?=$h?chk($r['h_tp']):'-'?></td><td class="text-center"><?=$h?chk($r['h_tl']):'-'?></td><td><?=ket_html($r['h_ket'])?></td><td><?=$h?badge($r['status_cetak']):'-'?></td><td><?=$h?badge($r['absen']):'-'?></td>
    <td><?=$a?e($r['a_nama']):'-'?></td><td class="text-center"><?=$a?$r['a_jk']:'-'?></td><td class="text-center"><?=$a?chk($r['a_tp']):'-'?></td><td class="text-center"><?=$a?chk($r['a_tl']):'-'?></td><td><?=ket_html($r['a_ket'])?></td>
    <td colspan="2"><?=badge($r['hasil'])?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php pager($L); ?>
</div></div>
<?php foot();
