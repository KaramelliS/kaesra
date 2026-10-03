<#

.SYNOPSIS

    Kaesra için CS 1.6 sunucusu kurar: motor, metamod, AMX Mod X, ReAPI,

    EasyHttp, sonra Kaesra'in kendisi.



.DESCRIPTION

    Beş bileşen birbirine bağlı ve kurulum sırası önemli. Bu betik sırayı

    ve bilinen beş tuzağı kodda tutuyor — elle kurarken her biri yaklaşık

    bir saat kaybettiriyor ve hata mesajları sebebi söylemiyor:



      1. ReHLDS paketinden YALNIZ swds.dll kopyalanır. hlds.exe de

         kopyalanırsa sunucu 'Failed to load "SDL3.dll"' deyip donar;

         Steam'in steam_legacy dalı SDL2 ile geliyor.

      2. liblist.gam içindeki gamedll satırı metamod'a çevrilir.

         'steamcmd validate' bunu ve mp.dll'i geri alır — validate'ten

         sonra bu betiği yeniden çalıştırın.

      3. game_init.cfg içinde bot_enable "0" duruyor; 1 yapılmadan

         bot_quota cvar'ı kaydolmuyor ve rcon komuta sessiz kalıyor.

      4. BotProfile.db ayracı BOŞLUK olmalı, sekme değil. Sekme olunca

         'Error parsing BotProfile.db - expected =' diyor ve tek bot

         bile girmiyor.

      5. Sıra: önce HLDS, sonra istemci. Revolution emülatörlü bir

         istemciyi önce açmak HKCU\...\ActiveProcess anahtarını bozuyor

         ve HLDS bir daha Steam'i ilklendiremiyor.



    Sürümler surumler.ini içinde; buraya gömülü değil.



.EXAMPLE

    .\kurulum.ps1 -Hedef "C:\Games\CS 1.6\cstrike"



.EXAMPLE

    .\kurulum.ps1 -Hedef "C:\Games\CS 1.6\cstrike" -YalnizKontrol

    # hiçbir dosyaya dokunmadan neyin eksik olduğunu raporlar



.NOTES

    Windows PowerShell 5.1 ve üzeri. İnternet gerekir (GitHub Releases +

    amxmodx.org). SteamCMD ve HLDS'in kendisi bu betiğin işi DEĞİL:

    onları önce kurun, bkz. KURULUM.md adım 1.

#>



[CmdletBinding()]

param(

    [Parameter(Mandatory = $true)]

    [string]$Hedef,



    # valve klasoru. Verilmezse hedefin kardeşi sayılır (cstrike <-> valve).

    [string]$Valve,



    # Hiçbir şey yazmadan durumu raporla.

    [switch]$YalnizKontrol,



    # İndirilmiş paketleri yeniden kullan, ağa çıkma.

    [switch]$AtlaIndirme,



    # BotProfile.db ve bot ayarlarını da kur (test sunucusu için).

    [switch]$Botlar

)



$ErrorActionPreference = 'Stop'

Set-StrictMode -Version 2.0



# Konsol OEM kod sayfasında (857/437) Türkçe karakterler bozuk çıkıyordu.

# Çıkışı UTF-8'e sabitliyoruz; girdiye dokunmuyoruz.

try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }



# GitHub TLS 1.2 istiyor; PowerShell 5.1 varsayılanı 1.0 ve el sıkışma

# sessizce başarısız oluyor ("İstek iptal edildi" dışında ipucu yok).

[Net.ServicePointManager]::SecurityProtocol =

    [Net.SecurityProtocolType]::Tls12 -bor [Net.ServicePointManager]::SecurityProtocol



$Kok      = Split-Path -Parent $MyInvocation.MyCommand.Path

$ProjeKok = Split-Path -Parent $Kok

$Onbellek = Join-Path $Kok 'onbellek'

$Surumler = Join-Path $Kok 'surumler.ini'



function Yaz([string]$Mesaj, [string]$Tur = 'bilgi') {

    $renk = @{ bilgi='Gray'; tamam='Green'; uyari='Yellow'; hata='Red'; adim='Cyan' }[$Tur]

    Write-Host $Mesaj -ForegroundColor $renk

}

function Dur([string]$Mesaj) { Yaz "HATA: $Mesaj" 'hata'; exit 1 }



# ---------------------------------------------------------------- sürümler



function SurumleriOku {

    if (-not (Test-Path $Surumler)) { Dur "surumler.ini bulunamadı: $Surumler" }



    $bol = @{}; $suanki = $null

    foreach ($satir in (Get-Content $Surumler -Encoding UTF8)) {

        if ($satir -match '^\s*#' -or $satir -match '^\s*$') { continue }

        if ($satir -match '^\[(.+)\]\s*$') { $suanki = $Matches[1]; $bol[$suanki] = @{}; continue }

        if ($satir -match '^\s*([A-Za-z]+)\s*=\s*(.*)$' -and $suanki) {

            $bol[$suanki][$Matches[1]] = $Matches[2].Trim()

        }

    }

    return $bol

}



function UrlGetir($Bolum, [string]$Isletim) {

    $etiket = $Bolum.etiket

    if ($Bolum.ContainsKey($Isletim)) {

        return @($Bolum[$Isletim] -split ' ' | ForEach-Object {

            "https://github.com/$($Bolum.depo)/releases/download/$etiket/$($_ -replace '%s', $etiket)"

        })

    }

    if ($Bolum.ContainsKey('dosya')) {

        $ad = $Bolum.dosya -replace '%s', $etiket

        return @("https://github.com/$($Bolum.depo)/releases/download/$etiket/$ad")

    }

    Dur "$($Bolum.aciklama) için $Isletim adresi surumler.ini içinde tanımlı değil"

}



function Indir([string]$Url, [string]$Klasor) {

    $ad = Split-Path $Url -Leaf

    $yol = Join-Path $Klasor $ad



    if (Test-Path $yol) {

        $boyut = (Get-Item $yol).Length

        if ($boyut -gt 1024) {

            Yaz " _cached_ $ad ($([math]::Round($boyut/1MB,1)) MB)" 'tamam'

            return $yol

        }

        Remove-Item $yol -Force   # yarım kalmış indirme

    }



    Yaz "  indiriliyor $ad"

    $gecici = "$yol.indiriliyor"

    try {

        Invoke-WebRequest -Uri $Url -OutFile $gecici -UseBasicParsing -TimeoutSec 300

    } catch {

        if (Test-Path $gecici) { Remove-Item $gecici -Force }

        Dur "indirilemedi: $Url`n  $($_.Exception.Message)"

    }

    Move-Item $gecici $yol -Force

    return $yol

}



# ------------------------------------------------------------- dosya işleri



function Ac([string]$Zip, [string]$Klasor) {

    if (Test-Path $Klasor) { Remove-Item $Klasor -Recurse -Force }

    New-Item -ItemType Directory -Path $Klasor -Force | Out-Null



    if ($Zip -match '\.zip$') {

        Expand-Archive -Path $Zip -DestinationPath $Klasor -Force

    } elseif ($Zip -match '\.(tar\.gz|tgz)$') {

        # Windows 10 1803+ ile tar.exe System32 içinde geliyor.

        $tar = Get-Command tar.exe -ErrorAction SilentlyContinue

        if (-not $tar) { Dur "tar.exe bulunamadı; .tar.gz paketi açılamıyor: $Zip" }

        & tar.exe -xzf $Zip -C $Klasor

        if ($LASTEXITCODE -ne 0) { Dur "tar açılamadı: $Zip" }

    } else {

        Dur "bilinmeyen paket biçimi: $Zip"

    }

    return $Klasor

}



function IlkBul([string]$Kok2, [string]$Desen) {

    $b = Get-ChildItem -Path $Kok2 -Recurse -Filter $Desen -File -ErrorAction SilentlyContinue |

         Select-Object -First 1

    if ($b) { return $b.FullName }

    return $null

}



# ------------------------------------------------------------------ kurulum



$S = SurumleriOku

$Hedef = (Resolve-Path $Hedef -ErrorAction SilentlyContinue).Path

if (-not $Hedef) { Dur "hedef klasör yok: $Hedef (önce HLDS + cstrike kurulu olmalı)" }

if (-not (Test-Path (Join-Path $Hedef 'liblist.gam'))) {

    Dur "bu bir cstrike klasörü gibi görünmüyor (liblist.gam yok): $Hedef"

}

if (-not $Valve) { $Valve = Split-Path -Parent $Hedef }

$Valve = (Resolve-Path $Valve -ErrorAction SilentlyContinue).Path



if (-not (Test-Path $Onbellek)) { New-Item -ItemType Directory -Path $Onbellek -Force | Out-Null }



Yaz "Hedef : $Hedef" 'adim'

Yaz "Valve : $Valve"

Yaz "Sürüm : AMXX $($S.amxmodx.etiket) · ReHLDS $($S.rehlds.etiket) · ReGameDLL $($S.regamedll.etiket) · ReAPI $($S.reapi.etiket) · Metamod-R $($S.metamod.etiket) · EasyHttp $($S.easyhttp.etiket)"

Yaz ''



$Gunluk = [System.Collections.Generic.List[string]]::new()

function Kaydet([string]$s) { $script:Gunluk.Add($s) }



if ($YalnizKontrol) {

    Yaz "Yalnız kontrol modu — hiçbir dosyaya dokunulmayacak." 'uyari'

    Yaz ''

    $eksik = @()

    foreach ($k in @('amxmodx','rehlds','regamedll','metamod','reapi','easyhttp')) {

        foreach ($u in (UrlGetir $S[$k] 'windows')) {

            $y = Join-Path $Onbellek (Split-Path $u -Leaf)

            if (Test-Path $y) { Yaz "  [var]   $(Split-Path $u -Leaf)" 'tamam' }

            else { Yaz "  [yok]   $(Split-Path $u -Leaf)" 'uyari'; $eksik += $u }

        }

    }

    Yaz ''

    if ($eksik.Count -eq 0) { Yaz 'Bütün paketler önbellekte.' 'tamam' }

    else { Yaz "$($eksik.Count) paket indirilmemiş. -AtlaIndirme olmadan çalıştırın." 'uyari' }

    exit 0

}



# --- 1) indir -----------------------------------------------------------

Yaz '[1/6] Paketler indiriliyor' 'adim'

$Paket = @{}

foreach ($k in @('amxmodx','rehlds','regamedll','metamod','reapi','easyhttp')) {

    Yaz "  $($S[$k].aciklama)"

    $Paket[$k] = @()

    foreach ($u in (UrlGetir $S[$k] 'windows')) {

        $Paket[$k] += (Indir $u $Onbellek)

    }

}

Yaz ''



# --- 2) motor: ReHLDS + ReGameDLL ---------------------------------------

Yaz '[2/6] Motor kuruluyor (ReHLDS + ReGameDLL)' 'adim'



$g = Ac $Paket['rehlds'][0] (Join-Path $env:TEMP "kaesra-rehlds-$PID")



# TUZAK 1: hlds.exe KOPYALANMIYOR. Yalnız swds.dll.

$swds = IlkBul $g 'swds.dll'

if (-not $swds) { Dur "ReHLDS paketinde swds.dll bulunamadı" }

Copy-Item $swds (Join-Path $Valve 'swds.dll') -Force

Kaydet "swds.dll -> valve\  (hlds.exe BILEREK kopyalanmadi: SDL3.dll assertion)"

Yaz "  swds.dll kopyalandı (hlds.exe bilerek atlandı)" 'tamam'



$gd = Ac $Paket['regamedll'][0] (Join-Path $env:TEMP "kaesra-regamedll-$PID")

foreach ($h in @('mp.dll','cs.dll')) {

    $bul = IlkBul $gd $h

    if ($bul) {

        $yer = Join-Path $Hedef "dlls\$h"

        if (-not (Test-Path (Join-Path $Hedef 'dlls'))) {

            New-Item -ItemType Directory -Path (Join-Path $Hedef 'dlls') -Force | Out-Null

        }

        Copy-Item $bul $yer -Force

        Kaydet "$h -> cstrike\dlls\"

        Yaz "  $h kopyalandı" 'tamam'

    }

}

Yaz ''



# --- 3) Metamod-R + liblist.gam -----------------------------------------

Yaz '[3/6] Metamod-R kuruluyor' 'adim'

$mm = Ac $Paket['metamod'][0] (Join-Path $env:TEMP "kaesra-metamod-$PID")

$mmDll = IlkBul $mm 'metamod.dll'

if (-not $mmDll) { Dur "Metamod-R paketinde metamod.dll bulunamadı" }

$mmHedef = Join-Path $Hedef 'addons\metamod'

New-Item -ItemType Directory -Path $mmHedef -Force | Out-Null

Copy-Item $mmDll (Join-Path $mmHedef 'metamod.dll') -Force

Kaydet "metamod.dll -> cstrike\addons\metamod\"



# TUZAK 2: liblist.gam gamedll satırı.

$liblist = Join-Path $Hedef 'liblist.gam'

$icerik = Get-Content $liblist -Raw

$yeni = $icerik -replace 'gamedll\s+"[^"]*"', 'gamedll "addons\metamod\metamod.dll"'

if ($yeni -eq $icerik) {

    $yeni = $icerik.TrimEnd() + "`r`ngamedll `"addons\metamod\metamod.dll`"`r`n"

}

if ($yeni -ne $icerik) {

    Copy-Item $liblist "$liblist.kaesra-yedek" -Force

    Set-Content -Path $liblist -Value $yeni -NoNewline -Encoding ASCII

    Kaydet "liblist.gam gamedll -> metamod (yedek: liblist.gam.kaesra-yedek)"

    Yaz "  liblist.gam: gamedll -> addons\metamod\metamod.dll" 'tamam'

} else {

    Yaz "  liblist.gam zaten metamod'a bakıyor" 'tamam'

}

Yaz "  UYARI: 'steamcmd validate' bu satırı geri alır; validate'ten sonra yeniden çalıştırın." 'uyari'

Yaz ''



# --- 4) AMX Mod X + ReAPI + EasyHttp ------------------------------------

Yaz '[4/6] AMX Mod X, ReAPI ve EasyHttp kuruluyor' 'adim'

foreach ($p in $Paket['amxmodx']) {

    $d = Ac $p (Join-Path $env:TEMP "kaesra-amxx-$([guid]::NewGuid().ToString('N').Substring(0,8))")

    $addons = Join-Path $d 'addons'

    if (Test-Path $addons) {

        Copy-Item "$addons\*" $Valve -Recurse -Force

        Kaydet "amxmodx: $(Split-Path $p -Leaf) -> valve\"

    } else {

        Yaz "  beklenmedik paket yapısı: $p (addons/ yok)" 'uyari'

    }

}

foreach ($k in @('reapi','easyhttp')) {

    $d = Ac $Paket[$k][0] (Join-Path $env:TEMP "kaesra-$k-$PID")

    $addons = Join-Path $d 'addons'

    if (Test-Path $addons) {

        Copy-Item "$addons\*" $Valve -Recurse -Force

        Kaydet "${k}: -> valve\"

        Yaz "  $k kuruldu" 'tamam'

    } else {

        Yaz "  beklenmedik paket yapısı: $k" 'uyari'

    }

}

Yaz ''



# --- 5) Kaesra ------------------------------------------------------------

Yaz '[5/6] Kaesra kuruluyor' 'adim'

$amxx = Join-Path $Valve 'addons\amxmodx'

if (-not (Test-Path $amxx)) { Dur "AMX Mod X kurulamadı: $amxx yok" }



$plugins  = Join-Path $amxx 'plugins'

$configs  = Join-Path $amxx 'configs'

$scripting= Join-Path $amxx 'scripting'

foreach ($d in @($plugins,$configs,$scripting)) {

    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Path $d -Force | Out-Null }

}



# 5a) kaynak + derleme

$smaKaynak = Join-Path $ProjeKok 'oyun\kaesra.sma'

if (-not (Test-Path $smaKaynak)) { Dur "kaesra.sma bulunamadı: $smaKaynak" }

Copy-Item $smaKaynak (Join-Path $scripting 'kaesra.sma') -Force

Kaydet 'kaesra.sma -> scripting\'



$derleyici = Join-Path $scripting 'amxxpc.exe'

if (Test-Path $derleyici) {

    Push-Location $scripting

    try {

        $cikti = & $derleyici 'kaesra.sma' "-o$plugins\kaesra.amxx" 2>&1

        if ($LASTEXITCODE -ne 0) {

            Yaz ($cikti -join "`n") 'hata'

            Dur 'kaesra.sma derlenemedi — yukarıdaki hataya bakın'

        }

        $uyari = ($cikti | Select-String -Pattern 'warning' -SimpleMatch).Count

        $ek = if ($uyari -gt 0) { ", $uyari uyarı" } else { "" }

        Kaydet "kaesra.amxx derlendi$ek"

        Yaz "  kaesra.amxx derlendi$ek" 'tamam'

    } finally { Pop-Location }

} else {

    Yaz "  amxxpc.exe yok, derleme atlandı — hazır .amxx kullanılıyor" 'uyari'

    $hazir = Join-Path $ProjeKok 'oyun\kaesra.amxx'

    if (Test-Path $hazir) { Copy-Item $hazir (Join-Path $plugins 'kaesra.amxx') -Force }

}



# 5b) kaesra.cfg

$cfgKaynak = Join-Path $ProjeKok 'oyun\kaesra.cfg'

$cfgHedef  = Join-Path $configs 'kaesra.cfg'

if (Test-Path $cfgHedef) {

    Yaz "  kaesra.cfg zaten var, ÜSTÜNE YAZILMADI (ayarlarınız korunuyor)" 'uyari'

} else {

    Copy-Item $cfgKaynak $cfgHedef -Force

    Kaydet 'kaesra.cfg -> configs\'

    Yaz "  kaesra.cfg kuruldu" 'tamam'

}



# 5c) plugins.ini — kaesra.amxx satırı

$pluginsIni = Join-Path $configs 'plugins.ini'

$metin = if (Test-Path $pluginsIni) { Get-Content $pluginsIni } else { @() }

if (-not ($metin -match '^\s*kaesra\.amxx\s*$')) {

    Add-Content -Path $pluginsIni -Value 'kaesra.amxx' -Encoding ASCII

    Kaydet 'plugins.ini += kaesra.amxx'

    Yaz "  plugins.ini: kaesra.amxx eklendi" 'tamam'

} else {

    Yaz "  plugins.ini: kaesra.amxx zaten kayıtlı" 'tamam'

}



# 5d) modules.ini — reapi + easy_http açık olmalı

$modulesIni = Join-Path $configs 'modules.ini'

$mod = if (Test-Path $modulesIni) { Get-Content $modulesIni -Raw } else { '' }

foreach ($m in @('reapi','easy_http')) {

    if ($mod -notmatch "(?m)^\s*;$m\s*$" -and $mod -notmatch "(?m)^\s*$m\s*$") {

        Add-Content -Path $modulesIni -Value $m -Encoding ASCII

        Kaydet "modules.ini += $m"

        Yaz "  modules.ini: $m eklendi" 'tamam'

    } elseif ($mod -match "(?m)^\s*;$m\s*$") {

        # yorum satırını aç

        (Get-Content $modulesIni) -replace "^\s*;\s*$m\s*$", $m |

            Set-Content $modulesIni -Encoding ASCII

        Kaydet "modules.ini: $m yorumdan cikarildi"

        Yaz "  modules.ini: $m etkinleştirildi" 'tamam'

    }

}



# 5e) server.cfg sonuna exec satırı

$serverCfg = Join-Path $Hedef 'server.cfg'

$execSatir = 'exec addons/amxmodx/configs/kaesra.cfg'

$sc = if (Test-Path $serverCfg) { Get-Content $serverCfg } else { @() }

if (-not ($sc -match [regex]::Escape($execSatir))) {

    Add-Content -Path $serverCfg -Value "`r`n// Kaesra ayarlari (kurulum betigi ekledi)" -Encoding ASCII

    Add-Content -Path $serverCfg -Value $execSatir -Encoding ASCII

    Kaydet 'server.cfg += exec kaesra.cfg'

    Yaz "  server.cfg: exec satırı eklendi" 'tamam'

} else {

    Yaz "  server.cfg: exec satırı zaten var" 'tamam'

}

Yaz ''



# --- 6) botlar (isteğe bağlı) -------------------------------------------

if ($Botlar) {

    Yaz '[6/6] Bot ayarları' 'adim'

    # TUZAK 3: bot_enable

    $gi = Join-Path $Hedef 'game_init.cfg'

    if (Test-Path $gi) {

        $g2 = (Get-Content $gi) -replace 'bot_enable\s+"0"', 'bot_enable "1"'

        Set-Content -Path $gi -Value $g2 -Encoding ASCII

        Kaydet 'game_init.cfg: bot_enable 1'

        Yaz '  game_init.cfg: bot_enable "1"' 'tamam'

    } else {

        Yaz '  game_init.cfg yok — bot_enable elle açılmalı' 'uyari'

    }

    # TUZAK 4: BotProfile.db ayracı boşluk olmalı

    $bpKaynak = Join-Path $Kok 'BotProfile.db'

    $bpHedef  = Join-Path $Hedef 'BotProfile.db'

    if (Test-Path $bpKaynak) {

        Copy-Item $bpKaynak $bpHedef -Force

        $sekme = (Get-Content $bpHedef -Raw) -match "`t"

        if ($sekme) {

            Yaz '  UYARI: BotProfile.db içinde sekme var — botlar hiç girmeyebilir' 'uyari'

        } else {

            Yaz '  BotProfile.db kuruldu (ayraç boşluk, doğrulandı)' 'tamam'

            Kaydet 'BotProfile.db -> cstrike\'

        }

    }

    Yaz ''

}



# --- özet ----------------------------------------------------------------

Yaz 'KURULUM TAMAMLANDI' 'tamam'

Yaz ''

Yaz 'Yapılanlar:'

foreach ($g2 in $Gunluk) { Yaz "  - $g2" }

Yaz ''

Yaz 'SIRADAKİ ADIMLAR' 'adim'

Yaz '  1. Web servisini başlatın:'
Yaz '       cd sunucu; php -S 0.0.0.0:8130'
Yaz '  2. kaesra.cfg içindeki kaesra_api adresinin bu servise baktığını'
Yaz '     doğrulayın (varsayılan http://127.0.0.1:8130).'
Yaz '     Anahtar, jeton veya token YOK — eklenti servise doğrudan yazar.'
Yaz '  3. Sunucuyu başlatın:'
Yaz '       hlds.exe -console -game cstrike -insecure -port 27015 +maxplayers 16 +map de_dust2'
Yaz '  4. Konsolda doğrulayın:'
Yaz '       kaesra_durum'
Yaz '     "api : http://..." satırını görmelisiniz.'
Yaz '  5. İlk tur bitince sıralama dolmaya başlar; /top ile kontrol edin.'

Yaz ''

Yaz '  TUZAK 5 — sıra önemli: ÖNCE sunucuyu, SONRA istemciyi açın.' 'uyari'

Yaz '  Revolution emülatörlü bir istemciyi önce açmak ActiveProcess' 'uyari'

Yaz '  anahtarını bozuyor ve HLDS bir daha Steam ilklendirmesini yapamıyor.' 'uyari'

