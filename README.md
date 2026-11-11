# WHMCS Detaylı Kullanıcı Hareketleri Günlüğü

Bu bağımsız WHMCS addon'u, desteklenen WHMCS hook noktalarından müşteri faaliyet günlüğü oluşturur. WHMCS çekirdek dosyalarını, admin template'lerini veya mevcut Netgsm, WhatsApp, telefon doğrulama ve bildirim addon'larını değiştirmez.

## Kurulum

1. `modules/addons/user_activity_log` klasörünü WHMCS kurulumundaki aynı konuma yükleyin.
2. **Configuration > System Settings > Addon Modules** bölümünde **Detaylı Kullanıcı Hareketleri Günlüğü** addon'unu etkinleştirin.
3. Addon izinlerinden günlüğü görecek admin rollerini seçin.
4. Bir admin müşteri profilini açtığında özet bölümündeki **Hareket Günlüğü** bağlantısını kullanın veya `addonmodules.php?module=user_activity_log` sayfasını açın.

WHMCS, aktif addon modülünün kökündeki `hooks.php` dosyasını otomatik yükler. Aktivasyon sırasında yalnızca `mod_user_activity_log_events` tablosu oluşturulur. Modül kapatılırsa kayıtlar silinmez.

`hooks.php` sonradan eklenir veya değiştirilirse **Configuration > System Settings > Addon Modules** bölümünde addon ayarını yeniden kaydedin; WHMCS hook dosyasını aktivasyon/ayar kaydı sırasında yeniden algılar.

## Kaydedilen bağlam

Her olayda mümkün olduğunda müşteri ID, authenticated user ID, admin ID, aktör tipi, ilgili kayıt ID'si, IP adresi, HMAC ile özetlenmiş session ID, user-agent, HTTP metodu, istek yolu, olay tarihi ve güvenli metadata tutulur.

Şifre, OTP, doğrulama kodu, token, secret, kart bilgisi, CVV, ham callback, e-posta gövdesi, ticket mesajı ve attachment değerleri günlüğe yazılmaz. Metadata alanları ayrıca anahtar adına göre maskelenir, derinlik ve uzunlukla sınırlanır.

## Kapsam

- Kullanıcı ve admin login/logout
- Müşteri oluşturma, profil güncelleme, şifre değiştirme, kapatma, silme öncesi kayıt ve merge
- Müşteri alanı sayfa görüntülemeleri, ürün/servis, fatura ve domain detay görüntülemeleri
- Admin müşteri profil özeti, sipariş detay görüntüleme ve servis düzenleme
- Sipariş checkout, kabul, ödeme, iptal, iade talebi ve fraud olayları
- Fatura oluşturma, ödeme, ödeme ekleme, iptal, iade ve unpaid olayları
- Finansal transaction oluşturulması
- E-posta pre-send ve WHMCS e-posta log olayları
- Ticket açma, admin tarafından açma, müşteri/admin yanıtı, admin ticket görüntüleme, durum, konu, departman ve öncelik değişiklikleri, kapatma, silme ve merge
- Provisioning servis create, suspend, unsuspend, terminate, uzak şifre değiştirme, paket değiştirme, custom işlem ve başarısız sonuçları
- Configurable option ve ürün upgrade olayları
- E-posta doğrulama durumu değişiklikleri ve varsa mevcut telefon doğrulama addon'undaki başarılı doğrulama kaydı

Addon sayfasında müşteri ID, olay anahtarı, IP ve tarih aralığı filtreleri; sayfalama; toplam olay, farklı IP ve farklı session sayaçları bulunur.

## Bilinen WHMCS sınırları

- WHMCS, addon etkinleştirilmeden önce gerçekleşmiş faaliyetleri geriye dönük olarak üretemez. Aktivasyon sonrası hook'lara ulaşan olaylar kaydedilir.
- Başarısız müşteri login için tüm sürümlerde genel bir hook bulunmadığından bu modül başarısız denemeleri kesin olarak kaydetmez. Başarılı kimlik doğrulama `UserLogin` ile kaydedilir.
- SMS, WhatsApp veya başka provider'ın kendi iç gönderim akışı bu modülün hook kapsamına girmez. Provider addon'u ayrı bir hook sunuyorsa ayrıca bağlanabilir.
- Mevcut telefon doğrulama addon'u doğrulama anındaki IP'yi saklamadığı için entegrasyon yalnızca başarılı doğrulama zamanını/yöntemini ve maskelenmiş telefon bilgisini sonraki müşteri alanı isteğinde yakalar; doğrulama anının IP'si geriye dönük üretilemez.
- `UserLogout` olayında WHMCS'nin verdiği User nesnesi kullanılır; tarayıcı kapatma, session timeout ve ağ kopması logout olarak kabul edilemez.
- Müşteri alanı page-view kaydı yüksek hacim üretebilir. Addon ayarlarından kapatılabilir; kayıtlar varsayılan olarak otomatik silinmez. IP ve kullanıcı-agent kişisel veri sayılabileceğinden kurulumun veri saklama politikasına göre harici retention planı uygulanmalıdır.
- WHMCS hook parametreleri sürüme ve kullanılan modüle göre değişebildiğinden ilgili entity ID bulunamazsa olay yine IP/oturum ve mevcut bağlamla kaydedilir.

## Yerel smoke test

PHP CLI ile:

```text
php modules/addons/user_activity_log/tests/smoke.php
```

Test veritabanına yazmaz; addon hook kayıtlarını sahte `add_hook` callback'iyle kontrol eder, gerçek WHMCS isteği oluşturmaz.