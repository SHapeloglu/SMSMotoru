<?php
// Kuyruktaki SMS'leri gönderir.
//   php src/worker.php          → sürekli çalışır (VPS: systemd/supervisor)
//   php src/worker.php --cron   → en fazla ~50 sn çalışıp çıkar (paylaşımlı hosting: dakikada bir cron)
require_once __DIR__.'/bootstrap.php';require_once __DIR__.'/ProviderFactory.php';
$cron=in_array('--cron',$argv??[],true);
$deadline=$cron?time()+50:PHP_INT_MAX;
$lock=fopen(sys_get_temp_dir().'/sms_panel_worker_'.md5(__DIR__).'.lock','c');
if(!flock($lock,LOCK_EX|LOCK_NB))exit("Worker zaten çalışıyor.\n");

$finish=function(int $mid) use($pdo){
 $left=$pdo->prepare("SELECT COUNT(*) FROM message_recipients WHERE message_id=? AND status='queued'");$left->execute([$mid]);
 if((int)$left->fetchColumn()===0)$pdo->prepare("UPDATE messages SET status='submitted' WHERE id=?")->execute([$mid]);
};

while(time()<$deadline){
 // 'sending' da alınır: 100'den fazla alıcılı mesaj bir sonraki turda kaldığı yerden devam eder
 $m=$pdo->query("SELECT * FROM messages WHERE status IN ('queued','scheduled','sending') AND (scheduled_at IS NULL OR scheduled_at<=NOW()) ORDER BY id LIMIT 1")->fetch();
 if(!$m){if($cron)break;sleep(2);continue;}
 $pdo->prepare("UPDATE messages SET status='sending' WHERE id=?")->execute([$m['id']]);

 $p=$pdo->prepare('SELECT * FROM providers WHERE id=?');$p->execute([$m['provider_id']]);$providerRow=$p->fetch();
 try{
  if(!$providerRow)throw new RuntimeException('Sağlayıcı bulunamadı (silinmiş olabilir).');
  $creds=json_decode(decrypt_secret((string)$providerRow['credentials_encrypted'],$config['security']['encryption_key']),true)?:[];
  $provider=provider_factory($providerRow['name'],$creds);
 }catch(Throwable $e){
  $pdo->prepare("UPDATE message_recipients SET status='failed',error_message=? WHERE message_id=? AND status='queued'")->execute([$e->getMessage(),$m['id']]);
  $pdo->prepare("UPDATE messages SET status='failed' WHERE id=?")->execute([$m['id']]);
  continue;
 }

 $optout=$pdo->prepare('SELECT 1 FROM optouts WHERE phone=?');
 $consent=$pdo->prepare("SELECT 1 FROM contacts WHERE phone=? AND consent_status='granted' LIMIT 1");
 $r=$pdo->prepare("SELECT * FROM message_recipients WHERE message_id=? AND status='queued' ORDER BY id LIMIT 100");$r->execute([$m['id']]);
 foreach($r->fetchAll() as $rec){
  if(time()>=$deadline)break;
  // Kuyruğa alındıktan sonra ret listesine eklenen numara gönderilmez
  $optout->execute([$rec['phone']]);
  if($optout->fetchColumn()){
   $pdo->prepare("UPDATE message_recipients SET status='optout' WHERE id=?")->execute([$rec['id']]);continue;
  }
  // WhatsApp: kuyruğa alındıktan sonra izni geri alınan kişiye gönderilmez
  if($m['message_type']==='whatsapp'){
   $consent->execute([$rec['phone']]);
   if(!$consent->fetchColumn()){$pdo->prepare("UPDATE message_recipients SET status='no_consent' WHERE id=?")->execute([$rec['id']]);continue;}
  }
  $res=$provider->send($rec['phone'],$rec['rendered_message'],$m['sender']?:(string)$providerRow['sender'],['iys'=>$m['message_type']==='commercial'?'Y':'N']);
  $status=$res['success']?'submitted':'failed';
  $pdo->prepare('UPDATE message_recipients SET status=?,provider_message_id=?,error_message=?,sent_at=NOW() WHERE id=?')->execute([$status,$res['provider_id']??null,$res['error']??null,$rec['id']]);
  $pdo->prepare('INSERT INTO provider_logs(provider_id,action,http_code,success,response_body) VALUES(?,?,?,?,?)')->execute([$providerRow['id'],'send',$res['http_code']??null,$res['success']?1:0,$res['raw']??'']);
 }
 $finish((int)$m['id']);
}
