<?php
function xlsx_rows(string $file):array{
 $zip=new ZipArchive(); if($zip->open($file)!==true)throw new RuntimeException('XLSX açılamadı.');
 $shared=[];
 if(($s=$zip->getFromName('xl/sharedStrings.xml'))!==false){
  $xml=simplexml_load_string($s);
  foreach($xml->si as $si)$shared[]=(string)$si->t;
 }
 $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
 if($sheet===false)throw new RuntimeException('İlk çalışma sayfası bulunamadı.');
 $xml=simplexml_load_string($sheet);$rows=[];
 foreach($xml->sheetData->row as $r){
  $out=[];
  foreach($r->c as $c){
   $ref=(string)$c['r'];preg_match('/([A-Z]+)/',$ref,$m);$col=$m[1]??'A';
   $idx=0;for($i=0;$i<strlen($col);$i++)$idx=$idx*26+(ord($col[$i])-64);$idx--;
   $v=(string)$c->v;
   if((string)$c['t']==='s')$v=$shared[(int)$v]??'';
   $out[$idx]=$v;
  }
  if($out)$rows[]=$out;
 }
 $zip->close();return $rows;
}
