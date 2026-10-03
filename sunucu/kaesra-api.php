<?php
declare(strict_types=1);

/**
 * API uçlarının ortak katmanı: hata biçimi, kimlik, tekrar koruması.
 *
 * İki uç var (senkron ve oyuncu) ve ikisi de aynı sözleşmeye uyuyor.
 * Bunu her dosyada tekrar yazmak, birini düzeltip diğerini unutmanın en
 * kısa yolu olurdu.
 *
 * Hata gövdesi RFC 9457 problem+json. Spec alanları İngilizce (type, title,
 * status, detail), alan hataları Türkçe uzantı.
 */

require_once __DIR__ . '/kaesra-depo.php';

/** Kabul edilen zaman sapması. Saati 5 dakika şaşmış sunucu çalışır. */
const ZAMAN_PENCERESI = 300;

/** Tek istekte gönderilebilecek en fazla oyuncu. */
const AZAMI_OYUNCU = 40;

/** İstek gövdesi tavanı; sınırsız gövde bedava servis reddi demek. */
const AZAMI_GOVDE = 262144;   // 256 KiB

/**
 * Paylaşılan anahtarın durduğu dosya. Depoya (depo.json) bilerek konmadı:
 * depo yedeklenirken, taşınırken veya birine gösterilirken anahtar sızmaz.
 */
const ANAHTAR_DOSYA = __DIR__ . '/veri/anahtar.txt';

/**
 * RFC 9457 `type` alanının kökü. Hata tiplerini belgeleyen bir sayfanız
 * varsa buraya onun adresini yazın; yoksa bu haliyle de geçerli — `type`
 * alanının çözülebilir olması şart değil, tanımlayıcı olması yeterli.
 */
const HATA_TIP_KOK = 'https://example.invalid/kaesra/hata/';

/**
 * RFC 9457 hata gövdesi ve çıkış.
 *
 * detail bilerek uzun: Stripe'ın hata mesajları ~250 karakter ve başlığın
 * adını, biçimini, örnek değeri söylüyor. "Gecersiz istek" yazıp kapatmak
 * karşı taraftaki geliştiriciyi log okumaya mahkûm ediyor.
 */
function sorun(int $durum, string $baslik, string $ayrinti, array $ek = []): never
{
    http_response_code($durum);
    header('Content-Type: application/problem+json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode([
        'type'   => HATA_TIP_KOK . kod($baslik),
        'title'  => $baslik,
        'status' => $durum,
        'detail' => $ayrinti,
    ] + $ek, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function kod(string $baslik): string
{
    $sade = strtr($baslik, [
        'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
    ]);

    return strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $sade));
}

/** Anahtarı logda ve hata gövdesinde kısaltır: ilk 6 + son 4. */
function jetonKirp(string $jeton): string
{
    $n = strlen($jeton);
    return $n <= 12 ? str_repeat('*', $n) : substr($jeton, 0, 6) . '***' . substr($jeton, -4);
}

function yontemDayat(string $beklenen): void
{
    $gelen = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($gelen === $beklenen) {
        return;
    }

    header('Allow: ' . $beklenen);
    sorun(405, 'Yontem desteklenmiyor', sprintf(
        'Bu uc yalniz %s kabul ediyor, %s geldi.',
        $beklenen,
        preg_replace('/[^A-Z]/', '', $gelen) ?: '?'
    ));
}

/**
 * Paylaşılan anahtar ile kimlik doğrulama.
 *
 * Bu bir ÜRÜN ANAHTARI DEĞİL. Satış/lisans sistemi bu sürümde yok; anahtar
 * yalnızca "bu web servisine hangi oyun sunucusu yazabilir" sorusunun
 * cevabı. Her kurulum kendi anahtarını `anahtar-uret.php` ile üretir ve
 * aynı değeri iki tarafa yazar:
 *
 *   sunucu/veri/anahtar.txt                  ← web tarafı
 *   cstrike/addons/amxmodx/configs/kaesra.cfg ← kaesra_anahtar
 *
 * Anahtar bilerek depoda (depo.json) değil ayrı dosyada duruyor: depo
 * yedeklenirken veya birine gösterilirken anahtar sızmaz.
 *
 * 401 "anahtarı bilmiyorsun", 503 "sunucu tarafı daha kurulmamış".
 * Karşılaştırma hash_equals ile, yani sabit zamanlı — yanıt süresi ölçülerek
 * anahtar harf harf tahmin edilemesin diye.
 *
 * @return string "ip:port" biçiminde sunucu anahtarı
 */
function anahtarDogrula(int $port): string
{
    if (!is_file(ANAHTAR_DOSYA)) {
        header('Retry-After: 60');
        sorun(503, 'Anahtar uretilmemis', sprintf(
            'Sunucu tarafi henuz kurulmamis: %s dosyasi yok. "php anahtar-uret.php" '
            . 'calistirin; uretilen degeri hem bu dosyaya hem oyun sunucusundaki '
            . 'kaesra_anahtar cvar degerine yazin.',
            basename(ANAHTAR_DOSYA)
        ));
    }

    $beklenen = strtolower(trim((string) file_get_contents(ANAHTAR_DOSYA)));

    if (!preg_match('/^[0-9a-f]{64}$/', $beklenen)) {
        sorun(503, 'Anahtar dosyasi bozuk', sprintf(
            '%s icinde 64 haneli onaltilik bir anahtar olmali; bulunan %d karakter '
            . 've bicime uymuyor. Dosyayi silip "php anahtar-uret.php" ile yeniden '
            . 'uretin.',
            basename(ANAHTAR_DOSYA), strlen($beklenen)
        ));
    }

    $gelen = strtolower(trim((string) ($_SERVER['HTTP_X_KAESRA_ANAHTAR'] ?? '')));

    if ($gelen === '') {
        header('WWW-Authenticate: X-Kaesra-Anahtar realm="kaesra"');
        sorun(401, 'Anahtar yok', 'X-Kaesra-Anahtar basligi gerekiyor. Oyun '
            . 'sunucusunda kaesra_anahtar cvar degerine yazilir; eklenti her istekte bu '
            . 'basligi kendisi ekler. Ornek: "X-Kaesra-Anahtar: 3f2a...9c1d" '
            . '(64 hane onaltilik).');
    }

    if (!hash_equals($beklenen, $gelen)) {
        header('WWW-Authenticate: X-Kaesra-Anahtar realm="kaesra", error="invalid_key"');
        sorun(401, 'Anahtar taninmiyor', sprintf(
            'Gonderilen anahtar (%s) sunucudakiyle eslesmiyor. Iki tarafi da ayni '
            . 'degerle guncelleyin: sunucu/veri/anahtar.txt ve kaesra.cfg icindeki '
            . 'kaesra_anahtar. Basinda veya sonunda bosluk kalmadigindan emin olun.',
            jetonKirp($gelen)
        ));
    }

    /*
     * IP gövdeden DEĞİL REMOTE_ADDR'dan okunuyor: sunucu kendi adresini
     * uyduramasın. Port gövdeden geliyor çünkü sunucu dış portunu başka
     * türlü bilemez; yalnız parti anahtarını (idempotency) ayırmakta
     * kullanılıyor, bir yetki kararı değil.
     */
    return ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . ':' . $port;
}

/**
 * Zaman damgası ve tek kullanımlık jeton — tekrar saldırısına karşı.
 *
 * Nonce'u sadece kaydetmek yetmiyor, pencereyi de kontrol etmek gerekiyor:
 * pencere olmadan liste sonsuza kadar büyür, nonce olmadan pencere içinde
 * aynı istek defalarca oynatılabilir. İkisi birlikte anlamlı.
 *
 * @return array{0: array, 1: string} güncellenmiş depo ve nonce
 */
function tekrariEngelle(array $depo, int $simdi): array
{
    $zaman = (int) ($_SERVER['HTTP_X_KAESRA_ZAMAN'] ?? 0);
    $nonce = (string) ($_SERVER['HTTP_X_KAESRA_TEK'] ?? '');

    if ($zaman === 0 || $nonce === '') {
        sorun(400, 'Tekrar basliklari eksik', 'X-Kaesra-Zaman (unix saniye) ve '
            . 'X-Kaesra-Tek (32 haneli onaltilik) basliklarinin ikisi de gerekli. '
            . 'Ornek: "X-Kaesra-Zaman: ' . $simdi . '", '
            . '"X-Kaesra-Tek: 9f2c41ab7d0e5638c1a4b90fe2731d5c".');
    }

    if (!preg_match('/^[0-9a-fA-F]{16,64}$/', $nonce)) {
        sorun(400, 'Tek kullanimlik bozuk', 'X-Kaesra-Tek 16 ile 64 hane arasi '
            . 'onaltilik olmali; gelen ' . strlen($nonce) . ' karakter.');
    }

    $sapma = abs($simdi - $zaman);
    if ($sapma > ZAMAN_PENCERESI) {
        sorun(400, 'Zaman damgasi pencere disinda', sprintf(
            'X-Kaesra-Zaman %d, sunucu saati %d, aradaki fark %d saniye ve tavan %d. '
            . 'Oyun sunucusunun saati kaymis olabilir.',
            $zaman, $simdi, $sapma, ZAMAN_PENCERESI
        ));
    }

    $anahtar = strtolower($nonce);

    if (isset($depo['tekKullanim'][$anahtar])) {
        sorun(409, 'Tekrarlanmis istek', sprintf(
            'Bu X-Kaesra-Tek degeri %d saniye once kullanildi. Her istek yeni bir '
            . 'rastgele deger uretmeli.',
            $simdi - $depo['tekKullanim'][$anahtar]
        ));
    }

    $depo['tekKullanim'][$anahtar] = $simdi;

    return [$depo, $anahtar];
}

/** Gövdeyi okur ve JSON'a çevirir; bozuksa 400. */
function govdeyiCoz(): array
{
    $uzunluk = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($uzunluk > AZAMI_GOVDE) {
        sorun(413, 'Govde cok buyuk', sprintf(
            'Istek govdesi %d bayt, tavan %d. Partiyi bol: tek istekte en fazla %d oyuncu.',
            $uzunluk, AZAMI_GOVDE, AZAMI_OYUNCU
        ));
    }

    $ham = file_get_contents('php://input');
    if ($ham === false || $ham === '') {
        sorun(400, 'Govde bos', 'POST govdesi JSON olmali. Content-Type: application/json.');
    }

    try {
        $veri = json_decode($ham, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        sorun(400, 'JSON cozumlenemedi', 'Govde gecerli JSON degil: ' . $e->getMessage()
            . '. Ilk 60 karakter: ' . substr($ham, 0, 60));
    }

    if (!is_array($veri)) {
        sorun(400, 'JSON nesne degil', 'Govdenin koku bir JSON nesnesi olmali, '
            . gettype($veri) . ' geldi.');
    }

    return $veri;
}

/** Alan hatalarını RFC 9457 uzantısı olarak toplar. */
function alanHatasi(array $hatalar): never
{
    sorun(422, 'Alanlar gecersiz', sprintf(
        '%d alan kabul edilmedi. Ayrinti "hatalar" dizisinde.',
        count($hatalar)
    ), ['hatalar' => $hatalar]);
}

function json(int $durum, array $govde): never
{
    http_response_code($durum);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($govde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
