<?php
/* Kurlar: devam eden ve tamamlanan kurlar, kur ayrıntısı; eğitmen tamamlanan kuru silebilir */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

function arsiv_kurlari()
{
    $l = array_values(array_filter(kurlar()['liste'], function ($k) {
        return ($k['durum'] ?? '') === 'arsiv';
    }));
    usort($l, function ($a, $b) {
        return strcmp($b['kapanis'] ?? '', $a['kapanis'] ?? '');
    });
    return $l;
}
function kur_kazananlar($kid)
{
    $out = [];
    foreach (glob(kur_dir($kid) . '/oylama-h*.json') ?: [] as $f) {
        $o = json_read($f);
        if (!empty($o['kazanan']) && ($h = haber_yukle($o['kazanan'])) && $h['durum'] === 'yayinda') {
            $out[(int)preg_replace('/\D/', '', basename($f))] = $h;
        }
    }
    ksort($out);
    return $out;
}
function kur_ozet($kur)
{
    $s = $kur['istatistik'] ?? null;
    if (!$s) { // eski kurlar için anlık hesap
        $users = kullanicilar($kur['id']);
        $s = [
            'katilimci' => count(array_filter($users, function ($u) {
                return in_array($u['durum'], ['onayli', 'kapali'], true);
            })),
            'gonderi' => count(haberler(function ($h) use ($kur) {
                return $h['kur'] === $kur['id'] && $h['durum'] !== 'taslak';
            })),
        ];
    }
    $s['yayinda'] = count(yayindakiler($kur['id']));
    $s['kazanan'] = count(kur_kazananlar($kur['id']));
    return $s;
}
function tarih_araligi($kur)
{
    return tr_date($kur['acilis'] ?? '') . ' – ' . (($kur['durum'] ?? '') === 'arsiv' ? tr_date($kur['kapanis'] ?? '') : 'devam ediyor');
}

/* ---------- Silme (yalnızca eğitmen) ---------- */
if (is_post()) {
    require_admin();
    csrf_check();
    $kur = kur_bul((string)($_POST['kur'] ?? ''));
    if (!$kur || ($kur['durum'] ?? '') !== 'arsiv') {
        flash('Yalnızca arşivdeki kurlar silinebilir.', 'err');
        redirect('arsiv.php');
    }
    if (tr_lower(trim((string)($_POST['onay'] ?? ''))) !== 'sil') {
        flash('Silmek için kutuya SİL yazın.', 'err');
        redirect('arsiv.php?kur=' . $kur['id'] . '#sil');
    }
    foreach (haberler(function ($h) use ($kur) {
        return $h['kur'] === $kur['id'];
    }) as $h) {
        if (!empty($h['foto'])) {
            @unlink(UPLOADS . '/foto/' . basename($h['foto']));
        }
        @unlink(haber_path($h['id']));
    }
    foreach (glob(kur_dir($kur['id']) . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir(kur_dir($kur['id']));
    json_update(kurlar_path(), function (&$d) use ($kur) {
        $d['liste'] = array_values(array_filter($d['liste'], function ($k) use ($kur) {
            return $k['id'] !== $kur['id'];
        }));
    });
    flash('"' . $kur['ad'] . '" kuru ve tüm haberleri silindi.');
    redirect('arsiv.php');
}

function sil_formu($kur)
{
    ?>
    <details class="sil-detay">
      <summary style="cursor:pointer;color:var(--bad);font-weight:600;font-size:16px">Kuru sil</summary>
      <form method="post" action="arsiv.php" class="stack" style="margin-top:10px;gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="kur" value="<?= e($kur['id']) ?>">
        <p class="help">Kur, yayımlanan haberleri ve fotoğraflarıyla birlikte kalıcı olarak silinir. Geri alınamaz.</p>
        <label for="onay-<?= e($kur['id']) ?>">Onaylamak için SİL yazın</label>
        <input id="onay-<?= e($kur['id']) ?>" name="onay" type="text" autocomplete="off" required>
        <button class="btn btn-danger" type="submit" style="align-self:flex-start">Kalıcı olarak sil</button>
      </form>
    </details>
<?php
}

$kur = !empty($_GET['kur']) ? kur_bul((string)$_GET['kur']) : null;
$aktifMi = $kur && ($kur['durum'] ?? '') !== 'arsiv';

/* ---------- Kur ayrıntısı ---------- */
if ($kur):
    $s = kur_ozet($kur);
    $kazananlar = kur_kazananlar($kur['id']);
    $haberler = yayindakiler($kur['id']);
    page_head($kur['ad'] . ' · Kurlar', 'kurlar');
    ?>
<div class="wrap section stack" style="gap:28px">
  <p class="crumbs"><a href="arsiv.php">Kurlar</a> / <?= e($kur['ad']) ?></p>
  <div class="stack" style="gap:6px">
    <span class="row"><?php if ($aktifMi): ?><span class="tag tag-ok">Devam ediyor · Hafta <?= (int)($kur['hafta'] ?? 1) ?> / 8</span><?php else: ?><span class="tag">Tamamlandı</span><?php endif; ?></span>
    <h1><?= e($kur['ad']) ?></h1>
    <p class="muted"><?= e(tarih_araligi($kur)) ?><?= $aktifMi ? '' : ' · ' . (int)($kur['hafta'] ?? 8) . ' hafta' ?></p>
    <?php if ($aktifMi && !ogrenci() && !is_admin()): ?><div class="row" style="margin-top:8px"><?php if (kayit_acik()): ?><a class="btn btn-primary" href="kayit.php">Kayıt ol</a><?php endif; ?><a class="btn" href="giris.php">Giriş yap</a></div><?php endif; ?>
  </div>
  <div class="stats">
    <div class="stat"><b><?= (int)$s['katilimci'] ?></b><span>katılımcı</span></div>
    <div class="stat"><b><?= (int)$s['gonderi'] ?></b><span>gönderi</span></div>
    <div class="stat"><b><?= (int)$s['yayinda'] ?></b><span>yayımlanan haber</span></div>
    <div class="stat"><b><?= (int)$s['kazanan'] ?></b><span>haftanın haberi seçimi</span></div>
  </div>

  <?php if ($kazananlar): ?>
  <section class="card stack">
    <h2>Her haftanın seçilen haberi</h2>
    <ul class="winners">
      <?php foreach ($kazananlar as $hf => $h): ?>
        <li><a href="haber.php?id=<?= e($h['id']) ?>"><span class="wk">Hafta <?= $hf ?></span><span><strong style="font-family:var(--serif);font-size:20px"><?= e($h['baslik']) ?></strong><br><span class="meta"><?= e($h['yazar']) ?> · <?= kaynak_sayisi($h) ?> kaynak</span></span></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="stack">
    <h2>Yayımlanan haberler</h2>
    <?php if ($haberler): ?>
      <div class="grid"><?php foreach ($haberler as $h) { haber_karti($h); } ?></div>
    <?php else: ?><p class="muted">Bu kurda yayımlanan haber yok.</p><?php endif; ?>
  </section>

  <?php if (is_admin()):
      $users = kullanicilar($kur['id']);
      $gonderiler = haberler(function ($h) use ($kur) {
          return $h['kur'] === $kur['id'] && $h['durum'] !== 'taslak';
      });
      $say = [];
      foreach ($gonderiler as $h) {
          $say[$h['yazar_id']]['g'] = ($say[$h['yazar_id']]['g'] ?? 0) + 1;
          if ($h['durum'] === 'yayinda') {
              $say[$h['yazar_id']]['y'] = ($say[$h['yazar_id']]['y'] ?? 0) + 1;
          }
      }
      $silinme = !$aktifMi && !empty($kur['kapanis']) ? strtotime($kur['kapanis']) + (int)cfg('saklama_gun', 60) * 86400 : null;
  ?>
  <section class="card stack" id="egitmen">
    <h2>Eğitmen bilgileri</h2>
    <p class="small muted">Bu bölümü yalnızca siz görürsünüz.</p>
    <div class="kv">
      <span class="muted">Rapor</span><span><a href="rapor.php?kur=<?= e($kur['id']) ?>">Kur raporu</a></span>
      <span class="muted">Soru sonuçları</span><span><?php if (is_file(soru_gecmisi_path($kur['id']))): ?><a href="egitmen.php?csv=sorular&amp;kur=<?= e($kur['id']) ?>">CSV indir</a><?php else: ?>—<?php endif; ?></span>
      <span class="muted">Kişisel veriler</span><span><?= !empty($kur['temizlendi']) ? 'Silindi (' . e(tr_date($kur['temizlendi'])) . ')' : ($silinme ? e(tr_date(date('c', $silinme))) . ' tarihinde silinecek' : '—') ?></span>
    </div>
    <?php if ($users): ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Ad soyad</th><th>Rumuz</th><th>E-posta</th><th>Gönderi</th><th>Yayımlanan</th></tr></thead>
          <tbody>
          <?php foreach ($users as $x): if (!in_array($x['durum'], ['onayli', 'kapali'], true)) { continue; } ?>
            <tr><td><?= e($x['ad']) ?></td><td><?= e($x['rumuz']) ?></td><td><?= e($x['eposta']) ?></td><td><?= (int)($say[$x['id']]['g'] ?? 0) ?></td><td><?= (int)($say[$x['id']]['y'] ?? 0) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="muted small">Katılımcı listesi saklama süresi dolduğu için silindi.</p>
    <?php endif; ?>
  </section>
  <?php if (!$aktifMi): ?><section class="card stack danger-zone" id="sil"><?php sil_formu($kur); ?></section><?php endif; ?>
  <?php endif; ?>
</div>
<?php
    page_foot();
    exit;
endif;

/* ---------- Kur listesi ---------- */
$liste = arsiv_kurlari();
$aktif = kur_aktif();
page_head('Kurlar', 'kurlar');
?>
<div class="wrap section stack" style="gap:28px">
  <div class="stack" style="gap:6px">
    <h1>Kurlar</h1>
  </div>

  <?php if ($aktif): $s = kur_ozet($aktif); ?>
  <section class="stack">
    <h2>Devam eden kur</h2>
    <article class="kur-card kur-aktif">
      <span class="row"><span class="tag tag-ok">Hafta <?= (int)($aktif['hafta'] ?? 1) ?> / 8</span><span class="kicker"><?= e(tr_date($aktif['acilis'] ?? '')) ?> tarihinde başladı</span></span>
      <h2><a href="arsiv.php?kur=<?= e($aktif['id']) ?>"><?= e($aktif['ad']) ?></a></h2>
      <p class="meta"><?= (int)$s['katilimci'] ?> katılımcı · <?= (int)$s['yayinda'] ?> yayımlanan haber · <?= (int)$s['kazanan'] ?> haftanın haberi seçimi · kayıt <?= kayit_acik() ? 'açık' : 'kapalı' ?></p>
      <div class="row">
        <a class="btn btn-sm" href="arsiv.php?kur=<?= e($aktif['id']) ?>">Kuru aç →</a>
        <?php if (!ogrenci() && !is_admin() && kayit_acik()): ?><a class="btn btn-sm btn-primary" href="kayit.php">Kayıt ol</a><?php endif; ?>
      </div>
    </article>
  </section>
  <?php endif; ?>

  <section class="stack">
    <h2>Tamamlanan kurlar</h2>
    <?php if (!$liste): ?>
      <p class="muted">Henüz tamamlanan kur yok.</p>
    <?php else: ?>
    <div class="kur-list">
      <?php foreach ($liste as $k): $s = kur_ozet($k); ?>
        <article class="kur-card">
          <span class="kicker"><?= e(tarih_araligi($k)) ?></span>
          <h2><a href="arsiv.php?kur=<?= e($k['id']) ?>"><?= e($k['ad']) ?></a></h2>
          <p class="meta"><?= (int)$s['katilimci'] ?> katılımcı · <?= (int)$s['yayinda'] ?> yayımlanan haber · <?= (int)$s['kazanan'] ?> haftanın haberi seçimi</p>
          <a class="btn btn-sm" href="arsiv.php?kur=<?= e($k['id']) ?>" style="align-self:flex-start">Kuru aç →</a>
          <?php if (is_admin()) { sil_formu($k); } ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php page_foot();
