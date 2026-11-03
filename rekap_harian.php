<?php require 'inc/app.php';
// Handler polling realtime status cetak & absen
if(isset($_GET['action']) && $_GET['action'] === 'check_status_realtime'){
  header('Content-Type: application/json; charset=utf-8');
  $ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
  if(empty($ids)){
    echo json_encode(['success' => true, 'data' => []]);
    exit;
  }
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("SELECT id, no_permohonan, status_cetak, absen FROM v_harian WHERE id IN ($in)");
  $st->execute($ids);
  $rows = $st->fetchAll();
  $data = [];
  foreach($rows as $r){
    $data[] = [
      'id' => (int)$r['id'],
      'no' => $r['no_permohonan'],
      'status_cetak' => $r['status_cetak'],
      'badge_cetak' => badge($r['status_cetak']),
      'absen' => $r['absen'],
      'badge_absen' => badge($r['absen'])
    ];
  }
  $sc = (int)$pdo->query("SELECT COUNT(*) FROM v_harian WHERE status_cetak='Sudah Dicetak'")->fetchColumn();
  $bc = (int)$pdo->query("SELECT COUNT(*) FROM v_harian WHERE status_cetak='Belum Dicetak'")->fetchColumn();
  echo json_encode([
    'success' => true,
    'data' => $data,
    'sc' => $sc,
    'bc' => $bc,
    'time' => date('H:i:s')
  ]);
  exit;
}

if(isset($_GET['action']) && $_GET['action'] === 'sync_now'){
  sync_database_views($pdo, true);
  flash('success', 'Status cetak & database view berhasil disinkronkan secara realtime.');
  header('Location: rekap_harian.php');
  exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){ check_csrf(); $a=$_POST['action']??'';
  try{
    if($a==='import'){ [$t,$m]=do_import('harian'); flash($t,$m); }
    elseif($a==='delete'){ $pdo->prepare('DELETE FROM rekap_harian WHERE id=?')->execute([(int)$_POST['id']]); flash('success','Data dihapus.'); }
    elseif($a==='delete_periode'){
      $dari=ndate($_POST['dari']??''); $sampai=ndate($_POST['sampai']??'');
      if(!$dari||!$sampai) throw new Exception('Tanggal dari dan sampai wajib diisi.');
      if($sampai<$dari) throw new Exception('Tanggal sampai tidak boleh lebih awal dari tanggal dari.');
      $st=$pdo->prepare('DELETE FROM rekap_harian WHERE tgl_permohonan BETWEEN ? AND ?');
      $st->execute([$dari,$sampai]); $n=$st->rowCount();
      flash('success',"$n data periode ".dmy($dari)." s/d ".dmy($sampai)." berhasil dihapus.");
    }
    elseif($a==='save'){
      $tgl=ndate($_POST['tgl']??'');$no=clean_no($_POST['no']??'');$nama=trim($_POST['nama']??'');$jk=njk($_POST['jk']??'');
      if(!$tgl||$no===''||$nama===''||!$jk)throw new Exception('Tanggal, No. Permohonan, Nama, dan L/P wajib diisi.');
      $v=[$tgl,$no,$nama,nkey($nama),$jk,isset($_POST['tp'])?1:0,isset($_POST['tl'])?1:0,mk_ket()]; $id=(int)($_POST['id']??0);
      if($id){$v[]=$id;$pdo->prepare('UPDATE rekap_harian SET tgl_permohonan=?,no_permohonan=?,nama=?,nama_key=?,jk=?,tp=?,tl=?,keterangan=? WHERE id=?')->execute($v);}
      else $pdo->prepare('INSERT INTO rekap_harian(tgl_permohonan,no_permohonan,nama,nama_key,jk,tp,tl,keterangan) VALUES(?,?,?,?,?,?,?,?)')->execute($v);
      sync_database_views($pdo, true);
      flash('success','Data disimpan.');
    }
  }catch(Exception $x){flash('danger',$x->getMessage());}
  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}
$L=listing('v_harian',['nama','no_permohonan','keterangan','status_cetak','absen'],'tgl_permohonan','tgl_permohonan DESC,nama ASC,id ASC',['nama','tgl_permohonan','no_permohonan','keterangan','status_cetak','absen']);
head('Rekap Harian','rekap_harian.php'); ?>
<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="badge bg-success-subtle text-success border border-success d-inline-flex align-items-center gap-1 py-2 px-2" id="realtimeBadge">
        <span class="spinner-grow spinner-grow-sm text-success" style="width:0.5rem;height:0.5rem" role="status"></span>
        <span id="realtimeBadgeText">Realtime Sync: Aktif</span>
      </span>
      <button type="button" id="btnSyncRealtime" class="btn btn-sm btn-outline-success" title="Sinkronkan status cetak sekarang">
        <i class="bi bi-arrow-repeat" id="iconSync"></i> Sinkronkan Status
      </button>
      <span class="text-muted small d-none d-md-inline">&bull; Data terurut abjad A-Z &bull; Sinkron otomatis dengan Data Sudah Dicetak</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg"></i> Tambah Data</button>
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mImport"><i class="bi bi-filetype-csv"></i> Import CSV / Excel</button>
      <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#mDeletePeriode"><i class="bi bi-trash3"></i> Hapus Per Periode</button>
    </div></div>
  <?php filter_bar($L); ?>
  <div class="table-responsive"><table class="table table-hover table-bordered align-middle mb-0">
    <thead><tr><th>No</th><?=sort_th($L,'tgl_permohonan','Tgl. Permohonan')?><?=sort_th($L,'no_permohonan','No. Permohonan')?><?=sort_th($L,'nama','Nama')?><th class="text-center">L/P</th><th class="text-center">TP</th><th class="text-center">TL</th><?=sort_th($L,'keterangan','Keterangan')?><?=sort_th($L,'status_cetak','Status Cetak')?><?=sort_th($L,'absen','Absen')?><th class="text-center">Aksi</th></tr></thead><tbody>
  <?php if(!$L['rows'])echo '<tr><td colspan="11" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
  foreach($L['rows'] as $i=>$r): ?>
    <tr id="row-<?=$r['id']?>" data-id="<?=$r['id']?>" data-no="<?=e($r['no_permohonan'])?>"><td><?=$L['off']+$i+1?></td><td><?=dmy($r['tgl_permohonan'])?></td><td><?=e($r['no_permohonan'])?></td><td><?=e($r['nama'])?></td>
    <td class="text-center"><?=$r['jk']?></td><td class="text-center"><?=chk($r['tp'])?></td><td class="text-center"><?=chk($r['tl'])?></td>
    <td><?=ket_html($r['keterangan'])?></td>
    <td class="cell-status-cetak text-center" data-id="<?=$r['id']?>"><?=badge($r['status_cetak'])?></td>
    <td class="cell-absen text-center" data-id="<?=$r['id']?>"><?=badge($r['absen'])?></td>
    <td class="text-center text-nowrap"><button class="btn btn-sm btn-outline-primary btn-edit" data-r="<?=e(json_encode($r))?>"><i class="bi bi-pencil"></i></button>
    <form method="post" class="d-inline" onsubmit="return confirm('Hapus data ini?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php pager($L); ?>
</div></div>

<div class="modal fade" id="mForm" tabindex="-1"><div class="modal-dialog"><form method="post" id="fm" class="modal-content">
  <?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="modal-header"><h5 class="modal-title" id="mTitle">Tambah Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-3">
    <div class="col-6"><label class="form-label">Tgl. Permohonan</label><input type="date" name="tgl" class="form-control" required></div>
    <div class="col-6"><label class="form-label">No. Permohonan</label><input name="no" class="form-control" required></div>
    <div class="col-8"><label class="form-label">Nama</label><input name="nama" class="form-control" required></div>
    <div class="col-4"><label class="form-label">L/P</label><select name="jk" class="form-select" required><option value="">-</option><option>L</option><option>P</option></select></div>
    <div class="col-12 d-flex gap-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="tp" value="1" id="ctp"><label class="form-check-label" for="ctp">TP</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="tl" value="1" id="ctl"><label class="form-check-label" for="ctl">TL</label></div></div>
    <?=ket_fields()?>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div>
<?php import_modal('harian','Rekap Harian','Tgl. Permohonan, No. Permohonan, Nama, L/P, TP, TL, Keterangan','harian.csv'); ?>

<!-- Modal Hapus Per Periode -->
<div class="modal fade" id="mDeletePeriode" tabindex="-1" aria-labelledby="mDeletePeriodeLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" id="fmDeletePeriode" class="modal-content" onsubmit="return konfirmasiHapusPeriode()">
      <?=csrf_field()?><input type="hidden" name="action" value="delete_periode">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="mDeletePeriodeLabel"><i class="bi bi-trash3"></i> Hapus Data Per Periode</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
          <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
          <div><strong>Perhatian!</strong> Semua data rekap harian dalam rentang tanggal yang dipilih akan <strong>dihapus permanen</strong> dan tidak dapat dikembalikan.</div>
        </div>
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="dari" id="dpDari" class="form-control" required>
          </div>
          <div class="col-6">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="sampai" id="dpSampai" class="form-control" required>
          </div>
          <div class="col-12">
            <div class="form-text text-muted"><i class="bi bi-info-circle"></i> Data yang dihapus meliputi seluruh baris rekap harian yang tanggal permohonannya berada dalam rentang di atas (inklusif).</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Hapus Sekarang</button>
      </div>
    </form>
  </div>
</div>
<?php foot(); ?>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin-icon { display: inline-block; animation: spin 0.8s linear infinite; }
.row-updated { transition: background-color 0.8s ease; background-color: #d1e7dd !important; }
</style>
<script><?=ket_js()?>
document.addEventListener('DOMContentLoaded',()=>{
 const f=document.getElementById('fm'),m=new bootstrap.Modal('#mForm');
 document.getElementById('btnAdd').onclick=()=>{f.reset();f.id.value='';mTitle.textContent='Tambah Data';m.show()};
 document.querySelectorAll('.btn-edit').forEach(b=>b.onclick=()=>{const r=JSON.parse(b.dataset.r);f.reset();
  f.id.value=r.id;f.tgl.value=r.tgl_permohonan;f.no.value=r.no_permohonan;f.nama.value=r.nama;f.jk.value=r.jk;f.tp.checked=r.tp==1;f.tl.checked=r.tl==1;setKet(f,r.keterangan);
  mTitle.textContent='Edit Data';m.show()});

 // Realtime Synchronization Engine
 const rows = document.querySelectorAll('tr[data-id]');
 const ids = Array.from(rows).map(r => r.dataset.id).filter(Boolean);
 const badgeText = document.getElementById('realtimeBadgeText');
 const btnSync = document.getElementById('btnSyncRealtime');
 const iconSync = document.getElementById('iconSync');
 let isChecking = false;

 async function checkRealtimeSync(isManual = false) {
   if(!ids.length || isChecking) return;
   isChecking = true;
   if(isManual && iconSync) {
     iconSync.classList.add('spin-icon');
     if(btnSync) btnSync.disabled = true;
   }

   try {
     const url = 'rekap_harian.php?action=check_status_realtime&ids=' + encodeURIComponent(ids.join(','));
     const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
     if(!res.ok) throw new Error('HTTP ' + res.status);
     const json = await res.json();
     if(json.success && Array.isArray(json.data)) {
       let updatedCount = 0;
       json.data.forEach(item => {
         const cellCetak = document.querySelector('.cell-status-cetak[data-id="' + item.id + '"]');
         if(cellCetak) {
           const cur = cellCetak.innerHTML.trim();
           const next = item.badge_cetak.trim();
           if(cur !== next) {
             cellCetak.innerHTML = next;
             const tr = document.getElementById('row-' + item.id);
             if(tr) {
               tr.classList.add('row-updated');
               setTimeout(() => tr.classList.remove('row-updated'), 2500);
             }
             const btnEdit = tr ? tr.querySelector('.btn-edit') : null;
             if(btnEdit && btnEdit.dataset.r) {
               try {
                 const obj = JSON.parse(btnEdit.dataset.r);
                 obj.status_cetak = item.status_cetak;
                 obj.absen = item.absen;
                 btnEdit.dataset.r = JSON.stringify(obj);
               } catch(e){}
             }
             updatedCount++;
           }
         }

         const cellAbsen = document.querySelector('.cell-absen[data-id="' + item.id + '"]');
         if(cellAbsen && cellAbsen.innerHTML.trim() !== item.badge_absen.trim()) {
           cellAbsen.innerHTML = item.badge_absen;
         }
       });

       if(badgeText) {
         badgeText.textContent = 'Realtime Sync: Aktif (' + json.time + ')';
       }
       if(isManual) {
         if(updatedCount > 0) {
           alert('Sinkronisasi selesai: ' + updatedCount + ' data status cetak berhasil diperbarui!');
         } else {
           const toast = document.createElement('div');
           toast.className = 'alert alert-success position-fixed top-0 end-0 m-3 shadow';
           toast.style.zIndex = '9999';
           toast.innerHTML = '<i class="bi bi-check-circle"></i> Status cetak sudah 100% sinkron & realtime.';
           document.body.appendChild(toast);
           setTimeout(() => toast.remove(), 2500);
         }
       }
     }
   } catch(err) {
     if(badgeText) badgeText.textContent = 'Realtime Sync: Menghubungkan ulang...';
   } finally {
     isChecking = false;
     if(isManual && iconSync) {
       iconSync.classList.remove('spin-icon');
       if(btnSync) btnSync.disabled = false;
     }
   }
 }

 // Auto-polling setiap 4 detik (hanya saat tab aktif)
 setInterval(() => {
   if(!document.hidden) checkRealtimeSync(false);
 }, 4000);

 // Cek saat kembali ke tab ini
 document.addEventListener('visibilitychange', () => {
   if(!document.hidden) checkRealtimeSync(false);
 });

 // Tombol manual trigger
 if(btnSync) {
   btnSync.addEventListener('click', () => checkRealtimeSync(true));
 }
});

function konfirmasiHapusPeriode(){
  const dari=document.getElementById('dpDari').value;
  const sampai=document.getElementById('dpSampai').value;
  if(!dari||!sampai){alert('Tanggal dari dan sampai wajib diisi.');return false;}
  if(sampai<dari){alert('Tanggal sampai tidak boleh lebih awal dari tanggal dari.');return false;}
  const fmtDari=dari.split('-').reverse().join('-');
  const fmtSampai=sampai.split('-').reverse().join('-');
  return confirm('Yakin hapus SEMUA data rekap harian periode '+fmtDari+' s/d '+fmtSampai+'?\nTindakan ini tidak dapat dibatalkan!');
}
</script>
