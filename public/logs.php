<?php
require_once __DIR__.'/../src/bootstrap.php';
require_login();
$rows=$pdo->query('SELECT m.*,p.name provider FROM messages m LEFT JOIN providers p ON p.id=m.provider_id ORDER BY m.id DESC LIMIT 100')->fetchAll();
?><!doctype html><html lang="tr"><meta charset="utf-8"><title>Raporlar</title>
<body style="font-family:Arial;max-width:1100px;margin:40px auto"><a href="index.php">← Panel</a>
<h2>Gönderim Geçmişi</h2><table border="1" cellpadding="8" cellspacing="0" width="100%">
<tr><th>ID</th><th>Tarih</th><th>Sağlayıcı</th><th>Alıcı</th><th>Durum</th><th>Mesaj</th></tr>
<?php foreach($rows as $r):?><tr><td><?=$r['id']?></td><td><?=e($r['created_at'])?></td><td><?=e($r['provider'])?></td><td><?=$r['total_recipients']?></td><td><?=e($r['status'])?></td><td><?=e(mb_substr($r['message_template'],0,80))?></td></tr><?php endforeach;?>
</table></body></html>