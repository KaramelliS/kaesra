<?php
declare(strict_types=1);

/**
 * Oyun tarafının okuduğu tek uç: bir veya birkaç oyuncunun rütbe özeti.
 *
 *   GET /api-oyuncu.php?kimlik=STEAM_0:1:492817364
 *   GET /api-oyuncu.php?kimlik=STEAM_0:1:492817364,VALVE_0:0:1774092388
 *
 * Plugin bunu oyuncu bağlanınca bir kez çağırıp önbelleğe alıyor; sohbet
 * etiketi, /kp ve rütbe atlama duyurusu hep o önbellekten okunuyor. Her
 * mesajda istek atmak 32 kişilik bir sunucuda saniyede onlarca çağrı demekti.
 *
 * Toplu sorgu (virgülle ayrılmış kimlik) bilerek var: harita başında 20
 * oyuncu birden bağlanıyor ve 20 ayrı istek yerine bir tane gidiyor.
 *
 * Hata gövdesi RFC 9457 problem+json. Şu an tohum veriden okuyor; gerçek
 * sorguya geçince yalnız bu dosyanın gövdesi değişecek, sözleşme aynı kalacak.
 */

require __DIR__ . '/kaesra-ortak.php';

const AZAMI_KIMLIK = 32;

header('Cache-Control: no-store');

/** RFC 9457. Spec alanları İngilizce, alan hataları Türkçe uzantı. */
function sorun(int $durum, string $baslik, string $ayrinti, array $ek = []): never
{
    http_response_code($durum);
    header('Content-Type: application/problem+json; charset=utf-8');

    echo json_encode([
        'type'   => HATA_TIP_KOK . strtolower(str_replace(' ', '-', $baslik)),
        'title'  => $baslik,
        'status' => $durum,
        'detail' => $ayrinti,
    ] + $ek, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    sorun(405, 'Yontem desteklenmiyor', 'Bu uc yalniz GET kabul ediyor.');
}

$ham = (string) ($_GET['kimlik'] ?? '');
if (trim($ham) === '') {
    sorun(400, 'Kimlik yok', 'kimlik parametresi zorunlu. Ornek: ?kimlik=STEAM_0:1:492817364');
}

$istenenler = array_values(array_unique(array_filter(array_map('trim', explode(',', $ham)))));

if (count($istenenler) > AZAMI_KIMLIK) {
    sorun(422, 'Cok fazla kimlik', sprintf(
        'Tek istekte en fazla %d kimlik sorgulanabilir, %d gonderildi.',
        AZAMI_KIMLIK,
        count($istenenler)
    ), ['gonderilen' => count($istenenler), 'tavan' => AZAMI_KIMLIK]);
}

$oyuncular = oyuncuListesi();

/** Kimlik -> [sira, kayit]. Döngü içinde arama yapmamak için tek geçiş. */
$dizin = [];
foreach ($oyuncular as $i => $o) {
    $dizin[$o['kimlik']] = [$i + 1, $o];
}

$sonuc = [];
$bulunamayan = [];

foreach ($istenenler as $kimlik) {
    if (!isset($dizin[$kimlik])) {
        $bulunamayan[] = $kimlik;
        continue;
    }

    [$sira, $o] = $dizin[$kimlik];
    $k = kademe($o['kp']);
    $enIyiSilah = silah(array_key_first($o['silahlar']));

    $sonuc[] = [
        'kimlik'    => $kimlik,
        'ad'        => $o['ad'],
        'sira'      => $sira,
        'kp'        => $o['kp'],
        'kademe'    => $k['ad'],
        'kademeNo'  => $k['no'],
        'renk'      => $k['renk'],
        'rr'        => $k['rr'],
        'kalan'     => $k['kalan'],
        'ustKademe' => $k['ustAdi'],
        'kill'      => $o['kill'],
        'death'     => $o['death'],
        'kd'        => (float) str_replace(',', '.', kd($o['kill'], $o['death'])),
        'hs'        => hsYuzde($o['hs'], $o['kill']),
        'mvp'       => $o['mvp'],
        'saniye'    => $o['saniye'],
        'ajan'      => $o['ajan'],
        'enIyiSilah'=> $enIyiSilah['ad'],
    ];
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'toplam'      => count($oyuncular),
    'oyuncular'   => $sonuc,
    'bulunamayan' => $bulunamayan,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
