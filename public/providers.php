<?php
require_once __DIR__.'/../src/bootstrap.php';require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $name=$_POST['name'];$cred=json_decode($_POST['credentials']??'{}',true)?:[];
 $enc=encrypt_secret(json_encode($cred,JSON_UNESCAPED_UNICODE),$config['security']['encryption_key']);
 $stmt=$pdo->prepare('INSERT INTO providers(name,enabled,credentials_encrypted,sender,price_per_sms,priority,supports_iys)
 VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),credentials_encrypted=VALUES(credentials_encrypted),
 sender=VALUES(sender),price_per_sms=VALUES(price_per_sms),priority=VALUES(priority),supports_iys=VALUES(supports_iys)');
 $stmt->execute([$name,isset($_POST['enabled'])?1:0,$enc,$_POST['sender']??'',(float)($_POST['price']??0),(int)($_POST['priority']??100),isset($_POST['iys'])?1:0]);
 $saved='Sağlayıcı ayarları kaydedildi.';
}
$rows=$pdo->query('SELECT * FROM providers ORDER BY priority,name')->fetchAll();
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>Sağlayıcılar</title>
<style>body{font-family:Arial;max-width:1050px;margin:30px auto;background:#f7f8fb}.box{background:#fff;padding:22px;margin:15px 0;border-radius:12px}input,select,textarea{padding:9px;margin:4px 0}textarea{width:100%;font-family:monospace}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}</style>
<a href="index.php">← Panel</a><h1>SMS Sağlayıcıları</h1>
<p>Fiyat alanı, otomatik en ucuz sağlayıcı seçimi için manuel referans fiyattır. Gerçek API fiyatını sağlayıcıdan otomatik çekmek mümkün değilse burada güncellenir.</p>
<?php if(isset($saved)):?><p><?=e($saved)?></p><?php endif;?>
<div class="box"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="grid"><select name="name"><option>netgsm</option><option>mutlucell</option><option>vatansms</option><option>iletimerkezi</option></select>
<input name="sender" placeholder="Gönderici başlığı"><input name="price" type="number" step="0.000001" placeholder="TL/SMS">
<input name="priority" type="number" value="100" placeholder="Öncelik"><label><input type="checkbox" name="enabled" checked> Aktif</label><label><input type="checkbox" name="iys"> İYS destekli</label></div>
<p><textarea name="credentials" rows="10" placeholder='{"usercode":"","password":"","send_endpoint":"","balance_endpoint":"","iys_endpoint":""}'></textarea></p>
<button>Kaydet</button></form></div>
<table border="1" cellpadding="8" cellspacing="0" width="100%" bgcolor="white"><tr><th>Sağlayıcı</th><th>Aktif</th><th>TL/SMS</th><th>Öncelik</th><th>İYS</th></tr>
<?php foreach($rows as $r):?><tr><td><?=e($r['name'])?></td><td><?=$r['enabled']?'Evet':'Hayır'?></td><td><?=e((string)$r['price_per_sms'])?></td><td><?=$r['priority']?></td><td><?=$r['supports_iys']?'Evet':'Hayır'?></td></tr><?php endforeach;?></table>
</html>