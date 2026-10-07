<?php
require_once __DIR__.'/../src/bootstrap.php';require_once __DIR__.'/../src/ProviderFactory.php';require_login();
$providers=$pdo->query('SELECT * FROM providers WHERE enabled=1 ORDER BY priority,price_per_sms')->fetchAll();
$groups=$pdo->query("SELECT DISTINCT group_name FROM contacts WHERE group_name IS NOT NULL AND group_name<>'' ORDER BY group_name")->fetchAll();
$result=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $template=trim($_POST['message']??'');$type=$_POST['message_type']??'informational';$group=$_POST['group_name']??'';
 $contactsStmt=$group?$pdo->prepare('SELECT * FROM contacts WHERE group_name=? ORDER BY id'):null;
 if($contactsStmt){$contactsStmt->execute([$group]);$contacts=$contactsStmt->fetchAll();}else{$contacts=$pdo->query('SELECT * FROM contacts ORDER BY id')->fetchAll();}
 $pid=$_POST['provider_id']??'auto';
 if($pid==='auto'){
  $providersWithPrice=array_filter($providers,fn($p)=>(float)$p['price_per_sms']>0);
  usort($providersWithPrice,fn($a,$b)=>((float)$a['price_per_sms']<=>$b['price_per_sms'])?:($a['priority']<=>$b['priority']));
  $p=$providersWithPrice[0]??$providers[0]??null;
 }else{$q=$pdo->prepare('SELECT * FROM providers WHERE id=? AND enabled=1');$q->execute([(int)$pid]);$p=$q->fetch();}
 if(!$p||!$template)die('Sağlayıcı veya mesaj eksik.');
 // Alıcıları önce süz, sonra say: ret listesi her türde hariç, ticaride yalnız izinliler, aynı numara bir kez
 $optout=array_flip($pdo->query('SELECT phone FROM optouts')->fetchAll(PDO::FETCH_COLUMN));
 $recipients=[];$skippedOptout=0;$skippedConsent=0;
 foreach($contacts as $c){
  if(isset($optout[$c['phone']])){$skippedOptout++;continue;}
  if($type==='commercial' && $c['consent_status']!=='granted'){$skippedConsent++;continue;}
  if(!isset($recipients[$c['phone']]))$recipients[$c['phone']]=$c;
 }
 $scheduled=$_POST['scheduled_at']?:null;$sender=trim($_POST['sender']??'');
 if(!$recipients){
  $result='Gönderilecek kişi yok.'.($skippedConsent?" $skippedConsent kişi ticari ileti izni olmadığı için,":'').($skippedOptout?" $skippedOptout kişi ret listesinde olduğu için":'').' atlandı.';
 }else{
  $segmentsTotal=0;$rendered=[];
  foreach($recipients as $phone=>$c){$rendered[$phone]=render_sms($template,$c);$segmentsTotal+=sms_segments($rendered[$phone]);}
  $estimated=(float)$p['price_per_sms']*$segmentsTotal;
  $stmt=$pdo->prepare('INSERT INTO messages(provider_id,sender,message_template,message_type,total_recipients,total_segments,estimated_cost,status,scheduled_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
  $status=$scheduled?'scheduled':'queued';
  $stmt->execute([$p['id'],$sender,$template,$type,count($recipients),$segmentsTotal,$estimated,$status,$scheduled,$_SESSION['user_id']]);$mid=(int)$pdo->lastInsertId();
  $ins=$pdo->prepare('INSERT INTO message_recipients(message_id,contact_id,phone,rendered_message) VALUES(?,?,?,?)');
  foreach($recipients as $phone=>$c)$ins->execute([$mid,$c['id'],$phone,$rendered[$phone]]);
  $info=count($recipients).' alıcı, '.number_format($estimated,2,',','.').' TL tahmini maliyet.'
   .($skippedConsent?" İzni olmayan $skippedConsent kişi atlandı.":'').($skippedOptout?" Ret listesindeki $skippedOptout kişi atlandı.":'');
  $result=($scheduled?"Planlandı. ID: $mid — ":"Kuyruğa alındı. ID: $mid — ").$info;
 }
}
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>SMS Gönder</title>
<style>body{font-family:Arial;max-width:950px;margin:30px auto;background:#f7f8fb}.box{background:#fff;padding:22px;border-radius:12px}input,select,textarea{padding:9px;margin:5px 0}textarea{width:100%}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}</style>
<a href="index.php">← Panel</a><h1>SMS Gönder</h1><?php if($result):?><div class="box"><?=e($result)?></div><?php endif;?>
<div class="box"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="grid"><label>Sağlayıcı<select name="provider_id"><option value="auto">Otomatik — en ucuz</option><?php foreach($providers as $p):?><option value="<?=$p['id']?>"><?=e($p['name'])?> — <?=number_format((float)$p['price_per_sms'],4)?> TL/SMS</option><?php endforeach;?></select></label>
<label>Grup<select name="group_name"><option value="">Tüm kişiler</option><?php foreach($groups as $g):?><option><?=e($g['group_name'])?></option><?php endforeach;?></select></label>
<label>Gönderici<input name="sender" maxlength="30"></label><label>Planlama<input type="datetime-local" name="scheduled_at"></label>
<label>Mesaj türü<select name="message_type"><option value="informational">Bilgilendirme</option><option value="commercial">Ticari / İYS izinli</option><option value="otp">OTP</option></select></label></div>
<p><textarea name="message" rows="8" required placeholder="Merhaba {AD}, ..."></textarea></p>
<p>Kullanılabilir alanlar: <code>{AD}</code> <code>{SOYAD}</code> <code>{FIRMA}</code> <code>{TELEFON}</code>. Türkçe karakterler SMS segment sayısını artırabilir.</p>
<p>Ticari gönderimde yalnızca <b>consent_status=granted</b> kişiler kuyruğa alınır. Ret listesindeki numaralara hiçbir türde gönderim yapılmaz.</p>
<button style="padding:12px 22px">Kuyruğa Al / Planla</button></form></div></html>