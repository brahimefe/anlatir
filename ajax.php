<?php
/* Canlı ders: durum sorgusu (telefon, sunucu, projeksiyon) ve eylemler */
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cevap($data)
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}
function hata($status, $msg)
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$kur = kur_aktif();
if (!$kur) {
    hata(404, 'Aktif kur yok.');
}
$kid = $kur['id'];
$admin = is_admin();
$k = katilim();
if (!$admin && !$k) {
    hata(401, 'Derse yeniden katıl.');
}
$a = (string)($_GET['a'] ?? $_POST['a'] ?? 'durum');

$pingPath = kur_dir($kid) . '/canli-ping.json';
$yanitPath = kur_dir($kid) . '/canli-yanit.json';

/* ---------- Eylemler ---------- */
if (is_post()) {
    if (!csrf_ok()) {
        hata(400, 'Sayfanın süresi dolmuş. Sayfayı yenileyin.');
    }
    $c = canli($kid);

    if ($a === 'cevap') {
        $soru = $c['soru'];
        $secim = (int)($_POST['secim'] ?? -1);
        if (!$soru || empty($soru['acik']) || (string)($_POST['soru'] ?? '') !== $soru['id']) {
            hata(409, 'Bu soru kapandı.');
        }
        if ($secim < 0 || $secim >= count($soru['secenekler'])) {
            hata(400, 'Bir seçenek seç.');
        }
        $pid = $admin ? 'egitmen' : $k['pid'];
        json_update($yanitPath, function (&$d) use ($soru, $pid, $secim) {
            if (($d['soru'] ?? '') !== $soru['id']) {
                $d = ['soru' => $soru['id'], 'yanitlar' => []];
            }
            $d['yanitlar'][$pid] = $secim;
        });
        cevap([]);
    }

    if ($a === 'oy') {
        $u = ogrenci();
        if (!$u) {
            hata(403, 'Oy vermek için giriş yapmalısın.');
        }
        $hafta = $c['oylama'];
        if (!$hafta) {
            hata(409, 'Oylama kapalı.');
        }
        $hid = (string)($_POST['haber'] ?? '');
        $sonuc = json_update(oylama_path($kid, $hafta), function (&$o) use ($u, $hid) {
            if (($o['durum'] ?? '') !== 'acik') {
                return 'Oylama kapalı.';
            }
            if (!in_array($hid, $o['finalistler'], true)) {
                return 'Geçersiz seçim.';
            }
            if (isset($o['oylar'][$u['id']])) {
                return 'Oyunu zaten verdin.';
            }
            $h = haber_yukle($hid);
            if (!$h || $h['yazar_id'] === $u['id']) {
                return 'Kendi haberine oy veremezsin.';
            }
            $o['oylar'][$u['id']] = $hid;
            return 'ok';
        }, oylama($kid, $hafta));
        if ($sonuc !== 'ok') {
            hata(409, $sonuc);
        }
        cevap([]);
    }

    if (!$admin) {
        hata(403, 'Bu işlem yalnızca eğitmen içindir.');
    }

    switch ($a) {
        case 'baslat':
            soru_arsivle($kid, $c);
            $hafta = max(1, min(8, (int)($_POST['hafta'] ?? 1)));
            oylama_iptal($kid, $c['oylama']);
            $bekleyen = canli_guncelle($kid, function (&$d) use ($hafta) {
                $b = (array)($d['bekleyen_katilim'] ?? []);
                $d = array_merge(canli_varsayilan(), ['aktif' => true, 'hafta' => $hafta, 'sayfa' => 1, 'baslangic' => time(), 'qr' => $d['qr'] ?? true, 'rev' => $d['rev'] ?? 0,
                    'ders_kodu' => $d['ders_kodu'] ?: kur_kodu_uret()]);
                return $b;
            });
            // dersten önce kodu okutanlar (son 4 saat) bu haftanın katılımına yazılır
            $bekleyen = array_filter((array)$bekleyen, function ($t) {
                return strtotime($t) > time() - 4 * 3600;
            });
            if ($bekleyen) {
                yoklama_isle($kid, $hafta, $bekleyen);
            }
            @unlink($yanitPath);
            $tarihler = $kur['ders_tarihleri'] ?? [];
            if (empty($tarihler[$hafta])) {
                $tarihler[$hafta] = date('c');
            }
            $alan = ['hafta' => max($hafta, (int)($kur['hafta'] ?? 1)), 'ders_tarihleri' => $tarihler]; // geçmiş bir hafta tekrarlanırsa açık hafta geri düşmez
            if ($hafta >= 2 && !empty($kur['kayit_acik']) && empty($kur['kayit_elle'])) {
                $alan['kayit_acik'] = false;
            }
            kur_guncelle($kid, $alan);
            cevap([]);

        case 'sayfa':
            $n = max(1, (int)($_POST['n'] ?? 1));
            canli_guncelle($kid, function (&$d) use ($n) {
                $d['sayfa'] = $d['sayfa_sayisi'] ? min($n, $d['sayfa_sayisi']) : $n;
            });
            cevap([]);

        case 'sayfa_sayisi':
            $n = max(0, (int)($_POST['n'] ?? 0));
            canli_guncelle($kid, function (&$d) use ($n) {
                $d['sayfa_sayisi'] = $n;
            });
            cevap([]);

        case 'qr':
            $g = !empty($_POST['goster']);
            canli_guncelle($kid, function (&$d) use ($g) {
                $d['qr'] = $g;
            });
            cevap([]);

        case 'soru':
            soru_arsivle($kid, $c);
            $metin = post('metin', 300);
            $sec = [];
            foreach ((array)($_POST['secenekler'] ?? []) as $s) {
                $s = cut_text(trim((string)$s), 120);
                if ($s !== '') {
                    $sec[] = $s;
                }
            }
            if (text_len($metin) < 3 || count($sec) < 2) {
                hata(400, 'Soru ve en az iki seçenek yazın.');
            }
            $id = new_id();
            canli_guncelle($kid, function (&$d) use ($id, $metin, $sec) {
                $d['soru'] = ['id' => $id, 'metin' => $metin, 'secenekler' => array_slice($sec, 0, 6), 'acik' => true, 'sonuc' => false];
            });
            json_write($yanitPath, ['soru' => $id, 'yanitlar' => []]);
            cevap([]);

        case 'soru_sonuc':
            canli_guncelle($kid, function (&$d) {
                if ($d['soru']) {
                    $d['soru']['sonuc'] = true;
                    $d['soru']['acik'] = false;
                }
            });
            soru_arsivle($kid, $c);
            cevap([]);

        case 'soru_kapat':
            soru_arsivle($kid, $c);
            canli_guncelle($kid, function (&$d) {
                $d['soru'] = null;
            });
            cevap([]);

        case 'oylama_baslat':
            soru_arsivle($kid, $c);
            if (!$c['aktif']) {
                hata(409, 'Oylama canlı ders sırasında başlatılır.');
            }
            $hafta = (int)$c['hafta'];
            if (!empty(oylama($kid, $hafta)['kazanan'])) {
                hata(409, 'Bu haftanın haberi zaten seçildi.');
            }
            $havuz = finalistler($kid);
            if (count($havuz) < 2) {
                hata(400, 'Oylama için en az iki finalist seçin (Eğitmen paneli › Yayımlanan haberler › Finalist yap).');
            }
            json_write(oylama_path($kid, $hafta), ['finalistler' => $havuz, 'durum' => 'acik', 'oylar' => [], 'kazanan' => null]);
            canli_guncelle($kid, function (&$d) use ($hafta) {
                $d['oylama'] = $hafta;
                $d['soru'] = null;
            });
            cevap([]);

        case 'oylama_bitir':
        case 'kazanan_sec':
            $hafta = (int)$c['oylama'];
            if (!$hafta) {
                hata(409, 'Açık oylama yok.');
            }
            $secilen = (string)($_POST['haber'] ?? '');
            $kazanan = json_update(oylama_path($kid, $hafta), function (&$o) use ($a, $secilen) {
                if ($a === 'kazanan_sec') {
                    if (!in_array($secilen, $o['esitlik'] ?? [], true)) {
                        return null;
                    }
                    $o['kazanan'] = $secilen;
                    $o['durum'] = 'kapali';
                    unset($o['esitlik']);
                    return $secilen;
                }
                $say = array_fill_keys($o['finalistler'], 0);
                foreach ($o['oylar'] as $hid) {
                    if (isset($say[$hid])) {
                        $say[$hid]++;
                    }
                }
                $max = $say ? max($say) : 0;
                $ust = array_keys(array_filter($say, function ($n) use ($max) {
                    return $n === $max;
                }));
                if ($max === 0 || count($ust) > 1) {
                    $o['durum'] = 'esitlik';
                    $o['esitlik'] = $max === 0 ? $o['finalistler'] : $ust;
                    return null;
                }
                $o['kazanan'] = $ust[0];
                $o['durum'] = 'kapali';
                return $ust[0];
            }, oylama($kid, $hafta));
            if ($kazanan) {
                foreach (oylama($kid, $hafta)['finalistler'] as $fid) {
                    $h = haber_yukle($fid);
                    if ($h) {
                        $h['finalist'] = false;
                        if ($fid === $kazanan) {
                            $h['kazanan'] = true;
                            $h['kazanan_hafta'] = $hafta;
                        }
                        haber_kaydet($h);
                    }
                }
                json_write(finalist_path($kid), []);
            }
            cevap(['kazanan' => $kazanan]);

        case 'oylama_kapat':
            canli_guncelle($kid, function (&$d) {
                $d['oylama'] = null;
            });
            cevap([]);

        case 'rumuz':
            $pid = (string)($_POST['pid'] ?? '');
            $yeni = cut_text(trim((string)($_POST['yeni'] ?? '')), 40);
            if ($yeni === '' || $pid === '') {
                hata(400, 'Yeni rumuzu yazın.');
            }
            json_update($pingPath, function (&$p) use ($pid, $yeni) {
                if (isset($p[$pid])) {
                    $p[$pid]['r'] = $yeni;
                    $p[$pid]['degis'] = $yeni;
                }
            });
            if ($pid[0] === 'u' && safe_id(substr($pid, 1))) {
                kullanici_guncelle($kid, substr($pid, 1), ['rumuz' => $yeni]);
            }
            cevap([]);

        case 'bitir':
            soru_arsivle($kid, $c);
            oylama_iptal($kid, $c['oylama']);
            canli_guncelle($kid, function (&$d) {
                $d['aktif'] = false;
                $d['soru'] = null;
                $d['oylama'] = null;
                $d['bitti'] = ['hafta' => $d['hafta'], 'zaman' => time()];
                $d['ders_kodu'] = null; // bir sonraki ders için yeni kod üretilir
                $d['bekleyen_katilim'] = [];
            });
            cevap([]);

        default:
            hata(400, 'Geçersiz işlem.');
    }
}

/* ---------- Durum sorgusu ---------- */
$c = canli($kid);
$simdi = time();

// Katılımcı ping'i (en fazla 20 saniyede bir yazılır)
if ($k && !$admin) {
    $son = (int)($_SESSION['son_ping'] ?? 0);
    $pid = $k['pid'];
    if ($simdi - $son > 20 || isset($_GET['ilk'])) {
        $_SESSION['son_ping'] = $simdi;
        $degis = json_update($pingPath, function (&$p) use ($pid, $k, $simdi) {
            $d = $p[$pid]['degis'] ?? null;
            $p[$pid] = ['r' => $d ?: $k['rumuz'], 't' => $simdi];
            foreach ($p as $id => $row) {
                if ($simdi - (int)$row['t'] > 3600) {
                    unset($p[$id]);
                }
            }
            return $d;
        });
        if ($degis) {
            $_SESSION['katilim']['rumuz'] = $degis;
            $k['rumuz'] = $degis;
        }
    }
}

session_write_close();
$w = week($c['hafta']);
$out = [
    'rev'          => (int)$c['rev'],
    'aktif'        => (bool)$c['aktif'],
    'hafta'        => (int)$c['hafta'],
    'baslik'       => $w['baslik'] ?? '',
    'sayfa'        => (int)$c['sayfa'],
    'sayfa_sayisi' => (int)$c['sayfa_sayisi'],
    'sunum'        => is_file(sunum_path($c['hafta'])) ? (int)filemtime(sunum_path($c['hafta'])) : 0,
    'qr'           => (bool)$c['qr'],
    'kod'          => $admin ? ders_kodu($kid) : null,
    'rumuz'        => $k['rumuz'] ?? null,
    'giris'        => (bool)ogrenci(),
    'katildim'     => null,
    'soru'         => null,
    'oylama'       => null,
    'bitti'        => null,
];

if (!$admin && ($ou = ogrenci())) {
    $out['katildim'] = $c['aktif'] ? isset(yoklama($kid)[(string)(int)$c['hafta']][$ou['id']]) : isset($c['bekleyen_katilim'][$ou['id']]);
}

if ($c['soru']) {
    $s = $c['soru'];
    $y = json_read($yanitPath, []);
    $yan = ($y['soru'] ?? '') === $s['id'] ? (array)$y['yanitlar'] : [];
    $soru = ['id' => $s['id'], 'metin' => $s['metin'], 'secenekler' => $s['secenekler'], 'acik' => !empty($s['acik']), 'sonuc' => !empty($s['sonuc']), 'yanit_sayisi' => count($yan)];
    $mine = $admin ? 'egitmen' : ($k['pid'] ?? '');
    $soru['benim'] = isset($yan[$mine]) ? (int)$yan[$mine] : null;
    if ($admin || $soru['sonuc']) {
        $say = array_fill(0, count($s['secenekler']), 0);
        foreach ($yan as $v) {
            if (isset($say[$v])) {
                $say[$v]++;
            }
        }
        $soru['sayilar'] = $say;
    }
    $out['soru'] = $soru;
}

if ($c['oylama']) {
    $o = oylama($kid, $c['oylama']);
    $u = ogrenci();
    $fin = [];
    foreach ($o['finalistler'] as $hid) {
        $h = haber_yukle($hid);
        if ($h) {
            $fin[] = ['id' => $hid, 'baslik' => $h['baslik'], 'yazar' => $h['yazar'], 'kaynak' => kaynak_sayisi($h), 'foto' => foto_url($h), 'kendi' => $u && $h['yazar_id'] === $u['id']];
        }
    }
    $ov = ['hafta' => (int)$c['oylama'], 'durum' => $o['durum'], 'finalistler' => $fin, 'oy_sayisi' => count($o['oylar']),
        'benim' => $u ? ($o['oylar'][$u['id']] ?? null) : null, 'kazanan' => $o['kazanan'] ?? null, 'esitlik' => $o['esitlik'] ?? []];
    if ($admin) {
        $say = [];
        foreach ($o['oylar'] as $hid) {
            $say[$hid] = ($say[$hid] ?? 0) + 1;
        }
        $ov['sayilar'] = $say;
    }
    $out['oylama'] = $ov;
}

if (!$c['aktif'] && $c['bitti']) {
    $bh = (int)$c['bitti']['hafta'];
    $out['bitti'] = ['hafta' => $bh, 'baslik' => week($bh)['baslik'] ?? '', 'odev' => week($bh)['odev'] ?? '', 'son_teslim' => son_teslim_yazi($kur, $bh),
        'sonraki' => $bh < 8 ? ['hafta' => $bh + 1, 'baslik' => week($bh + 1)['baslik']] : null];
}

if ($admin) {
    $p = json_read($pingPath, []);
    $bagli = [];
    foreach ($p as $id => $row) {
        if ($simdi - (int)$row['t'] < 75) {
            $bagli[] = ['pid' => $id, 'rumuz' => $row['r']];
        }
    }
    usort($bagli, function ($x, $y) {
        return strcmp(tr_lower($x['rumuz']), tr_lower($y['rumuz']));
    });
    $out['bagli'] = $bagli;
    $out['katilan'] = $c['aktif'] ? count((array)(yoklama($kid)[(string)(int)$c['hafta']] ?? [])) : count((array)$c['bekleyen_katilim']);
    $out['misafir'] = count(array_filter($bagli, function ($b) {
        return $b['pid'][0] === 'p';
    }));
    $out['baslangic'] = (int)$c['baslangic'];
    $out['katil_url'] = base_url() . '/katil.php?kod=' . rawurlencode($out['kod']);
    $out['katil_kisa'] = preg_replace('#^https?://#', '', base_url()) . '/katil';
}
cevap($out);
