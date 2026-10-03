# Yayın metni

İki biçim var: **forum/Discord** gönderisi (samimi, kısa) ve **GitHub
release** notu (yapısal). İkisini de olduğu gibi kopyalayabilirsiniz.
Köşeli parantezleri doldurmayı unutmayın: `[DEPO-ADRESI]`, `[İLETİŞİM]`.

---

## 1) Forum / Discord gönderisi

> **Sicil — CS 1.6 için rank ve istatistik sistemi (açık kaynak)**
>
> İki yıldır kendi sunucumda çalıştırdığım rank sistemini açık kaynak
> yapıyorum. Oyuncu öldürür, asist yapar, bomba kurar, tur kazanır; Sicil
> bunları toplar ve oyun içinde MOTD sayfaları olarak gösteriyor:
> profil, sıralama, rütbe merdiveni, silah ve harita kırılımı, iki oyuncuyu
> yan yana karşılaştırma.
>
> **Nesi farklı?**
>
> - KP ve rütbe **oyun sunucusunda hesaplanmıyor**. Eklenti yalnız ham
>   sayaç gönderiyor, formül PHP tarafında. Yani `.sma`'yı düzenleyerek
>   rütbe şişirmek yok; formülü değiştirdiğimde kimseye yeni `.amxx`
>   dağıtmam gerekmiyor ve formülü düzelttiğimde **bütün sıralama geriye
>   dönük düzeliyor**.
> - `csstats` modülüne bağımlılık yok; toplama ReAPI hook zincirleriyle.
> - Kurulum tek betik: `kurulum.ps1` yedi paketi resmî kaynaklarından
>   indiriyor ve elle kurarken saatler yediren beş tuzaktan dördünü
>   otomatik uyguluyor.
> - Görsel kimlik **tema** olarak ayrıldı. Varsayılan tema görselsiz ve
>   telifsiz; kendi temanızı tek klasör açıp kuruyorsunuz.
>
> **Kurulum:** README'deki üç adım. Web tarafı PHP 8, veritabanı istemiyor
> (tek JSON dosyası; MySQL şeması hazır, geçiş tek dosya).
>
> **Lisans:** CC BY-NC-SA 4.0. Kullanmak, değiştirmek, paylaşmak serbest —
> **para kazanan sunucunuzda çalıştırmak dahil** (VIP/admin satan sunucu
> da serbest, bunu açıkça yazdım). Yasak olan tek şey eserin kendisini
> satmak ve kendi eserin gibi göstermek. Ayrıntı NOTICE dosyasında.
>
> **Dürüst olalım, eksikleri de var:** depo tek JSON dosyası (~30 sunucu
> üstünde tıkanır), sıralama indekssiz, MOTD penceresi kaydırılamadığı için
> sayfalar sabit ölçüye sığdırıldı. Hepsi README'de yazıyor.
>
> Repo: [DEPO-ADRESI]
> Soru/öneri: [İLETİŞİM]
>
> Kullanan olursa sıralama sayfasının ekran görüntüsünü görmek isterim. 🙏

---

## 2) GitHub release notu (v0.5.0)

```markdown
# v0.5.0 — açık kaynak ilk sürüm

CS 1.6 için rank ve istatistik sistemi. ReAPI hook zincirleri +
AmxxEasyHttp ile toplama; PHP tarafında KP hesabı ve MOTD sunumu.

## Öne çıkanlar

- **KP sunucuda hesaplanmıyor.** Eklenti ham sayaç gönderir; formül
  `sunucu/sicil-ortak.php → kazanilanPuan()`. Rütbe şişirme kapalı,
  formül değişimi `.amxx` dağıtımı gerektirmiyor, formül düzelince tüm
  sıralama geriye dönük yeniden hesaplanıyor.
- **Tema sistemi.** Rütbe/silah adları ve görseller tek klasörde
  (`sunucu/tema/<ad>/katalog.php`). Varsayılan `klasik`: 25 kademe,
  görselsiz, telifsiz.
- **Tek betik kurulum.** `kurulum/kurulum.ps1` yedi paketi resmî
  kaynaklardan indirir (`kurulum/surumler.ini`), ReHLDS'ten yalnız
  `swds.dll` kopyalar, `liblist.gam`'ı çevirir, `sicil.sma`'yı derler.
- **17 sınamalı test paketi.** `test/api-sina.sh` mutlu yol kadar hata
  dallarını da sınar (401/400/409/422 + idempotency).

## Bu sürümle değişenler

- Satış/lisans katmanı kaldırıldı. Yerine kurulum-başına **paylaşılan
  anahtar** (`X-Sicil-Anahtar`) + nonce ve 300 sn zaman penceresi.
  Kimlik doğrulaması artık gövde doğrulamasından **önce** çalışıyor.
- Valorant/Riot Games varlıkları kaldırıldı (telif). Eski ekran
  görüntüleri de bu yüzden yayınlanmadı.
- Sabit kodlanmış rcon şifresi, clan jetonları, gerçek oyuncu verisi ve
  gerçek IP adresleri temizlendi.
- Sıralama sayfası tek oyunculu ve boş depoda artık uyarı vermiyor
  (MOTD penceresi PHP uyarısını doğrudan oyuncuya gösteriyordu).

## Kurulum

README.md → "Hızlı başlangıç" (3 adım). Ayrıntı ve tuzaklar: KURULUM.md

## Lisans

CC BY-NC-SA 4.0 — telif (c) 2026 Berkay. Kullanım, değişiklik ve paylaşım
serbest; **ticari sunucuda çalıştırmak dahil**. Satmak ve kendi eserin
gibi göstermek yasak. Ayrıntı: LICENSE, NOTICE.

## Bilinen sınırlar

Tek JSON depo (~30 sunucu üstünde tıkanır), indekssiz sıralama,
kaydırılamayan MOTD (sayfalar 860×550'ye sığdırıldı). Hepsi README'de.
```

---

## Paylaşmadan önce kontrol listesi

- [ ] `[DEPO-ADRESI]` ve `[İLETİŞİM]` dolduruldu
- [ ] Repo **public** yapıldı ve ilk push'ta `sunucu/veri/` boş geldi
      (gitignore'da; ama push sonrası `git ls-files | grep veri` ile
      doğrulayın)
- [ ] `NOTICE` içindeki `<DEPO-ADRESI>` ve `<İLETİŞİM-ADRESİ>` güncellendi
- [ ] `sunucu/yapilandirma.php` içindeki `sunucuAdi` kendi sunucu adınız
- [ ] Kendi sunucunuzda Valorant temasını **kapalı** tuttuğunuzdan
      eminsiniz (açık depoda yalnız `klasik` tema var)
- [ ] Bir tur oynatıp `/top` ve `/rank`'i gerçek oyuncuyla gördünüz
      (OKUBENI'deki "oyunda görülmedi" listesi hâlâ geçerli)
