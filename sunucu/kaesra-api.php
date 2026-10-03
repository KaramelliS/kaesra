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
 * RFC 9457 `type` alanının kökü. Hata tiplerini belgeleyen bir sayfanız
 * varsa buraya onun adresini yazın; yoksa bu haliyle de geçerli — `type`
 * alanının çözülebilir olması şart değil, tanımlayıcı olması yeterli.
 */
const HATA_TIP_KOK = 'https://github.com/KaramelliS/kaesra/blob/main/KURULUM.md#sorun-giderme';

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
