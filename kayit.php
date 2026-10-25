<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$kur = kur_aktif();
$hata = '';
$tamam = false;
$v = ['ad' => '', 'rumuz' => '', 'imza' => 'rumuz', 'eposta' => ''];

if (is_post() && $kur && kayit_acik()) {
    $v = [
        'ad'     => post('ad', 80),
        'rumuz'  => post('rumuz', 40),
        'imza'   => ($_POST['imza'] ?? '') === 'ad' ? 'ad' : 'rumuz',
        'eposta' => tr_lower(post('eposta', 200)),
    ];
    $sifre = (string)($_POST['sifre'] ?? '');
    if (!csrf_ok()) {
        $hata = 'Sayfanın süresi dolmuş. Tekrar deneyin.';
    } elseif (text_len($v['ad']) < 3) {
        $hata = 'Adını ve soyadını yaz.';
    } elseif (text_len($v['rumuz']) < 2) {
        $hata = 'Bir rumuz seç.';
    } elseif (!filter_var($v['eposta'], FILTER_VALIDATE_EMAIL)) {
        $hata = 'Geçerli bir e-posta adresi yaz.';
    } elseif (strlen($sifre) < 8) {
        $hata = 'Şifre en az 8 karakter olmalı.';
    } elseif (empty($_POST['kvkk'])) {
        $hata = 'Aydınlatma metnini okuduğunu onayla.';
    } else {
        $sonuc = json_update(kullanicilar_path($kur['id']), function (&$list) use ($v, $sifre) {
            foreach ($list as $u) {
                if ($u['eposta'] === $v['eposta']) {
                    return 'var';
                }
            }
            if (count($list) >= 200) {
                return 'dolu';
            }
            $list[] = [
                'id' => new_id(), 'ad' => $v['ad'], 'rumuz' => $v['rumuz'], 'imza' => $v['imza'],
                'eposta' => $v['eposta'], 'hash' => password_hash($sifre, PASSWORD_DEFAULT),
                'durum' => 'bekliyor', 'tarih' => date('c'),
            ];
            return 'ok';
        });
        if ($sonuc === 'var') {
            $hata = 'Bu e-posta ile zaten kayıt olunmuş. Giriş yapmayı dene.';
        } elseif ($sonuc === 'dolu') {
            $hata = 'Bu kurun kayıt sınırı doldu. Eğitmenle görüş.';
        } else {
            $tamam = true;
        }
    }
}

page_head('Kayıt ol');
?>
<div class="wrap section" style="max-width:520px">
<?php if (!$kur || !kayit_acik()): ?>
  <div class="card stack">
    <h1 style="font-size:32px">Kayıt şu an kapalı</h1>
    <p>Kayıtlar eğitmen tarafından açılır. Derse yine de misafir olarak katılabilirsin: <a href="katil.php">ders koduyla canlı derse katıl</a>.</p>
  </div>
<?php elseif ($tamam): ?>
  <div class="card stack">
    <h1 style="font-size:32px">Kaydın alındı</h1>
    <p>Eğitmen kaydını onayladığında <strong><?= e($v['eposta']) ?></strong> adresi ve şifrenle giriş yapabilirsin.</p>
    <p class="muted small">Onaydan sonra her derste salondaki QR'ı okutman yeterli; katılımın kendiliğinden işlenir.</p>
  </div>
<?php else: ?>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <h1 style="font-size:32px">Seminere kayıt</h1>
    <?php if ($hata): ?><p class="flash err" style="margin:0"><?= e($hata) ?></p><?php endif; ?>
    <div class="field"><label>Kur</label><p style="margin:0;font-weight:600"><?= e($kur['ad']) ?></p></div>
    <div class="field"><label for="ad">Ad soyad</label><input id="ad" name="ad" type="text" required autocomplete="name" value="<?= e($v['ad']) ?>"></div>
    <div class="field"><label for="rumuz">Rumuz</label><input id="rumuz" name="rumuz" type="text" required maxlength="40" placeholder="Ör. Kırmızı Kalem" value="<?= e($v['rumuz'] ?: ($_SESSION['katilim']['rumuz'] ?? '')) ?>">
</div>
    <fieldset class="card" style="padding:12px 16px;margin:0">
      <legend style="font-weight:600;font-size:16px;padding:0 4px">Yayımlanan haberlerimde görünsün</legend>
      <label class="check" style="min-height:40px"><input type="radio" name="imza" value="rumuz"<?= $v['imza'] === 'rumuz' ? ' checked' : '' ?>> Rumuzum</label>
      <label class="check" style="min-height:40px"><input type="radio" name="imza" value="ad"<?= $v['imza'] === 'ad' ? ' checked' : '' ?>> Ad soyadım</label>
    </fieldset>
    <div class="field"><label for="eposta">E-posta</label><input id="eposta" name="eposta" type="email" required autocomplete="email" value="<?= e($v['eposta']) ?>"></div>
    <div class="field"><label for="sifre">Şifre</label><input id="sifre" name="sifre" type="password" required minlength="8" autocomplete="new-password"><span class="help">En az 8 karakter</span></div>
    <label class="check"><input type="checkbox" name="kvkk" value="1" required> <span><a href="kvkk.php" target="_blank">Aydınlatma metnini</a> okudum.</span></label>
    <button class="btn btn-primary btn-block" type="submit">Kayıt ol</button>
    <p class="small muted">Kaydın eğitmen onayından sonra etkinleşir.</p>
  </form>
<?php endif; ?>
</div>
<?php page_foot();
