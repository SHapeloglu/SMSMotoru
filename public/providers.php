<?php
require_once __DIR__.'/../src/bootstrap.php';require_once __DIR__.'/../src/ProviderFactory.php';require_login();
$key=$config['security']['encryption_key'];
$waRowStmt=$pdo->prepare("SELECT * FROM providers WHERE name='whatsapp'");
$loadWa=function() use($waRowStmt,$key){$waRowStmt->execute();$r=$waRowStmt->fetch();return [$r,$r?(json_decode(decrypt_secret((string)$r['credentials_encrypted'],$key),true)?:[]):[]];};
$upsert=$pdo->prepare('INSERT INTO providers(name,enabled,credentials_encrypted,sender,price_per_sms,priority,supports_iys)
 VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),credentials_encrypted=VALUES(credentials_encrypted),
 sender=VALUES(sender),price_per_sms=VALUES(price_per_sms),priority=VALUES(priority),supports_iys=VALUES(supports_iys)');
$saved=null;$waTest=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $action=$_POST['action']??'sms';
 if($action==='sms'){
  $name=$_POST['name'];$cred=json_decode($_POST['credentials']??'{}',true)?:[];
  $upsert->execute([$name,isset($_POST['enabled'])?1:0,encrypt_secret(json_encode($cred,JSON_UNESCAPED_UNICODE),$key),$_POST['sender']??'',(float)($_POST['price']??0),(int)($_POST['priority']??100),isset($_POST['iys'])?1:0]);
  $saved='SMS sağlayıcı ayarları kaydedildi.';
 }
 if($action==='whatsapp'){
  [$row,$old]=$loadWa();
  $cred=[
   'display_phone'=>trim($_POST['display_phone']??''),
   'phone_number_id'=>trim($_POST['phone_number_id']??''),
   'waba_id'=>trim($_POST['waba_id']??''),
   'api_version'=>trim($_POST['api_version']??'')?:WhatsAppProvider::DEFAULT_API_VERSION,
   // Boş bırakılırsa kayıtlı anahtar korunur (anahtar sayfada hiçbir zaman gösterilmez)
   'access_token'=>trim($_POST['access_token']??'')?:($old['access_token']??''),
  ];
  $upsert->execute(['whatsapp',isset($_POST['enabled'])?1:0,encrypt_secret(json_encode($cred,JSON_UNESCAPED_UNICODE),$key),$cred['display_phone'],(float)($_POST['price']??0),1000,0]);
  $saved='WhatsApp ayarları kaydedildi.';
 }
 if($action==='whatsapp_test'){
  [$row,$cred]=$loadWa();
  $phone=normalize_phone($_POST['test_phone']??'');$tpl=trim($_POST['test_template']??'');$lang=trim($_POST['test_lang']??'')?:'tr';
  if(!$row)$waTest=['ok'=>false,'msg'=>'Önce WhatsApp ayarlarını kaydedin.'];
  elseif($phone==='')$waTest=['ok'=>false,'msg'=>'Geçerli bir telefon numarası girin.'];
  elseif(!wa_template_name_ok($tpl))$waTest=['ok'=>false,'msg'=>'Şablon adı yalnız küçük harf, rakam ve _ içerebilir.'];
  else{
   [$json]=wa_payload_json($tpl,$lang,param_lines($_POST['test_params']??''),['phone'=>$phone]);
   $res=provider_factory('whatsapp',$cred)->send($phone,$json,'');
   $pdo->prepare('INSERT INTO provider_logs(provider_id,action,http_code,success,response_body) VALUES(?,?,?,?,?)')->execute([$row['id'],'wa_test',$res['http_code']??null,$res['success']?1:0,$res['raw']??'']);
   $waTest=$res['success']?['ok'=>true,'msg'=>"Gönderildi. Mesaj kimliği: {$res['provider_id']}"]:['ok'=>false,'msg'=>'Gönderilemedi: '.$res['error']];
  }
 }
}
[$waRow,$wa]=$loadWa();
$rows=$pdo->query("SELECT * FROM providers WHERE name<>'whatsapp' ORDER BY priority,name")->fetchAll();
$tokenHint=!empty($wa['access_token'])?'Kayıtlı (…'.substr($wa['access_token'],-4).') — değiştirmek için yenisini yapıştırın':'Access Token';
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>Sağlayıcılar</title>
<style>body{font-family:Arial;max-width:1050px;margin:30px auto;background:#f7f8fb}.box{background:#fff;padding:22px;margin:15px 0;border-radius:12px}input,select,textarea{padding:9px;margin:4px 0}textarea{width:100%;font-family:monospace}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.grid2 input{width:100%;box-sizing:border-box}label small{color:#666}.ok{color:#15803d}.err{color:#b91c1c}.note{background:#fff7ed;border-left:4px solid #f97316;padding:10px 14px}</style>
<a href="index.php">← Panel</a><h1>Sağlayıcılar</h1>
<?php if($saved):?><p class="ok"><b><?=e($saved)?></b></p><?php endif;?>

<h2>SMS</h2>
<p>Fiyat alanı, otomatik en ucuz sağlayıcı seçimi için manuel referans fiyattır. Gerçek API fiyatını sağlayıcıdan otomatik çekmek mümkün değilse burada güncellenir.</p>
<div class="box"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="sms">
<div class="grid"><select name="name"><option>netgsm</option><option>mutlucell</option><option>vatansms</option><option>iletimerkezi</option></select>
<input name="sender" placeholder="Gönderici başlığı"><input name="price" type="number" step="0.000001" placeholder="TL/SMS">
<input name="priority" type="number" value="100" placeholder="Öncelik"><label><input type="checkbox" name="enabled" checked> Aktif</label><label><input type="checkbox" name="iys"> İYS destekli</label></div>
<p><textarea name="credentials" rows="6" placeholder='{"usercode":"","password":"","send_endpoint":"","balance_endpoint":"","iys_endpoint":""}'></textarea></p>
<button>Kaydet</button></form></div>
<table border="1" cellpadding="8" cellspacing="0" width="100%" bgcolor="white"><tr><th>Sağlayıcı</th><th>Aktif</th><th>TL/SMS</th><th>Öncelik</th><th>İYS</th></tr>
<?php foreach($rows as $r):?><tr><td><?=e($r['name'])?></td><td><?=$r['enabled']?'Evet':'Hayır'?></td><td><?=e((string)$r['price_per_sms'])?></td><td><?=$r['priority']?></td><td><?=$r['supports_iys']?'Evet':'Hayır'?></td></tr><?php endforeach;?></table>

<h2 id="whatsapp">WhatsApp (Meta Cloud API)</h2>
<p class="note">WhatsApp ile yalnız <b>izni "verildi"</b> olan kişilere gönderim yapılır (Meta kuralı: kişi numarasını vermiş ve WhatsApp mesajına onay vermiş olmalı). İzinler <a href="contacts.php">Kişiler</a> sayfasından yönetilir. Mesajlar Meta'nın onayladığı şablonlarla gider.</p>
<div class="box"><form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="whatsapp">
<div class="grid2">
<label>Görünen numara <small>(bilgi amaçlı, ör. +90 532 …)</small><input name="display_phone" value="<?=e($wa['display_phone']??'')?>"></label>
<label>Phone Number ID <small>(Meta > WhatsApp > API Setup)</small><input name="phone_number_id" value="<?=e($wa['phone_number_id']??'')?>" required></label>
<label>Access Token <small>(kalıcı "System User" anahtarı önerilir)</small><input name="access_token" type="password" placeholder="<?=e($tokenHint)?>" <?=empty($wa['access_token'])?'required':''?>></label>
<label>WhatsApp Business Account ID <small>(isteğe bağlı)</small><input name="waba_id" value="<?=e($wa['waba_id']??'')?>"></label>
<label>API sürümü <small>(Meta'nın güncel sürümü)</small><input name="api_version" value="<?=e($wa['api_version']??WhatsAppProvider::DEFAULT_API_VERSION)?>" pattern="v\d+\.\d+"></label>
<label>Mesaj başı fiyat <small>(TL, maliyet tahmini için)</small><input name="price" type="number" step="0.000001" value="<?=e((string)($waRow['price_per_sms']??''))?>"></label>
</div>
<p><label><input type="checkbox" name="enabled" <?=(!$waRow||$waRow['enabled'])?'checked':''?>> Aktif</label></p>
<button>WhatsApp ayarlarını kaydet</button></form></div>

<?php if($waRow):?>
<div class="box"><h3>Test gönderimi</h3>
<p>Kendi numaranıza şablon mesajı göndererek bağlantıyı deneyin. Yeni hesaplarda Meta'nın hazır <code>hello_world</code> şablonu (dil <code>en_US</code>, değişkensiz) vardır.</p>
<?php if($waTest):?><p class="<?=$waTest['ok']?'ok':'err'?>"><b><?=e($waTest['msg'])?></b></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="whatsapp_test">
<div class="grid2"><input name="test_phone" placeholder="Numara (ör. 0532 …)" required><input name="test_template" value="hello_world" placeholder="Şablon adı" required>
<input name="test_lang" value="en_US" placeholder="Dil kodu (tr, en_US)"><textarea name="test_params" rows="2" placeholder="Şablon değişkenleri, her satıra bir tane (yoksa boş bırakın)"></textarea></div>
<button>Test mesajı gönder</button></form></div>
<?php endif;?>
</html>
