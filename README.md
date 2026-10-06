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

## Kurulum
1. PHP 8.1+ (cURL, PDO_MySQL, OpenSSL, ZipArchive, SimpleXML) ve MySQL gerekir.
2. `config/config.php.example` -> `config/config.php`
3. `database/schema.sql` MySQL'e aktarılır.
4. Web document root `public/` klasörüne yönlendirilir.
5. `/install.php` ile ilk yönetici oluşturulur ve install.php silinir.
6. Sağlayıcılar sayfasında API bilgileri ve referans SMS fiyatları girilir.
7. Kuyruğu göndermek için sunucuda `php src/worker.php` çalıştırılabilir; cron/systemd/supervisor ile sürekli çalıştırılması önerilir.

## API güvenliği
- API bilgileri DB'de AES-256-CBC ile şifrelenir.
- HTTPS zorunlu tutulmalıdır.
- `config.php` web root dışında tutulmalıdır.
- Üretimde hata gösterimi kapatılmalıdır.

## Çok önemli üretim notu
Bu proje, sağlayıcıların API'sine bağlanacak ortak bir altyapıdır. Her sağlayıcının canlı endpoint, authentication, XML/JSON şeması, İYS ve rapor API'si zaman içinde değişebilir. Bu nedenle canlı SMS göndermeden önce ilgili sağlayıcının resmi güncel API dokümanındaki endpoint/parametreler adapter'a işlenmeli ve tek numara test edilmelidir. Fiyatlar otomatik olarak sağlayıcıdan alınmıyorsa paneldeki `TL/SMS` alanı yalnızca referans değeridir.

İYS: Ticari ileti gönderiminde izin/ret durumları, gönderici başlığı ve ileti türü mevzuata uygun biçimde ayrıca yönetilmelidir.
