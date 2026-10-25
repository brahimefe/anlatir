/* Kahramanın Tercihi — atölye akışı
   Klavye / sunum kumandası: → veya PageDown = ileri, ← veya PageUp = geri, F = tam ekran */
(function () {
  'use strict';
  var A = window.ATOLYE, V = A.veri;
  var EKRANLAR = ['giris1', 'giris2', 'giris3', 'film', 'anlatim', 'daire', 'uygulama', 'kahraman', 'kapanis'];
  var BOLUM_NO = [0, 0, 0, 1, 2, 3, 4, 5, 6];
  var HAREKET = ['Ayrılış', 'Erginlenme', 'Dönüş'];
  var sahne = document.getElementById('sahne');

  var d = {
    ekran: 0, satir: 0, vs: 0,
    film: null, asama: 0,
    adim: 0, cevap: [null, null, null, null, null, null], uyari: '',
    baslangic: Date.now()
  };

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function cevap(i) { return d.cevap[i]; }

  /* Uygunsuz içerik denetimi: salonun cevapları buradan geçer. */
  var YASAK_KOK = ['siktir', 'sikik', 'sikim', 'sikiş', 'amına', 'amina', 'orospu', 'yarrak', 'yarak', 'pezevenk', 'kahpe', 'ibne', 'gavat',
    'şerefsiz', 'serefsiz', 'piç', 'porno', 'seks', 'sex', 'öldür', 'oldur', 'intihar', 'bomba', 'uyuşturucu', 'uyusturucu', 'esrar', 'kokain', 'eroin',
    'tecavüz', 'tecavuz', 'katliam', 'terör', 'teror'];
  var YASAK_TAM = ['amk', 'aq', 'mk', 'oç', 'oc', 'göt', 'got', 'sik', 'am', 'sikerim', 'silah', 'kan', 'ölüm', 'olum'];
  function uygunsuzMu(metin) {
    var kelimeler = String(metin || '').toLocaleLowerCase('tr-TR').split(/[^a-zçğıöşüâîû]+/);
    return kelimeler.some(function (k) {
      if (!k) return false;
      if (YASAK_TAM.indexOf(k) > -1) return true;
      return YASAK_KOK.some(function (y) { return k.indexOf(y) === 0; });
    });
  }
  function iki(n) { return (n < 10 ? '0' : '') + n; }

  /* ───────────── Gezinme ───────────── */
  function ekranAdi() { return EKRANLAR[d.ekran]; }
  function git(n) {
    d.ekran = Math.max(0, Math.min(EKRANLAR.length - 1, n));
    ciz();
  }
  function ileriMumkun() {
    var e = ekranAdi();
    if (e === 'film') return d.film !== null;
    if (e === 'uygulama') return cevap(d.adim) !== null;
    return e !== 'kapanis';
  }
  function ileri() {
    var e = ekranAdi();
    if (e === 'giris2' && d.satir < V.hikaye_satirlari.length) { d.satir++; return ciz(); }
    if (e === 'giris3' && d.vs < 11) { d.vs++; return ciz(); }
    if (e === 'film') { if (d.film === null) return; return git(d.ekran + 1); }
    if (e === 'anlatim' && d.asama < 11) { d.asama++; return ciz(); }
    if (e === 'uygulama') { if (cevap(d.adim) === null) return; if (d.adim < 5) { d.adim++; d.uyari = ''; return ciz(); } }
    if (!ileriMumkun() && e !== 'uygulama') return;
    git(d.ekran + 1);
  }
  function geri() {
    var e = ekranAdi();
    if (e === 'giris2' && d.satir > 0) { d.satir--; return ciz(); }
    if (e === 'giris3' && d.vs > 0) { d.vs--; return ciz(); }
    if (e === 'anlatim' && d.asama > 0) { d.asama--; return ciz(); }
    if (e === 'uygulama' && d.adim > 0) { d.adim--; d.uyari = ''; return ciz(); }
    git(d.ekran - 1);
  }
  function ileriYazisi() {
    var e = ekranAdi();
    if (e === 'giris2' && d.satir < V.hikaye_satirlari.length) return 'Sonraki cümle ›';
    if ((e === 'giris3' && d.vs < 11) || (e === 'anlatim' && d.asama < 11)) return 'Sonraki aşama ›';
    if (e === 'uygulama') return d.adim < 5 ? 'Sonraki adım ›' : 'Kahraman kartı ›';
    return 'İleri ›';
  }

  /* ───────────── Kahraman ───────────── */
  function secimler() { return d.cevap.map(function (c) { return c || ''; }); }
  function nokta(deg, r, cx, cy) { var a = deg * Math.PI / 180; return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; }

  function ustCubuk() {
    var b = BOLUM_NO[d.ekran], sn = Math.floor((Date.now() - d.baslangic) / 1000), bol = V.bolumler[b];
    var cip = V.bolumler.map(function (x, i) { return '<span class="cip ' + (i === b ? 'simdi' : (i < b ? 'gecti' : '')) + '">' + esc(x[0]) + '</span>'; }).join('');
    return '<div class="u-cubuk"><div class="u-sol"><a class="u-logo" href="../atolyeler.php">anlatır<span>.</span></a><div class="cipler">' + cip + '</div></div>' +
      '<div class="u-sag"><span class="plan">Plan ' + bol[1] + ':00–' + bol[2] + ':00</span>' +
      '<span class="saat' + (sn > bol[2] * 60 ? ' gecikti' : '') + '" id="saat">' + Math.floor(sn / 60) + ':' + iki(sn % 60) + '</span>' +
      '<button class="yuv" data-e="geri" aria-label="Geri">‹</button>' +
      (ekranAdi() !== 'kapanis' ? '<button class="btn kucuk ana" data-e="ileri"' + (ileriMumkun() || ekranAdi() === 'giris2' ? '' : ' disabled') + '>' + ileriYazisi() + '</button>' : '') +
      '<button class="yuv" data-e="tamekran" aria-label="Tam ekran" title="Tam ekran (F)">⛶</button></div></div>';
  }

  var CIZ = {
    giris1: function () {
      return '<div class="sutun buyu"><div class="sutun buyu" style="justify-content:center;gap:28px">' +
        '<div class="etk" style="font-size:20px;letter-spacing:4px">Monomit: tek hikâye</div>' +
        '<h1 class="h-dev">Kahramanın<br><span class="vurgu">Tercihi</span></h1>' +
        '<p class="yatik" style="margin:0;font-size:34px;color:var(--metin2)">Önce size bir hikâye anlatacağım.</p></div>' +
        '<div class="satir ara"><div class="soluk" style="font-size:18px">İbn Haldun Üniversitesi · TercihFest</div>' +
        '<button class="btn ana" data-e="ileri">Hikâyeyi anlat</button></div></div>';
    },
    giris2: function () {
      var s = V.hikaye_satirlari.map(function (l, i) { return '<div class="hikaye-satiri" style="opacity:' + (i < d.satir ? 1 : .1) + '">' + esc(l) + '</div>'; }).join('');
      return '<div class="sutun buyu" style="gap:24px"><div class="etk">Tek hikâye</div><div class="sutun" style="gap:14px">' + s + '</div>' +
        (d.satir >= V.hikaye_satirlari.length ? '<div class="yatik vurgu" style="margin-top:auto;font-weight:600;font-size:56px">Hangi filmi anlattım?</div>' : '') + '</div>';
    },
    giris3: function () {
      var vs = d.vs, dots = '', liste = '';
      V.vogler.forEach(function (v, i) {
        var p = nokta(V.aci[i], 220, 300, 300), s = i === vs ? 50 : 38, c = i === vs ? 'simdi' : (i < vs ? 'gecti' : '');
        dots += '<button class="nokta-btn ' + c + '" data-e="vs" data-i="' + i + '" aria-label="' + (i + 1) + '. ' + esc(v[0]) + '" style="width:' + s + 'px;height:' + s + 'px;left:' + (p[0] - s / 2) + 'px;top:' + (p[1] - s / 2) + 'px">' + (i + 1) + '</button>';
        liste += '<button class="' + c + '" data-e="vs" data-i="' + i + '"><span>' + (i + 1) + '</span>' + esc(v[0]) + '</button>';
      });
      var yay = '';
      if (vs > 0) {
        var st = nokta(-90, 220, 300, 300), en = nokta(V.aci[vs], 220, 300, 300), ac = V.aci[vs] + 90; if (ac < 0) ac += 360;
        yay = '<path d="M' + st[0].toFixed(1) + ' ' + st[1].toFixed(1) + ' A220 220 0 ' + (ac > 180 ? 1 : 0) + ' 1 ' + en[0].toFixed(1) + ' ' + en[1].toFixed(1) + '" fill="none" stroke="#E8A33D" stroke-width="4" stroke-linecap="round"/>';
      }
      var v = V.vogler[vs];
      return '<div class="sutun buyu" style="gap:18px"><div class="satir ara" style="align-items:baseline">' +
        '<h1 class="h2" style="font-size:42px">Hepsi doğru: insanlık aynı hikâyeyi anlatıyor.</h1>' +
        '<div class="soluk" style="font-size:16px">Christopher Vogler, <i>The Writer\'s Journey</i> · Campbell\'dan uyarlama</div></div>' +
        '<div class="satir buyu" style="gap:48px">' +
        '<div class="daire-kap" style="width:600px;height:600px"><svg width="600" height="600" viewBox="0 0 600 600" aria-hidden="true">' +
        '<circle cx="300" cy="300" r="220" fill="none" stroke="#3A3228" stroke-width="2"/>' + yay +
        '<line x1="40" y1="300" x2="560" y2="300" stroke="#5A4E3E" stroke-width="2" stroke-dasharray="8 6"/>' +
        '<text x="300" y="205" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="17" fill="#8C7F6C">Bilinen dünya</text>' +
        '<text x="300" y="405" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="17" fill="#8C7F6C">Bilinmeyen dünya</text>' +
        '<text x="300" y="268" text-anchor="middle" font-family="Fraunces, serif" font-size="26" font-weight="600" fill="#F4EEE3">' + HAREKET[v[2]] + '</text>' +
        '<text x="552" y="290" text-anchor="end" font-family="Instrument Sans, sans-serif" font-size="14" fill="#E8A33D">eşik</text></svg>' + dots + '</div>' +
        '<div class="sutun buyu" style="gap:16px"><div class="etk" style="font-size:16px">Vogler\'in 12 aşaması · ' + (vs + 1) + ' / 12</div>' +
        '<h2 class="h2 vurgu">' + esc(v[0]) + '</h2><p style="margin:0;font-size:24px;line-height:1.5;min-height:108px">' + esc(v[1]) + '</p>' +
        '<div class="v-liste">' + liste + '</div>' +
        '<div class="satir ara" style="padding-top:8px"><div class="yatik" style="font-size:24px">Bugün bu hikâyenin kahramanı sizsiniz.</div>' +
        '<span class="satir" style="gap:10px"><button class="btn kucuk" data-e="geri">‹ Önceki</button><button class="btn kucuk cizgili" data-e="ileri">Sonraki ›</button></span></div></div></div></div>';
    },
    film: function () {
      var kartlar = '';
      V.filmler.forEach(function (f, i) {
        var secili = i === d.film;
        kartlar += '<div class="film-kart' + (secili ? ' kazanan' : '') + '"><div class="satir ara" style="align-items:flex-start"><span class="roma">' + ['I', 'II', 'III'][i] + '</span></div>' +
          '<div class="sutun buyu" style="gap:8px"><div style="font-family:var(--baslik);font-size:34px;font-weight:600">' + esc(f.ad) + '</div><div class="soluk" style="font-size:19px;color:var(--ikincil)">' + esc(f.etiket) + '</div></div>' +
          '<button class="btn kucuk ' + (secili ? 'ana' : 'cizgili') + '" data-e="filmSec" data-i="' + i + '" style="justify-content:center">Bu filmle başla</button></div>';
      });
      return '<div class="sutun buyu" style="gap:26px"><div class="sutun" style="gap:10px"><h1 class="h1">Hangi kahramanın yolculuğunu izleyelim?</h1></div>' +
        '<div class="film-izgara">' + kartlar + '</div></div>';
    },
    anlatim: function () {
      var f = V.filmler[d.film === null ? 1 : d.film], a = d.asama, v = V.vogler[a], kod = f.id + '-' + iki(a + 1), yol = 'sahne.php?f=' + kod;
      var dots = V.aci.map(function (deg, i) {
        var p = nokta(deg, 150, 200, 200), s = i === a ? 28 : 16;
        return '<div class="k-nokta ' + (i === a ? 'simdi' : (i < a ? 'gecti' : '')) + '" style="width:' + s + 'px;height:' + s + 'px;left:' + (p[0] - s / 2) + 'px;top:' + (p[1] - s / 2) + 'px"></div>';
      }).join('');
      var haplar = V.vogler.map(function (x, i) { return '<button class="' + (i === a ? 'simdi' : (i < a ? 'gecti' : '')) + '" data-e="asama" data-i="' + i + '">' + (i + 1) + ' · ' + esc(x[3]) + '</button>'; }).join('');
      return '<div class="sutun buyu" style="gap:20px"><div class="satir ara"><div class="etk">' + esc(f.kisa.toLocaleUpperCase('tr-TR')) + ' · Kahramanın yolculuğu</div><div style="font-size:18px;color:var(--ikincil)">Aşama ' + (a + 1) + ' / 12</div></div>' +
        '<div class="satir buyu" style="gap:56px;align-items:flex-start"><div class="sutun buyu" style="gap:18px">' +
        '<div class="medya"><img src="' + yol + '" alt="' + esc(f.kisa + ': ' + v[0]) + '" data-yedek="1"><div class="yer" hidden><div class="soluk" style="font-size:14px;letter-spacing:2px">GÖRSEL YOK · sahneler/' + esc(kod) + '.jpg</div><div class="sahne-yazi" style="font-size:23px">' + esc(f.sahneler[a]) + '</div></div>' + (f.kaynak ? '<div class="kunye">Görsel: ' + esc(f.kaynak) + ' · eğitim amaçlı alıntı</div>' : '') + '</div>' +
        '<div class="sahne-yazi" data-altyazi="1">' + esc(f.sahneler[a]) + '</div>' +
        '<h1 class="h1 vurgu" style="font-size:52px;line-height:1">' + esc(v[0]) + '</h1><p style="margin:0;font-size:24px;line-height:1.4">' + esc(v[1]) + '</p></div>' +
        '<div class="daire-kap" style="width:400px;height:400px"><svg width="400" height="400" viewBox="0 0 400 400" aria-hidden="true"><circle cx="200" cy="200" r="150" fill="none" stroke="#3A3228" stroke-width="2"/><line x1="30" y1="200" x2="370" y2="200" stroke="#5A4E3E" stroke-width="2" stroke-dasharray="8 6"/>' +
        '<text x="200" y="130" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="16" fill="#B5AA98">Bilinen dünya</text><text x="200" y="280" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="16" fill="#B5AA98">Bilinmeyen dünya</text></svg>' + dots + '</div></div>' +
        '<div class="haplar">' + haplar + '</div></div>';
    },
    daire: function () {
      var parca = '';
      V.aci.forEach(function (deg, i) {
        var a = deg * Math.PI / 180, c = Math.cos(a), s = Math.sin(a), dx = 390 + 220 * c, dy = 320 + 220 * s, lx = 390 + 246 * c, ly = 320 + 246 * s;
        var tr = c > 0.3 ? 'translate(0,-50%)' : (c < -0.3 ? 'translate(-100%,-50%)' : (s < 0 ? 'translate(-50%,-100%)' : 'translate(-50%,0)'));
        var es = i === 4 ? ' esik' : '';
        parca += '<div class="d-nokta' + es + '" style="left:' + (dx - 15) + 'px;top:' + (dy - 15) + 'px">' + (i + 1) + '</div>' +
          '<div class="d-etiket' + es + '" style="left:' + lx + 'px;top:' + ly + 'px;transform:' + tr + '">' + esc(V.vogler[i][0]) + '</div>';
      });
      return '<div class="satir buyu" style="gap:40px"><div class="daire-kap" style="width:820px;height:640px"><svg width="780" height="640" viewBox="0 0 780 640" aria-hidden="true">' +
        '<circle cx="390" cy="320" r="220" fill="none" stroke="#5A4E3E" stroke-width="2"/><line x1="130" y1="320" x2="650" y2="320" stroke="#E8A33D" stroke-width="2" stroke-dasharray="10 7"/>' +
        '<text x="390" y="205" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="18" fill="#8C7F6C">Bilinen dünya</text><text x="390" y="445" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="18" fill="#8C7F6C">Bilinmeyen dünya</text>' +
        '<text x="480" y="275" text-anchor="middle" font-family="Fraunces, serif" font-size="28" font-weight="600" fill="#F4EEE3">Ayrılış</text><text x="300" y="275" text-anchor="middle" font-family="Fraunces, serif" font-size="28" font-weight="600" fill="#F4EEE3">Dönüş</text>' +
        '<text x="390" y="390" text-anchor="middle" font-family="Fraunces, serif" font-size="28" font-weight="600" fill="#F4EEE3">Erginlenme</text></svg>' + parca + '</div>' +
        '<div class="sutun buyu" style="gap:26px"><div class="etk">Tahtadaki daire</div><h1 class="h1" style="font-size:56px">Her şey eşikte başlar.</h1>' +
        '<p style="margin:0;font-size:22px;line-height:1.5;color:var(--metin2)">Kahraman sağdaki eşikten bilinmeyene geçer, en altta büyük sınavla yüzleşir ve soldan değişmiş olarak döner.</p>' +
        '<button class="btn ana" data-e="ileri" style="align-self:flex-start">Kendi kahramanımızı yaratalım</button></div></div>';
    },
    uygulama: function () {
      var i0 = d.adim, adim = V.adimlar[i0], yan = '', seg = '';
      V.adimlar.forEach(function (x, i) {
        var c = cevap(i);
        yan += '<div class="sutun" style="gap:4px"><div class="e ' + (i === i0 ? 'simdi' : (c !== null ? 'var' : '')) + '">' + (i + 1) + ' · ' + esc(x.etiket) + '</div><div class="d"' + (c === null ? ' style="color:var(--soluk)"' : '') + '>' + (c !== null ? esc(c) : (i === i0 ? 'yazılıyor…' : '')) + '</div></div>';
        seg += '<span class="seg ' + (c !== null ? 'var' : (i === i0 ? 'simdi' : '')) + '"></span>';
      });
      var oneriler = adim.secenekler.map(function (t, i) { return '<button class="btn kucuk cizgili" data-e="oneri" data-i="' + i + '">' + esc(t) + '</button>'; }).join('');
      var sonraki = cevap(i0) === null ? '' : '<button class="btn ana" data-e="ileri">' + (i0 < 5 ? 'Sonraki adım ›' : 'Kahraman kartı ›') + '</button>';
      return '<div class="satir buyu" style="gap:56px;align-items:stretch"><div class="yan"><div class="etk">Bizim kahramanımız</div>' + yan + '</div>' +
        '<div class="sutun buyu" style="gap:30px"><div class="satir ara"><div style="font-size:18px;color:var(--ikincil)">Adım ' + (i0 + 1) + ' / 6 · Vogler: ' + esc(adim.asama) + '</div><div class="satir" style="gap:8px">' + seg + '</div></div>' +
        '<h1 class="h1" style="font-size:72px">' + esc(adim.soru) + '</h1>' +
        '<div class="sutun" style="gap:10px"><input id="cevapGir" class="cevap-gir" type="text" maxlength="60" autocomplete="off" spellcheck="false" placeholder="Salonun cevabını yazın, Enter\'a basın" value="' + esc(cevap(i0) || '') + '">' +
        '<div style="min-height:24px;font-size:17px;color:var(--uyari)" role="status">' + esc(d.uyari) + '</div></div>' +
        '<div class="sutun" style="gap:12px"><div style="font-size:16px;color:var(--soluk)">Salon sessiz kalırsa:</div><div class="satir" style="gap:10px;flex-wrap:wrap">' + oneriler + '</div></div>' +
        '<div class="satir ara" style="margin-top:auto"><div style="font-size:16px;color:var(--soluk)">Enter: kaydet ve ilerle · ↑: önceki adım</div>' + sonraki + '</div></div></div>';
    },
    kahraman: function () {
      var P = secimler();
      var liste = V.adimlar.map(function (x, i) {
        return '<div style="font-size:25px;line-height:1.35"><span style="color:var(--ikincil)">' + esc(x.etiket) + ':</span> ' + esc(P[i]) + '</div>';
      }).join('');
      var soru = function (etiket, metin, vurgu) {
        return '<div class="sutun" style="gap:6px;padding:22px 26px;border:' + (vurgu ? '2px solid var(--vurgu);background:var(--yuzey2)' : '1px solid var(--cizgi)') + ';border-radius:20px">' +
          '<div class="' + (vurgu ? 'vurgu' : '') + '" style="font-size:15px;letter-spacing:2px;color:' + (vurgu ? '' : 'var(--ikincil)') + '">' + etiket + '</div>' +
          '<div style="font-family:var(--baslik);font-size:28px;font-weight:600;line-height:1.2">' + metin + '</div></div>';
      };
      return '<div class="sutun buyu" style="gap:22px"><div class="etk">Kahramanımız hazır</div>' +
        '<div class="satir buyu" style="gap:40px;align-items:stretch"><div class="k-kart"><h1 class="h1 vurgu" style="font-size:64px;line-height:1">Bizim kahramanımız</h1>' +
        '<div class="sutun" style="gap:12px">' + liste + '</div>' +
        '<div class="yatik" style="margin-top:auto;padding-top:20px;border-top:1px solid var(--cizgi);font-size:28px">Hikâyenin geri kalanını o yazacak.</div></div>' +
        '<div class="k-yan">' + soru('BÖLÜM', 'Hangi bölümü okurdu?', true) + soru('BEKLENTİ', 'Oraya giderken ne bekliyordu?') + soru('GERÇEKLİK', 'Gerçekte onu ne bekliyor?') + '</div></div></div>';
    },
    kapanis: function () {
      return '<div class="satir buyu" style="gap:56px"><svg width="600" height="600" viewBox="0 0 620 620" role="img" aria-label="Tercih süreci dairede">' +
        '<circle cx="310" cy="310" r="200" fill="none" stroke="#3A3228" stroke-width="2"/><line x1="80" y1="310" x2="540" y2="310" stroke="#5A4E3E" stroke-width="2" stroke-dasharray="8 6"/>' +
        '<circle cx="310" cy="110" r="9" fill="#8C7A5E"/><text x="310" y="84" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="19" fill="#D9CFBF">İlgi alanın</text>' +
        '<circle cx="410" cy="137" r="9" fill="#8C7A5E"/><text x="426" y="130" font-family="Instrument Sans, sans-serif" font-size="19" fill="#D9CFBF">Sınav sonucun</text>' +
        '<circle cx="474" cy="195" r="9" fill="#8C7A5E"/><text x="490" y="192" font-family="Instrument Sans, sans-serif" font-size="19" fill="#D9CFBF">"Ya yanlış seçersem?"</text>' +
        '<circle cx="503" cy="258" r="14" fill="#E8A33D"/><text x="524" y="252" font-family="Instrument Sans, sans-serif" font-size="20" font-weight="600" fill="#E8A33D">TercihFest</text><text x="524" y="274" font-family="Instrument Sans, sans-serif" font-size="16" fill="#B5AA98">buradasın</text>' +
        '<circle cx="510" cy="310" r="11" fill="none" stroke="#F4EEE3" stroke-width="3"/><text x="530" y="340" font-family="Instrument Sans, sans-serif" font-size="19" fill="#F4EEE3">Tercih listen</text>' +
        '<circle cx="451" cy="451" r="9" fill="#15120E" stroke="#6B5F4E" stroke-width="2"/><text x="468" y="470" font-family="Instrument Sans, sans-serif" font-size="19" fill="#B5AA98">Üniversite yılları</text>' +
        '<circle cx="110" cy="310" r="9" fill="#15120E" stroke="#6B5F4E" stroke-width="2"/><text x="96" y="345" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="19" fill="#B5AA98">Dönüş</text></svg>' +
        '<div class="sutun buyu" style="gap:24px"><div style="font-size:21px;color:var(--metin2)">Sizin beklentiniz ne?</div>' +
        '<h1 class="h1" style="font-size:76px;line-height:1">Sıradaki kahraman: <span class="vurgu">Sen.</span></h1>' +
        '<p style="margin:0;font-size:25px;line-height:1.45;color:var(--metin2)">Bugün akıl hocanla karşılaştığın gün. Burada soracağın her soru, eşikteki kararını güçlendirir.</p>' +
        '<div style="padding:22px 26px;border:1px solid var(--cizgi);border-radius:20px;font-family:var(--baslik);font-size:28px">Akıl hocası yol gösterir; eşiği kahraman geçer.</div>' +
        '<span class="satir"><button class="btn" data-e="bastan">Atölyeyi baştan başlat</button><a class="btn" href="cikis.php">Çıkış</a></span></div></div>';
    }
  };

  function cevapKaydet(metin) {
    var t = String(metin || '').replace(/\s+/g, ' ').trim();
    if (!t) { d.uyari = 'Önce bir cevap yazın.'; return ciz(); }
    if (uygunsuzMu(t)) { d.uyari = 'Bu cevap kullanılamaz. Başka bir cevap yazın.'; return ciz(); }
    d.cevap[d.adim] = t.charAt(0).toLocaleUpperCase('tr-TR') + t.slice(1); d.uyari = '';
    ileri();
  }
  function ciz() {
    sahne.innerHTML = ustCubuk() + '<div class="icerik">' + CIZ[ekranAdi()]() + '</div>';
    var gir = document.getElementById('cevapGir');
    if (gir) { gir.focus(); gir.setSelectionRange(gir.value.length, gir.value.length); }
    var vid = sahne.querySelector('img[data-yedek]');
    if (vid) vid.addEventListener('error', function () {
      vid.hidden = true; vid.parentNode.querySelector('.yer').hidden = false;
      var alt = sahne.querySelector('[data-altyazi]'); if (alt) alt.hidden = true; var kn = sahne.querySelector('.kunye'); if (kn) kn.hidden = true;
    });
  }

  /* ───────────── Olaylar ───────────── */
  sahne.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-e]'); if (!b || b.disabled) return;
    var e = b.getAttribute('data-e'), i = parseInt(b.getAttribute('data-i') || '0', 10);
    switch (e) {
      case 'ileri': ileri(); break;
      case 'geri': geri(); break;
      case 'git': git(i); break;
      case 'vs': d.vs = i; ciz(); break;
      case 'asama': d.asama = i; ciz(); break;
      case 'filmSec': d.film = i; d.asama = 0; git(4); break;
      case 'oneri': var g = document.getElementById('cevapGir'); if (g) { g.value = V.adimlar[d.adim].secenekler[i]; g.focus(); } break;
      case 'bastan':
        d.ekran = 0; d.satir = 0; d.vs = 0; d.film = null; d.asama = 0;
        d.adim = 0; d.cevap = [null, null, null, null, null, null]; d.uyari = ''; d.baslangic = Date.now(); ciz(); break;
      case 'tamekran':
        if (document.fullscreenElement) document.exitFullscreen().catch(function () {});
        else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(function () {});
        break;
    }
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.target && ev.target.id === 'cevapGir') {   // cevap kutusu: Enter / PageDown kaydeder ve ilerler
      if (ev.key === 'Enter' || ev.key === 'PageDown') { ev.preventDefault(); cevapKaydet(ev.target.value); }
      else if (ev.key === 'PageUp' || ev.key === 'ArrowUp') { ev.preventDefault(); geri(); }
      return;
    }
    if (ev.target && /INPUT|TEXTAREA|SELECT/.test(ev.target.tagName)) return;
    if (ev.key === 'ArrowRight' || ev.key === 'PageDown') { ev.preventDefault(); if (ileriMumkun() || ekranAdi() === 'giris2' || ekranAdi() === 'giris3' || ekranAdi() === 'anlatim') ileri(); }
    else if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') { ev.preventDefault(); geri(); }
    else if (ev.key === 'f' || ev.key === 'F') { var t = sahne.querySelector('[data-e="tamekran"]'); if (t) t.click(); }
  });

  function olcekle() {
    var s = Math.min(window.innerWidth / 1440, window.innerHeight / 900);
    sahne.style.transform = 'translate(-50%, -50%) scale(' + s + ')';
  }
  window.addEventListener('resize', olcekle);
  setInterval(function () {
    var el = document.getElementById('saat'); if (!el) return;
    var sn = Math.floor((Date.now() - d.baslangic) / 1000), bol = V.bolumler[BOLUM_NO[d.ekran]];
    el.textContent = Math.floor(sn / 60) + ':' + iki(sn % 60);
    el.classList.toggle('gecikti', sn > bol[2] * 60);
  }, 1000);

  olcekle();
  ciz();
})();
