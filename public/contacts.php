<?php
require_once __DIR__.'/../src/bootstrap.php';require_login();require_once __DIR__.'/../src/xlsx.php';
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $action=$_POST['action']??'import';
 if($action==='import' && isset($_FILES['file'])&&is_uploaded_file($_FILES['file']['tmp_name'])){
  try{
   $ext=strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
   $rows=$ext==='xlsx'?xlsx_rows($_FILES['file']['tmp_name']):csv_rows($_FILES['file']['tmp_name']);
   $res=contacts_from_rows($rows,trim($_POST['group_name']??''),isset($_POST['mobile_only']));
   if($res['error']){$msg='Hata: '.$res['error'];}
   else{
    $stmt=$pdo->prepare('INSERT INTO contacts(first_name,last_name,phone,group_name,company,source) VALUES(?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE first_name=COALESCE(VALUES(first_name),first_name),last_name=COALESCE(VALUES(last_name),last_name),
      company=COALESCE(VALUES(company),company),source=COALESCE(VALUES(source),source)');
    foreach($res['contacts'] as $c)$stmt->execute([$c['first_name'],$c['last_name'],$c['phone'],$c['group_name'],$c['company'],$c['source']]);
    $msg=count($res['contacts']).' kişi içe aktarıldı.'.($res['skipped_nonmobile']?' Cep telefonu olmayan '.$res['skipped_nonmobile'].' numara (sabit hat / yurt dışı) atlandı.':'').($res['skipped']?' Geçersiz '.$res['skipped'].' numara atlandı.':'');
   }
  }catch(Throwable $e){$msg='Hata: '.$e->getMessage();}
 }
 if($action==='optout'){
  $phones=phones_from_text($_POST['phones']??'');
  $stmt=$pdo->prepare('INSERT IGNORE INTO optouts(phone,note) VALUES(?,?)');
  foreach($phones as $p)$stmt->execute([$p,trim($_POST['note']??'')?:null]);
  $msg=count($phones).' numara ret listesine eklendi. Bu numaralara artık hiçbir SMS gönderilmez.';
 }
 if($action==='consent_grant' || $action==='consent_revoke'){
  // İzin numara bazında tutulur: numaranın bütün gruplardaki kayıtları güncellenir
  $phones=phones_from_text($_POST['phones']??'');
  $status=$action==='consent_grant'?'granted':'unknown';
  $stmt=$pdo->prepare('UPDATE contacts SET consent_status=? WHERE phone=?');$found=0;
  foreach($phones as $p){$stmt->execute([$status,$p]);if($stmt->rowCount())$found++;}
  $missing=count($phones)-$found;
  $msg=$action==='consent_grant'
   ? "$found numaranın izni \"verildi\" olarak işaretlendi.".($missing?" $missing numara kişi listesinde yok (önce içe aktarın).":'')
   : "$found numaranın izni kaldırıldı.";
 }
 if($action==='optout_remove'){
  $pdo->prepare('DELETE FROM optouts WHERE phone=?')->execute([normalize_phone($_POST['phone']??'')]);
  $msg='Numara ret listesinden çıkarıldı.';
 }
}
$totals=$pdo->query('SELECT COUNT(*) c, COUNT(DISTINCT phone) u FROM contacts')->fetch();
$groups=$pdo->query("SELECT COALESCE(group_name,'(grupsuz)') g, COUNT(*) c FROM contacts GROUP BY group_name ORDER BY g")->fetchAll();
$optouts=$pdo->query('SELECT * FROM optouts ORDER BY created_at DESC LIMIT 200')->fetchAll();
$optoutCount=(int)$pdo->query('SELECT COUNT(*) FROM optouts')->fetchColumn();
$grantedCount=(int)$pdo->query("SELECT COUNT(DISTINCT phone) FROM contacts WHERE consent_status='granted'")->fetchColumn();
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>Kişiler</title>
<style>body{font-family:Arial;max-width:900px;margin:35px auto}.box{padding:20px;background:#f5f7fb;border-radius:12px;margin-bottom:16px}textarea{width:100%}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:6px;text-align:left}</style>
<a href="index.php">← Panel</a><h1>Kişiler</h1>
<?php if($msg):?><div class="box"><b><?=e($msg)?></b></div><?php endif;?>
<div class="box"><h3>İçe aktar</h3>
<p><b>Desteklenen:</b> .xlsx ve .csv (ayraç <code>;</code> veya <code>,</code> otomatik bulunur)</p>
<p>Kolonlar: <code>telefon, ad, soyad, grup, firma</code> — ilk satır başlık olmalı.<br>
Data Hunter'ın <b>Firma CSV</b> ve normal <b>CSV</b> dosyaları doğrudan yüklenebilir (firma adı ve kaynak sayfa da alınır).</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="import">
<p><input type="file" name="file" accept=".xlsx,.csv" required></p>
<p><input name="group_name" placeholder="Grup adı (dosyada grup sütunu yoksa)" style="width:60%"></p>
<p><label><input type="checkbox" name="mobile_only" checked> Yalnız cep telefonlarını al (05…) — sabit hatlar (0212, 0216, 0850…) ve yurt dışı numaralar alınmaz</label></p>
<button>İçe Aktar</button></form></div>

<div class="box"><h3>Kayıtlar</h3><p>Toplam <?=$totals['c']?> kayıt, <?=$totals['u']?> farklı numara.</p>
<table><tr><th>Grup</th><th>Kişi</th></tr><?php foreach($groups as $g):?><tr><td><?=e($g['g'])?></td><td><?=$g['c']?></td></tr><?php endforeach;?></table></div>

<div class="box"><h3>İzinler (<?=$grantedCount?> numara izinli)</h3>
<p>WhatsApp ve ticari SMS yalnız izni verilmiş numaralara gider. Size numarasını verip mesaj almayı kabul eden kişileri (ör. SMS'inize olumlu dönenler, formu dolduranlar) burada işaretleyin.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<textarea name="phones" rows="4" placeholder="Her satıra bir numara" required></textarea>
<p><button name="action" value="consent_grant">İzin verildi olarak işaretle</button> <button name="action" value="consent_revoke">İzni kaldır</button></p></form></div>

<div class="box"><h3>Ret listesi (<?=$optoutCount?>)</h3>
<p>"SMS almak istemiyorum" diyen numaraları buraya ekleyin. Ret listesindeki numaralara <b>bilgilendirme dahil hiçbir</b> SMS gönderilmez; sonraki içe aktarmalarda da bu korunur.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="optout">
<textarea name="phones" rows="4" placeholder="Her satıra bir numara (0532..., +90532..., 532...)" required></textarea>
<p><input name="note" placeholder="Not (ör. SMS ile RET yazdı, 07.10.2026)" style="width:60%"> <button>Ret listesine ekle</button></p></form>
<?php if($optouts):?><table><tr><th>Numara</th><th>Not</th><th>Tarih</th><th></th></tr>
<?php foreach($optouts as $o):?><tr><td><?=e($o['phone'])?></td><td><?=e($o['note'])?></td><td><?=e($o['created_at'])?></td>
<td><form method="post" style="margin:0" onsubmit="return confirm('Bu numara ret listesinden çıkarılsın mı?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="optout_remove"><input type="hidden" name="phone" value="<?=e($o['phone'])?>"><button>Çıkar</button></form></td></tr><?php endforeach;?></table><?php endif;?>
</div>
</html>
