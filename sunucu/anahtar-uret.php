<?php
declare(strict_types=1);

/**
 * Paylaşılan anahtarı üretir.
 *
 *   php anahtar-uret.php           üret ve veri/anahtar.txt'ye yaz
 *   php anahtar-uret.php --goster  yalnız ekrana bas, dosyaya yazma
 *   php anahtar-uret.php --zorla   var olanı değiştir
 *
 * Çıkan 64 haneli değeri İKİ yere de yazmak gerekiyor:
 *
 *   1. sunucu/veri/anahtar.txt                      (bu betik yapar)
 *   2. cstrike/addons/amxmodx/configs/sicil.cfg     → sicil_anahtar "..."
 *
 * İkisi birebir aynı olmazsa API her isteği 401 ile çevirir ve eklenti
 * sessizce veri göndermez.
 *
 * Bu bir ürün anahtarı değil, bir sırdır: kimseye vermeyin, repoya
 * koymayın, ekran görüntüsüne almayın. Sızarsa --zorla ile yenileyin.
 */

$kok = __DIR__;
$hedef = $kok . '/veri/anahtar.txt';

$argumanlar = array_slice($argv ?? [], 1);
$goster = in_array('--goster', $argumanlar, true);
$zorla = in_array('--zorla', $argumanlar, true);

/**
 * 32 bayt = 64 hane onaltılık.
 *
 * random_bytes tercih ediliyor; yoksa /dev/urandom, o da yoksa OpenSSL.
 * Üçü de kriptografik olarak güvenli. uniqid()/rand() BİLEREK kullanılmıyor:
 * ikisi de tahmin edilebilir ve anahtarın bütün değeri tahmin edilemez
 * olmasında.
 */
function uret(): string
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    }

    if (is_readable('/dev/urandom')) {
        $ham = file_get_contents('/dev/urandom', false, null, 0, 32);
        if ($ham !== false && strlen($ham) === 32) {
            return bin2hex($ham);
        }
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $güçlü = false;
        $ham = openssl_random_pseudo_bytes(32, $güçlü);
        if ($güçlü && $ham !== false) {
            return bin2hex($ham);
        }
    }

    fwrite(STDERR, "Guvenli rastgele kaynak bulunamadi. Anahtar uretilmedi.\n");
    exit(1);
}

$anahtar = uret();

if ($goster) {
    echo $anahtar, "\n";
    exit(0);
}

if (is_file($hedef) && !$zorla) {
    $eski = trim((string) file_get_contents($hedef));
    fwrite(STDERR, sprintf(
        "%s zaten var (%s***%s).\nUstune yazmak icin:  php anahtar-uret.php --zorla\n"
        . "Not: yenilerseniz sicil.cfg icindeki sicil_anahtar degerini de\n"
        . "guncellemeniz gerekiyor, yoksa API 401 doner.\n",
        basename($hedef),
        substr($eski, 0, 6),
        substr($eski, -4)
    ));
    exit(2);
}

$klasor = dirname($hedef);
if (!is_dir($klasor) && !mkdir($klasor, 0775, true) && !is_dir($klasor)) {
    fwrite(STDERR, "Klasor olusturulamadi: $klasor\n");
    exit(1);
}

/* 0600: anahtarı yalnız dosyanın sahibi okuyabilsin. Paylaşımlı barındırmada
   world-readable bir anahtar, herkesin sizin adınıza istatistik yazabilmesi
   demek. */
if (file_put_contents($hedef, $anahtar . "\n") === false) {
    fwrite(STDERR, "Yazilamadi: $hedef\n");
    exit(1);
}
@chmod($hedef, 0600);

echo "Anahtar uretildi: $hedef\n\n";
echo "  $anahtar\n\n";
echo "Simdi bunu oyun sunucusuna yazin:\n";
echo "  cstrike/addons/amxmodx/configs/sicil.cfg\n";
echo "  sicil_anahtar \"$anahtar\"\n\n";
echo "Sonra haritayi yenileyin (changelevel de_dust2) ve konsolda\n";
echo "  sicil_durum\n";
echo "calistirip 'paylasilan anahtar: ayarli' satirini dogrulayin.\n";
