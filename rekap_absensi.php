<?php require 'inc/app.php';
if($_SERVER['REQUEST_METHOD']==='POST'){ check_csrf(); $a=$_POST['action']??'';
  try{
    if($a==='import'){ [$t,$m]=do_import('absensi'); flash($t,$m); }
    elseif($a==='delete'){ $pdo->prepare('DELETE FROM rekap_absensi WHERE id=?')->execute([(int)$_POST['id']]); flash('success','Data dihapus.'); }
    elseif($a==='save'){
      $tgl=ndate($_POST['tgl']??'')?:date('Y-m-d');$nama=trim($_POST['nama']??'');$jk=njk($_POST['jk']??'');
      if($nama===''||!$jk)throw new Exception('Nama dan L/P wajib diisi.');
      $v=[$tgl,$nama,nkey($nama),$jk,isset($_POST['tp'])?1:0,isset($_POST['tl'])?1:0,mk_ket()]; $id=(int)($_POST['id']??0);
      if($id){$v[]=$id;$pdo->prepare('UPDATE rekap_absensi SET tgl_absen=?,nama=?,nama_key=?,jk=?,tp=?,tl=?,keterangan=? WHERE id=?')->execute($v);}
      else $pdo->prepare('INSERT INTO rekap_absensi(tgl_absen,nama,nama_key,jk,tp,tl,keterangan) VALUES(?,?,?,?,?,?,?)')->execute($v);
      flash('success','Data disimpan.');
    }
  }catch(Exception $x){flash('danger',$x->getMessage());}
  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}
$L=listing('v_absensi',['nama','keterangan','status_cetak','absen'],'tgl_absen','tgl_absen DESC,nama ASC,id ASC',['nama','tgl_absen','keterangan','status_cetak','absen']);
head('Rekap Absensi','rekap_absensi.php'); ?>
<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div class="text-muted">Data terurut abjad A-Z. Keterangan &amp; status cetak mengikuti Rekap Harian (dicocokkan berdasarkan nama).</div>
    <div class="d-flex gap-2"><button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg"></i> Tambah Data</button>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mImport"><i class="bi bi-filetype-csv"></i> Import CSV / Excel</button></div></div>
  <?php filter_bar($L); ?>
  <div class="table-responsive"><table class="table table-hover table-bordered align-middle mb-0">
    <thead><tr><th>No</th><?=sort_th($L,'tgl_absen','Tgl. Absen')?><?=sort_th($L,'nama','Nama')?><th class="text-center">L/P</th><th class="text-center">TP</th><th class="text-center">TL</th><?=sort_th($L,'keterangan','Keterangan')?><?=sort_th($L,'status_cetak','Status Cetak','text-center')?><?=sort_th($L,'absen','Absen','text-center')?><th class="text-center">Aksi</th></tr></thead><tbody>
  <?php if(!$L['rows'])echo '<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
  foreach($L['rows'] as $i=>$r): ?>
    <tr><td><?=$L['off']+$i+1?></td><td><?=dmy($r['tgl_absen'])?></td><td><?=e($r['nama'])?></td>
    <td class="text-center"><?=$r['jk']?></td><td class="text-center"><?=chk($r['tp'])?></td><td class="text-center"><?=chk($r['tl'])?></td>
    <td><?=ket_html($r['keterangan'])?></td><td class="text-center"><?=badge($r['status_cetak'])?></td><td class="text-center"><?=badge($r['absen'])?></td>
    <td class="text-center text-nowrap"><button class="btn btn-sm btn-outline-primary btn-edit" data-r="<?=e(json_encode($r))?>"><i class="bi bi-pencil"></i></button>
    <form method="post" class="d-inline" onsubmit="return confirm('Hapus data ini?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php pager($L); ?>
</div></div>

<div class="modal fade" id="mForm" tabindex="-1"><div class="modal-dialog"><form method="post" id="fm" class="modal-content">
  <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title" id="mTitle">Tambah Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-3">
    <div class="col-12"><label class="form-label">Tgl. Absen</label><input type="date" name="tgl" class="form-control"></div>
    <div class="col-8"><label class="form-label">Nama</label><input name="nama" class="form-control" required></div>
    <div class="col-4"><label class="form-label">L/P</label><select name="jk" class="form-select" required><option value="">-</option><option>L</option><option>P</option></select></div>
    <div class="col-12 d-flex gap-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="tp" value="1" id="ctp"><label class="form-check-label" for="ctp">TP</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="tl" value="1" id="ctl"><label class="form-check-label" for="ctl">TL</label></div></div>
    <?=ket_fields()?>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div>
<?php import_modal('absensi','Rekap Absensi','Tanggal (opsional), Nama, L/P, TP, TL, Keterangan','absensi.csv'); foot(); ?>
<script><?=ket_js()?>
document.addEventListener('DOMContentLoaded',()=>{
 const f=document.getElementById('fm'),m=new bootstrap.Modal('#mForm');
 document.getElementById('btnAdd').onclick=()=>{f.reset();f.id.value='';mTitle.textContent='Tambah Data';m.show()};
 document.querySelectorAll('.btn-edit').forEach(b=>b.onclick=()=>{const r=JSON.parse(b.dataset.r);f.reset();
  f.id.value=r.id;f.tgl.value=r.tgl_absen;f.nama.value=r.nama;f.jk.value=r.jk;f.tp.checked=r.tp==1;f.tl.checked=r.tl==1;setKet(f,r.ket_asli);
  mTitle.textContent='Edit Data';m.show()});
});
</script>
