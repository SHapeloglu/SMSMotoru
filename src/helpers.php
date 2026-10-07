<?php
// Veritabanı gerektirmeyen yardımcılar (bootstrap.php yükler; testlerde tek başına da yüklenebilir)
declare(strict_types=1);

function e(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}

// Telefonu "905321234567" biçimine çevirir; telefon değilse '' döner.
function normalize_phone(string $p):string{
 $p=preg_replace('/\D+/','',$p);
 if(str_starts_with($p,'00'))$p=substr($p,2);
 if(strlen($p)===11 && str_starts_with($p,'0'))$p='90'.substr($p,1);
 elseif(strlen($p)===10 && preg_match('/^[2-58]/',$p))$p='90'.$p;
 if(str_starts_with($p,'90'))return preg_match('/^90[2-58]\d{9}$/',$p)?$p:'';
 return (strlen($p)>=11 && strlen($p)<=15)?$p:'';   // yurt dışı numara (ülke kodlu)
}

function sms_segments(string $text):int{
 $len=mb_strlen($text,'UTF-8');
 $gsmSpecial=preg_match('/[çğıöşüÇĞİÖŞÜ€^{}\\\\\[\]~|]/u',$text);
 $limit=$gsmSpecial?70:160;
 $multi=$gsmSpecial?67:153;
 return $len<= $limit ? 1 : (int)ceil($len/$multi);
}

function render_sms(string $tpl,array $c):string{
 return strtr($tpl,['{AD}'=>$c['first_name']??'','{SOYAD}'=>$c['last_name']??'','{FIRMA}'=>$c['company']??'','{TELEFON}'=>$c['phone']??'']);
}

// CSV satırlarını okur: ayraç (; , sekme) otomatik bulunur, UTF-8 BOM atılır.
function csv_rows(string $file):array{
 $text=(string)file_get_contents($file);
 $text=preg_replace('/^\xEF\xBB\xBF/','',$text);
 $lines=preg_split('/\r\n|\n|\r/',$text);
 $lines=array_values(array_filter($lines,fn($l)=>trim($l)!==''));
 if(!$lines)return [];
 $first=$lines[0];
 $counts=[';'=>substr_count($first,';'),','=>substr_count($first,','),"\t"=>substr_count($first,"\t")];
 arsort($counts);$sep=array_key_first($counts);
 return array_map(fn($l)=>str_getcsv($l,$sep,'"','\\'),$lines);
}

// Excel'in ="+905..." hücresini ve tırnakları temizler
function clean_cell($v):string{
 $v=trim((string)$v);
 if(preg_match('/^="(.*)"$/s',$v,$m))$v=$m[1];
 return trim($v);
}

// Satırlardan kişi listesi üretir. Desteklenen başlıklar:
//  - Genel:                 telefon, ad, soyad, grup, firma
//  - Data Hunter Firma CSV: Firma;Telefon;E-Posta;...;Sayfa  (bir hücrede "a | b" birden çok numara)
//  - Data Hunter CSV:       Email;Tip;Kaynak;Tarih;Firma;Sayfa (yalnız Tip=Telefon satırları)
// Dönüş: ['contacts'=>[[phone,first_name,last_name,group_name,company,source],...], 'skipped'=>int, 'error'=>?string]
function contacts_from_rows(array $rows,string $defaultGroup=''):array{
 if(!$rows)return ['contacts'=>[],'skipped'=>0,'error'=>'Dosya boş.'];
 $header=array_map(fn($x)=>mb_strtolower(clean_cell($x),'UTF-8'),$rows[0]);
 $col=fn(string $name)=>($i=array_search($name,$header,true))===false?null:$i;
 $cell=fn(array $row,?int $i)=>$i===null?'':clean_cell($row[$i]??'');

 $iTip=$col('tip');$iEmail=$col('email');
 $dhValueCsv=$iTip!==null && $iEmail!==null && $col('telefon')===null;
 $iPhone=$dhValueCsv?$iEmail:$col('telefon');
 if($iPhone===null)return ['contacts'=>[],'skipped'=>0,'error'=>'"telefon" başlıklı sütun bulunamadı. İlk satır başlık olmalı (ör. telefon, ad, soyad, grup, firma).'];
 $iAd=$col('ad');$iSoyad=$col('soyad');$iGrup=$col('grup');$iFirma=$col('firma');
 $iSource=$col('sayfa')??$col('kaynak');

 $out=[];$seen=[];$skipped=0;
 for($r=1;$r<count($rows);$r++){
  $row=$rows[$r];
  if($dhValueCsv && mb_strtolower($cell($row,$iTip),'UTF-8')!=='telefon')continue;
  $group=$cell($row,$iGrup)?:$defaultGroup;
  foreach(preg_split('/\s*\|\s*/',$cell($row,$iPhone)) as $raw){
   if($raw==='')continue;
   $phone=normalize_phone($raw);
   if($phone===''){$skipped++;continue;}
   $key=$phone.'|'.$group;
   if(isset($seen[$key]))continue;
   $seen[$key]=true;
   $out[]=['phone'=>$phone,'first_name'=>$cell($row,$iAd)?:null,'last_name'=>$cell($row,$iSoyad)?:null,
           'group_name'=>$group?:null,'company'=>$cell($row,$iFirma)?:null,'source'=>$cell($row,$iSource)?:null];
  }
 }
 return ['contacts'=>$out,'skipped'=>$skipped,'error'=>null];
}

// Serbest metinden numara listesi (ret listesi için): satır, virgül veya noktalı virgülle ayrılmış
function phones_from_text(string $text):array{
 $out=[];
 foreach(preg_split('/[\r\n,;]+/',$text) as $raw){$p=normalize_phone($raw);if($p!=='')$out[$p]=true;}
 return array_keys($out);
}

// --- WhatsApp şablonları ---
function wa_template_name_ok(string $n):bool{return (bool)preg_match('/^[a-z0-9_]{1,512}$/',$n);}

// Şablon değişkenlerini ({{1}}, {{2}}...) kişiye göre doldurur. $paramLines: her satır bir değişken, ör. "{AD}" veya "{FIRMA}".
// Dönüş: [json, eksikAlanVarMi]. Meta boş değişkeni reddettiği için boş kalan alan "eksik" sayılır.
function wa_payload_json(string $template,string $lang,array $paramLines,array $contact):array{
 $params=[];$missing=false;
 foreach($paramLines as $line){$v=trim(render_sms($line,$contact));if($v==='')$missing=true;$params[]=$v;}
 return [json_encode(['template'=>$template,'lang'=>$lang,'params'=>$params],JSON_UNESCAPED_UNICODE),$missing];
}

function param_lines(string $text):array{
 return array_values(array_filter(array_map('trim',preg_split('/\r\n|\n|\r/',$text)),fn($l)=>$l!==''));
}
