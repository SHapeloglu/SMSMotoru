<?php require_once __DIR__.'/../src/bootstrap.php';require_login();
$stats=[
 'contacts'=>(int)$pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn(),
 'messages'=>(int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
 'queued'=>(int)$pdo->query("SELECT COUNT(*) FROM messages WHERE status IN ('queued','sending','scheduled')")->fetchColumn(),
 'cost'=>(float)$pdo->query('SELECT COALESCE(SUM(estimated_cost),0) FROM messages')->fetchColumn()
];?>
<!doctype html><html lang="tr"><meta charset="utf-8"><title>SMS Paneli</title>
<style>body{font-family:Arial;margin:0;background:#f5f7fb;color:#172033}header{background:#111827;color:white;padding:18px 28px}main{max-width:1100px;margin:28px auto;padding:0 18px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.card{background:white;padding:20px;border-radius:14px;box-shadow:0 2px 10px #0001}.nav{margin-top:20px;display:flex;gap:10px;flex-wrap:wrap}.nav a{background:#2563eb;color:white;text-decoration:none;padding:11px 15px;border-radius:8px}@media(max-width:800px){.grid{grid-template-columns:1fr 1fr}}</style>
<header><b>Toplu SMS Paneli V2</b></header><main><h1>Kontrol Merkezi</h1><div class="grid">
<div class="card"><small>Kişiler</small><h2><?=$stats['contacts']?></h2></div><div class="card"><small>Mesajlar</small><h2><?=$stats['messages']?></h2></div><div class="card"><small>Kuyruk</small><h2><?=$stats['queued']?></h2></div><div class="card"><small>Tahmini harcama</small><h2><?=number_format($stats['cost'],2,',','.')?> TL</h2></div></div>
<div class="nav"><a href="send.php">Mesaj Gönder</a><a href="contacts.php">Excel / CSV</a><a href="providers.php">Sağlayıcılar</a><a href="logs.php">Raporlar</a></div></main></html>