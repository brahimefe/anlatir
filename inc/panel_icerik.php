<?php
/*
 * Eğitmen paneli > İçerik sekmesi: haftalar, haber ekleme, örnek haberler, duyuru, ana sayfa, görseller, notlar.
 * egitmen.php tarafından yüklenir.
 */

function icerik_bolumleri()
{
    return [
        'hafta'     => 'Haftalar',
        'haber'     => 'Haber ekle',
        'ornekler'  => 'Örnek haberler',
        'duyuru'    => 'Duyuru bandı',
        'anasayfa'  => 'Ana sayfa yazıları',
        'gorseller' => 'Görseller ve fotoğraflar',
        'notlar'    => 'Hazır not cümleleri',
        'genel'     => 'İmza, konular, alt bilgi',
    ];
}
function icerik_gruplari()
{
    return [
        'Dersler'   => ['hafta', 'ornekler', 'haber'],
        'Site'      => ['anasayfa', 'duyuru', 'gorseller', 'genel'],
        'İnceleme'  => ['notlar'],
    ];
}

/* Site bölümleri panelde ayrı "Site" sekmesinde açılır */
function icerik_sekmesi($b)
{
    return in_array($b, icerik_gruplari()['Site'], true) ? 'site' : 'icerik';
}

function icerik_url($b, array $ek = [], $capa = '')
{
    return 'egitmen.php?' . http_build_query(array_merge(['sekme' => icerik_sekmesi($b), 'b' => $b], $ek)) . ($capa !== '' ? '#' . $capa : '');
}

/* ---------- İşlemler: işlem bu dosyaya aitse yönlendirme adresini döndürür ---------- */
function icerik_islem($islem, $kid)
{
    switch ($islem) {
        case 'hafta_kaydet':
            $n = max(1, min(8, (int)($_POST['n'] ?? 1)));
            $sorular = [];
            foreach ((array)($_POST['soru'] ?? []) as $i => $q) {
                $q = cut_text(trim((string)$q), 300);
                $sec = satirlar($_POST['secenek'][$i] ?? '', 6, 120);
                if ($q !== '' && count($sec) >= 2) {
                    $sorular[] = [$q, $sec];
                }
            }
            $ornekIds = array_column(ornekler(), 'id');
            $yeni = [
                'baslik'    => post('baslik', 120),
                'kisa'      => post('kisa', 160),
                'ozet'      => satirlar($_POST['ozet'] ?? '', 12),
                'atolye'    => satirlar($_POST['atolye'] ?? '', 10),
                'foto'      => post('foto_baslik', 120) !== '' ? ['baslik' => post('foto_baslik', 120), 'maddeler' => satirlar($_POST['foto_maddeler'] ?? '', 10)] : null,
                'odev'      => post('odev', 1000),
                'odev_turu' => ($_POST['odev_turu'] ?? '') === 'metin' ? 'metin' : 'haber',
                'canta'     => in_array($_POST['canta'] ?? '', ['kaynak', 'saha', 'kontrol'], true) ? $_POST['canta'] : null,
                'ornekler'  => array_values(array_intersect((array)($_POST['ornekler'] ?? []), $ornekIds)),
                'sorular'   => $sorular,
            ];
            if (text_len($yeni['baslik']) < 2) {
                flash('Haftanın başlığı boş olamaz.', 'err');
                return icerik_url('hafta', ['h' => $n]);
            }
            // varsayılandan farklı olmayan alanlar saklanmaz; böylece kod güncellemeleri o alanlara yansır
            $vars = week($n)['_varsayilan'] ?? [];
            $fark = [];
            foreach ($yeni as $k => $v) {
                if (!array_key_exists($k, $vars) || $vars[$k] !== $v) {
                    $fark[$k] = $v;
                }
            }
            icerik_guncelle(function (&$c) use ($n, $fark) {
                if ($fark) {
                    $c['haftalar'][$n] = $fark;
                } else {
                    unset($c['haftalar'][$n]);
                }
            });
            flash('Hafta ' . $n . ' kaydedildi.');
            return icerik_url('hafta', ['h' => $n]);

        case 'hafta_sifirla':
            $n = max(1, min(8, (int)($_POST['n'] ?? 1)));
            icerik_guncelle(function (&$c) use ($n) {
                unset($c['haftalar'][$n]);
            });
            flash('Hafta ' . $n . ' varsayılan içeriğe döndü.');
            return icerik_url('hafta', ['h' => $n]);

        case 'haber_ekle':
            return panel_haber_kaydet($kid);

        case 'panel_haber_sil':
            $id = (string)($_POST['id'] ?? '');
            if (strpos($id, 'ornek-p') === 0) {
                panel_ornegi_sil($id);
            } elseif (($h = haber_yukle($id)) && ($h['yazar_id'] ?? '') === 'egitmen') {
                if (!empty($h['foto'])) {
                    @unlink(UPLOADS . '/foto/' . basename($h['foto']));
                }
                @unlink(haber_path($h['id']));
            }
            flash('Haber silindi.');
            return icerik_url(($_POST['geri'] ?? '') === 'ornekler' ? 'ornekler' : 'haber');

        case 'ornek_gizle':
        case 'ornek_goster':
            $id = (string)($_POST['id'] ?? '');
            icerik_guncelle(function (&$c) use ($id, $islem) {
                $g = array_values(array_diff((array)($c['gizli_ornekler'] ?? []), [$id]));
                if ($islem === 'ornek_gizle') {
                    $g[] = $id;
                }
                $c['gizli_ornekler'] = $g;
            });
            flash($islem === 'ornek_gizle' ? 'Örnek haber gizlendi; sitede görünmez.' : 'Örnek haber yeniden görünür.');
            return icerik_url('ornekler');

        case 'ornek_foto':
        case 'ornek_foto_sil':
            $id = (string)($_POST['id'] ?? '');
            $kod = array_column(require __DIR__ . '/ornekler.php', 'id');
            if (!in_array($id, $kod, true)) {
                return icerik_url('ornekler');
            }
            $eski = (array)(icerik('ornek_foto', [])[$id] ?? []);
            try {
                if ($islem === 'ornek_foto') {
                    $f = $_FILES['foto'] ?? [];
                    $yeniFoto = ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? haber_foto_kaydet($f, $eski['foto'] ?? null) : ($eski['foto'] ?? null);
                    if (!$yeniFoto) {
                        throw new RuntimeException('Bir fotoğraf seçin.');
                    }
                    $kayit = ['foto' => $yeniFoto, 'altyazi' => post('altyazi', 300), 'kredi' => post('kredi', 120)];
                    icerik_guncelle(function (&$c) use ($id, $kayit) {
                        $c['ornek_foto'][$id] = $kayit;
                    });
                    flash('Fotoğraf eklendi; haberde “Temsilî fotoğraf” ibaresiyle görünür.');
                } else {
                    if (!empty($eski['foto'])) {
                        @unlink(UPLOADS . '/foto/' . basename($eski['foto']));
                    }
                    icerik_guncelle(function (&$c) use ($id) {
                        unset($c['ornek_foto'][$id]);
                    });
                    flash('Fotoğraf kaldırıldı.');
                }
            } catch (RuntimeException $ex) {
                flash($ex->getMessage(), 'err');
            }
            return icerik_url('ornekler', [], 'o-' . $id);

        case 'duyuru_kaydet':
            $bitis = (string)($_POST['bitis'] ?? '');
            icerik_guncelle(function (&$c) use ($bitis) {
                $c['duyuru'] = [
                    'metin' => post('metin', 200),
                    'bitis' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $bitis) ? $bitis : '',
                    'acik'  => !empty($_POST['acik']),
                ];
            });
            flash('Duyuru kaydedildi.');
            return icerik_url('duyuru');

        case 'anasayfa_kaydet':
            icerik_guncelle(function (&$c) {
                foreach (array_keys(anasayfa_alanlari()) as $k) {
                    $v = post($k, $k === 'alt' ? 400 : 120);
                    $c['anasayfa'][$k] = $v === anasayfa_alanlari()[$k][1] ? '' : $v;
                }
            });
            flash('Ana sayfa yazıları kaydedildi.');
            return icerik_url('anasayfa');

        case 'genel_kaydet':
            $ad = [];
            $gizli = [];
            $ek = (array)(konu_ayarlari()['ek'] ?? []);
            foreach (konular(true) as $k => $etiket) {
                $yeniAd = cut_text(trim((string)($_POST['konu_ad'][$k] ?? '')), 60);
                if ($yeniAd !== '' && $yeniAd !== (varsayilan_konular()[$k] ?? $ek[$k] ?? '')) {
                    $ad[$k] = $yeniAd;
                }
                if (!empty($_POST['konu_gizli'][$k]) && $k !== 'diger') {
                    $gizli[] = $k;
                }
            }
            foreach ((array)($_POST['konu_yeni'] ?? []) as $yeni) {
                $yeni = cut_text(trim((string)$yeni), 60);
                $anahtar = konu_slug($yeni);
                if ($yeni !== '' && $anahtar !== '' && !isset(konular(true)[$anahtar])) {
                    $ek[$anahtar] = $yeni;
                }
            }
            icerik_guncelle(function (&$c) use ($ad, $gizli, $ek) {
                $c['genel'] = ['rumuz' => post('rumuz', 40), 'alt_bilgi' => post('alt_bilgi', 120)];
                $c['konular'] = ['ad' => $ad, 'gizli' => $gizli, 'ek' => $ek];
            });
            flash('Genel ayarlar kaydedildi.');
            return icerik_url('genel');

        case 'notlar_kaydet':
            $liste = satirlar($_POST['notlar'] ?? '', 30, 200);
            icerik_guncelle(function (&$c) use ($liste) {
                if (!empty($_POST['varsayilan'])) {
                    unset($c['notlar']);
                } else {
                    $c['notlar'] = $liste;
                }
            });
            flash('Hazır notlar kaydedildi.');
            return icerik_url('notlar');
    }
    return null;
}

/* Haber ekle / düzenle: kendi imzam, örnek haber ya da katılımcı adına */
function panel_haber_kaydet($kid)
{
    $mod = in_array($_POST['mod'] ?? '', ['kendi', 'ornek', 'katilimci'], true) ? $_POST['mod'] : 'kendi';
    $id = (string)($_POST['id'] ?? '');
    $eski = $id !== '' ? haber_yukle($id) : null;
    if ($id !== '' && !$eski && strpos($id, 'ornek-p') === 0) {
        $eski = json_read(STORAGE . '/ornekler/' . $id . '.json', null); // gizlenmiş olabilir
    }
    $geri = icerik_url('haber', $id !== '' ? ['id' => $id] : ['mod' => $mod]);

    $kaynaklar = [];
    foreach ((array)($_POST['bilgi'] ?? []) as $i => $b) {
        $b = cut_text(trim((string)$b), 300);
        $k = cut_text(trim((string)($_POST['kaynak'][$i] ?? '')), 300);
        if ($b !== '' || $k !== '') {
            $kaynaklar[] = ['bilgi' => $b, 'kaynak' => $k];
        }
    }
    $v = [
        'baslik'    => post('baslik', 160),
        'spot'      => post('spot', 400),
        'yer'       => post('yer', 80),
        'konu'      => isset(konular()[$_POST['konu'] ?? '']) ? $_POST['konu'] : '',
        'metin'     => post('metin', (int)cfg('max_chars', 12000)),
        'kaynaklar' => array_slice($kaynaklar, 0, 30),
        'altyazi'   => post('altyazi', 300),
        'alt'       => post('alt', 300),
    ];
    $hafta = max(1, min(8, (int)($_POST['hafta'] ?? 1)));
    $tur = $mod === 'katilimci' ? hafta_turu($hafta) : ($mod === 'ornek' ? 'ornek' : 'haber');

    $hata = '';
    if (text_len($v['metin']) < 30) {
        $hata = 'Metin çok kısa.';
    } elseif ($tur !== 'odev' && text_len($v['baslik']) < 5) {
        $hata = 'Habere bir başlık yazın.';
    } elseif ($tur === 'haber' && (text_len($v['yer']) < 2 || $v['konu'] === '')) {
        $hata = 'Haberin yerini ve konusunu girin.';
    } elseif ($mod === 'katilimci' && empty($_POST['izin'])) {
        $hata = 'Katılımcı adına haber girmek için kullanım iznini aldığınızı işaretleyin.';
    }
    $u = null;
    if (!$hata && $mod === 'katilimci') {
        $u = $kid ? kullanici_bul($kid, (string)($_POST['uid'] ?? '')) : null;
        if (!$u || $u['durum'] !== 'onayli') {
            $hata = 'Listeden onaylı bir katılımcı seçin.';
        }
    }
    $foto = $eski['foto'] ?? null;
    if (!$hata) {
        try {
            if (!empty($_POST['foto_sil']) && $foto) {
                @unlink(UPLOADS . '/foto/' . basename($foto));
                $foto = null;
            }
            $f = $_FILES['foto'] ?? null;
            if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $foto = haber_foto_kaydet($f, $foto);
            }
        } catch (RuntimeException $ex) {
            $hata = $ex->getMessage();
        }
    }
    if (!$hata && $foto && text_len($v['altyazi']) < 5) {
        $hata = 'Fotoğrafa bir altyazı yazın: ne, nerede, ne zaman.';
    }
    if ($hata) {
        $_SESSION['panel_haber_form'] = array_diff_key($_POST, ['csrf' => 1]);
        flash($hata, 'err');
        return $geri;
    }
    $simdi = date('c');
    $rumuz = cfg('egitmen_rumuz', 'Yoka Rtık');

    if ($mod === 'ornek') {
        $o = array_merge($v, [
            'id'          => ($eski && strpos($eski['id'], 'ornek-p') === 0) ? $eski['id'] : 'ornek-p' . new_id(),
            'tur'         => 'ornek',
            'etiket'      => in_array($_POST['etiket'] ?? '', ['İyi örnek', 'Hatalı örnek', 'Doğrulama'], true) ? $_POST['etiket'] : 'İyi örnek',
            'neden'       => post('neden', 200),
            'hatalar'     => satirlar($_POST['hatalar'] ?? '', 10, 300),
            'ornek_hafta' => $hafta,
            'durum'       => 'yayinda',
            'yazar'       => $rumuz,
            'foto'        => $foto,
            'foto_yer'    => 'uploads',
            'not_okura'   => '',
            'guncelleme'  => $simdi,
        ]);
        if (($o['etiket'] ?? '') !== 'Hatalı örnek') {
            $o['hatalar'] = [];
        }
        panel_ornegi_kaydet($o);
        flash('Örnek haber kaydedildi. Hafta ' . $hafta . ' ders sayfasında görünür.');
        return icerik_url('ornekler');
    }

    if ($mod === 'kendi') {
        $yayinla = ($_POST['islem2'] ?? '') === 'yayinla';
        $h = array_merge($eski && ($eski['yazar_id'] ?? '') === 'egitmen' ? $eski : ['id' => new_id(), 'tarih' => $simdi], $v, [
            'tur'        => 'haber',
            'kur'        => '',
            'hafta'      => 0,
            'yazar_id'   => 'egitmen',
            'yazar'      => $rumuz,
            'foto'       => $foto,
            'onay'       => ['lisans' => true, 'foto' => true, 'ornek' => true],
            'yz'         => ['kullandi' => ($_POST['yz'] ?? '') === 'evet', 'aciklama' => post('yz_aciklama', 300)],
            'durum'      => $yayinla ? 'yayinda' : 'taslak',
            'guncelleme' => $simdi,
        ]);
        if ($yayinla) {
            $h['yayin_tarihi'] = $h['yayin_tarihi'] ?? $simdi;
        }
        haber_kaydet($h);
        flash($yayinla ? 'Haber yayımlandı.' : 'Taslak kaydedildi; yalnızca siz görürsünüz.');
        return icerik_url('haber', ['id' => $h['id']]);
    }

    // katılımcı adına: normal gönderim gibi inceleme sırasına girer
    $h = array_merge($v, [
        'id'           => new_id(),
        'tur'          => $tur,
        'hafta'        => $hafta,
        'kur'          => $kid,
        'yazar_id'     => $u['id'],
        'yazar'        => gorunen_ad($u),
        'foto'         => $foto,
        'onay'         => ['lisans' => true, 'foto' => (bool)$foto, 'ornek' => false],
        'yz'           => null,
        'egitmen_girdi' => true,
        'durum'        => 'bekliyor',
        'tarih'        => $simdi,
        'guncelleme'   => $simdi,
        'not'          => '',
    ]);
    if ($tur === 'odev') {
        $h['onay'] = ['lisans' => false, 'foto' => false, 'ornek' => false];
    }
    haber_kaydet($h);
    flash($u['rumuz'] . ' adına gönderi eklendi. İnceleyip karar verebilirsiniz.');
    return 'egitmen.php?haber=' . $h['id'];
}

/* ---------- Görünüm ---------- */
function icerik_paneli($kur, $kid, $sekme = 'icerik')
{
    $bolumler = icerik_bolumleri();
    $b = $_GET['b'] ?? '';
    if (!isset($bolumler[$b]) || ($sekme !== 'hepsi' && icerik_sekmesi($b) !== $sekme)) {
        $b = $sekme === 'site' ? 'anasayfa' : 'hafta';
    }
    $gruplar = array_filter(icerik_gruplari(), function ($anahtarlar, $grup) use ($sekme) {
        return $sekme === 'hepsi' || ($grup === 'Site') === ($sekme === 'site');
    }, ARRAY_FILTER_USE_BOTH);
    ?>
  <div class="icerik-yerlesim">
    <nav class="icerik-nav" aria-label="<?= $sekme === 'site' ? 'Site ayarları' : 'İçerik türleri' ?>">
      <?php foreach ($gruplar as $grup => $anahtarlar): ?>
        <span class="icerik-nav-grup"><?= e($grup) ?></span>
        <?php foreach ($anahtarlar as $k): ?>
          <a href="<?= e(icerik_url($k)) ?>"<?= $k === $b ? ' aria-current="page"' : '' ?>><?= e($bolumler[$k]) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="stack" style="min-width:0">
      <?php
        if ($b === 'hafta') {
            icerik_hafta_gorunum();
        } elseif ($b === 'haber') {
            icerik_haber_gorunum($kid);
        } elseif ($b === 'ornekler') {
            icerik_ornekler_gorunum();
        } elseif ($b === 'duyuru') {
            icerik_duyuru_gorunum();
        } elseif ($b === 'anasayfa') {
            icerik_anasayfa_gorunum();
        } elseif ($b === 'gorseller') {
            gorseller_karti();
        } elseif ($b === 'genel') {
            icerik_genel_gorunum();
        } else {
            icerik_notlar_gorunum();
        }
      ?>
    </div>
  </div>
<?php
}

function icerik_hafta_gorunum()
{
    $n = max(1, min(8, (int)($_GET['h'] ?? kur_hafta() ?: 1)));
    $w = week($n);
    $hazir = hafta_hazirlik($n, $w);
    $tamam = !in_array(false, array_column($hazir, 1), true);
    $sorular = (array)$w['sorular'];
    if (!$sorular) {
        $sorular[] = ['', []];
    }
    ?>
  <div class="hafta-cipler" role="list">
    <?php foreach (weeks() as $i => $wi): $ok = !in_array(false, array_column(hafta_hazirlik($i, $wi), 1), true); ?>
      <a role="listitem" class="hafta-cip" href="<?= e(icerik_url('hafta', ['h' => $i])) ?>"<?= $i === $n ? ' aria-current="page"' : '' ?>><span class="nokta <?= $ok ? 'ok' : 'eksik' ?>" aria-hidden="true"></span>Hafta <?= $i ?><span class="hidden"><?= $ok ? ' (hazır)' : ' (eksik var)' ?></span></a>
    <?php endforeach; ?>
  </div>

  <section class="card stack">
    <div class="row between" style="align-items:baseline">
      <h2 style="margin:0">Hafta <?= $n ?> · <?= e($w['baslik']) ?></h2>
      <a href="hafta.php?n=<?= $n ?>" target="_blank">Öğrencinin gördüğü sayfa →</a>
    </div>
    <div class="row" style="gap:8px">
      <?php foreach ($hazir as [$et, $ok]): ?><span class="tag <?= $ok ? 'tag-ok' : 'tag-gold' ?>"><?= e($et) ?><?= $ok ? ' ✓' : '' ?></span><?php endforeach; ?>
    </div>
    <?php if (!$tamam): ?><p class="help" style="margin:0">Sarı etiketler eksik olanları gösterir.</p><?php endif; ?>
    <nav class="hafta-atla" aria-label="Bu haftanın bölümleri"><a href="#sunum">Sunum</a><a href="#ders-sayfasi">Ders sayfası</a><a href="#odev-duzen">Ödev</a><a href="#sorular-duzen">Canlı sorular</a><a href="#ornek-duzen">Örnek haber</a></nav>
  </section>

  <section class="card stack" id="sunum">
    <h3 style="margin:0">Sunum</h3>
    <?php $var = is_file(sunum_path($n)); ?>
    <form method="post" enctype="multipart/form-data" class="row" style="gap:10px;align-items:center"><?= csrf_field() ?><input type="hidden" name="islem" value="sunum_yukle"><input type="hidden" name="hafta" value="<?= $n ?>">
      <span style="flex:1 1 220px"><?= $var ? 'Yüklü · ' . round(filesize(sunum_path($n)) / 1048576, 1) . ' MB · ' . e(tr_date(date('c', filemtime(sunum_path($n))), true)) : 'Sunum yüklenmedi' ?></span>
      <input type="file" name="pdf" accept="application/pdf" required aria-label="Hafta <?= $n ?> sunumu (PDF)" style="flex:1 1 220px;min-height:44px;padding:6px">
      <button class="btn btn-primary" type="submit"><?= $var ? 'Değiştir' : 'Yükle' ?></button>
    </form>
    <?php if ($var): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="islem" value="sunum_sil"><input type="hidden" name="hafta" value="<?= $n ?>"><button class="btn btn-sm btn-danger" type="submit">Sunumu sil</button></form><?php endif; ?>
    <p class="help" style="margin:0">PDF olarak yükleyin (PowerPoint: Dosya › Farklı kaydet › PDF). Bir kez yüklenir, sonraki kurlarda da kullanılır.</p>
  </section>

  <form method="post" class="stack" data-izle>
    <?= csrf_field() ?><input type="hidden" name="islem" value="hafta_kaydet"><input type="hidden" name="n" value="<?= $n ?>">
    <section class="card stack" id="ders-sayfasi">
      <h3 style="margin:0">Ders sayfası</h3>
      <div class="row" style="gap:12px;align-items:flex-start">
        <div class="field" style="flex:1 1 260px"><label for="h-baslik">Başlık</label><input id="h-baslik" name="baslik" type="text" maxlength="120" value="<?= e($w['baslik']) ?>" required></div>
        <div class="field" style="flex:1 1 260px"><label for="h-kisa">Kısa açıklama</label><input id="h-kisa" name="kisa" type="text" maxlength="160" value="<?= e($w['kisa']) ?>"></div>
      </div>
      <div class="field"><label for="h-ozet">Dersin özeti <span class="help">Her satır bir madde olur.</span></label><textarea id="h-ozet" name="ozet" rows="6"><?= e(implode("\n", (array)$w['ozet'])) ?></textarea></div>
      <div class="field"><label for="h-atolye">Atölye görevi <span class="help">Her satır bir adım olur.</span></label><textarea id="h-atolye" name="atolye" rows="4"><?= e(implode("\n", (array)$w['atolye'])) ?></textarea></div>
      <details class="kutu-gri"<?= !empty($w['foto']) ? ' open' : '' ?>>
        <summary style="cursor:pointer;font-weight:600">Fotoğraf bölümü <span class="help">(ders sayfasında ayrı kutu; isteğe bağlı)</span></summary>
        <div class="stack" style="margin-top:12px">
          <div class="field"><label for="h-fb">Başlık <span class="help">Boş bırakırsanız bu hafta fotoğraf bölümü görünmez.</span></label><input id="h-fb" name="foto_baslik" type="text" maxlength="120" value="<?= e($w['foto']['baslik'] ?? '') ?>" placeholder="Ör. Fotoğraf 1 · Haber fotoğrafı nedir?"></div>
          <div class="field"><label for="h-fm">Maddeler <span class="help">Her satır bir madde.</span></label><textarea id="h-fm" name="foto_maddeler" rows="3"><?= e(implode("\n", (array)($w['foto']['maddeler'] ?? []))) ?></textarea></div>
        </div>
      </details>
    </section>

    <section class="card stack" id="odev-duzen">
      <h3 style="margin:0">Ödev</h3>
      <div class="field"><label for="h-odev">Ödev metni</label><textarea id="h-odev" name="odev" rows="3"><?= e($w['odev']) ?></textarea></div>
      <div class="row" style="gap:20px">
        <label class="check"><input type="radio" name="odev_turu" value="metin"<?= ($w['odev_turu'] ?? '') === 'metin' ? ' checked' : '' ?>> <span>Yazılı ödev <span class="help">(yalnızca siz görürsünüz)</span></span></label>
        <label class="check"><input type="radio" name="odev_turu" value="haber"<?= ($w['odev_turu'] ?? 'haber') !== 'metin' ? ' checked' : '' ?>> <span>Haber <span class="help">(yayına aday)</span></span></label>
      </div>
      <p class="help" style="margin:0">Son teslim, dersi başlattığınız andan 7 gün sonra kendiliğinden belirlenir.</p>
    </section>

    <section class="card stack" id="sorular-duzen">
      <h3 style="margin:0">Canlı ders soruları</h3>
      <p class="help" style="margin:0">Derste sunucu ekranından tek dokunuşla telefonlara gönderilir. Seçenekleri alt alta yazın (en az 2).</p>
      <div class="stack" id="soru-listesi" data-sonraki="<?= count($sorular) ?>">
      <?php foreach ($sorular as $i => [$q, $sec]): ?>
        <div class="soru-satir">
          <div class="field"><label for="s-<?= $i ?>">Soru</label><input id="s-<?= $i ?>" name="soru[<?= $i ?>]" type="text" maxlength="300" value="<?= e($q) ?>"></div>
          <div class="field"><label for="sc-<?= $i ?>">Seçenekler</label><textarea id="sc-<?= $i ?>" name="secenek[<?= $i ?>]" rows="3"><?= e(implode("\n", (array)$sec)) ?></textarea></div>
          <button type="button" class="btn btn-sm soru-sil" data-soru-sil aria-label="Bu soruyu sil">Sil</button>
        </div>
      <?php endforeach; ?>
      </div>
      <button type="button" class="btn" data-soru-ekle style="align-self:flex-start">+ Soru ekle</button>
      <template id="soru-sablon"><div class="soru-satir"><div class="field"><label for="s-__i__">Soru</label><input id="s-__i__" name="soru[__i__]" type="text" maxlength="300"></div><div class="field"><label for="sc-__i__">Seçenekler</label><textarea id="sc-__i__" name="secenek[__i__]" rows="3"></textarea></div><button type="button" class="btn btn-sm soru-sil" data-soru-sil aria-label="Bu soruyu sil">Sil</button></div></template>
    </section>

    <section class="card stack" id="ornek-duzen">
      <h3 style="margin:0">Bu haftanın örnek haberi</h3>
      <?php foreach (ornekler() as $o): ?>
        <label class="check"><input type="checkbox" name="ornekler[]" value="<?= e($o['id']) ?>"<?= in_array($o['id'], (array)$w['ornekler'], true) ? ' checked' : '' ?>> <span><?= e($o['baslik']) ?> <span class="tag <?= ($o['etiket'] ?? '') === 'Hatalı örnek' ? 'tag-bad' : 'tag-ok' ?>"><?= e($o['etiket'] ?? '') ?></span></span></label>
      <?php endforeach; ?>
      <a href="<?= e(icerik_url('haber', ['mod' => 'ornek', 'hafta' => $n])) ?>">+ Bu hafta için yeni örnek haber yaz</a>
      <div class="field" style="max-width:420px"><label for="h-canta">Yazdırılabilir saha kartı</label>
        <select id="h-canta" name="canta"><?php foreach (['' => 'Yok', 'kaynak' => 'Kaynak listesi', 'saha' => 'İzin cümleleri ve röportaj soruları', 'kontrol' => 'Haber kontrol listesi'] as $ck => $ca): ?><option value="<?= $ck ?>"<?= (string)($w['canta'] ?? '') === $ck ? ' selected' : '' ?>><?= e($ca) ?></option><?php endforeach; ?></select></div>
    </section>

    <div class="kaydet-bar">
      <span class="help" data-durum><?= !empty($w['_degisti']) ? 'Bu haftada sizin yaptığınız değişiklikler var.' : 'Varsayılan içerik.' ?></span>
      <span class="row" style="gap:8px">
        <?php if (!empty($w['_degisti'])): ?><button class="btn" type="submit" name="islem" value="hafta_sifirla" formnovalidate>Varsayılana dön</button><?php endif; ?>
        <button class="btn btn-primary" type="submit">Kaydet</button>
      </span>
    </div>
  </form>
<?php
}

function icerik_haber_gorunum($kid)
{
    $id = (string)($_GET['id'] ?? '');
    $h = null;
    if ($id !== '') {
        $h = haber_yukle($id) ?: (strpos($id, 'ornek-p') === 0 ? json_read(STORAGE . '/ornekler/' . $id . '.json', null) : null);
        if ($h && !(($h['yazar_id'] ?? '') === 'egitmen' || strpos($h['id'], 'ornek-p') === 0)) {
            $h = null; // öğrenci haberleri inceleme ekranından düzenlenir
        }
    }
    $form = $_SESSION['panel_haber_form'] ?? null;
    unset($_SESSION['panel_haber_form']);
    $mod = $h ? (($h['tur'] ?? '') === 'ornek' ? 'ornek' : 'kendi') : ($form['mod'] ?? (in_array($_GET['mod'] ?? '', ['kendi', 'ornek', 'katilimci'], true) ? $_GET['mod'] : 'kendi'));
    $v = array_merge(['baslik' => '', 'spot' => '', 'yer' => '', 'konu' => '', 'metin' => '', 'kaynaklar' => [], 'altyazi' => '', 'alt' => '', 'etiket' => 'İyi örnek', 'neden' => '', 'hatalar' => [], 'ornek_hafta' => (int)($_GET['hafta'] ?? 1), 'hafta' => kur_hafta() ?: 1, 'yz' => null, 'foto' => null], (array)$h);
    if ($form) {
        foreach (['baslik', 'spot', 'yer', 'konu', 'metin', 'altyazi', 'alt', 'etiket', 'neden'] as $k) {
            $v[$k] = (string)($form[$k] ?? '');
        }
        $v['hatalar'] = satirlar($form['hatalar'] ?? '');
        $v['kaynaklar'] = [];
        foreach ((array)($form['bilgi'] ?? []) as $i => $bb) {
            $v['kaynaklar'][] = ['bilgi' => (string)$bb, 'kaynak' => (string)($form['kaynak'][$i] ?? '')];
        }
        $v['ornek_hafta'] = $v['hafta'] = (int)($form['hafta'] ?? 1);
    }
    $kaynaklar = array_pad((array)$v['kaynaklar'], max(3, count((array)$v['kaynaklar']) + 1), ['bilgi' => '', 'kaynak' => '']);
    $katilimcilar = $kid ? array_values(array_filter(kullanicilar($kid), function ($u) {
        return $u['durum'] === 'onayli';
    })) : [];
    $modlar = ['kendi' => 'Kendi imzamla', 'ornek' => 'Örnek haber', 'katilimci' => 'Bir katılımcı adına'];
    $aciklama = [
        'kendi'     => cfg('egitmen_rumuz', 'Yoka Rtık') . ' imzasıyla. Hemen yayımlayabilir ya da taslak olarak saklayabilirsiniz.',
        'ornek'     => 'Kurgusal örnek olarak; seçtiğiniz haftanın ders sayfasında ve Haberler › Örnek haberler’de görünür.',
        'katilimci' => 'Ödevini kâğıtta getiren ya da telefonla gönderemeyen katılımcı için. Normal bir gönderi gibi inceleme sırasına girer.',
    ];
    ?>
  <section class="card stack">
    <h2 style="margin:0"><?= $h ? 'Haberi düzenle' : 'Haber ekle' ?></h2>
    <?php if (!$h): ?>
    <div class="row" style="gap:8px" role="group" aria-label="Kimin adına?">
      <?php foreach ($modlar as $mk => $ma): ?><a class="chip-btn" href="<?= e(icerik_url('haber', ['mod' => $mk])) ?>" aria-pressed="<?= $mk === $mod ? 'true' : 'false' ?>"><?= e($ma) ?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="help" style="margin:0"><?= e($aciklama[$mod]) ?></p>
    <?php if ($mod === 'katilimci' && !$katilimcilar): ?><p class="flash warn" style="margin:0">Aktif kurda onaylı katılımcı yok.</p><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="stack" id="panel-haber" data-izle>
      <?= csrf_field() ?><input type="hidden" name="islem" value="haber_ekle"><input type="hidden" name="mod" value="<?= e($mod) ?>"><?php if ($h): ?><input type="hidden" name="id" value="<?= e($h['id']) ?>"><?php endif; ?>

      <?php if ($mod === 'katilimci'): ?>
      <div class="kutu-gri stack">
        <div class="row" style="gap:12px;align-items:flex-start">
          <div class="field" style="flex:1 1 260px"><label for="ph-uid">Katılımcı</label><select id="ph-uid" name="uid" required><option value="">Seç</option><?php foreach ($katilimcilar as $ku): ?><option value="<?= e($ku['id']) ?>"<?= ($form['uid'] ?? '') === $ku['id'] ? ' selected' : '' ?>><?= e($ku['ad']) ?> · <?= e($ku['rumuz']) ?></option><?php endforeach; ?></select></div>
          <div class="field" style="flex:1 1 260px"><label for="ph-hafta">Hangi haftanın ödevi?</label><select id="ph-hafta" name="hafta"><?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>"<?= (int)$v['hafta'] === $i ? ' selected' : '' ?>>Hafta <?= $i ?> · <?= hafta_turu($i) === 'odev' ? 'yazılı ödev' : 'haber' ?></option><?php endfor; ?></select></div>
        </div>
        <label class="check"><input type="checkbox" name="izin" value="1" required> <span>Katılımcının bu metni yazdığını ve sitede kullanılmasına izin verdiğini teyit ediyorum.</span></label>
      </div>
      <?php elseif ($mod === 'ornek'): ?>
      <div class="kutu-gri stack">
        <div class="row" style="gap:12px;align-items:flex-start">
          <div class="field" style="flex:1 1 200px"><label for="ph-etiket">Etiket</label><select id="ph-etiket" name="etiket"><?php foreach (['İyi örnek', 'Hatalı örnek', 'Doğrulama'] as $et): ?><option<?= $v['etiket'] === $et ? ' selected' : '' ?>><?= $et ?></option><?php endforeach; ?></select></div>
          <div class="field" style="flex:1 1 200px"><label for="ph-ohafta">Hangi haftada görünsün?</label><select id="ph-ohafta" name="hafta"><?php for ($i = 1; $i <= 8; $i++): ?><option value="<?= $i ?>"<?= (int)$v['ornek_hafta'] === $i ? ' selected' : '' ?>>Hafta <?= $i ?> · <?= e(week($i)['baslik']) ?></option><?php endfor; ?></select></div>
        </div>
        <div class="field"><label for="ph-neden">Neden örnek? <span class="help">(ders sayfasında başlığın altında görünür)</span></label><input id="ph-neden" name="neden" type="text" maxlength="200" value="<?= e($v['neden']) ?>" placeholder="Ör. Her bilgi kaynağına bağlı; rakamlar kaynakla birebir aynı."></div>
        <div class="field"><label for="ph-hatalar">Hatalar <span class="help">(yalnızca hatalı örnekte; her satır bir hata)</span></label><textarea id="ph-hatalar" name="hatalar" rows="3"><?= e(implode("\n", (array)$v['hatalar'])) ?></textarea></div>
      </div>
      <?php endif; ?>

      <div class="field"><label for="ph-baslik">Başlık</label><input id="ph-baslik" name="baslik" type="text" maxlength="160" value="<?= e($v['baslik']) ?>"></div>
      <div class="field"><label for="ph-spot">Spot <span class="help">(isteğe bağlı)</span></label><input id="ph-spot" name="spot" type="text" maxlength="400" value="<?= e($v['spot']) ?>"></div>
      <div class="row" style="gap:12px;align-items:flex-start">
        <div class="field" style="flex:1 1 220px"><label for="ph-yer">Yer</label><input id="ph-yer" name="yer" type="text" maxlength="80" value="<?= e($v['yer']) ?>" placeholder="Şehir, ilçe ya da mekân"></div>
        <div class="field" style="flex:1 1 220px"><label for="ph-konu">Konu</label><select id="ph-konu" name="konu"><option value="">Seç</option><?php foreach (konular() as $kk => $ka): ?><option value="<?= $kk ?>"<?= $v['konu'] === $kk ? ' selected' : '' ?>><?= e($ka) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label for="ph-metin">Metin</label><textarea id="ph-metin" name="metin" rows="10" required><?= e($v['metin']) ?></textarea></div>

      <fieldset class="stack" style="border:0;padding:0;margin:0">
        <legend style="font-weight:600;margin-bottom:6px">Kaynaklar <span class="help">(bilgi ve kaynağı)</span></legend>
        <?php foreach ($kaynaklar as $i => $k): ?>
          <div class="src-row"><input type="text" name="bilgi[]" value="<?= e($k['bilgi']) ?>" placeholder="Bilgi" aria-label="Bilgi <?= $i + 1 ?>"><input type="text" name="kaynak[]" value="<?= e($k['kaynak']) ?>" placeholder="Kaynak" aria-label="Kaynak <?= $i + 1 ?>"></div>
        <?php endforeach; ?>
      </fieldset>

      <div class="foto-kutu">
        <?php if (!empty($v['foto'])): ?><img src="<?= e(foto_url($v)) ?>" alt=""><?php else: ?><span class="foto-bos" aria-hidden="true">Fotoğraf yok</span><?php endif; ?>
        <div class="stack" style="flex:1 1 260px;gap:8px">
          <div class="field"><label for="ph-foto">Fotoğraf <span class="help">(JPG, PNG, WEBP; yönü düzeltilir, konum bilgisi silinir, 1600 piksele küçültülür)</span></label><input id="ph-foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
          <div class="field"><label for="ph-altyazi">Altyazı</label><input id="ph-altyazi" name="altyazi" type="text" maxlength="300" value="<?= e($v['altyazi']) ?>" placeholder="Ne, nerede, ne zaman"></div>
          <?php if (!empty($v['foto'])): ?><label class="check"><input type="checkbox" name="foto_sil" value="1"> <span>Fotoğrafı kaldır</span></label><?php endif; ?>
        </div>
      </div>

      <?php if ($mod === 'kendi'): $yz = $v['yz']; ?>
      <div class="row" style="gap:20px">
        <label class="check"><input type="radio" name="yz" value="hayir"<?= !$yz || !$yz['kullandi'] ? ' checked' : '' ?>> <span>Yapay zekâ kullanmadım</span></label>
        <label class="check"><input type="radio" name="yz" value="evet"<?= $yz && $yz['kullandi'] ? ' checked' : '' ?>> <span>Kullandım:</span></label>
        <input type="text" name="yz_aciklama" maxlength="300" value="<?= e($yz['aciklama'] ?? '') ?>" placeholder="Hangi iş için?" aria-label="Yapay zekâ kullanım açıklaması" style="flex:1 1 220px">
      </div>
      <?php endif; ?>

      <div class="kaydet-bar">
        <?php if ($h): ?><a href="<?= e(($h['tur'] ?? '') === 'ornek' || ($h['durum'] ?? '') === 'yayinda' ? 'haber.php?id=' . rawurlencode($h['id']) : '#') ?>" target="_blank"><?= ($h['durum'] ?? '') === 'yayinda' ? 'Sitede gör →' : '' ?></a><?php else: ?><span></span><?php endif; ?>
        <span class="row" style="gap:8px">
          <?php if ($mod === 'kendi'): ?>
            <button class="btn" type="submit" name="islem2" value="taslak">Taslak kaydet</button>
            <button class="btn btn-primary" type="submit" name="islem2" value="yayinla"><?= ($h['durum'] ?? '') === 'yayinda' ? 'Güncelle' : 'Yayımla' ?></button>
          <?php elseif ($mod === 'ornek'): ?>
            <button class="btn btn-primary" type="submit">Örneği kaydet</button>
          <?php else: ?>
            <button class="btn btn-primary" type="submit"<?= $katilimcilar ? '' : ' disabled' ?>>İnceleme sırasına ekle</button>
          <?php endif; ?>
        </span>
      </div>
    </form>
    <?php if ($h): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="islem" value="panel_haber_sil"><input type="hidden" name="id" value="<?= e($h['id']) ?>">
        <details><summary class="link-tehlike">Bu haberi sil</summary><p class="help">Haber ve fotoğrafı kalıcı olarak silinir.</p><button class="btn btn-sm btn-danger" type="submit">Kalıcı olarak sil</button></details></form>
    <?php endif; ?>
  </section>

  <?php $benim = haberler(function ($x) {
      return ($x['yazar_id'] ?? '') === 'egitmen';
  });
  usort($benim, function ($a, $b) {
      return strcmp($b['guncelleme'] ?? '', $a['guncelleme'] ?? '');
  });
  if ($benim): ?>
  <section class="card stack">
    <h3 style="margin:0">Kendi imzanızla eklediğiniz haberler</h3>
    <ul class="list-plain">
      <?php foreach ($benim as $x): ?>
        <li class="satir"><span><a href="<?= e(icerik_url('haber', ['id' => $x['id']])) ?>"><?= e($x['baslik']) ?></a> <span class="tag <?= $x['durum'] === 'yayinda' ? 'tag-ok' : '' ?>"><?= $x['durum'] === 'yayinda' ? 'Yayında' : 'Taslak' ?></span></span><span class="help"><?= e(tr_date($x['guncelleme'] ?? $x['tarih'], true)) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
<?php
}

function icerik_ornekler_gorunum()
{
    $gizli = gizli_ornekler();
    $kod = require __DIR__ . '/ornekler.php';
    $hepsi = array_merge(panel_ornekleri(), $kod);
    $haftasi = [];
    foreach (weeks() as $n => $w) {
        foreach ((array)$w['ornekler'] as $oid) {
            $haftasi[$oid][] = $n;
        }
    }
    ?>
  <section class="card stack">
    <div class="row between" style="align-items:baseline">
      <h2 style="margin:0">Örnek haberler</h2>
      <a class="btn btn-primary" href="<?= e(icerik_url('haber', ['mod' => 'ornek'])) ?>">+ Yeni örnek haber</a>
    </div>
    <p class="help" style="margin:0">Kurgusal örnekler <?= e(cfg('egitmen_rumuz', 'Yoka Rtık')) ?> imzasıyla yayımlanır. Sitenin kendi örnekleri silinemez, gizlenebilir.</p>
    <ul class="list-plain">
      <?php foreach ($hepsi as $o): $panel = strpos($o['id'], 'ornek-p') === 0; $g = in_array($o['id'], $gizli, true); if (!$panel) { $o = ornek_foto_uygula($o); } ?>
        <li class="satir" id="o-<?= e($o['id']) ?>">
          <?php if (!empty($o['foto'])): ?><img class="ornek-kucuk" src="<?= e(foto_url($o)) ?>" alt=""><?php else: ?><span class="ornek-kucuk foto-bos" aria-hidden="true">Fotoğraf yok</span><?php endif; ?>
          <span class="stack" style="gap:4px">
            <strong><?= e($o['baslik']) ?></strong>
            <span class="row" style="gap:6px"><span class="tag <?= ($o['etiket'] ?? '') === 'Hatalı örnek' ? 'tag-bad' : 'tag-ok' ?>"><?= e($o['etiket'] ?? '') ?></span>
              <span class="help"><?= isset($haftasi[$o['id']]) ? 'Hafta ' . implode(', ', $haftasi[$o['id']]) : 'Hiçbir haftaya bağlı değil' ?><?= $panel ? ' · panelden eklendi' : ' · sitenin örneği' ?><?= $g ? ' · gizli' : '' ?></span></span>
          </span>
          <span class="row" style="gap:6px">
            <?php if (!$g): ?><a class="btn btn-sm" href="haber.php?id=<?= e(rawurlencode($o['id'])) ?>" target="_blank">Gör</a><?php endif; ?>
            <?php if ($panel): ?><a class="btn btn-sm" href="<?= e(icerik_url('haber', ['id' => $o['id']])) ?>">Düzenle</a>
            <?php else: ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($o['id']) ?>"><button class="btn btn-sm" name="islem" value="<?= $g ? 'ornek_goster' : 'ornek_gizle' ?>"><?= $g ? 'Göster' : 'Gizle' ?></button></form><?php endif; ?>
          </span>
          <?php if (!$panel): ?>
          <details class="ornek-foto" style="flex-basis:100%"<?= ($_GET['acik'] ?? '') === $o['id'] ? ' open' : '' ?>>
            <summary><?= !empty($o['foto']) ? 'Fotoğrafı değiştir' : 'Fotoğraf ekle' ?></summary>
            <form method="post" enctype="multipart/form-data" class="stack" style="gap:10px;margin-top:10px"><?= csrf_field() ?><input type="hidden" name="islem" value="ornek_foto"><input type="hidden" name="id" value="<?= e($o['id']) ?>">
              <div class="field"><label for="of-<?= e($o['id']) ?>">Fotoğraf <span class="help">(JPG, PNG, WEBP; 1600 piksele küçültülür, konum bilgisi silinir)</span></label><input id="of-<?= e($o['id']) ?>" type="file" name="foto" accept="image/jpeg,image/png,image/webp"<?= empty($o['foto']) ? ' required' : '' ?>></div>
              <div class="row" style="gap:12px;align-items:flex-start">
                <div class="field" style="flex:2 1 260px"><label for="oa-<?= e($o['id']) ?>">Altyazı</label><input id="oa-<?= e($o['id']) ?>" name="altyazi" type="text" maxlength="300" value="<?= e($o['altyazi'] ?? '') ?>" placeholder="Ör. Bir metro istasyonu."></div>
                <div class="field" style="flex:1 1 200px"><label for="ok-<?= e($o['id']) ?>">Fotoğrafı çeken <span class="help">(isteğe bağlı)</span></label><input id="ok-<?= e($o['id']) ?>" name="kredi" type="text" maxlength="120" value="<?= e($o['foto_kredi'] ?? '') ?>" placeholder="Ör. Ad Soyad / Unsplash"></div>
              </div>
              <p class="help" style="margin:0">Haberde “Temsilî fotoğraf.” ibaresiyle görünür.</p>
              <div class="row" style="gap:8px"><button class="btn btn-sm btn-primary" type="submit">Kaydet</button><?php if (!empty($o['foto'])): ?><button class="btn btn-sm" type="submit" name="islem" value="ornek_foto_sil" formnovalidate>Fotoğrafı kaldır</button><?php endif; ?></div>
            </form>
          </details>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php
}

function icerik_duyuru_gorunum()
{
    $d = array_merge(['metin' => '', 'bitis' => '', 'acik' => false], (array)icerik('duyuru', []));
    $gorunur = duyuru() !== null;
    ?>
  <section class="card stack">
    <h2 style="margin:0">Duyuru bandı</h2>
    <p class="help" style="margin:0">Sitenin tüm sayfalarının üstünde kısa bir duyuru gösterir. Ör. “Bu haftaki ders salon 2’de.” Bitiş tarihinden sonra kendiliğinden kalkar.</p>
    <p class="tag <?= $gorunur ? 'tag-ok' : '' ?>" style="align-self:flex-start;margin:0"><?= $gorunur ? 'Şu an sitede görünüyor' : 'Şu an görünmüyor' ?></p>
    <form method="post" class="stack" data-izle><?= csrf_field() ?><input type="hidden" name="islem" value="duyuru_kaydet">
      <div class="field"><label for="d-metin">Duyuru metni</label><input id="d-metin" name="metin" type="text" maxlength="200" value="<?= e($d['metin']) ?>"></div>
      <div class="row" style="gap:16px;align-items:flex-end">
        <div class="field"><label for="d-bitis">Bitiş tarihi <span class="help">(isteğe bağlı)</span></label><input id="d-bitis" name="bitis" type="date" value="<?= e($d['bitis']) ?>"></div>
        <label class="check" style="min-height:48px"><input type="checkbox" name="acik" value="1"<?= $d['acik'] ? ' checked' : '' ?>> <span>Sitede göster</span></label>
      </div>
      <div class="kaydet-bar"><span class="help" data-durum></span><button class="btn btn-primary" type="submit">Kaydet</button></div>
    </form>
  </section>
<?php
}

function icerik_anasayfa_gorunum()
{
    ?>
  <section class="card stack">
    <h2 style="margin:0">Ana sayfa yazıları</h2>
    <p class="help" style="margin:0">Ziyaretçinin gördüğü ana sayfa. Bir alanı boşaltırsanız varsayılan metin kullanılır.</p>
    <form method="post" class="stack" data-izle><?= csrf_field() ?><input type="hidden" name="islem" value="anasayfa_kaydet">
      <?php foreach (anasayfa_alanlari() as $k => [$etiket, $vars]): ?>
        <div class="field"><label for="a-<?= $k ?>"><?= e($etiket) ?></label>
          <?php if ($k === 'alt'): ?><textarea id="a-<?= $k ?>" name="<?= $k ?>" rows="3" maxlength="400"><?= e(anasayfa_metin($k)) ?></textarea>
          <?php else: ?><input id="a-<?= $k ?>" name="<?= $k ?>" type="text" maxlength="120" value="<?= e(anasayfa_metin($k)) ?>"><?php endif; ?>
          <span class="help">Varsayılan: <?= e($vars) ?></span></div>
      <?php endforeach; ?>
      <div class="kaydet-bar"><a href="index.php" target="_blank">Ana sayfayı aç →</a><button class="btn btn-primary" type="submit">Kaydet</button></div>
    </form>
  </section>
<?php
}

function icerik_notlar_gorunum()
{
    $ozel = is_array(icerik('notlar'));
    ?>
  <section class="card stack">
    <h2 style="margin:0">Hazır not cümleleri</h2>
    <p class="help" style="margin:0">Gönderileri incelerken tek dokunuşla not kutusuna eklenir. Her satır bir not.</p>
    <form method="post" class="stack" data-izle><?= csrf_field() ?><input type="hidden" name="islem" value="notlar_kaydet">
      <div class="field"><label for="n-liste" class="hidden">Notlar</label><textarea id="n-liste" name="notlar" rows="12"><?= e(implode("\n", hazir_notlar())) ?></textarea></div>
      <div class="kaydet-bar"><span class="help" data-durum></span><span class="row" style="gap:8px"><?php if ($ozel): ?><button class="btn" type="submit" name="varsayilan" value="1">Varsayılana dön</button><?php endif; ?><button class="btn btn-primary" type="submit">Kaydet</button></span></div>
    </form>
  </section>
<?php
}

function icerik_genel_gorunum()
{
    $ayar = konu_ayarlari();
    ?>
  <section class="card stack">
    <h2 style="margin:0">İmza, konular, alt bilgi</h2>
    <form method="post" class="stack" data-izle><?= csrf_field() ?><input type="hidden" name="islem" value="genel_kaydet">
      <div class="row" style="gap:12px;align-items:flex-start">
        <div class="field" style="flex:1 1 260px"><label for="g-rumuz">Eğitmen imzası</label><input id="g-rumuz" name="rumuz" type="text" maxlength="40" value="<?= e(genel_ayar('rumuz')) ?>" placeholder="<?= e(cfg('egitmen_rumuz', 'Yoka Rtık')) ?>"><span class="help">Örnek haberlerde ve sizin eklediğiniz haberlerde görünür.</span></div>
        <div class="field" style="flex:1 1 260px"><label for="g-alt">Sayfa altındaki yazı</label><input id="g-alt" name="alt_bilgi" type="text" maxlength="120" value="<?= e(genel_ayar('alt_bilgi')) ?>" placeholder="anlatır"></div>
      </div>
      <fieldset class="stack" style="border:0;padding:0;margin:0">
        <legend style="font-weight:600;margin-bottom:6px">Haber konuları <span class="help">(gönderi formunda ve haberler sayfasındaki süzgeçte)</span></legend>
        <?php foreach (konular(true) as $k => $etiket): $gizli = in_array($k, (array)($ayar['gizli'] ?? []), true); ?>
          <div class="row" style="gap:10px;align-items:center">
            <label class="hidden" for="k-<?= e($k) ?>">Konu adı</label><input id="k-<?= e($k) ?>" name="konu_ad[<?= e($k) ?>]" type="text" maxlength="60" value="<?= e($etiket) ?>" style="flex:1 1 240px">
            <?php if ($k !== 'diger'): ?><label class="check"><input type="checkbox" name="konu_gizli[<?= e($k) ?>]" value="1"<?= $gizli ? ' checked' : '' ?>> <span>Gizle</span></label><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="row" style="gap:10px">
          <label class="hidden" for="k-yeni-1">Yeni konu</label><input id="k-yeni-1" name="konu_yeni[]" type="text" maxlength="60" placeholder="+ Yeni konu" style="flex:1 1 240px">
          <label class="hidden" for="k-yeni-2">Yeni konu</label><input id="k-yeni-2" name="konu_yeni[]" type="text" maxlength="60" placeholder="+ Yeni konu" style="flex:1 1 240px">
        </div>
        <p class="help" style="margin:0">Konu adını değiştirirseniz eski haberler de yeni adla görünür. Gizlenen konu yeni gönderilerde seçilemez; eski haberlerdeki etiket kalır.</p>
      </fieldset>
      <p class="help" style="margin:0">KVKK ve kullanım izni metinleri hukuki olduğu için burada değil; değişiklik gerekirse birlikte yaparız.</p>
      <div class="kaydet-bar"><span class="help" data-durum></span><button class="btn btn-primary" type="submit">Kaydet</button></div>
    </form>
  </section>
<?php
}
