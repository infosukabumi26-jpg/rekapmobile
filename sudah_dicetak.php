<?php require 'inc/app.php';
if($_SERVER['REQUEST_METHOD']==='POST'){ check_csrf(); $a=$_POST['action']??'';
  try{
    if($a==='import'){ [$t,$m]=do_import('sudah'); sync_database_views($pdo, true); flash($t,$m); }
    elseif($a==='delete'){ 
      $pdo->prepare('DELETE FROM sudah_dicetak WHERE id=?')->execute([(int)$_POST['id']]); 
      sync_database_views($pdo, true);
      flash('success','Data dihapus.'); 
    }
    elseif($a==='save'){
      $tgl=ndate($_POST['tgl']??'');$no=clean_no($_POST['no']??'');$nama=trim($_POST['nama']??'');
      if(!$tgl||$no===''||$nama==='')throw new Exception('Tanggal, No. Permohonan, dan Nama Pemohon wajib diisi.');
      $id=(int)($_POST['id']??0);
      if($id){
        $pdo->prepare('UPDATE sudah_dicetak SET tgl_permohonan=?,no_permohonan=?,nama_pemohon=? WHERE id=?')->execute([$tgl,$no,$nama,$id]);
      } else {
        $pdo->prepare('INSERT INTO sudah_dicetak(tgl_permohonan,no_permohonan,nama_pemohon) VALUES(?,?,?) ON DUPLICATE KEY UPDATE tgl_permohonan=VALUES(tgl_permohonan),nama_pemohon=VALUES(nama_pemohon)')->execute([$tgl,$no,$nama]);
      }
      sync_database_views($pdo, true);
      flash('success','Data sudah dicetak berhasil disimpan.');
    }
  }catch(Exception $x){flash('danger',$x->getMessage());}
  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}
$L=listing('sudah_dicetak',['nama_pemohon','no_permohonan'],'tgl_permohonan','tgl_permohonan DESC,nama_pemohon ASC,id ASC',['nama_pemohon','tgl_permohonan','no_permohonan']);
head('Data Sudah Dicetak','sudah_dicetak.php'); ?>
<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div class="text-muted">Data terurut abjad A-Z. No. Permohonan di sini langsung menentukan status cetak pada Rekap Harian &amp; Absensi secara <b>realtime otomatis</b>.</div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" id="btnAddSudah"><i class="bi bi-plus-lg"></i> Tambah Data</button>
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mImport"><i class="bi bi-filetype-csv"></i> Import CSV / Excel</button>
    </div>
  </div>
  <?php filter_bar($L); ?>
  <div class="table-responsive"><table class="table table-hover table-bordered align-middle mb-0">
    <thead><tr><th>No</th><?=sort_th($L,'tgl_permohonan','Tgl. Permohonan')?><?=sort_th($L,'no_permohonan','No. Permohonan')?><?=sort_th($L,'nama_pemohon','Nama Pemohon')?><th class="text-center">Aksi</th></tr></thead><tbody>
  <?php if(!$L['rows'])echo '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
  foreach($L['rows'] as $i=>$r): ?>
    <tr><td><?=$L['off']+$i+1?></td><td><?=dmy($r['tgl_permohonan'])?></td><td><?=e($r['no_permohonan'])?></td><td><?=e($r['nama_pemohon'])?></td>
    <td class="text-center text-nowrap">
      <button class="btn btn-sm btn-outline-primary btn-edit" data-r="<?=e(json_encode($r))?>"><i class="bi bi-pencil"></i></button>
      <form method="post" class="d-inline" onsubmit="return confirm('Hapus data ini? Status di Rekap Harian akan otomatis kembali menjadi Belum Dicetak.')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php pager($L); ?>
</div></div>

<!-- Modal Tambah/Edit Data Sudah Dicetak -->
<div class="modal fade" id="mFormSudah" tabindex="-1"><div class="modal-dialog"><form method="post" id="fmSudah" class="modal-content">
  <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title" id="mTitleSudah">Tambah Data Sudah Dicetak</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-3">
    <div class="col-6"><label class="form-label">Tgl. Permohonan</label><input type="date" name="tgl" class="form-control" value="<?=date('Y-m-d')?>" required></div>
    <div class="col-6"><label class="form-label">No. Permohonan</label><input name="no" class="form-control" placeholder="mis. PM-0001" required></div>
    <div class="col-12"><label class="form-label">Nama Pemohon</label><input name="nama" class="form-control" required></div>
    <div class="col-12 form-text text-muted">Begitu data ini disimpan, status permohonan dengan No. Permohonan yang sama pada Rekap Harian akan <b>langsung berubah menjadi "Sudah Dicetak"</b> secara realtime.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div>
<?php
$modeField = '
<div class="mt-3 border rounded p-3 bg-light">
  <label class="form-label fw-semibold mb-2"><i class="bi bi-gear"></i> Mode Import (jika No. Permohonan sudah ada)</label>
  <div class="d-flex flex-column gap-2">
    <div class="form-check">
      <input class="form-check-input" type="radio" name="import_mode" id="modeSkip" value="skip" checked>
      <label class="form-check-label" for="modeSkip">
        <strong>Lewati</strong> &mdash; data yang sudah ada tidak diubah
      </label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="import_mode" id="modeReplace" value="replace">
      <label class="form-check-label" for="modeReplace">
        <strong>Timpa / Replace</strong> &mdash; data yang sudah ada diperbarui (tanggal &amp; nama)
      </label>
    </div>
  </div>
</div>';
import_modal('sudah','Data Sudah Dicetak','Tgl. Permohonan, No. Permohonan, Nama Pemohon','sudah_dicetak.csv',$modeField); foot(); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const f = document.getElementById('fmSudah'), m = new bootstrap.Modal('#mFormSudah'), title = document.getElementById('mTitleSudah');
  const btnAdd = document.getElementById('btnAddSudah');
  if(btnAdd) {
    btnAdd.onclick = () => {
      f.reset();
      f.id.value = '';
      f.tgl.value = '<?=date('Y-m-d')?>';
      title.textContent = 'Tambah Data Sudah Dicetak';
      m.show();
    };
  }
  document.querySelectorAll('.btn-edit').forEach(b => {
    b.onclick = () => {
      const r = JSON.parse(b.dataset.r);
      f.reset();
      f.id.value = r.id;
      f.tgl.value = r.tgl_permohonan;
      f.no.value = r.no_permohonan;
      f.nama.value = r.nama_pemohon;
      title.textContent = 'Edit Data Sudah Dicetak';
      m.show();
    };
  });
});
</script>
