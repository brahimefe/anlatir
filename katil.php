<?php
/* QR ya da ders koduyla gelinen sayfa.
 * Onaylı ve giriş yapmış öğrenci: kod doğruysa katılımı işlenir, canlı derse geçer.
 * Giriş yapmamış: giriş yap / kayıt ol / misafir olarak izle (misafirin katılımı işlenmez). */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$kur = kur_aktif();
$kid = $kur['id'] ?? null;
$kod = strtoupper(trim((string)($_POST['kod'] ?? $_GET['kod'] ?? '')));
$hata = '';
$u = ogrenci();

if (!$u && katilim() && !isset($_GET['degistir']) && !is_post() && $kod === '') {
    redirect('canli.php');
}

/* Giriş yapmış öğrenci: kod geçerliyse doğrudan katıl */
if ($u && $kod !== '' && !isset($_GET['degistir'])) {
    if (is_post() && !csrf_ok()) {
        $hata = 'Sayfanın süresi dolmuş. Tekrar deneyin.';
    } elseif (!ders_kodu_gecerli($kid, $kod)) {
        $hata = 'Kod geçersiz ya da süresi dolmuş. Salondaki ekranda yazan kodu gir.';
    } else {
        ders_katil($kid, $u['id']);
        $_SESSION['katilim'] = ['kur' => $kid, 'rumuz' => $u['rumuz'], 'pid' => 'u' . $u['id']];
        flash('Derse katıldın; katılımın işlendi.');
        redirect('canli.php');
    }
}

/* Misafir: rumuzla izler */
if (!$u && is_post()) {
    $rumuz = preg_replace('/\s+/u', ' ', post('rumuz', 40));
    if (!csrf_ok()) {
        $hata = 'Sayfanın süresi dolmuş. Tekrar deneyin.';
    } elseif (!$kur || !ders_kodu_gecerli($kid, $kod)) {
        $hata = 'Kod geçersiz ya da süresi dolmuş. Salondaki ekranda yazan kodu gir.';
    } elseif (text_len($rumuz) < 2) {
        $hata = 'Bir rumuz yaz ya da önerilerden birini seç.';
    } else {
        $eski = $_SESSION['katilim']['pid'] ?? null;
        $_SESSION['katilim'] = [
            'kur'   => $kid,
            'rumuz' => $rumuz,
            'pid'   => $eski && $eski[0] === 'p' && ($_SESSION['katilim']['kur'] ?? '') === $kid ? $eski : 'p' . new_id(),
        ];
        redirect('canli.php');
    }
}

/* Rumuz değiştirme (giriş yapmış ya da misafir) */
if (isset($_GET['degistir']) && is_post() && katilim()) {
    $rumuz = preg_replace('/\s+/u', ' ', post('rumuz', 40));
    if (csrf_ok() && text_len($rumuz) >= 2) {
        $_SESSION['katilim']['rumuz'] = $rumuz;
        if ($u) {
            kullanici_guncelle($kid, $u['id'], ['rumuz' => $rumuz]);
        }
        redirect('canli.php');
    }
    $hata = 'Bir rumuz yaz.';
}

$oneriler = ['Gece Muhabiri', 'Not Defteri', 'Beşinci Soru', 'Kırmızı Kalem', 'Sessiz Mikrofon', 'Dipnot Avcısı'];
shuffle($oneriler);
$donus = 'katil.php' . ($kod !== '' ? '?kod=' . rawurlencode($kod) : '');
page_head('Derse katıl');
?>
<div class="phone stack">
<?php if (isset($_GET['degistir']) && katilim()): ?>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <h1 style="font-size:30px">Rumuzunu değiştir</h1>
    <?php if ($hata): ?><p class="flash err" style="margin:0"><?= e($hata) ?></p><?php endif; ?>
    <div class="field"><label for="rumuz">Rumuz</label><input id="rumuz" name="rumuz" type="text" required maxlength="40" value="<?= e(katilim()['rumuz']) ?>"></div>
    <button class="btn btn-primary btn-block" type="submit">Kaydet</button>
    <a href="canli.php">Vazgeç</a>
  </form>
<?php elseif ($u): ?>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <span class="kicker">Canlı ders</span>
    <h1 style="font-size:34px">Derse katıl</h1>
    <p>Merhaba <strong><?= e($u['rumuz']) ?></strong>. Salondaki ekrandaki ders kodunu gir; katılımın işlenir.</p>
    <?php if ($hata): ?><p class="flash err" style="margin:0"><?= e($hata) ?></p><?php endif; ?>
    <div class="field"><label for="kod">Ders kodu</label>
      <input id="kod" name="kod" type="text" required autocomplete="off" autocapitalize="characters" value="<?= e($kod) ?>"></div>
    <button class="btn btn-primary btn-block" type="submit">Derse katıl</button>
  </form>
<?php else: ?>
  <div class="card stack">
    <span class="kicker">Canlı ders</span>
    <h1 style="font-size:34px">Derse katıl</h1>
    <?php if ($hata): ?><p class="flash err" style="margin:0"><?= e($hata) ?></p><?php endif; ?>
    <p>Katılımının sayılması için kayıtlı hesabınla giriş yap.</p>
    <a class="btn btn-primary btn-block" href="giris.php?donus=<?= e(rawurlencode($donus)) ?>">Giriş yap ve katıl</a>
    <p class="small muted">Hesabın yok mu? <?= kayit_acik() ? '<a href="kayit.php">Kayıt ol</a>; eğitmen onayladıktan sonra katılımın sayılır.' : 'Kayıt şu an kapalı; eğitmenle görüş.' ?></p>
  </div>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <h2 style="font-size:22px">Misafir olarak izle</h2>
    <p class="help">Sunumu izler, sorulara cevap verebilirsin. Katılımın işlenmez.</p>
    <div class="field"><label for="kod">Ders kodu</label>
      <input id="kod" name="kod" type="text" required autocomplete="off" autocapitalize="characters" value="<?= e($kod) ?>"></div>
    <div class="field"><label for="rumuz">Rumuzun</label>
      <input id="rumuz" name="rumuz" type="text" required maxlength="40" value="<?= e($_SESSION['katilim']['rumuz'] ?? '') ?>" placeholder="Ör. Kırmızı Kalem"></div>
    <div class="row" aria-label="Rumuz önerileri">
      <?php foreach (array_slice($oneriler, 0, 3) as $o): ?>
        <button type="button" class="chip-btn" data-rumuz="<?= e($o) ?>"><?= e($o) ?></button>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-block" type="submit">Misafir olarak izle</button>
  </form>
<?php endif; ?>
</div>
<script>
document.querySelectorAll("[data-rumuz]").forEach(function (b) {
  b.addEventListener("click", function () { document.getElementById("rumuz").value = b.getAttribute("data-rumuz"); });
});
</script>
<?php page_foot();
