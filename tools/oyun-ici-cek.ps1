# Oyun içi ekran görüntüsü yakalayıcı
#
#   powershell -ExecutionPolicy Bypass -File .\oyun-ici-cek.ps1
#   powershell -ExecutionPolicy Bypass -File .\oyun-ici-cek.ps1 -Sure 120 -Aralik 3
#
# Ne yapar: Counter-Strike penceresini bulur ve her -Aralik saniyede bir
# kırparak ..\docs\oyun-ici\ klasörüne yazar. Sen oyunda /top, /rank,
# /rutbeler açarken görüntüler kendiliğinden birikir; fareye dokunmana
# gerek yok.
#
# Neden böyle: CS 1.6 istemcisi arka plandan başlatılamıyor (launcher'a
# elle tıklamak gerekiyor) ve MOTD penceresi ancak oyun içinde çiziliyor.
# Otomatik sürümün yapabildiği en iyi şey MOTD HTML'ini aynı ölçüde render
# etmek (docs/motd-*.png); GERÇEK oyun içi görünüm için bu betik + bir
# insan eli gerekiyor. İkisi birlikte tam resmi veriyor.
#
# Not: yalnızca CS penceresini kırpar, bütün masaüstünü almaz.

param(
    [int]$Sure   = 90,     # toplam kaç saniye izlensin
    [int]$Aralik = 3,      # kaç saniyede bir kare alınsın
    [string]$Cikti = (Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) '..\docs\oyun-ici')
)

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

Add-Type @"
using System;
using System.Runtime.InteropServices;
public struct RECT { public int Left, Top, Right, Bottom; }
public class Win {
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] public static extern bool IsIconic(IntPtr h);
}
"@

if (-not (Test-Path $Cikti)) { New-Item -ItemType Directory -Path $Cikti -Force | Out-Null }

$pencere = Get-Process | Where-Object {
    $_.MainWindowTitle -ne '' -and $_.MainWindowTitle -match 'Counter-Strike|Half-Life'
} | Select-Object -First 1

if (-not $pencere) {
    Write-Host "Counter-Strike penceresi bulunamadi. Once oyunu ac (CsOyna.exe)," -ForegroundColor Yellow
    Write-Host "sunucuya baglan, sonra bu betigi calistir." -ForegroundColor Yellow
    exit 1
}

Write-Host "Yakalanan pencere: $($pencere.MainWindowTitle)" -ForegroundColor Cyan
Write-Host "$Sure saniye boyunca her $Aralik sn'de bir kare -> $Cikti" -ForegroundColor Cyan
Write-Host "Simdi oyunda /top, /rank, /rutbeler, /silahlar, /haritalar, /karsilastir ac." -ForegroundColor Cyan
Write-Host "Durdurmak icin Ctrl+C." -ForegroundColor Cyan

$bitis = (Get-Date).AddSeconds($Sure)
$sayac = 0

while ((Get-Date) -lt $bitis) {
    $p = Get-Process -Id $pencere.Id -ErrorAction SilentlyContinue
    if (-not $p -or $p.MainWindowHandle -eq [IntPtr]::Zero) {
        Write-Host "Pencere kapandi, bitiriliyor." -ForegroundColor Yellow
        break
    }
    if (-not [Win]::IsIconic($p.MainWindowHandle)) {
        $r = New-Object RECT
        [Win]::GetWindowRect($p.MainWindowHandle, [ref]$r) | Out-Null
        $w = $r.Right - $r.Left; $h = $r.Bottom - $r.Top
        if ($w -gt 100 -and $h -gt 100) {
            $sayac++
            $ad = Join-Path $Cikti ("oyun-ici-{0:HHmmss}-{1:D2}.png" -f (Get-Date), $sayac)
            $bmp = New-Object System.Drawing.Bitmap $w, $h
            $g = [System.Drawing.Graphics]::FromImage($bmp)
            $g.CopyFromScreen($r.Left, $r.Top, 0, 0, (New-Object System.Drawing.Size $w, $h))
            $g.Dispose()
            $bmp.Save($ad, [System.Drawing.Imaging.ImageFormat]::Png)
            $bmp.Dispose()
            Write-Host ("  kare {0:D2} -> {1}" -f $sayac, (Split-Path $ad -Leaf)) -ForegroundColor Gray
        }
    }
    Start-Sleep -Seconds $Aralik
}

Write-Host "`nBitti: $sayac kare -> $Cikti" -ForegroundColor Green
Write-Host "Iyileri sec, gerisini sil; sonra repo'ya commit'le." -ForegroundColor Green
