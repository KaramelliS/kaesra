<?php
declare(strict_types=1);

/**
 * GoldSrc rcon istemcisi.
 *
 *   SICIL_RCON_SIFRE=<sifre> php rcon.php "status"
 *   php rcon.php --sifre <sifre> "bot_quota 10" "bot_add_t"
 *
 * Sifre dosyada DURMUYOR - ortamdan veya komut satirindan geliyor.
 *
 * Protokol iki adım: önce challenge sayısı isteniyor, sonra komut o sayıyla
 * birlikte gönderiliyor. Challenge'ı önbelleğe almıyoruz — sunucu yeniden
 * başlayınca eskisi geçersiz oluyor ve hata mesajı "Bad rcon_password" diye
 * geliyor, ki yanıltıcı.
 */

/*
 * Adres, port ve şifre SABİT KODLANMIYOR. Eski sürümde şifre dosyanın
 * içindeydi ve depo herkese açıldığı anda sızıyordu. Şimdi üçü de
 * ortamdan veya komut satırından geliyor:
 *
 *   SICIL_RCON_$sifre=benimsifrem php rcon.php "status"
 *   php rcon.php --sifre x --adres 127.0.0.1 --port 27015 "status"
 *
 * Geliştirmede kolaylık için şifre test/.rcon-sifre dosyasından da
 * okunabiliyor; o dosya .gitignore'da.
 */
$secenekler = getopt('', ['adres:', 'port:', 'sifre:']);

$sunucu = (string) ($secenekler['adres'] ?? (getenv('SICIL_RCON_ADRES') ?: '127.0.0.1'));
$port   = (int) ($secenekler['port'] ?? (getenv('SICIL_RCON_$port') ?: 27015));

$sifre = (string) ($secenekler['sifre'] ?? (getenv('SICIL_RCON_$sifre') ?: ''));
if ($sifre === '') {
    $dosya = __DIR__ . '/.rcon-sifre';
    if (is_file($dosya)) {
        $sifre = trim((string) file_get_contents($dosya));
    }
}

// getopt argümanları $argv içinde kalıyor; değerleriyle birlikte temizle.
$komutlar = [];
$atla = 0;
foreach (array_slice($argv, 1) as $parca) {
    if ($atla > 0) { $atla--; continue; }
    if ($parca === '--adres' || $parca === '--port' || $parca === '--sifre') { $atla = 1; continue; }
    if (str_starts_with($parca, '--')) { continue; }
    $komutlar[] = $parca;
}

if ($komutlar === []) {
    fwrite(STDERR, "Kullanım: SICIL_RCON_$sifre=<sifre> php rcon.php \"status\"
");
    exit(2);
}
if ($sifre === '') {
    fwrite(STDERR, 'rcon sifresi verilmedi. SICIL_RCON_SIFRE ortam degiskenini,
'
        . '--sifre secenegini veya test/.rcon-sifre dosyasini kullanin.
');
    exit(2);
}

$soket = @stream_socket_client(
    sprintf('udp://%s:%d', $sunucu, $port),
    $hataNo,
    $hataMesaji,
    3
);

if ($soket === false) {
    fwrite(STDERR, sprintf(
        "%s:%d adresine UDP soketi açılamadı: %s (%d)\n",
        $sunucu,
        $port,
        $hataMesaji,
        $hataNo
    ));
    exit(1);
}

stream_set_timeout($soket, 3);

foreach ($komutlar as $komut) {
    $challenge = challengeAl($soket);
    if ($challenge === null) {
        fwrite(STDERR, "Sunucu challenge vermedi. Ayakta mı? (port " . $port . ")\n");
        exit(1);
    }

    $yanit = gonder($soket, sprintf('rcon %s "%s" %s', $challenge, $sifre, $komut));

    echo "\$ {$komut}\n";
    echo rtrim($yanit) === '' ? "(yanıt yok)\n" : rtrim($yanit) . "\n";
    echo str_repeat('-', 60) . "\n";
}

fclose($soket);

function gonder($soket, string $yuk): string
{
    fwrite($soket, "\xFF\xFF\xFF\xFF" . $yuk . "\n");

    $toplam = '';
    // Uzun yanıtlar (status, cvarlist) birden fazla pakete bölünüyor;
    // zaman aşımına kadar okumaya devam ediyoruz.
    //
    // Başlık dört bayt (FF FF FF FF). Rcon yanıtlarında beşinci bayt 'l' tip
    // işareti, ama challenge yanıtında yok — beşinci baytı koşulsuz atmak
    // "challenge rcon 123" metnini "hallenge rcon 123" yapıyor ve çözümleme
    // sessizce başarısız oluyor.
    while (($parca = fread($soket, 4096)) !== false && $parca !== '') {
        $govde = substr($parca, 4);
        if (($govde[0] ?? '') === 'l') {
            $govde = substr($govde, 1);
        }
        $toplam .= $govde;

        $durum = stream_get_meta_data($soket);
        if ($durum['timed_out']) {
            break;
        }
    }

    return $toplam;
}

function challengeAl($soket): ?string
{
    $yanit = gonder($soket, 'challenge rcon');
    return preg_match('/challenge rcon (-?\d+)/', $yanit, $eslesme) === 1
        ? $eslesme[1]
        : null;
}
