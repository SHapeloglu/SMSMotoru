<?php
require_once __DIR__.'/../src/bootstrap.php';require_once __DIR__.'/../src/ProviderFactory.php';require_login();
// SMS sağlayıcıları; WhatsApp ayrı kanal olduğu için "otomatik en ucuz" seçimine hiçbir zaman girmez
$providers=$pdo->query("SELECT * FROM providers WHERE enabled=1 AND name<>'whatsapp' ORDER BY priority,price_per_sms")->fetchAll();
$wa=$pdo->query("SELECT * FROM providers WHERE enabled=1 AND name='whatsapp'")->fetch();
$groups=$pdo->query("SELECT DISTINCT group_name FROM contacts WHERE group_name IS NOT NULL AND group_name<>'' ORDER BY group_name")->fetchAll();
$result=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $channel=($_POST['channel']??'sms')==='whatsapp'?'whatsapp':'sms';
 $template=trim($_POST['message']??'');$type=$_POST['message_type']??'informational';$group=$_POST['group_name']??'';
 $waTemplate=trim($_POST['wa_template']??'');$waLang=trim($_POST['wa_lang']??'')?:'tr';$waParams=param_lines($_POST['wa_params']??'');
 $contactsStmt=$group?$pdo->prepare('SELECT * FROM contacts WHERE group_name=? ORDER BY id'):null;
 if($contactsStmt){$contactsStmt->execute([$group]);$contacts=$contactsStmt->fetchAll();}else{$contacts=$pdo->query('SELECT * FROM contacts ORDER BY id')->fetchAll();}

 $error=null;
 if($channel==='whatsapp'){
  $p=$wa;
  if(!$p)$error='WhatsApp ayarlı ya da aktif değil (Sağlayıcılar > WhatsApp).';
  elseif(!wa_template_name_ok($waTemplate))$error='Geçerli bir WhatsApp şablon adı girin (küçük harf, rakam, _).';
 }else{
  $pid=$_POST['provider_id']??'auto';
  if($pid==='auto'){
   $providersWithPrice=array_filter($providers,fn($p)=>(float)$p['price_per_sms']>0);
   usort($providersWithPrice,fn($a,$b)=>((float)$a['price_per_sms']<=>$b['price_per_sms'])?:($a['priority']<=>$b['priority']));
   $p=$providersWithPrice[0]??$providers[0]??null;
  }else{$q=$pdo->prepare("SELECT * FROM providers WHERE id=? AND enabled=1 AND name<>'whatsapp'");$q->execute([(int)$pid]);$p=$q->fetch();}
  if(!$p||!$template)$error='Sağlayıcı veya mesaj eksik.';
 }

 if($error){$result=$error;}
 else{
  // Alıcıları önce süz, sonra say: ret listesi her türde hariç; ticari SMS'te ve WhatsApp'ta yalnız izinliler; aynı numara bir kez
  $needConsent=$channel==='whatsapp' || $type==='commercial';
  $optout=array_flip($pdo->query('SELECT phone FROM optouts')->fetchAll(PDO::FETCH_COLUMN));
  $recipients=[];$skippedOptout=0;$skippedConsent=0;$skippedMissing=0;$rendered=[];
  foreach($contacts as $c){
   if(isset($optout[$c['phone']])){$skippedOptout++;continue;}
   if($needConsent && $c['consent_status']!=='granted'){$skippedConsent++;continue;}
   if(isset($recipients[$c['phone']]))continue;
   if($channel==='whatsapp'){
    [$json,$missing]=wa_payload_json($waTemplate,$waLang,$waParams,$c);
    if($missing){$skippedMissing++;continue;}   // ör. {FIRMA} boş: Meta boş değişkeni reddeder
    $rendered[$c['phone']]=$json;
   }else{$rendered[$c['phone']]=render_sms($template,$c);}
   $recipients[$c['phone']]=$c;
  }
  $scheduled=$_POST['scheduled_at']?:null;$sender=$channel==='sms'?trim($_POST['sender']??''):'';
  $skippedInfo=($skippedConsent?" İzni olmayan $skippedConsent kişi atlandı.":'').($skippedOptout?" Ret listesindeki $skippedOptout kişi atlandı.":'')
   .($skippedMissing?" Şablon değişkeni boş kalan $skippedMissing kişi atlandı.":'');
  if(!$recipients){
   $result='Gönderilecek kişi yok.'.$skippedInfo;
  }else{
   $units=0;foreach($rendered as $r)$units+=$channel==='whatsapp'?1:sms_segments($r);
   $estimated=(float)$p['price_per_sms']*$units;
   $summary=$channel==='whatsapp'?"[WhatsApp şablon: $waTemplate ($waLang)]".($waParams?' '.implode(' | ',$waParams):''):$template;
   $msgType=$channel==='whatsapp'?'whatsapp':$type;
   $stmt=$pdo->prepare('INSERT INTO messages(provider_id,sender,message_template,message_type,total_recipients,total_segments,estimated_cost,status,scheduled_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
   $status=$scheduled?'scheduled':'queued';
   $stmt->execute([$p['id'],$sender,$summary,$msgType,count($recipients),$units,$estimated,$status,$scheduled,$_SESSION['user_id']]);$mid=(int)$pdo->lastInsertId();
   $ins=$pdo->prepare('INSERT INTO message_recipients(message_id,contact_id,phone,rendered_message) VALUES(?,?,?,?)');
   foreach($recipients as $phone=>$c)$ins->execute([$mid,$c['id'],$phone,$rendered[$phone]]);
   $info=count($recipients).' alıcı, '.number_format($estimated,2,',','.').' TL tahmini maliyet.'.$skippedInfo;
   $result=($scheduled?"Planlandı. ID: $mid — ":"Kuyruğa alındı. ID: $mid — ").$info;
  }
 }
}
$channelSel=$_POST['channel']??($_GET['channel']??'sms');
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>Mesaj Gönder</title>
<style>body{font-family:Arial;max-width:950px;margin:30px auto;background:#f7f8fb}.box{background:#fff;padding:22px;border-radius:12px;margin-bottom:14px}input,select,textarea{padding:9px;margin:5px 0}textarea{width:100%}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.tabs label{margin-right:18px;font-weight:bold}.note{background:#fff7ed;border-left:4px solid #f97316;padding:10px 14px}</style>
<a href="index.php">← Panel</a><h1>Mesaj Gönder</h1><?php if($result):?><div class="box"><?=e($result)?></div><?php endif;?>
<div class="box"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p class="tabs">Kanal:
 <label><input type="radio" name="channel" value="sms" <?=$channelSel!=='whatsapp'?'checked':''?> onchange="pick()"> SMS</label>
 <label><input type="radio" name="channel" value="whatsapp" <?=$channelSel==='whatsapp'?'checked':''?> onchange="pick()" <?=$wa?'':'disabled'?>> WhatsApp<?=$wa?'':' (ayarlı değil)'?></label></p>
<div class="grid">
<label>Grup<select name="group_name"><option value="">Tüm kişiler</option><?php foreach($groups as $g):?><option><?=e($g['group_name'])?></option><?php endforeach;?></select></label>
<label>Planlama<input type="datetime-local" name="scheduled_at"></label></div>

<div id="sms">
<div class="grid"><label>Sağlayıcı<select name="provider_id"><option value="auto">Otomatik — en ucuz</option><?php foreach($providers as $p):?><option value="<?=$p['id']?>"><?=e($p['name'])?> — <?=number_format((float)$p['price_per_sms'],4)?> TL/SMS</option><?php endforeach;?></select></label>
<label>Gönderici<input name="sender" maxlength="30"></label>
<label>Mesaj türü<select name="message_type"><option value="informational">Bilgilendirme</option><option value="commercial">Ticari / İYS izinli</option><option value="otp">OTP</option></select></label></div>
<p><textarea name="message" rows="8" placeholder="Merhaba {AD}, ..."></textarea></p>
<p>Kullanılabilir alanlar: <code>{AD}</code> <code>{SOYAD}</code> <code>{FIRMA}</code> <code>{TELEFON}</code>. Türkçe karakterler SMS segment sayısını artırabilir.</p>
<p>Ticari gönderimde yalnızca <b>izni verilmiş</b> kişiler kuyruğa alınır. Ret listesindeki numaralara hiçbir türde gönderim yapılmaz.</p>
</div>

<div id="whatsapp">
<p class="note">WhatsApp'ta yalnız <b>izni verilmiş</b> kişilere gönderilir; ret listesi her zaman uygulanır. Şablon Meta'da onaylanmış olmalı.</p>
<div class="grid"><label>Şablon adı<input name="wa_template" placeholder="ör. bilgi_talebi_yanit" pattern="[a-z0-9_]+"></label>
<label>Dil kodu<input name="wa_lang" value="tr"></label></div>
<p><label>Şablon değişkenleri — her satır sırayla <code>{{1}}</code>, <code>{{2}}</code>…<textarea name="wa_params" rows="4" placeholder="{AD}&#10;{FIRMA}"></textarea></label></p>
<p>Satırlarda <code>{AD}</code> <code>{SOYAD}</code> <code>{FIRMA}</code> <code>{TELEFON}</code> veya sabit metin kullanılabilir. Değişkeni boş kalan kişiler atlanır.</p>
</div>
<button style="padding:12px 22px">Kuyruğa Al / Planla</button></form></div>
<script>
function pick(){const wa=document.querySelector('[name=channel][value=whatsapp]').checked;
 document.getElementById('sms').style.display=wa?'none':'';document.getElementById('whatsapp').style.display=wa?'':'none';
 document.querySelector('[name=message]').required=!wa;document.querySelector('[name=wa_template]').required=wa;}
pick();
</script></html>
