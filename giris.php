<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$rol = ($_GET['rol'] ?? '') === 'egitmen' ? 'egitmen' : 'ogrenci';
$donus = safe_return($_GET['donus'] ?? ($rol === 'egitmen' ? 'egitmen.php' : 'index.php'));
$hata = '';

function giris_bekle()
{
    $_SESSION['giris_hata'] = (int)($_SESSION['giris_hata'] ?? 0) + 1;
    sleep(min(5, $_SESSION['giris_hata']));
}

if (is_post()) {
    if (!csrf_ok()) {
        $hata = 'Sayfanın süresi dolmuş. Tekrar deneyin.';
    } elseif ($rol === 'egitmen') {
        $sifre = (string)cfg('egitmen_sifre', '');
        if ($sifre === '' || strpos($sifre, 'BURAYA') === 0) {
            $hata = 'Eğitmen şifresi henüz tanımlanmamış. Sunucuda config.local.php dosyasını oluşturun.';
        } elseif (hash_equals($sifre, (string)($_POST['sifre'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['egitmen'] = true;
            unset($_SESSION['giris_hata'], $_SESSION['ogrenci']);
            redirect($donus);
        } else {
            giris_bekle();
            $hata = 'Şifre hatalı.';
        }
    } else {
        $kur = kur_aktif();
        $eposta = tr_lower(post('eposta', 200));
        $bulunan = null;
        if ($kur) {
            foreach (kullanicilar($kur['id']) as $u) {
                if ($u['eposta'] === $eposta) {
                    $bulunan = $u;
                }
            }
        }
        if (!$bulunan || !password_verify((string)($_POST['sifre'] ?? ''), $bulunan['hash'])) {
            giris_bekle();
            $hata = 'E-posta ya da şifre hatalı.';
        } elseif ($bulunan['durum'] === 'bekliyor') {
            $hata = 'Kaydın eğitmen onayı bekliyor. Onaylandıktan sonra giriş yapabilirsin.';
        } elseif ($bulunan['durum'] !== 'onayli') {
            $hata = 'Bu hesap etkin değil. Eğitmenle görüş.';
        } else {
            session_regenerate_id(true);
            $_SESSION['ogrenci'] = ['id' => $bulunan['id'], 'kur' => $kur['id']];
            unset($_SESSION['giris_hata'], $_SESSION['katilim']);
            redirect($donus);
        }
    }
}

page_head('Giriş');
?>
<div class="wrap narrow section stack" style="max-width:520px">
  <div class="card stack" style="background:var(--accent-soft);border-color:#C9D4F5">
    <h2 style="font-size:24px">Canlı derse katıl</h2>
    <form action="katil.php" method="get" class="row" style="flex-wrap:nowrap">
      <label class="hidden" for="kod">Ders kodu</label>
      <input id="kod" name="kod" type="text" placeholder="Ders kodu" autocomplete="off" autocapitalize="characters" required>
      <button class="btn btn-primary" type="submit">Katıl</button>
    </form>
  </div>

  <div class="tabs" aria-label="Giriş türü">
    <a class="chip-btn" href="giris.php" aria-pressed="<?= $rol === 'ogrenci' ? 'true' : 'false' ?>">Öğrenci</a>
    <a class="chip-btn" href="giris.php?rol=egitmen" aria-pressed="<?= $rol === 'egitmen' ? 'true' : 'false' ?>">Eğitmen</a>
  </div>

  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <h1 style="font-size:32px"><?= $rol === 'egitmen' ? 'Eğitmen girişi' : 'Öğrenci girişi' ?></h1>
    <?php if ($hata): ?><p class="flash err" style="margin:0"><?= e($hata) ?></p><?php endif; ?>
    <?php if ($rol === 'ogrenci'): ?>
      <div class="field"><label for="eposta">E-posta</label><input id="eposta" name="eposta" type="email" autocomplete="email" required value="<?= e($_POST['eposta'] ?? '') ?>"></div>
    <?php endif; ?>
    <div class="field"><label for="sifre">Şifre</label><input id="sifre" name="sifre" type="password" autocomplete="current-password" required></div>
    <button class="btn btn-primary btn-block" type="submit">Giriş yap</button>
    <?php if ($rol === 'ogrenci'): ?>
      <p class="small muted">Şifreni unuttuysan eğitmene söyle.
      <?php if (kayit_acik()): ?><br>Hesabın yok mu? <a href="kayit.php">Kayıt ol</a><?php endif; ?></p>
    <?php endif; ?>
  </form>
</div>
<?php page_foot();
