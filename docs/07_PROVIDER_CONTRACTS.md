# 07 — Sağlayıcı adaptörleri ve teknik sözleşmeler

## Ortak sözleşme
Adapter dış sağlayıcı DTO'sunu uygulamaya sızdırmaz. Her operasyon:
- `ProviderContext` (account, environment, credential reference, correlation id)
- `ProviderOperationRequest` (operation UUID, resource mapping, frozen config, capability requirements)
- `ProviderResult` (`SUCCESS`, `DEFINITIVE_FAILURE`, `UNKNOWN`, `UNSUPPORTED`; external reference, evidence, sanitized error, retryAfter)

**Canlıya alma kapısı:** Sağlayıcı özelinde auth, test/staging hesabı, fiyat/stok, idempotency/reconciliation, hata kodu semantiği, quota, işlem sonlanması ve API versiyonu kontrol edilir.

```php
interface ComputeProviderInterface {
    public function capabilities(ProviderContext $ctx): CapabilitySet;
    public function quote(ComputeSpec $spec, ProviderContext $ctx): ProviderQuote;
    public function create(ComputeCreateCommand $cmd, ProviderContext $ctx): ProviderResult;
    public function inspect(ResourceRef $resource, ProviderContext $ctx): ProviderResourceState;
    public function power(ResourceRef $resource, PowerAction $action, ProviderContext $ctx): ProviderResult;
    public function reinstall(ResourceRef $resource, ImageRef $image, ProviderContext $ctx): ProviderResult;
    public function terminate(ResourceRef $resource, ProviderContext $ctx): ProviderResult;
}
interface HostingProviderInterface {
    public function createAccount(HostingCommand $command): ProviderResult;
    public function inspectAccount(ResourceRef $resource): ProviderResourceState;
    public function suspendAccount(ResourceRef $resource): ProviderResult;
    public function unsuspendAccount(ResourceRef $resource): ProviderResult;
    public function changePackage(ResourceRef $resource, PackageRef $package): ProviderResult;
    public function terminateAccount(ResourceRef $resource): ProviderResult;
}
interface DomainRegistrarInterface {
    public function availability(DomainName $domain): AvailabilityResult;
    public function register(DomainCommand $cmd): ProviderResult;
    public function renew(DomainCommand $cmd): ProviderResult;
    public function initiateTransfer(DomainTransferCommand $cmd): ProviderResult;
    public function status(DomainName $domain): ProviderDomainState;
    public function capabilities(Tld $tld): RegistrarCapabilities;
}
interface PaymentGatewayInterface {
    public function createAttempt(PaymentAttemptCommand $cmd): PaymentSession;
    public function verifyCallback(array $payload): VerifiedPaymentEvent;
    public function refund(RefundCommand $cmd): ProviderResult;
}
```
> Yukarıdaki PHP imzaları **bizim mimari sözleşmemizdir**, mevcut sağlayıcı SDK'sının birebir metodları değildir.

## PayTR — V1
- Hosted iFrame seçimi; token sunucu tarafında hazırlanır, ham kart bilgisi Hostider'a gelmez.
- `merchant_oid` tekil attempt, ödeme talebi snapshot + para birimi + net/tahsil edilen tutar karşılaştırılır.
- Callback HMAC doğrulanır, idempotent kaydedilir, yalnız commit sonrası düz `OK` yanıtı döner. Callback endpoint session/auth guard gerektirmez ama imza şarttır.
- Başarısız veya duplicate callback ikinci kurulum başlatamaz; HMAC'siz callback asla ödemeyi doğrulamaz.
- PayTR dokümanında `total_amount` ile gönderilen `payment_amount` farklı olabilecek koşullar var; ledger kaydında ikisi de korunur, mutabakat gerekir.
- TRY/USD kullanım kabiliyeti, komisyon, test hesabı, kısmi refund limitleri ve wallet top-up kategorisi ödeme sözleşmesiyle doğrulanır.
- Referans: https://dev.paytr.com/iframe-api/iframe-api-1-adim ve https://dev.paytr.com/iframe-api/iframe-api-2-adim

## Voyar — V1 Cloud
- Kullanıcının gönderdiği `voyar-api-client` PHP SDK'sı: Composer'sız `bootstrap.php`, PHP 7.4+, cURL/JSON; hedef runtime PHP 8.3+.
- SDK namespace `Voyar\\Client`; `Client::withCredentials(...)` ve DB `TokenStore` ile token; token 6 saat, IP bağlı; 2FA ilk login için manuel verify süreci var. 2FA'sız headless restart / güvenilir cihaz erişimi canlı onay gerektirir.
- `services()->create()` katalog ürünü (`pid`) veya `custom_package`, `type` (`prepaid`/`ondemand`), `days`, os/location parametreleri. `catalog()->customPackage()->options/quote` özel kaynak validasyonu.
- `service($publicId)->get()/extend()/upgrade()/terminate()`; `vm()->status()/start()/stop()/restart()/password()/createVncSession()` vb. SDK'da tanımlı. Custom package doğrudan genel upgrade desteklemeyebilir.
- **En kritik GATE:** Kullanıcının hedefi müşteri peşin 30 gün, Voyar'dan gerçek **saatlik** maliyet. `ondemand` için sağlayıcı faturalama yöntemi, minimum birim, stop/suspend/terminate maliyet kesimi, hesap bakiyesi/limit, ayrı addon maliyeti ve para birimi canlı test edilmeden otomatik ürün satışı açılmaz.
- Rate limit/timeout, auth 2FA, `services()->search()` ile kaybolan yanıt sonrası resource reconciliation, external task polling zorunlu.
- Standart SDK dokümanı içinde idempotency anahtarı desteği açıkça kanıtlanmadı: VM create/terminate tekrarını sistem tarafında güvenli hale getir.
- Resmi wiki URL'si https://wiki.voyar.net/api/ ; web metin çıkarma yöntemi içerik okumamış olduğundan SDK dosyası birincil inceleme materyalidir.

## cPanel/WHM — V1 Hosting
- WHM API 1, HTTPS:2087/443 API token; mümkün en dar ACL; SSL peer+hostname doğrulaması **asla** devre dışı bırakılmamalı.
- Uygulama eşleştirmeleri: WHM server ID, reseller owner, package, account username, domain, external resource ID.
- `createacct`, `listaccts`, `suspendacct`, `unsuspendacct`, `changepackage`, `removeacct`; API sürümleriyle uyumluluk saha testleri.
- Ağırlıklı seçim: sağlıklı/erişilebilir sunucular → kapasite ve account limit → ürün paketi mapping → ağırlık/iş yükü → reservation. Ölçümler bayatsa manuel veya fail-closed.
- Timeout sonrası `listaccts`/account inspect ile önce hesabın oluştuğu kontrol edilir; aynı username/domain iki kere açılmaz.
- Referans: https://api.docs.cpanel.net/ ve https://api.docs.cpanel.net/whm/tokens

## ResellerClub — V1 Domain
- Güncel REST API ile legacy HTTP API arasında tarihsel farklar olabilir. Kullanılacak hesap/API sürümü, endpoint ve auth canlı ortamda belirlenmeden eskiden kalma örnekler hardcode edilmez.
- Katalog/TLD/ücret, availability, customer/contact profiles, registration, renewal, transfer, nameserver, privacy ve DNS capability bazında eşleştirilir.
- Registrar harici order ID ve domain name+TLD çifti tut; grace/redemption/transfer durumları uzantıya göre.
- Referans: https://developer.resellerclub.com/ ve https://www.resellerclub.com/developer-portal

## DomainNameAPI — V1 Domain
- Sağlayıcının REST OT&E/production Swagger API'si var; alan adı ürünleri için hesabın izinleri ve gerekli contact verisi TLD'ye göre değişebilir.
- Sorgu, register, renew, transfer, nameserver, EPP/lock ve DNS için capability matrisi; provider'ın mümkün olmayan TLD operasyonu UI'da gizlenir.
- OT&E, gerçek ekonomik işlem yapmadan test için; production anahtarları dış ortamdan ayrı saklanır.
- Referans: https://www.domainnameapi.com/tr/domain-name-api-test-platformu-bilgileri ve https://api.domainresellerapi.com/swagger/index.html

## Hata semantiği ve failover matrisi
| İşlem | Kesin başarısız | Timeout/belirsiz | Tekrar |
|---|---|---|---|
| cPanel create | Sebep düzelt, kurala göre retry | Hesabı ara; manuel reconcile | Bulunmadığı doğrulandıysa |
| Voyar create | Kota/ürün/saldo düzelt | VM public id/task ara | İkinci create varsayılan yasak |
| Domain register | TLD+fiyat+contact kontrolüyle alternatife geçebilir | Registrar'da sorgula, ikinci registrar yasak | Kesin hiçbir kaynak/ücret oluşmadıysa |
| Domain renew | Tarih/order/charge kontrol | Tarih/işlem sorgula | Duplicate renew'e karşı fail-closed |
| PayTR payment callback | Ödemeyi reddet/incele | Yerel DB transaction tekrar | Sadece idempotent replay |
| Provider terminate | Sebep düzelt/manuel müdahale | Kaynağın gerçekten silindiğini doğrula | Kör tekrar yok |
