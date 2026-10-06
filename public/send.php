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
 $scheduled=$_POST['scheduled_at']?:null;$sender=trim($_POST['sender']??'');$segments=sms_segments($template);
 $estimated=(float)$p['price_per_sms']*$segments*count($contacts);
 $stmt=$pdo->prepare('INSERT INTO messages(provider_id,sender,message_template,message_type,total_recipients,total_segments,estimated_cost,status,scheduled_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
 $status=$scheduled?'scheduled':'queued';
 $stmt->execute([$p['id'],$sender,$template,$type,count($contacts),$segments,$estimated,$status,$scheduled,$_SESSION['user_id']]);$mid=(int)$pdo->lastInsertId();
 foreach($contacts as $c){
  $rendered=render_sms($template,$c);
  if($type==='commercial' && $c['consent_status']!=='granted')continue;
  $pdo->prepare('INSERT INTO message_recipients(message_id,contact_id,phone,rendered_message) VALUES(?,?,?,?)')->execute([$mid,$c['id'],$c['phone'],$rendered]);
 }
 if($scheduled)$result="Planlandı. ID: $mid — Tahmini maliyet: ".number_format($estimated,2,',','.')." TL";
 else $result="Kuyruğa alındı. ID: $mid — Tahmini maliyet: ".number_format($estimated,2,',','.')." TL. Gönderici worker ile çalıştırılabilir.";
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
<p>Türkçe karakterler SMS segment sayısını artırabilir. Ticari gönderimde yalnızca <b>consent_status=granted</b> kişiler kuyruğa alınır.</p>
<button style="padding:12px 22px">Kuyruğa Al / Planla</button></form></div></html>