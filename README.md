# Toplu SMS Paneli V2

Tek panelden Netgsm, Mutlucell, VatanSMS ve İletiMerkezi gibi sağlayıcıları adapter mimarisiyle yönetmek için hazırlanmış PHP/MySQL uygulaması.

## V2'de eklenenler
- XLSX + CSV içe aktarma
- Grup bazlı gönderim
- Kişiselleştirme: `{AD}`, `{SOYAD}`, `{TELEFON}`
- Türkçe karakter farkını dikkate alan SMS segment hesaplama
- Sağlayıcı fiyatına göre "Otomatik — en ucuz" seçimi
- Sağlayıcı önceliği
- Tahmini maliyet
- Kuyruk/worker mimarisi
- Planlı gönderim
- Ticari mesajlarda izinli (`granted`) kişileri filtreleme
- İYS durum sorgusu için adapter kancası
- Bakiye sorgusu için adapter kancası
- Sağlayıcı HTTP logları
- API credential'larının şifreli saklanması

## Kurulum (DirectAdmin / paylaşımlı hosting)

Gereken: PHP 8.1+ (cURL, PDO_MySQL, OpenSSL, Zip, SimpleXML, mbstring) ve MySQL/MariaDB.

1. **Dosyaları yerleştirin.** Alan adının klasöründe (`/domains/ALANADI/`) şu düzen olmalı:
   ```
   /domains/ALANADI/
   ├── config/        ← config.php burada (internetten erişilemez)
   ├── src/
   ├── database/
   └── public_html/   ← deponun public/ klasörünün İÇİNDEKİLER + kökteki .htaccess
   ```
   `public_html/public/` diye alt klasör olmamalı; `index.php` doğrudan `public_html` içinde durmalı.
2. **Veritabanı.** DirectAdmin → *MySQL Management* → yeni veritabanı + kullanıcı oluşturun (adlar `kullanici_sms` gibi önekli olur).
   *phpMyAdmin*'de bu veritabanını seçip `database/schema.sql` dosyasını **İçe Aktar** ile yükleyin.
3. **Ayar dosyası.** `config/config.php.example` dosyasını `config/config.php` olarak kopyalayın; veritabanı adı/kullanıcı/şifreyi
   ve `encryption_key` için uzun rastgele bir metin girin. (Bu anahtar sonradan değişirse kayıtlı sağlayıcı şifreleri çözülemez.)
4. **Yönetici.** `https://ALANADI/install.php` adresinden ilk kullanıcıyı oluşturun, ardından `public_html/install.php` dosyasını **silin**.
5. **Sağlayıcı.** *Sağlayıcılar* sayfasında API bilgilerini JSON olarak girin (ör. Netgsm: `{"usercode":"…","password":"…"}`).
   Sağlayıcı hatayı HTTP 200 ile dönüyorsa `"success_regex"` ile başarılı yanıt kalıbı verilebilir (Netgsm için hazır: `00/01/02` ile başlayan yanıt).
   Canlıya geçmeden önce **tek numaraya** test gönderin.
6. **Gönderim (cron).** DirectAdmin → *Cron Jobs* → her dakika (`* * * * *`):
   ```
   /usr/local/bin/php /home/KULLANICI/domains/ALANADI/src/worker.php --cron >/dev/null 2>&1
   ```
   `--cron` ile betik ~50 sn çalışıp çıkar (paylaşımlı hostingde uzun süren işlemler kapatılır). VPS'te `--cron` olmadan sürekli çalıştırılabilir.

## Kişiler ve ret listesi
- İçe aktarma: `.xlsx` / `.csv`, ayraç (`;` `,`) otomatik; başlıklar `telefon, ad, soyad, grup, firma`.
  Data Hunter'ın **Firma CSV** ve normal **CSV** dosyaları doğrudan yüklenebilir (firma adı ve kaynak sayfa saklanır). Geçersiz numaralar atlanır ve sayısı gösterilir.
- Mesajda `{AD}`, `{SOYAD}`, `{FIRMA}`, `{TELEFON}` kullanılabilir.
- **Ret listesi** (Kişiler sayfası): eklenen numaralara bilgilendirme dahil hiçbir SMS gönderilmez; kuyruktaki mesajlarda da gönderim anında kontrol edilir.
- Ticari türde yalnız `consent_status=granted` kişiler kuyruğa alınır.

## API güvenliği
- API bilgileri DB'de AES-256-CBC ile şifrelenir.
- HTTPS zorunlu tutulmalıdır.
- `config.php` web root dışında tutulmalıdır.
- Üretimde hata gösterimi kapatılmalıdır.

## Çok önemli üretim notu
Bu proje, sağlayıcıların API'sine bağlanacak ortak bir altyapıdır. Her sağlayıcının canlı endpoint, authentication, XML/JSON şeması, İYS ve rapor API'si zaman içinde değişebilir. Bu nedenle canlı SMS göndermeden önce ilgili sağlayıcının resmi güncel API dokümanındaki endpoint/parametreler adapter'a işlenmeli ve tek numara test edilmelidir. Fiyatlar otomatik olarak sağlayıcıdan alınmıyorsa paneldeki `TL/SMS` alanı yalnızca referans değeridir.

İYS: Ticari ileti gönderiminde izin/ret durumları, gönderici başlığı ve ileti türü mevzuata uygun biçimde ayrıca yönetilmelidir.
