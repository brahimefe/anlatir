<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$kur = kur_aktif();
$hafta = kur_hafta() ?: 1;
$w = week($hafta);
$u = ogrenci();
$icerde = $u || is_admin();
$canliVar = $kur && canli($kur['id'])['aktif'];

$yayin = yayindakiler();
$secilen = array_values(array_filter($yayin, function ($h) {
    return !empty($h['kazanan']);
}));
/* Ana sayfada gösterilecek örnekler: hatalı örnek ana sayfaya çıkmaz */
$vitrinOrnek = array_values(array_filter(ornekler(), function ($o) {
    return ($o['etiket'] ?? '') !== 'Hatalı örnek';
}));

/* Fotoğrafı olmayan habere sıradaki yer tutucu görsel */
function gorsel($h, $i)
{
    return foto_url($h) ?: site_gorsel('yer-tutucu-' . (($i % 3) + 1));
}
function rozet($h)
{
    if (($h['tur'] ?? '') === 'ornek') {
        return 'Örnek haber';
    }
    return !empty($h['kazanan']) ? '★ Haftanın haberi · Hafta ' . (int)($h['kazanan_hafta'] ?? $h['hafta']) : '';
}
function alt_satir($h)
{
    $m = ($h['tur'] ?? '') === 'ornek' ? 'Örnek · kurgusal' : haber_meta($h);
    return $h['yazar'] . ($m !== '' ? ' · ' . $m : '');
}

/* Haber bandı: seçilen haberler; üçten azsa örneklerle tamamlanır */
function haber_bandi($liste)
{
    $son = array_slice($liste, 0, 10);
    if (!$son) {
        return;
    }
    $parca = '';
    foreach ($son as $h) {
        $parca .= '<a href="haber.php?id=' . e($h['id']) . '"><b>' . e($h['yazar']) . '</b>' . e($h['baslik']) . '</a><span class="sep" aria-hidden="true">◆</span>';
    }
    $sure = max(24, count($son) * 8);
    ?>
<div class="as-ticker" role="region" aria-label="Haber bandı">
  <span class="lbl">Haber bandı</span>
  <div class="rail"><div class="move" style="--sure: <?= $sure ?>s"><?= $parca ?><span aria-hidden="true" style="display:contents"><?= str_replace('<a ', '<a tabindex="-1" ', $parca) ?></span></div></div>
</div>
<?php
}
$bant = $secilen;
foreach ($vitrinOrnek as $o) {
    if (count($bant) >= 3) {
        break;
    }
    $bant[] = $o;
}

/* Büyük + yan kartlı haber bölümü */
function haber_bolumu($liste, $ust, $baslik, $tumLink)
{
    if (!$liste) {
        return;
    }
    $buyuk = $liste[0];
    $yan = array_slice($liste, 1, 3);
    ?>
<section class="as-sec" id="haberler">
  <div class="wrap">
    <div class="as-head">
      <div><div class="kk" style="color:var(--accent);margin-bottom:10px"><?= e($ust) ?></div><h2><?= e($baslik) ?></h2></div>
      <a class="as-more" href="<?= e($tumLink) ?>">Tüm haberler →</a>
    </div>
    <div class="as-news<?= $yan ? '' : ' tek' ?>">
      <a class="as-big" href="haber.php?id=<?= e($buyuk['id']) ?>">
        <img src="<?= e(gorsel($buyuk, 1)) ?>" alt="" loading="lazy">
        <span class="b">
          <?php if (rozet($buyuk) !== ''): ?><span class="as-badge"><?= e(rozet($buyuk)) ?></span><?php endif; ?>
          <h3><?= e($buyuk['baslik']) ?></h3>
          <span class="as-meta"><?= e(alt_satir($buyuk)) ?> · <?= kaynak_sayisi($buyuk) ?> kaynak</span>
        </span>
      </a>
      <?php if ($yan): ?>
      <div class="as-side">
        <?php foreach ($yan as $i => $h): ?>
          <a class="as-small" href="haber.php?id=<?= e($h['id']) ?>"><span class="ph"><img src="<?= e(gorsel($h, $i + 2)) ?>" alt="" loading="lazy"></span><span><h3><?= e($h['baslik']) ?></h3><span class="as-meta"><?= e(alt_satir($h)) ?></span></span></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
}

page_head('Dijital Habercilik Semineri', 'ana', 'home');

/* ================= ÖĞRENCİ / EĞİTMEN ================= */
if ($icerde):
    $kid = $kur['id'] ?? null;
    $yeniNot = yeni_not_sayisi($u);
    $teslim = $kur ? son_teslim($kur, $hafta) : null;
    $odevDurum = null;
    $taslak = null;
    $ilerleme = '';
    if ($u) {
        $benim = haberler(function ($h) use ($u) {
            return ($h['yazar_id'] ?? '') === $u['id'];
        });
        $buHafta = array_values(array_filter($benim, function ($h) use ($hafta) {
            return (int)$h['hafta'] === $hafta;
        }));
        $gonderilen = array_values(array_filter($buHafta, function ($h) {
            return $h['durum'] !== 'taslak';
        }));
        foreach ($buHafta as $h) {
            if ($h['durum'] === 'taslak') {
                $taslak = $h;
            }
        }
        $etiket = ['bekliyor' => 'Gönderildi, eğitmende', 'duzeltme' => 'Düzeltme istendi', 'yayinda' => 'Yayımlandı', 'red' => 'Yayımlanmadı', 'goruldu' => 'Eğitmen gördü'];
        $odevDurum = $gonderilen ? ($etiket[$gonderilen[0]['durum']] ?? '') : ($taslak ? 'Taslak, gönderilmedi' : 'Henüz göndermedin');
        $haftalar = [];
        $yayinSayi = 0;
        foreach ($benim as $h) {
            if ($h['durum'] !== 'taslak' && (int)$h['hafta'] <= $hafta) {
                $haftalar[(int)$h['hafta']] = true;
            }
            if ($h['durum'] === 'yayinda') {
                $yayinSayi++;
            }
        }
        $ilerleme = count($haftalar) . ' / ' . $hafta . ' ödev teslim edildi · ' . $yayinSayi . ' haberin yayımlandı';
    }
    /* Son teslim geri sayımı */
    $sayac = '';
    $sayacAcil = false;
    if ($teslim && $u && empty($gonderilen)) {
        $kalan = $teslim - time();
        if ($kalan <= 0) {
            $sayac = 'Süre doldu';
            $sayacAcil = true;
        } elseif ($kalan < 86400) {
            $sayac = 'Son teslime ' . max(1, (int)ceil($kalan / 3600)) . ' saat';
            $sayacAcil = true;
        } else {
            $sayac = 'Son teslime ' . (int)ceil($kalan / 86400) . ' gün';
            $sayacAcil = $kalan < 2 * 86400;
        }
    }
    $ornk = array_filter(array_map('haber_yukle', $w['ornekler'] ?? []));
    $finalist = $kid ? array_filter(array_map('haber_yukle', finalistler($kid))) : [];
    $sinif = $kid ? yayindakiler($kid) : [];
    $sinifSecilen = array_values(array_filter($sinif, function ($h) {
        return !empty($h['kazanan']);
    }));
    usort($sinifSecilen, function ($a, $b) {
        return ($a['kazanan_hafta'] ?? 0) <=> ($b['kazanan_hafta'] ?? 0);
    });
    ?>
<div class="as">
<section class="as-hero kisa">
  <img class="as-hero-bg" src="<?= e(site_gorsel('acilis')) ?>" alt="">
  <div class="wrap as-hero-body<?= $u ? '' : ' tek' ?>">
    <div class="as-reveal">
      <?php if ($canliVar): ?><a class="as-live" href="canli.php"><span class="as-dot"></span>Ders şu an canlı · Hafta <?= $hafta ?></a><?php endif; ?>
      <div class="kk" style="color:#9FB4F5;margin-bottom:14px"><?= $kur ? e($kur['ad']) . ' · Hafta ' . $hafta . ' / 8' : 'Aktif kur yok' ?></div>
      <h1><?= $u ? 'Merhaba, ' . e($u['rumuz']) . '.' : 'Eğitmen görünümü' ?></h1>
      <?php if ($ilerleme): ?><p class="as-ilerleme"><?= e($ilerleme) ?></p><?php endif; ?>
      <div class="as-cta" style="margin-top:24px">
        <?php if ($canliVar): ?><a class="pill pill-w" href="canli.php">Canlı derse katıl</a><?php endif; ?>
        <?php if (is_admin()): ?><a class="pill <?= $canliVar ? 'pill-g' : 'pill-w' ?>" href="egitmen.php">Eğitmen paneli</a><?php endif; ?>
        <?php if ($u): ?>
          <a class="pill <?= $canliVar ? 'pill-g' : 'pill-w' ?>" href="hafta.php?n=<?= $hafta ?>">Hafta <?= $hafta ?> dersi</a>
          <a class="pill pill-g" href="profil.php">Profilim</a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($u): ?>
    <div class="as-card">
      <div class="b">
        <span class="row" style="gap:8px;justify-content:space-between">
          <span class="as-badge" style="background:#E9EEFB;color:var(--accent-dark)">Bu haftanın ödevi</span>
          <?php if ($sayac): ?><span class="as-sayac<?= $sayacAcil ? ' acil' : '' ?>"><?= e($sayac) ?></span><?php endif; ?>
        </span>
        <p style="font-size:18px;line-height:1.55"><?= e($w['odev']) ?></p>
        <?php if ($teslim): ?><span class="as-meta"><strong>Son teslim:</strong> <?= e(son_teslim_yazi($kur, $hafta)) ?></span><?php endif; ?>
        <span class="as-meta"><strong>Durumun:</strong> <?= e($odevDurum) ?></span>
        <div class="row" style="margin-top:8px">
          <?php if ($taslak && empty($gonderilen)): ?><a class="pill pill-a" href="gonder.php?id=<?= e($taslak['id']) ?>">Taslağa devam et</a>
          <?php else: ?><a class="pill pill-a" href="gonder.php?hafta=<?= $hafta ?>">Ödevi gönder</a><?php endif; ?>
          <a class="as-more" href="odevim.php" style="align-self:center">Ödevim<?= $yeniNot ? ' (' . $yeniNot . ' yeni not)' : '' ?> →</a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php haber_bandi($bant); ?>

<div class="wrap">
  <?php if ($yeniNot): ?>
    <a class="flash warn note-link" href="odevim.php"><strong><?= $yeniNot ?> gönderinde eğitmenden yeni not var.</strong> Ödevim →</a>
  <?php endif; ?>
  <?php $sagVar = (ai_acik() && $w['arac']) || $ornk; ?>
  <section class="as-sec as-week<?= $sagVar ? '' : ' tek' ?>" aria-labelledby="bu-hafta">
    <div>
      <div class="kk" style="color:var(--accent)">Bu hafta · <?= e($w['kisa']) ?></div>
      <h2 id="bu-hafta"><?= e($w['baslik']) ?></h2>
      <ul><?php foreach (array_slice($w['ozet'], 0, 4) as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
      <a class="as-more" href="hafta.php?n=<?= $hafta ?>">Dersin tamamı →</a>
    </div>
    <?php if ($sagVar): ?><div class="as-ex">
      <?php if (ai_acik() && $w['arac']): $ad = ['karsilastir' => 'Haber yaz ve karşılaştır', 'denetle' => 'Haberi denetle', 'ses' => 'Ses kaydını yazıya dök'][$w['arac']]; ?>
        <a href="masa.php?arac=<?= e($w['arac']) ?>"><span class="kk" style="color:var(--muted)">Haber Masası</span><strong><?= e($ad) ?></strong></a>
      <?php endif; ?>
      <?php foreach ($ornk as $o): ?>
        <a href="haber.php?id=<?= e($o['id']) ?>"><span class="kk" style="color:var(--muted)">Örnek haber · <?= e($o['etiket']) ?></span><strong><?= e($o['baslik']) ?></strong><span class="as-meta"><?= e($o['neden']) ?></span></a>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </section>

  <?php if ($finalist): ?>
  <section class="as-sec" style="padding-top:0" aria-labelledby="finalist">
    <div class="as-head"><div><div class="kk" style="color:var(--accent);margin-bottom:10px">Derste oylanacak</div><h2 id="finalist">Finalistler</h2></div></div>
    <div class="grid"><?php foreach ($finalist as $h) { haber_karti($h); } ?></div>
  </section>
  <?php endif; ?>

  <section class="as-sec" style="padding-top:0" aria-labelledby="sinif">
    <div class="as-head"><div><div class="kk" style="color:var(--accent);margin-bottom:10px"><?= $kur ? e($kur['ad']) : '' ?></div><h2 id="sinif">Sınıftan haberler</h2></div><?php if (count($sinif) > 6): ?><a class="as-more" href="arsiv.php?kur=<?= e($kid) ?>">Tümü →</a><?php endif; ?></div>
    <?php if ($sinif): ?>
      <div class="grid"><?php foreach (array_slice($sinif, 0, 6) as $h) { haber_karti($h); } ?></div>
    <?php else: ?>
      <p class="muted">Henüz yayımlanan haber yok.</p>
    <?php endif; ?>
  </section>

  <?php if ($sinifSecilen): ?>
  <section class="as-sec" style="padding-top:0" aria-labelledby="secilen">
    <div class="as-head"><div><div class="kk" style="color:var(--accent);margin-bottom:10px">Haftanın haberi</div><h2 id="secilen">Seçilen haberler</h2></div></div>
    <ul class="list-plain winners">
      <?php foreach ($sinifSecilen as $h): ?>
        <li><a href="haber.php?id=<?= e($h['id']) ?>"><span class="wk">Hafta <?= (int)($h['kazanan_hafta'] ?? $h['hafta']) ?></span><span><strong style="font-family:var(--serif);font-size:20px"><?= e($h['baslik']) ?></strong><br><span class="meta"><?= e($h['yazar']) ?></span></span></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
</div>
<?php
    page_foot();
    exit;
endif;

/* ================= ZİYARETÇİ ================= */
$tamamlanan = array_values(array_filter(kurlar()['liste'], function ($k) {
    return ($k['durum'] ?? '') === 'arsiv';
}));
usort($tamamlanan, function ($a, $b) {
    return strcmp($b['kapanis'] ?? '', $a['kapanis'] ?? '');
});
/* Sayılar ancak anlamlı olunca: en az bir kur tamamlanmış ve 10'dan fazla haber yayımlanmış */
$sayilar = [];
if ($tamamlanan && count($yayin) > 10) {
    $katilimci = 0;
    foreach (kurlar()['liste'] as $k) {
        $katilimci += ($k['durum'] ?? '') === 'arsiv'
            ? (int)($k['istatistik']['katilimci'] ?? 0)
            : count(array_filter(kullanicilar($k['id']), function ($x) {
                return $x['durum'] === 'onayli';
            }));
    }
    $sayilar = [[$katilimci, 'katılımcı'], [count($yayin), 'yayımlanan haber'], [count($secilen), 'haftanın haberi'], [count($tamamlanan), 'tamamlanan kur']];
}
$kart = $secilen[0] ?? ($vitrinOrnek[0] ?? null);
$liste = $secilen ? array_slice($secilen, 1) : [];
$ornekBolum = !$liste;
if ($ornekBolum) {
    $liste = array_values(array_filter($vitrinOrnek, function ($o) use ($kart) {
        return !$kart || $o['id'] !== $kart['id'];
    }));
}
?>
<div class="as">
<section class="as-hero">
  <img class="as-hero-bg" src="<?= e(site_gorsel('acilis')) ?>" alt="">
  <div class="wrap as-hero-body<?= $kart ? '' : ' tek' ?>">
    <div class="as-reveal">
      <?php if ($canliVar): ?><a class="as-live" href="katil.php"><span class="as-dot"></span>Ders şu an canlı · Hafta <?= $hafta ?></a><?php endif; ?>
      <div class="kk" style="color:#9FB4F5;margin-bottom:16px">Dijital Habercilik Semineri</div>
      <h1><?= e(anasayfa_metin('baslik')) ?></h1>
      <p class="as-lead"><?= e(anasayfa_metin('alt')) ?></p>
      <div class="as-cta">
        <?php if (kayit_acik()): ?><a class="pill pill-w" href="kayit.php">Kayıt ol →</a><?php endif; ?>
        <a class="pill <?= kayit_acik() ? 'pill-g' : 'pill-w' ?>" href="giris.php">Giriş yap</a>
      </div>
    </div>
    <?php if ($kart): ?>
      <a class="as-card" href="haber.php?id=<?= e($kart['id']) ?>">
        <img src="<?= e(gorsel($kart, 0)) ?>" alt="">
        <span class="b">
          <span class="as-badge"><?= e(rozet($kart)) ?></span>
          <h3><?= e($kart['baslik']) ?></h3>
          <span class="as-meta"><?= e(alt_satir($kart)) ?> · <?= kaynak_sayisi($kart) ?> kaynak</span>
        </span>
      </a>
    <?php endif; ?>
  </div>
</section>
<?php haber_bandi($bant); ?>

<?php if ($sayilar): ?>
<div class="wrap">
  <div class="as-stats">
    <?php foreach ($sayilar as [$n, $ad]): ?><div class="as-stat"><b><?= (int)$n ?></b><span><?= e($ad) ?></span></div><?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php $ornekBolum
    ? haber_bolumu($liste, 'Örnek · kurgusal', 'Örnek haberler', 'haberler.php?f=ornek')
    : haber_bolumu($liste, 'Haftanın haberi', 'Seçilen haberler', 'haberler.php?f=secilen'); ?>

<?php if ($kur || $tamamlanan): ?>
<section class="as-sec as-kurlar" id="kurlar">
  <div class="wrap">
    <div class="as-head">
      <div><div class="kk" style="color:var(--accent);margin-bottom:10px">Kurlar</div><h2><?= e(anasayfa_metin('kurlar')) ?></h2></div>
      <a class="as-more" href="arsiv.php">Tüm kurlar →</a>
    </div>
    <div class="as-kur-grid">
      <?php if ($kur): ?>
        <a class="as-kur on" href="arsiv.php?kur=<?= e($kur['id']) ?>">
          <span class="as-state"><span class="as-dot" style="width:8px;height:8px"></span>Devam ediyor · Hafta <?= $hafta ?> / 8</span>
          <h3><?= e($kur['ad']) ?></h3>
          <span class="as-prog" aria-hidden="true"><?php for ($i = 1; $i <= 8; $i++): ?><i class="<?= $i < $hafta ? 'f' : ($i === $hafta ? 'n' : '') ?>"></i><?php endfor; ?></span>
          <span class="as-meta"><?= e(tr_date($kur['acilis'] ?? '')) ?> tarihinde başladı · kayıt <?= kayit_acik() ? 'açık' : 'kapalı' ?></span>
        </a>
      <?php endif; ?>
      <?php foreach (array_slice($tamamlanan, 0, 2) as $k): ?>
        <a class="as-kur" href="arsiv.php?kur=<?= e($k['id']) ?>">
          <span class="as-meta"><?= e(ay_yil($k['acilis'] ?? '')) ?> – <?= e(ay_yil($k['kapanis'] ?? '')) ?></span>
          <h3><?= e($k['ad']) ?></h3>
          <span class="as-meta"><?= count(yayindakiler($k['id'])) ?> yayımlanan haber</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="as-close">
  <img src="<?= e(site_gorsel('kapanis')) ?>" alt="" loading="lazy">
  <div class="wrap">
    <h2><?= e(anasayfa_metin('kapanis')) ?></h2>
    <div class="as-cta" style="justify-content:center">
      <?php if (kayit_acik()): ?><a class="pill pill-w" href="kayit.php">Kayıt ol →</a><?php endif; ?>
      <a class="pill <?= kayit_acik() ? 'pill-g' : 'pill-w' ?>" href="giris.php">Giriş yap</a>
    </div>
  </div>
</section>
</div>
<?php page_foot();
