<?php
require_once __DIR__.'/bootstrap.php';require_once __DIR__.'/ProviderFactory.php';
$lock=fopen(sys_get_temp_dir().'/sms_panel_worker.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))exit("Worker zaten çalışıyor.\n");
while(true){
 $pdo->beginTransaction();
 $q=$pdo->query("SELECT * FROM messages WHERE status IN ('queued','scheduled') AND (scheduled_at IS NULL OR scheduled_at<=NOW()) ORDER BY id LIMIT 1 FOR UPDATE");
 $m=$q->fetch();
 if(!$m){$pdo->commit();sleep(2);continue;}
 $pdo->prepare("UPDATE messages SET status='sending' WHERE id=?")->execute([$m['id']]);
 $pdo->commit();
 $p=$pdo->prepare('SELECT * FROM providers WHERE id=?');$p->execute([$m['provider_id']]);$providerRow=$p->fetch();
 $creds=json_decode(decrypt_secret($providerRow['credentials_encrypted'],$config['security']['encryption_key']),true)?:[];
 $provider=provider_factory($providerRow['name'],$creds);
 $r=$pdo->prepare("SELECT * FROM message_recipients WHERE message_id=? AND status='queued' ORDER BY id LIMIT 100");$r->execute([$m['id']]);
 foreach($r as $rec){
  $res=$provider->send($rec['phone'],$rec['rendered_message'],$m['sender']?:$providerRow['sender'],['iys'=>$m['message_type']==='commercial'?'Y':'N']);
  $status=$res['success']?'submitted':'failed';
  $pdo->prepare('UPDATE message_recipients SET status=?,provider_message_id=?,error_message=?,sent_at=NOW() WHERE id=?')->execute([$status,$res['provider_id']??null,$res['error']??null,$rec['id']]);
  $pdo->prepare('INSERT INTO provider_logs(provider_id,action,http_code,success,response_body) VALUES(?,?,?,?,?)')->execute([$providerRow['id'],'send',$res['http_code']??null,$res['success']?1:0,$res['raw']??'']);
 }
 $left=(int)$pdo->query("SELECT COUNT(*) FROM message_recipients WHERE message_id=".(int)$m['id']." AND status='queued'")->fetchColumn();
 if($left===0)$pdo->prepare("UPDATE messages SET status='submitted' WHERE id=?")->execute([$m['id']]);
}