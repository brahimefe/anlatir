/* Kahramanın Tercihi — atölye akışı
   Klavye / sunum kumandası: → veya PageDown = ileri, ← veya PageUp = geri, F = tam ekran */
(function () {
  'use strict';
  var A = window.ATOLYE, V = A.veri;
  var EKRANLAR = ['giris1', 'giris2', 'giris3', 'film', 'anlatim', 'daire', 'uygulama', 'yaz', 'kahraman', 'kapanis'];
  var BOLUM_NO = [0, 0, 0, 1, 2, 3, 4, 5, 5, 6];
  var HAREKET = ['Ayrılış', 'Erginlenme', 'Dönüş'];
  var sahne = document.getElementById('sahne');

  var d = {
    ekran: 0, satir: 0, vs: 0,
    filmPuan: [null, null, null], film: null, asama: 0,
    adim: 0, cevap: [null, null, null, null, null, null], uyari: '', kasama: 1,
    kahraman: null, baslangic: Date.now()
  };

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function argmax(a) { var b = null; a.forEach(function (v, i) { if (v !== null && (b === null || v > a[b])) b = i; }); return b; }
  function cevap(i) { return d.cevap[i]; }

  /* Uygunsuz içerik denetimi: hem salonun cevapları hem yapay zekânın yanıtı buradan geçer. */
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

  /* ───────────── Alkış ölçer ───────────── */
  var Alkis = {
    ctx: null, analiz: null, tampon: null, zemin: null, tavan: null, durum: 'kapali', hata: '', mesgul: false, aktif: null,
    ac: function () {
      var self = this;
      if (self.analiz) return Promise.resolve(true);
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        self.durum = 'hata'; self.hata = 'Bu tarayıcı mikrofona erişim vermiyor. Chrome ya da Edge kullanın.';
        return Promise.resolve(false);
      }
      return navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false } })
        .then(function (akis) {
          var AC = window.AudioContext || window.webkitAudioContext;
          self.ctx = new AC();
          var kaynak = self.ctx.createMediaStreamSource(akis);
          self.analiz = self.ctx.createAnalyser();
          self.analiz.fftSize = 2048;
          self.tampon = new Float32Array(self.analiz.fftSize);
          kaynak.connect(self.analiz);
          if (self.ctx.state === 'suspended') self.ctx.resume();
          self.durum = 'acik'; self.hata = '';
          return true;
        })
        .catch(function (e) {
          self.durum = 'hata';
          self.hata = (e && e.name === 'NotAllowedError')
            ? 'Mikrofon izni verilmedi. Adres çubuğundaki kilit simgesinden mikrofona izin verip sayfayı yenileyin.'
            : 'Mikrofona ulaşılamadı. Başka bir uygulamanın mikrofonu kullanmadığından emin olun.';
          return false;
        });
    },
    db: function () {
      this.analiz.getFloatTimeDomainData(this.tampon);
      var t = 0;
      for (var i = 0; i < this.tampon.length; i++) t += this.tampon[i] * this.tampon[i];
      return 20 * Math.log10(Math.max(Math.sqrt(t / this.tampon.length), 1e-6));
    },
    yuzde: function (db) {
      var alt = this.zemin !== null ? this.zemin : -70;
      var ust = this.tavan !== null ? this.tavan : -10;
      return Math.max(0, Math.min(100, (db - alt) / Math.max(ust - alt, 1) * 100));
    },
    /* Bir ölçüm aşaması: 3-2-1 geri sayımı, ardından o.saniye boyunca ölçüm.
       o.etiket: ölçüm sırasında ekrandaki büyük yazı. o.alt: alt satır (isteğe bağlı).
       o.sure: true ise çubuk kalan süreyi, değilse ses düzeyini gösterir.
       o.bekle: true ise ölçüm bitince ekran kalır; yürütücü tıklayınca (→ / PageDown / Enter / Boşluk) devam eder.
       Esc her an iptal eder. */
    olc: function (o) {
      var self = this;
      var perde = document.createElement('div');
      perde.className = 'perde';
      perde.innerHTML = '<div class="say"></div><div class="alt"></div><div class="cubuk"><i></i></div>';
      sahne.appendChild(perde);
      var say = perde.querySelector('.say'), alt = perde.querySelector('.alt'), cub = perde.querySelector('.cubuk'), bar = cub.querySelector('i');
      var zamanlayicilar = [];
      function temizle() { zamanlayicilar.forEach(function (z) { clearInterval(z); }); zamanlayicilar = []; self.aktif = null; perde.remove(); }
      return new Promise(function (coz, reddet) {
        self.aktif = { devam: null, iptal: function () { temizle(); reddet('iptal'); } };
        cub.style.visibility = 'hidden'; bar.style.width = '0%';
        alt.textContent = o.alt || '';
        var adim = 3; say.textContent = adim;
        var geri = setInterval(function () {
          adim--;
          if (adim > 0) { say.textContent = adim; return; }
          clearInterval(geri);
          say.textContent = o.etiket; cub.style.visibility = '';
          var ornek = [], bas = performance.now(), sure = o.saniye * 1000;
          var iv = setInterval(function () {
            var db = self.db(); ornek.push(db);
            var gecen = performance.now() - bas;
            bar.style.width = (o.sure ? Math.min(100, gecen / sure * 100) : self.yuzde(db)) + '%';
            if (o.alt) alt.textContent = o.alt + ' · ' + (Math.max(0, sure - gecen) / 1000).toFixed(1) + ' sn';
            if (gecen >= sure) {
              clearInterval(iv);
              bar.style.width = o.sure ? '100%' : bar.style.width;
              var sonuc = ornek.reduce(function (a, b) { return a + b; }, 0) / Math.max(ornek.length, 1);
              if (o.bekle) {
                perde.setAttribute('data-e', 'olcDevam');
                self.aktif.devam = function () { temizle(); coz(sonuc); };
              } else { temizle(); coz(sonuc); }
            }
          }, 50);
          zamanlayicilar.push(iv);
        }, 1000);
        zamanlayicilar.push(geri);
      });
    }
  };
  // Kalibrasyon düğmesi yalnızca film ekranında; diğer ekranlarda yalnızca durum gösterilir.
  function mikPanel(kalibreDugmesi) {
    var nokta = Alkis.durum === 'acik' ? 'acik' : (Alkis.durum === 'hata' ? 'hata' : '');
    var yazi = Alkis.durum === 'acik'
      ? (Alkis.tavan !== null ? 'Mikrofon hazır · kalibre edildi' : 'Mikrofon açık · kalibre edilmedi')
      : (Alkis.durum === 'hata' ? 'Mikrofon kullanılamıyor' : 'Mikrofon kapalı');
    if (!kalibreDugmesi && Alkis.tavan === null && Alkis.durum !== 'hata') yazi += ' (kalibrasyon film ekranında yapılır)';
    return '<div class="mik-panel"><span class="mik-nokta ' + nokta + '"></span><span>' + yazi + '</span>' +
      (kalibreDugmesi ? '<button class="btn kucuk" data-e="kalibre">' + (Alkis.tavan !== null ? 'Yeniden kalibre et' : 'Mikrofonu aç ve kalibre et') + '</button>' : '') + '</div>' +
      (Alkis.hata ? '<div class="uyari-metni" style="margin-top:8px">' + esc(Alkis.hata) + '</div>' : '');
  }
  function bitir() { Alkis.mesgul = false; ciz(); }
  function kalibre() {
    if (Alkis.mesgul) return;
    Alkis.mesgul = true;                       // izin penceresi açıkken ikinci tıklamayı engeller
    Alkis.ac().then(function (tamam) {
      if (!tamam) { bitir(); return; }
      var zemin;
      Alkis.olc({ etiket: 'Sessiz olun', saniye: 3, sure: true, bekle: true })
        .then(function (z) {
          zemin = z;
          return Alkis.olc({ etiket: 'Alkışlayın!', saniye: 3, sure: true });
        })
        .then(function (t) {
          Alkis.zemin = zemin; Alkis.tavan = t;   // yalnızca iki ölçüm de tamamlanınca değiştir
          d.filmPuan = [null, null, null];        // eski ölçekle alınmış puanlar geçersiz
          Alkis.hata = (t - zemin < 6) ? 'Deneme alkışı sessizlikten yeterince ayrışmadı. Mikrofonu kontrol edip yeniden kalibre edin.' : '';
          bitir();
        }, bitir);
    });
  }
  function olcVeKaydet(baslik, kaydet) {
    if (Alkis.mesgul) return;
    Alkis.mesgul = true;
    Alkis.ac().then(function (tamam) {
      if (!tamam) { bitir(); return; }
      Alkis.olc({ etiket: 'Alkış!', alt: baslik, saniye: 3 }).then(function (db) {
        kaydet(Math.round(Alkis.yuzde(db)));
        bitir();
      }, bitir);
    });
  }

  /* ───────────── Gezinme ───────────── */
  function ekranAdi() { return EKRANLAR[d.ekran]; }
  function git(n) {
    d.ekran = Math.max(0, Math.min(EKRANLAR.length - 1, n));
    ciz();
    if (ekranAdi() === 'yaz') kahramanUret();
  }
  function ileriMumkun() {
    var e = ekranAdi();
    if (e === 'film') return argmax(d.filmPuan) !== null;
    if (e === 'uygulama') return cevap(d.adim) !== null;
    if (e === 'yaz') return false;
    return e !== 'kapanis';
  }
  function ileri() {
    if (Alkis.mesgul) return;
    var e = ekranAdi();
    if (e === 'giris2' && d.satir < V.hikaye_satirlari.length) { d.satir++; return ciz(); }
    if (e === 'giris3' && d.vs < 11) { d.vs++; return ciz(); }
    if (e === 'film') { var w = argmax(d.filmPuan); if (w === null) return; d.film = w; d.asama = 0; return git(d.ekran + 1); }
    if (e === 'anlatim' && d.asama < 11) { d.asama++; return ciz(); }
    if (e === 'uygulama') { if (cevap(d.adim) === null) return; if (d.adim < 5) { d.adim++; d.uyari = ''; return ciz(); } }
    if (e === 'kahraman' && d.kasama < 1) { d.kasama = 1; return ciz(); }
    if (!ileriMumkun() && e !== 'uygulama') return;
    git(d.ekran + 1);
  }
  function geri() {
    if (Alkis.mesgul) return;
    var e = ekranAdi();
    if (e === 'giris2' && d.satir > 0) { d.satir--; return ciz(); }
    if (e === 'giris3' && d.vs > 0) { d.vs--; return ciz(); }
    if (e === 'anlatim' && d.asama > 0) { d.asama--; return ciz(); }
    if (e === 'uygulama' && d.adim > 0) { d.adim--; d.uyari = ''; return ciz(); }
    if (e === 'kahraman') { if (d.kasama > 0 && d.kahraman && d.kahraman.kaynak === 'yz') { d.kasama = 0; return ciz(); } return git(d.ekran - 2); }
    git(d.ekran - 1);
  }
  function ileriYazisi() {
    var e = ekranAdi();
    if (e === 'giris2' && d.satir < V.hikaye_satirlari.length) return 'Sonraki cümle ›';
    if ((e === 'giris3' && d.vs < 11) || (e === 'anlatim' && d.asama < 11)) return 'Sonraki aşama ›';
    if (e === 'uygulama') return d.adim < 5 ? 'Sonraki adım ›' : 'Kahramanı yazdır ›';
    if (e === 'kahraman' && d.kasama < 1) return 'Kahraman kartı ›';
    return 'İleri ›';
  }

  /* ───────────── Kahraman ───────────── */
  function secimler() { return d.cevap.map(function (c) { return c || ''; }); }
  // Yapay zekâ kullanılamazsa: hikâye yerine salonun cevapları gösterilir, bölümü salon söyler.
  function yedekKahraman() {
    return { kaynak: 'liste', ad: 'Bizim kahramanımız', tanim: 'Salonun cevaplarından', hikaye: '', soz: 'Hikâyenin geri kalanını o yazacak.',
      bolum: '', gerekce: '', beklenti: '', gerceklik: '' };
  }
  /* ───────────── Yapay zekâ (anahtar bu bilgisayarda) ───────────── */
  var YZ = {
    anahtar: '', model: 'gpt-4o-mini', hatirla: false, durum: 'yok', mesaj: '', panel: false, sonHata: '',
    yukle: function () {
      try {
        var a = localStorage.getItem('anlatir_yz_anahtar');
        if (a) { this.anahtar = a; this.hatirla = true; }
        else { a = sessionStorage.getItem('anlatir_yz_anahtar'); if (a) this.anahtar = a; }
        this.model = localStorage.getItem('anlatir_yz_model') || this.model;
      } catch (e) {}
      this.durum = this.anahtar ? 'kayitli' : 'yok';
    },
    kaydet: function (anahtar, model, hatirla) {
      this.anahtar = anahtar.trim(); this.model = (model || 'gpt-4o-mini').trim(); this.hatirla = !!hatirla;
      try {
        localStorage.removeItem('anlatir_yz_anahtar'); sessionStorage.removeItem('anlatir_yz_anahtar');
        if (this.anahtar) (this.hatirla ? localStorage : sessionStorage).setItem('anlatir_yz_anahtar', this.anahtar);
        localStorage.setItem('anlatir_yz_model', this.model);
      } catch (e) {}
      this.durum = this.anahtar ? 'kayitli' : 'yok';
    },
    sil: function () {
      this.anahtar = ''; this.durum = 'yok'; this.mesaj = '';
      try { localStorage.removeItem('anlatir_yz_anahtar'); sessionStorage.removeItem('anlatir_yz_anahtar'); } catch (e) {}
    },
    istek: function (mesajlar, jsonMu, maks) {
      var self = this, kontrol = new AbortController(), zaman = setTimeout(function () { kontrol.abort(); }, 40000);
      var govde = { model: self.model, messages: mesajlar, max_tokens: maks || 700 };
      if (jsonMu) { govde.response_format = { type: 'json_object' }; govde.temperature = 0.5; }
      return fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST', signal: kontrol.signal,
        headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + self.anahtar },
        body: JSON.stringify(govde)
      }).then(function (r) {
        clearTimeout(zaman);
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (r.ok) return j;
          throw { durum: r.status, kod: (j && j.error && (j.error.code || j.error.type)) || '', ileti: (j && j.error && j.error.message) || '' };
        });
      }, function (e) {
        clearTimeout(zaman);
        throw { durum: 0, kod: e && e.name === 'AbortError' ? 'zaman_asimi' : 'ag', ileti: '' };
      });
    },
    hataMetni: function (h) {
      if (!h) return 'Bilinmeyen hata.';
      if (h.kod === 'zaman_asimi') return 'OpenAI 40 saniye içinde yanıt vermedi.';
      if (h.durum === 0) return 'OpenAI\'a ulaşılamadı. İnternet bağlantısını ya da okul ağının engelini kontrol edin.';
      if (h.durum === 401) return 'Anahtar geçersiz ya da silinmiş. OpenAI panelinden yeni bir anahtar alın.';
      if (h.kod === 'insufficient_quota') return 'Hesapta kredi yok ya da harcama limiti doldu. platform.openai.com › Billing.';
      if (h.durum === 429) return 'Çok fazla istek gönderildi. Biraz bekleyip yeniden deneyin.';
      if (h.durum === 404 || h.kod === 'model_not_found') return '"' + this.model + '" modeli bu hesapta bulunamadı. Model adını değiştirin.';
      if (h.durum === 403) return 'Bu anahtarın bu modele ya da bölgeye erişim izni yok.';
      return 'OpenAI hata döndürdü (' + h.durum + '). ' + (h.ileti || '');
    },
    test: function () {
      var self = this;
      if (!self.anahtar) { self.durum = 'yok'; self.mesaj = 'Önce anahtarı girin.'; return Promise.resolve(); }
      self.durum = 'deneniyor'; self.mesaj = 'Deneniyor…';
      return self.istek([{ role: 'user', content: 'Yalnızca "tamam" yaz.' }], false, 5)
        .then(function () { self.durum = 'hazir'; self.mesaj = 'Bağlantı çalışıyor. Model: ' + self.model; },
              function (h) { self.durum = 'hata'; self.mesaj = self.hataMetni(h); });
    }
  };
  YZ.yukle();

  // Bölüm seçiminde sıra etkisini kırmak için liste her istekte karıştırılır; son seçilen bölümler bir kez dışarıda bırakılır.
  var sonBolumler = [];
  function bolumListesi() {
    var l = V.ihu_bolumleri.filter(function (b) { return sonBolumler.indexOf(b) === -1; });
    for (var i = l.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = l[i]; l[i] = l[j]; l[j] = t; }
    return l;
  }
  function istemKur(P) {
    var satirlar = V.adimlar.map(function (x, i) { return '- ' + x.soru + ' ' + P[i]; }).join('\n');
    return [
      { role: 'system', content: 'Lise öğrencilerine (14-18 yaş) yönelik bir üniversite tanıtım atölyesinde kısa kahraman hikâyeleri yazan bir anlatıcısın. ' +
        'Kurallar: Sade, sıcak, yaşa uygun bir Türkçe kullan ve kısa cümleler kur. Şiddet, ölüm, kan, silah, korku öğesi, romantik ilişki, cinsellik, alkol, sigara, ' +
        'uyuşturucu, kumar, siyaset, din tartışması, gerçek kişi, ünlü ya da marka adı kullanma. Hakaret, alay ve kalıp yargı kullanma. ' +
        'Salonun cevaplarından biri uygunsuz, saldırgan ya da anlamsızsa onu yok say ve yerine uygun, genel bir öğe koy. ' +
        'Kahraman zorlanır ve yaşadıklarından bir şey öğrenir.' },
      { role: 'user', content: 'Salonun cevapları:\n' + satirlar +
        "\n\nBu cevaplardan Vogler'in kahramanın yolculuğunun 12 aşamasını eksiksiz izleyen bir kahraman hikâyesi yaz. İlk beş aşamayı salonun cevapları üzerine kur; sonraki aşamaları sen tamamla. " +
        'Büyük sınav bir kavga değil, kahramanın en büyük korkusuyla yüzleşmesi olsun. Kahraman sonunda evine değişmiş olarak dönsün ve öğrendiğini başkalarıyla paylaşsın.\n' +
        'Yanıtı yalnızca şu alanları içeren bir JSON nesnesi olarak ver:\n' +
        '"ad": Türkçe bir ad,\n"tanim": en çok 10 kelimelik tanım,\n' +
        '"hikaye": 12 cümlelik akıcı bir hikâye (toplam 150-180 kelime). Her cümle sırasıyla şu aşamalardan birini anlatsın: ' + V.vogler.map(function (v, i) { return (i + 1) + '. ' + v[0]; }).join(', ') + '. ' +
        'Cümleler birbirine geçiş sözcükleriyle bağlansın; metinde aşama adı, numara ya da başlık olmasın, düz bir hikâye gibi okunsun.\n' +
        '"soz": kahramanın tek cümlelik sözü (en çok 12 kelime),\n' +
        '"bolum": kahramanın üniversitede okuyacağı bölüm; YALNIZCA şu listeden biri, yazıldığı gibi: ' + bolumListesi().join(', ') + '. ' +
        'Bölümü kahramanın dünyasına, özelliğine ve onu yola çıkaran çağrıya göre seç; korku ya da akıl hocası gibi her hikâyede bulunan öğelere göre seçme. Listede önce gelen bölümü tercih etme.\n' +
        '"gerekce": tek cümle, neden bu bölüm,\n' +
        '"beklenti": tek cümle; kahramanın bu bölümü okumaya başlarken kafasında kurduğu tablo,\n' +
        '"gerceklik": tek cümle; bölümde karşılaştığı ve bu tabloyu bozan durum.\n\n' +
        'Beklenti ve gerçeklik için kurallar: İkisi de üçüncü şahısla, kahramanı anlatan bir dille yazılsın; "ben", "-acağım", "-iyorum" gibi birinci şahıs kullanma. ' +
        'İkisi de seçilen bölüme özgü olsun ve o bölümün gerçek bir ders, yöntem, ödev ya da çalışma biçimini adıyla ansın; başka bir bölüme de uyabilecek genel cümle kurma. ' +
        'Gerçeklik, beklentiyi doğrudan çürütsün: beklentide sandığı şeyin bölümde nasıl başka çıktığını göstersin. ' +
        'Somut ve gözleme dayalı yaz; duygu bildiren genel ifadeler ve öğüt cümleleri kullanma. Gerçeklik kahramanı küçümsemesin; bir keşif gibi okunsun.' }
    ];
  }

  function bolumBul(b) {
    var sade = function (x) { return String(x).toLocaleLowerCase('tr-TR').replace(/â/g, 'a').replace(/\s+/g, ' ').trim(); };
    for (var i = 0; i < V.ihu_bolumleri.length; i++) if (sade(V.ihu_bolumleri[i]) === sade(b)) return V.ihu_bolumleri[i];
    return null;
  }
  function kahramanUret() {
    var P = secimler(), basla = Date.now(), bitti = false;
    d.kahraman = null; d.kasama = 1; YZ.sonHata = '';
    function goster(k) {
      if (bitti) return; bitti = true;
      d.kahraman = k; d.kasama = k.kaynak === 'yz' ? 0 : 1;
      var bekle = Math.max(0, 3500 - (Date.now() - basla));
      setTimeout(function () { if (ekranAdi() === 'yaz') git(d.ekran + 1); }, bekle);
    }
    if (!YZ.anahtar) { YZ.sonHata = 'Yapay zekâ bağlı değil.'; goster(yedekKahraman()); return; }
    var SINIR = { ad: 40, tanim: 120, hikaye: 1300, soz: 140, bolum: 80, gerekce: 220, beklenti: 220, gerceklik: 220 };
    var KALIP = /(^|[^a-zçğıöşü])(ben|benim|bana)([^a-zçğıöşü]|$)|(acağım|eceğim|ıyorum|iyorum|uyorum|üyorum)([^a-zçğıöşü]|$)|zor zaman|zorluk|umu[dt]|her zaman|hayalleri/;
    var deneme = 0;
    function iste() {
    YZ.istek(istemKur(P), true, 1500).then(function (j) {
      var k = null;
      try { k = JSON.parse(j.choices[0].message.content); } catch (e) {}
      var alanlar = Object.keys(SINIR), sorun = '';
      if (!k || typeof k !== 'object') sorun = 'Yanıt beklenen biçimde değildi.';
      else alanlar.forEach(function (a) {
        if (sorun) return;
        var v = k[a] === undefined || k[a] === null ? '' : String(k[a]).trim();
        if (!v) sorun = 'Yanıtta "' + a + '" alanı eksikti.';
        else if (v.length > SINIR[a]) sorun = 'Yanıt fazla uzundu.';
        else if (uygunsuzMu(v)) sorun = 'Yanıt içerik denetiminden geçmedi.';
      });
      if (!sorun && !bolumBul(k.bolum)) sorun = 'Önerilen bölüm listede yoktu.';
      if (!sorun && sonBolumler.indexOf(bolumBul(k.bolum)) > -1) sorun = 'Aynı bölüm art arda seçildi.';
      if (!sorun && [k.beklenti, k.gerceklik].some(function (x) {
        x = String(x).toLocaleLowerCase('tr-TR');
        return KALIP.test(x) || x.split(/\s+/).length < 8;     // kalıp ifade ya da fazla kısa, genel cümle
      })) sorun = 'Beklenti/gerçeklik cümleleri kurallara uymadı.';
      if (sorun && deneme === 0) { deneme++; return iste(); }   // bir kez daha yazdır
      if (sorun) { YZ.sonHata = sorun; return goster(yedekKahraman()); }
      var sonuc = { kaynak: 'yz' };
      alanlar.forEach(function (a) { sonuc[a] = String(k[a]).trim(); });
      sonuc.bolum = bolumBul(k.bolum);
      sonBolumler = [sonuc.bolum].concat(sonBolumler).slice(0, 2);
      goster(sonuc);
    }, function (h) { YZ.sonHata = YZ.hataMetni(h); goster(yedekKahraman()); });
    }
    iste();
  }

  function yzPaneli() {
    if (!YZ.panel) return '';
    var renk = YZ.durum === 'hazir' ? 'var(--iyi)' : (YZ.durum === 'hata' ? 'var(--uyari)' : 'var(--ikincil)');
    return '<div class="perde" style="background:rgba(12,10,8,.88)"><form class="yz-form" id="yzForm" autocomplete="off">' +
      '<div class="satir ara"><h2 class="h2" style="font-size:36px">Yapay zekâ bağlantısı</h2><button type="button" class="yuv" data-e="yzKapat" aria-label="Kapat">×</button></div>' +
      '<p style="margin:0;font-size:17px;line-height:1.5;color:var(--ikincil)">Anahtar yalnızca bu bilgisayarın tarayıcısında durur; siteye yüklenmez. Kahraman üretilirken tarayıcı doğrudan OpenAI\'a bağlanır.</p>' +
      '<label for="yzAnahtar" style="font-size:16px;color:var(--metin2)">OpenAI API anahtarı</label>' +
      '<input id="yzAnahtar" type="password" spellcheck="false" placeholder="sk-…" value="' + esc(YZ.anahtar) + '">' +
      '<label for="yzModel" style="font-size:16px;color:var(--metin2)">Model</label>' +
      '<input id="yzModel" type="text" spellcheck="false" value="' + esc(YZ.model) + '">' +
      '<label class="satir" style="font-size:17px;gap:10px"><input id="yzHatirla" type="checkbox"' + (YZ.hatirla ? ' checked' : '') + ' style="width:22px;height:22px;accent-color:#E8A33D"> Bu bilgisayarda hatırla <span class="soluk">(ortak bilgisayarlarda işaretlemeyin)</span></label>' +
      '<div style="min-height:26px;font-size:17px;color:' + renk + '" role="status">' + esc(YZ.mesaj) + '</div>' +
      '<div class="satir ara"><button type="button" class="btn kucuk" data-e="yzSil">Anahtarı sil</button>' +
      '<span class="satir"><button type="button" class="btn kucuk cizgili" data-e="yzTest">Kaydet ve bağlantıyı test et</button>' +
      '<button type="button" class="btn kucuk ana" data-e="yzKaydet">Kaydet</button></span></div></form></div>';
  }
  function yzFormOku() {
    var a = document.getElementById('yzAnahtar'), m = document.getElementById('yzModel'), h = document.getElementById('yzHatirla');
    if (a) YZ.kaydet(a.value, m.value, h.checked);
  }

  /* ───────────── Çizim ───────────── */
  function nokta(deg, r, cx, cy) { var a = deg * Math.PI / 180; return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; }

  function ustCubuk() {
    var b = BOLUM_NO[d.ekran], sn = Math.floor((Date.now() - d.baslangic) / 1000), bol = V.bolumler[b];
    var cip = V.bolumler.map(function (x, i) { return '<span class="cip ' + (i === b ? 'simdi' : (i < b ? 'gecti' : '')) + '">' + esc(x[0]) + '</span>'; }).join('');
    return '<div class="u-cubuk"><div class="u-sol"><a class="u-logo" href="../atolyeler.php">anlatır<span>.</span></a><div class="cipler">' + cip + '</div></div>' +
      '<div class="u-sag"><span class="plan">Plan ' + bol[1] + ':00–' + bol[2] + ':00</span>' +
      '<span class="saat' + (sn > bol[2] * 60 ? ' gecikti' : '') + '" id="saat">' + Math.floor(sn / 60) + ':' + iki(sn % 60) + '</span>' +
      '<button class="yuv" data-e="geri" aria-label="Geri">‹</button>' +
      (ekranAdi() !== 'kapanis' && ekranAdi() !== 'yaz' ? '<button class="btn kucuk ana" data-e="ileri"' + (ileriMumkun() || ekranAdi() === 'giris2' ? '' : ' disabled') + '>' + ileriYazisi() + '</button>' : '') +
      '<button class="btn kucuk" data-e="yzAc" title="Yapay zekâ bağlantısı" style="gap:8px"><span class="mik-nokta ' + (YZ.durum === 'hazir' ? 'acik' : (YZ.durum === 'hata' ? 'hata' : '')) + '"></span>YZ</button>' +
      '<button class="yuv" data-e="tamekran" aria-label="Tam ekran" title="Tam ekran (F)">⛶</button></div></div>';
  }

  var CIZ = {
    giris1: function () {
      return '<div class="sutun buyu"><div class="sutun buyu" style="justify-content:center;gap:28px">' +
        '<div class="etk" style="font-size:20px;letter-spacing:4px">Monomit: tek hikâye</div>' +
        '<h1 class="h-dev">Kahramanın<br><span class="vurgu">Tercihi</span></h1>' +
        '<p class="yatik" style="margin:0;font-size:34px;color:var(--metin2)">Önce size bir hikâye anlatacağım.</p></div>' +
        '<div class="satir ara"><div class="soluk" style="font-size:18px">İbn Haldun Üniversitesi · TercihFest' + (YZ.anahtar ? '' : ' · <button class="btn kucuk" data-e="yzAc" style="height:34px;padding:0 14px;font-size:15px">Yapay zekâyı bağla</button>') + '</div>' +
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
      var w = argmax(d.filmPuan), kartlar = '';
      V.filmler.forEach(function (f, i) {
        var p = d.filmPuan[i], kaz = i === w;
        kartlar += '<div class="film-kart' + (kaz ? ' kazanan' : '') + '"><div class="satir ara" style="align-items:flex-start"><span class="roma">' + ['I', 'II', 'III'][i] + '</span>' + (kaz ? '<span class="rozet">En gür alkış</span>' : '') + '</div>' +
          '<div class="sutun buyu" style="gap:8px"><div style="font-family:var(--baslik);font-size:34px;font-weight:600">' + esc(f.ad) + '</div><div class="soluk" style="font-size:19px;color:var(--ikincil)">' + esc(f.etiket) + '</div></div>' +
          '<div class="sutun" style="gap:10px"><div style="font-size:18px;color:var(--ikincil)">' + (p === null ? 'Alkış ölçülmedi' : 'Alkış · ' + p) + '</div><div class="cubuk"><i style="width:' + (p || 0) + '%"></i></div></div>' +
          '<div class="satir"><button class="btn kucuk buyu" data-e="filmOlc" data-i="' + i + '" style="justify-content:center">Alkışı ölç</button>' +
          '<button class="btn kucuk cizgili buyu" data-e="filmSec" data-i="' + i + '" style="justify-content:center">Bu filmle başla</button></div></div>';
      });
      return '<div class="sutun buyu" style="gap:26px"><div class="sutun" style="gap:10px"><h1 class="h1">Hangi kahramanın yolculuğunu izleyelim?</h1>' +
        '<p style="margin:0;font-size:22px;color:var(--ikincil)">"Alkışı ölç": 3 saniye alkış dinlenir, en gür alkış kazanır. İsterseniz ölçmeden doğrudan bir film seçin.</p></div>' +
        '<div class="film-izgara">' + kartlar + '</div><div class="satir ara"><div>' + mikPanel(true) + '</div>' +
        '<span class="satir"><button class="btn" data-e="filmSifirla">Puanları sıfırla</button>' +
        (w !== null ? '<button class="btn ana" data-e="ileri">' + esc(V.filmler[w].kisa) + ' ile başla</button>' : '') + '</span></div></div>';
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
      var sonraki = cevap(i0) === null ? '' : '<button class="btn ana" data-e="ileri">' + (i0 < 5 ? 'Sonraki adım ›' : 'Kahramanı yazdır ›') + '</button>';
      return '<div class="satir buyu" style="gap:56px;align-items:stretch"><div class="yan"><div class="etk">Bizim kahramanımız</div>' + yan + '</div>' +
        '<div class="sutun buyu" style="gap:30px"><div class="satir ara"><div style="font-size:18px;color:var(--ikincil)">Adım ' + (i0 + 1) + ' / 6 · Vogler: ' + esc(adim.asama) + '</div><div class="satir" style="gap:8px">' + seg + '</div></div>' +
        '<h1 class="h1" style="font-size:72px">' + esc(adim.soru) + '</h1>' +
        '<div class="sutun" style="gap:10px"><input id="cevapGir" class="cevap-gir" type="text" maxlength="60" autocomplete="off" spellcheck="false" placeholder="Salonun cevabını yazın, Enter\'a basın" value="' + esc(cevap(i0) || '') + '">' +
        '<div style="min-height:24px;font-size:17px;color:var(--uyari)" role="status">' + esc(d.uyari) + '</div></div>' +
        '<div class="sutun" style="gap:12px"><div style="font-size:16px;color:var(--soluk)">Salon sessiz kalırsa:</div><div class="satir" style="gap:10px;flex-wrap:wrap">' + oneriler + '</div></div>' +
        '<div class="satir ara" style="margin-top:auto"><div style="font-size:16px;color:var(--soluk)">Enter: kaydet ve ilerle · ↑: önceki adım</div>' + sonraki + '</div></div></div>';
    },
    yaz: function () {
      var P = secimler(), cip = P.map(function (c) { return '<span style="padding:10px 18px;border:1px solid var(--cizgi);border-radius:999px;font-size:18px;color:var(--metin2)">' + esc(c) + '</span>'; }).join('');
      return '<div class="sutun buyu" style="align-items:center;justify-content:center;gap:36px">' +
        '<svg width="280" height="280" viewBox="0 0 320 320" aria-hidden="true"><circle cx="160" cy="160" r="130" fill="none" stroke="#3A3228" stroke-width="2"/><line x1="10" y1="160" x2="310" y2="160" stroke="#5A4E3E" stroke-width="2" stroke-dasharray="8 6"/>' +
        '<path d="M160 30 A130 130 0 0 1 290 160 A130 130 0 0 1 252 252" fill="none" stroke="#E8A33D" stroke-width="4" stroke-linecap="round"><animate attributeName="stroke-dasharray" values="0 900;600 900" dur="3.5s" fill="freeze"/></path></svg>' +
        '<div class="sutun" style="align-items:center;gap:14px"><h1 class="h1" style="font-size:62px">Kahramanımız yazılıyor…</h1><p class="yatik soluk" style="margin:0;font-size:28px;color:var(--ikincil)">Eşiği geçti. Şimdi sınanıyor.</p></div>' +
        '<div class="satir" style="flex-wrap:wrap;justify-content:center;max-width:1100px">' + cip + '</div></div>';
    },
    kahraman: function () {
      var k = d.kahraman || yedekKahraman(), P = secimler(), yz = k.kaynak === 'yz';
      if (yz && d.kasama < 1) return kahramanHikaye(k);
      var govde = yz
        ? ''
        : '<div class="sutun" style="gap:10px">' + V.adimlar.map(function (x, i) { return '<div style="font-size:23px;line-height:1.35"><span style="color:var(--ikincil)">' + esc(x.etiket) + ':</span> ' + esc(P[i]) + '</div>'; }).join('') + '</div>';
      var sag = yz
        ? '<div class="k-bolum"><div class="vurgu" style="font-size:16px;letter-spacing:2px">HANGİ BÖLÜMÜ OKURDU?</div><div style="font-family:var(--baslik);font-size:32px;font-weight:600;line-height:1.1">' + esc(k.bolum) + '</div><div style="font-size:19px;line-height:1.45;color:var(--metin2)">' + esc(k.gerekce) + '</div></div>' +
          '<div class="sutun" style="gap:14px;padding:26px 30px;border:1px solid var(--cizgi);border-radius:24px">' +
          '<div><div style="font-size:15px;letter-spacing:2px;color:var(--ikincil)">BEKLENTİSİ</div><div style="font-size:20px;line-height:1.4;margin-top:4px">' + esc(k.beklenti) + '</div></div>' +
          '<div><div class="vurgu" style="font-size:15px;letter-spacing:2px">GERÇEKLİK</div><div style="font-size:20px;line-height:1.4;margin-top:4px">' + esc(k.gerceklik) + '</div></div></div>'
        : '<div class="k-bolum"><div class="vurgu" style="font-size:16px;letter-spacing:2px">HANGİ BÖLÜMÜ OKURDU?</div><div style="font-family:var(--baslik);font-size:36px;font-weight:600;line-height:1.1">Siz söyleyin.</div></div>';
      return '<div class="sutun buyu" style="gap:22px"><div class="satir ara"><div class="etk">Kahramanımız hazır</div><div class="satir" style="gap:12px"><span class="soluk" style="font-size:15px">' + (yz ? 'Yapay zekâ yazdı' : esc(YZ.sonHata || 'Yapay zekâ kullanılamadı')) + '</span>' +
        '<button class="btn kucuk" data-e="yenidenYaz" title="Yeniden yaz">↻</button></div></div>' +
        '<div class="satir buyu" style="gap:40px;align-items:stretch"><div class="k-kart"><div class="sutun" style="gap:8px"><h1 class="h1 vurgu" style="font-size:72px;line-height:1">' + esc(k.ad) + '</h1><div style="font-size:21px;color:var(--ikincil)">' + esc(k.tanim) + '</div></div>' +
        govde +
        '<div class="yatik" style="margin-top:auto;padding-top:20px;border-top:1px solid var(--cizgi);font-size:28px">“' + esc(k.soz) + '”</div></div>' +
        '<div class="k-yan">' + sag + '</div></div></div>';
    },
    kapanis: function () {
      var k = d.kahraman || yedekKahraman();
      return '<div class="satir buyu" style="gap:56px"><svg width="600" height="600" viewBox="0 0 620 620" role="img" aria-label="Tercih süreci dairede">' +
        '<circle cx="310" cy="310" r="200" fill="none" stroke="#3A3228" stroke-width="2"/><line x1="80" y1="310" x2="540" y2="310" stroke="#5A4E3E" stroke-width="2" stroke-dasharray="8 6"/>' +
        '<circle cx="310" cy="110" r="9" fill="#8C7A5E"/><text x="310" y="84" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="19" fill="#D9CFBF">İlgi alanın</text>' +
        '<circle cx="410" cy="137" r="9" fill="#8C7A5E"/><text x="426" y="130" font-family="Instrument Sans, sans-serif" font-size="19" fill="#D9CFBF">Sınav sonucun</text>' +
        '<circle cx="474" cy="195" r="9" fill="#8C7A5E"/><text x="490" y="192" font-family="Instrument Sans, sans-serif" font-size="19" fill="#D9CFBF">"Ya yanlış seçersem?"</text>' +
        '<circle cx="503" cy="258" r="14" fill="#E8A33D"/><text x="524" y="252" font-family="Instrument Sans, sans-serif" font-size="20" font-weight="600" fill="#E8A33D">TercihFest</text><text x="524" y="274" font-family="Instrument Sans, sans-serif" font-size="16" fill="#B5AA98">buradasın</text>' +
        '<circle cx="510" cy="310" r="11" fill="none" stroke="#F4EEE3" stroke-width="3"/><text x="530" y="340" font-family="Instrument Sans, sans-serif" font-size="19" fill="#F4EEE3">Tercih listen</text>' +
        '<circle cx="451" cy="451" r="9" fill="#15120E" stroke="#6B5F4E" stroke-width="2"/><text x="468" y="470" font-family="Instrument Sans, sans-serif" font-size="19" fill="#B5AA98">Üniversite yılları</text>' +
        '<circle cx="110" cy="310" r="9" fill="#15120E" stroke="#6B5F4E" stroke-width="2"/><text x="96" y="345" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="19" fill="#B5AA98">Dönüş</text></svg>' +
        '<div class="sutun buyu" style="gap:24px"><div style="font-size:21px;color:var(--metin2)">' + (k.bolum ? esc(k.ad) + ' <span class="vurgu">' + esc(k.bolum) + '</span> okurdu. ' : '') + 'Sizin beklentiniz ne?</div>' +
        '<h1 class="h1" style="font-size:76px;line-height:1">Sıradaki kahraman: <span class="vurgu">Sen.</span></h1>' +
        '<p style="margin:0;font-size:25px;line-height:1.45;color:var(--metin2)">Bugün akıl hocanla karşılaştığın gün. Burada soracağın her soru, eşikteki kararını güçlendirir.</p>' +
        '<div style="padding:22px 26px;border:1px solid var(--cizgi);border-radius:20px;font-family:var(--baslik);font-size:28px">Akıl hocası yol gösterir; eşiği kahraman geçer.</div>' +
        '<span class="satir"><button class="btn" data-e="bastan">Atölyeyi baştan başlat</button><a class="btn" href="cikis.php">Çıkış</a></span></div></div>';
    }
  };

  // Kahramanın hikâyesi: 12 aşamayı izleyen akıcı metin, tek ekranda
  function kahramanHikaye(k) {
    return '<div class="sutun buyu" style="gap:26px;max-width:1180px;margin:0 auto;justify-content:center">' +
      '<div class="satir ara"><div class="etk">Kahramanımızın hikâyesi</div><button class="btn kucuk" data-e="yenidenYaz" title="Yeniden yaz">↻</button></div>' +
      '<h1 class="h1 vurgu" style="font-size:68px;line-height:1">' + esc(k.ad) + '</h1>' +
      '<p style="margin:0;font-size:' + (k.hikaye.length > 1050 ? 24 : (k.hikaye.length > 900 ? 26 : 28)) + 'px;line-height:1.55">' + esc(k.hikaye) + '</p></div>';
  }
  function cevapKaydet(metin) {
    var t = String(metin || '').replace(/\s+/g, ' ').trim();
    if (!t) { d.uyari = 'Önce bir cevap yazın.'; return ciz(); }
    if (uygunsuzMu(t)) { d.uyari = 'Bu cevap kullanılamaz. Başka bir cevap yazın.'; return ciz(); }
    d.cevap[d.adim] = t.charAt(0).toLocaleUpperCase('tr-TR') + t.slice(1); d.uyari = '';
    ileri();
  }
  function ciz() {
    sahne.innerHTML = ustCubuk() + '<div class="icerik">' + CIZ[ekranAdi()]() + '</div>' + yzPaneli();
    var gir = document.getElementById('cevapGir');
    if (gir && !YZ.panel) { gir.focus(); gir.setSelectionRange(gir.value.length, gir.value.length); }
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
    if (e === 'olcDevam') { if (Alkis.aktif && Alkis.aktif.devam) Alkis.aktif.devam(); return; }
    if (e === 'olcIptal') { if (Alkis.aktif) Alkis.aktif.iptal(); return; }
    if (Alkis.mesgul && e !== 'tamekran') return;
    switch (e) {
      case 'ileri': ileri(); break;
      case 'geri': geri(); break;
      case 'git': git(i); break;
      case 'vs': d.vs = i; ciz(); break;
      case 'asama': d.asama = i; ciz(); break;
      case 'kalibre': kalibre(); break;
      case 'filmOlc': olcVeKaydet(V.filmler[i].kisa, function (p) { d.filmPuan[i] = p; }); break;
      case 'filmSec': d.film = i; d.asama = 0; git(4); break;
      case 'filmSifirla': d.filmPuan = [null, null, null]; ciz(); break;
      case 'oneri': var g = document.getElementById('cevapGir'); if (g) { g.value = V.adimlar[d.adim].secenekler[i]; g.focus(); } break;
      case 'yenidenYaz': git(EKRANLAR.indexOf('yaz')); break;
      case 'bastan':
        d.ekran = 0; d.satir = 0; d.vs = 0; d.filmPuan = [null, null, null]; d.film = null; d.asama = 0;
        d.adim = 0; d.cevap = [null, null, null, null, null, null]; d.uyari = ''; d.kahraman = null; d.kasama = 1; d.baslangic = Date.now(); ciz(); break;
      case 'yzAc': YZ.panel = true; ciz(); var alan = document.getElementById('yzAnahtar'); if (alan) alan.focus(); break;
      case 'yzKapat': YZ.panel = false; ciz(); break;
      case 'yzKaydet': yzFormOku(); YZ.mesaj = YZ.anahtar ? 'Kaydedildi.' : 'Anahtar boş.'; YZ.panel = false; ciz(); break;
      case 'yzSil': YZ.sil(); ciz(); break;
      case 'yzTest': yzFormOku(); ciz(); YZ.test().then(ciz); break;
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
    if (YZ.panel) { if (ev.key === 'Escape') { YZ.panel = false; ciz(); } return; }
    if (Alkis.mesgul) {                         // ölçüm sürerken kumanda ekranı değiştiremez
      ev.preventDefault();
      if (!Alkis.aktif) return;
      if (ev.key === 'Escape') Alkis.aktif.iptal();
      else if (Alkis.aktif.devam && /^(ArrowRight|PageDown|Enter| )$/.test(ev.key)) Alkis.aktif.devam();
      return;
    }
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
