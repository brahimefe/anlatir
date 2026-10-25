/* Anlatır — canlı ders: telefon, sunucu ekranı ve projeksiyon */
(function () {
  "use strict";
  var root = document.getElementById("canli");
  if (!root) return;
  var MODE = root.getAttribute("data-mode");
  var CSRF = root.getAttribute("data-csrf");
  var S = null;
  var first = true;

  function $(id) { return document.getElementById(id); }
  function show(id, on) { var e = typeof id === "string" ? $(id) : id; if (e) e.classList.toggle("hidden", !on); }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function setText(id, t) { var e = $(id); if (e && e.textContent !== t) e.textContent = t; }

  /* ---------- Sunucu ile konuşma ---------- */
  async function act(a, data) {
    var fd = data instanceof FormData ? data : new FormData();
    fd.append("a", a);
    fd.append("csrf", CSRF);
    if (data && !(data instanceof FormData)) {
      Object.keys(data).forEach(function (k) {
        var v = data[k];
        if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + "[]", x); });
        else fd.append(k, v);
      });
    }
    var r = await fetch("ajax.php", { method: "POST", body: fd, credentials: "same-origin" });
    var j = null;
    try { j = await r.json(); } catch (e) {}
    if (!j || !j.ok) throw new Error((j && j.message) || "İşlem yapılamadı.");
    setTimeout(pollNow, 50);
    return j;
  }
  function uyar(e) { window.alert(e && e.message ? e.message : "İşlem yapılamadı."); }

  var timer = null;
  async function poll() {
    clearTimeout(timer);
    try {
      var r = await fetch("ajax.php?a=durum" + (first ? "&ilk=1" : ""), { credentials: "same-origin", cache: "no-store" });
      if (r.status === 401) { location.href = MODE === "telefon" ? "katil.php" : "giris.php?rol=egitmen"; return; }
      var j = await r.json();
      if (!j.ok) throw new Error(j.message);
      first = false;
      show("baglanti", false);
      S = j;
      render(j);
    } catch (e) {
      show("baglanti", true);
    }
    timer = setTimeout(poll, document.hidden ? 6000 : (MODE === "telefon" ? 2000 : 1200));
  }
  function pollNow() { poll(); }
  document.addEventListener("visibilitychange", function () { if (!document.hidden) pollNow(); });

  /* ---------- PDF ---------- */
  var pdfjs = window.pdfjsLib;
  if (pdfjs) pdfjs.GlobalWorkerOptions.workerSrc = "assets/lib/pdf.worker.min.js";
  var pdfDoc = null, pdfKey = "";
  function pdfLoad(hafta, mtime) {
    var key = hafta + ":" + mtime;
    if (key !== pdfKey || !pdfDoc) {
      pdfKey = key;
      pdfDoc = pdfjs.getDocument({ url: "sunum.php?h=" + hafta + "&v=" + mtime, disableRange: true, disableStream: true, withCredentials: true }).promise;
      pdfDoc.catch(function () { pdfKey = ""; });
    }
    return pdfDoc;
  }
  async function renderPage(canvas, doc, n, maxW, maxH) {
    var key = pdfKey + ":" + n + ":" + Math.round(maxW) + ":" + Math.round(maxH || 0);
    if (canvas.getAttribute("data-key") === key) return;
    canvas.setAttribute("data-key", key);
    var page = await doc.getPage(n);
    var base = page.getViewport({ scale: 1 });
    var scale = Math.min(maxW / base.width, maxH ? maxH / base.height : Infinity);
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var vp = page.getViewport({ scale: scale * dpr });
    if (canvas._task) { try { canvas._task.cancel(); } catch (e) {} }
    var off = document.createElement("canvas");
    off.width = Math.floor(vp.width);
    off.height = Math.floor(vp.height);
    canvas._task = page.render({ canvasContext: off.getContext("2d"), viewport: vp });
    try { await canvas._task.promise; } catch (e) { return; }
    if (canvas.getAttribute("data-key") !== key) return;
    canvas.width = off.width;
    canvas.height = off.height;
    canvas.style.width = Math.floor(vp.width / dpr) + "px";
    canvas.style.height = Math.floor(vp.height / dpr) + "px";
    canvas.getContext("2d").drawImage(off, 0, 0);
  }
  function canvasIn(box) {
    var c = box.querySelector("canvas");
    if (!c) { box.textContent = ""; c = document.createElement("canvas"); box.appendChild(c); }
    return c;
  }
  function placeholder(box, text) {
    if (box.querySelector("canvas") || box.textContent !== text) { box.textContent = ""; box.appendChild(el("p", "placeholder", text)); }
  }
  window.addEventListener("resize", function () {
    document.querySelectorAll("canvas[data-key]").forEach(function (c) { c.removeAttribute("data-key"); });
    if (S) render(S);
  });

  /* ---------- Ortak parçalar ---------- */
  function bars(box, secenekler, sayilar, vurgu) {
    box.textContent = "";
    var top = (sayilar || []).reduce(function (a, b) { return a + b; }, 0);
    secenekler.forEach(function (s, i) {
      var n = (sayilar || [])[i] || 0;
      var pct = top ? Math.round((n / top) * 100) : 0;
      var row = el("div", "opt-row");
      var lab = el("div", null, s + (vurgu === i ? "  ← senin cevabın" : ""));
      var bar = el("div", "bar");
      var fill = el("span"); fill.style.width = pct + "%";
      bar.appendChild(fill);
      bar.appendChild(el("em", null, pct + "% · " + n));
      row.appendChild(lab); row.appendChild(bar);
      box.appendChild(row);
    });
  }
  function radios(box, name, items) {
    box.textContent = "";
    items.forEach(function (it) {
      var lab = el("label", "opt");
      var inp = el("input"); inp.type = "radio"; inp.name = name; inp.value = it.value; inp.required = true;
      if (it.disabled) { inp.disabled = true; lab.style.opacity = ".55"; }
      lab.appendChild(inp);
      var t = el("span", null, it.text);
      if (it.sub) { t.appendChild(el("br")); t.appendChild(el("span", "help", it.sub)); }
      lab.appendChild(t);
      box.appendChild(lab);
    });
  }

  /* ================= TELEFON ================= */
  var soruId = null, oyKey = null;
  function renderTelefon(s) {
    show("katilim-uyari", s.aktif && s.katildim === false);
    var ders = s.aktif;
    setText("t-rumuz", s.rumuz || "");
    setText("t-hafta", ders ? "Canlı · Hafta " + s.hafta : (s.bitti ? "Ders bitti" : "Bekleniyor"));
    setText("t-sayfa", ders && s.sunum && s.sayfa_sayisi ? s.sayfa + " / " + s.sayfa_sayisi : "");
    show("v-bekle", !ders && !s.bitti);
    show("v-ders", ders);
    show("v-bitti", !ders && !!s.bitti);
    if (ders) {
      var box = $("slide");
      if (!s.sunum) placeholder(box, "Bu derste sunum yok. Eğitmeni takip et.");
      else if (pdfjs) {
        pdfLoad(s.hafta, s.sunum).then(function (doc) {
          var n = Math.min(s.sayfa, doc.numPages);
          renderPage(canvasIn(box), doc, n, box.clientWidth || 340, 0);
        }).catch(function () { placeholder(box, "Sunum açılamadı. Sayfayı yenile."); });
      }
    }
    soruTelefon(s);
    oyTelefon(s);
    if (s.bitti) bittiTelefon(s.bitti);
  }

  function soruTelefon(s) {
    var q = s.soru;
    show("v-soru", !!q && s.aktif);
    if (!q) { soruId = null; return; }
    if (q.id !== soruId) {
      soruId = q.id;
      setText("soru-metin", q.metin);
      radios($("soru-secenek"), "secim", q.secenekler.map(function (t, i) { return { value: i, text: t }; }));
    }
    var cevapli = q.benim !== null && q.benim !== undefined;
    show("soru-form", q.acik && !cevapli && !q.sonuc);
    show("soru-tamam", cevapli && !q.sonuc);
    show("soru-sonuc", !!q.sonuc);
    if (q.sonuc) bars($("soru-sonuc"), q.secenekler, q.sayilar, q.benim);
    if (!q.acik && !q.sonuc && !cevapli) { show("soru-tamam", true); $("soru-tamam").textContent = "Soru kapandı."; }
  }

  function oyTelefon(s) {
    var o = s.oylama;
    show("v-oylama", !!o);
    if (!o) { oyKey = null; return; }
    var key = o.hafta + ":" + o.finalistler.map(function (f) { return f.id; }).join(",");
    if (key !== oyKey) {
      oyKey = key;
      radios($("oy-secenek"), "haber", o.finalistler.map(function (f) {
        return { value: f.id, text: f.baslik, sub: f.kendi ? "Senin haberin" : (f.kaynak + " kaynak" + (f.foto ? " · fotoğraflı" : "")), disabled: f.kendi };
      }));
    }
    var acik = o.durum === "acik";
    setText("oy-durum", acik ? "Oylama açık" : "Oylama kapandı");
    show("oy-form", acik && s.giris && !o.benim);
    show("oy-giris", acik && !s.giris);
    show("oy-tamam", acik && !!o.benim);
    var sonuc = $("oy-sonuc");
    if (!acik) {
      var k = o.finalistler.filter(function (f) { return f.id === o.kazanan; })[0];
      sonuc.textContent = k ? "Haftanın haberi: “" + k.baslik + "” · " + k.yazar : "Sonuç birazdan açıklanacak.";
    }
    show(sonuc, !acik);
  }

  function bittiTelefon(b) {
    setText("bitti-hafta", "Hafta " + b.hafta);
    setText("bitti-tesekkur", b.baslik + " dersine katıldığın için teşekkürler.");
    setText("bitti-odev", b.odev + (b.son_teslim ? "  Son teslim: " + b.son_teslim + "." : ""));
    if ($("bitti-gonder")) $("bitti-gonder").href = "gonder.php?hafta=" + b.hafta;
    $("bitti-ders").href = "hafta.php?n=" + b.hafta;
    var son = $("bitti-sonraki");
    son.textContent = "";
    if (b.sonraki) {
      son.appendChild(el("span", "kicker", "Sonraki ders"));
      son.appendChild(el("p", null, "Hafta " + b.sonraki.hafta + " · " + b.sonraki.baslik));
    } else {
      son.appendChild(el("p", null, "Seminer tamamlandı. Haberlerin yayın için değerlendirilecek."));
    }
  }

  if (MODE === "telefon") {
    $("soru-form").addEventListener("submit", function (ev) {
      ev.preventDefault();
      var c = this.querySelector("input:checked");
      if (!c || !S || !S.soru) return;
      var b = this.querySelector("button"); b.disabled = true;
      act("cevap", { soru: S.soru.id, secim: c.value })
        .then(function () { S.soru.benim = Number(c.value); soruTelefon(S); })
        .catch(uyar)
        .finally(function () { b.disabled = false; });
    });
    $("oy-form").addEventListener("submit", function (ev) {
      ev.preventDefault();
      var c = this.querySelector("input:checked");
      if (!c) return;
      var b = this.querySelector("button"); b.disabled = true;
      act("oy", { haber: c.value })
        .then(function () { S.oylama.benim = c.value; oyTelefon(S); })
        .catch(uyar)
        .finally(function () { b.disabled = false; });
    });
  }

  /* ================= SUNUCU EKRANI ================= */
  var HAFTALAR = MODE === "sunucu" ? JSON.parse(root.getAttribute("data-haftalar") || "{}") : {};
  var cur = 1, pendingUntil = 0, notHafta = null, sayfaBildirildi = "";

  function maxSayfa() { return S && S.sayfa_sayisi ? S.sayfa_sayisi : 1; }
  function git(delta) {
    if (!S || !S.aktif || !S.sunum) return;
    var n = Math.max(1, Math.min(maxSayfa(), cur + delta));
    if (n === cur) return;
    cur = n;
    pendingUntil = Date.now() + 2500;
    slaytSunucu(S);
    act("sayfa", { n: n }).catch(function () {});
  }

  function slaytSunucu(s) {
    var box = $("p-slide"), next = $("p-next");
    setText("t-sayfa", s.sunum && s.sayfa_sayisi ? cur + " / " + s.sayfa_sayisi : "–");
    $("geri").disabled = !s.sunum || cur <= 1;
    $("ileri").disabled = !s.sunum || cur >= maxSayfa();
    if (!s.sunum || !pdfjs) {
      placeholder(box, "Bu hafta için sunum yüklenmemiş. Soru ve oylama sunumsuz da çalışır.");
      placeholder(next, "—");
      return;
    }
    pdfLoad(s.hafta, s.sunum).then(function (doc) {
      var key = pdfKey + ":" + doc.numPages;
      if (doc.numPages !== s.sayfa_sayisi && sayfaBildirildi !== key) {
        sayfaBildirildi = key;
        act("sayfa_sayisi", { n: doc.numPages }).catch(function () {});
      }
      renderPage(canvasIn(box), doc, Math.min(cur, doc.numPages), box.clientWidth || 800, window.innerHeight * 0.72);
      if (cur < doc.numPages) renderPage(canvasIn(next), doc, cur + 1, next.clientWidth || 300, 0);
      else placeholder(next, "Son slayt");
    }).catch(function () { placeholder(box, "Sunum açılamadı. PDF dosyasını kontrol edin."); });
  }

  function renderSunucu(s) {
    show("v-baslat", !s.aktif);
    show("v-ders", s.aktif);
    show("bitir-btn", s.aktif);
    setText("t-hafta", s.aktif ? "● Canlı · Hafta " + s.hafta + " · " + s.baslik : (s.bitti ? "Ders bitti · Hafta " + s.bitti.hafta : "Ders başlamadı"));
    setText("t-bagli", (s.katilan || 0) + " öğrenci katıldı · " + (s.misafir || 0) + " misafir");
    if (s.kod) setText("t-kod", s.kod);
    setText("qr-btn", s.qr ? "QR'ı gizle" : "QR'ı göster");
    if (!s.aktif) return;
    if (Date.now() > pendingUntil) cur = s.sayfa;
    slaytSunucu(s);

    if (notHafta !== s.hafta) {
      notHafta = s.hafta;
      var w = HAFTALAR[s.hafta] || { sorular: [], atolye: [] };
      var hs = $("hazir-sorular"); hs.textContent = "";
      w.sorular.forEach(function (q) {
        var b = el("button", "btn btn-sm qs-btn", "↗ " + q[0]);
        b.type = "button";
        b.addEventListener("click", function () { act("soru", { metin: q[0], secenekler: q[1] }).catch(uyar); });
        hs.appendChild(b);
      });
      var nt = $("notlar"); nt.textContent = "";
      w.atolye.forEach(function (t) { nt.appendChild(el("li", null, t)); });
    }

    var q = s.soru;
    show("soru-aktif", !!q);
    show("soru-yeni", !q);
    if (q) {
      setText("soru-metin", q.metin);
      bars($("soru-sonuc"), q.secenekler, q.sayilar, null);
      setText("soru-sayi", q.yanit_sayisi + " cevap" + (q.sonuc ? " · sonuç ekranda" : " · sonuç gizli"));
      $("sonuc-btn").disabled = !!q.sonuc;
    }

    var o = s.oylama, alan = $("oy-alan");
    alan.textContent = "";
    if (!o) {
      alan.appendChild(el("p", "small", "Oylama başlatılmadı."));
    } else {
      var durum = { acik: "Oylama açık · " + o.oy_sayisi + " oy", esitlik: "Eşitlik var: kazananı seçin", kapali: "Oylama kapandı", hazir: "Hazır" }[o.durum] || o.durum;
      alan.appendChild(el("strong", null, durum));
      o.finalistler.forEach(function (f) {
        var n = (o.sayilar || {})[f.id] || 0;
        var row = el("div", "row between small");
        row.appendChild(el("span", null, (o.kazanan === f.id ? "★ " : "") + f.baslik + " · " + f.yazar));
        if (o.durum === "esitlik" && (o.esitlik || []).indexOf(f.id) >= 0) {
          var b = el("button", "btn btn-sm", n + " oy · Kazanan seç");
          b.type = "button";
          b.addEventListener("click", function () { act("kazanan_sec", { haber: f.id }).catch(uyar); });
          row.appendChild(b);
        } else {
          row.appendChild(el("span", "mono", n + " oy"));
        }
        alan.appendChild(row);
      });
    }
    show("oy-baslat", !o);
    show("oy-bitir", !!o && (o.durum === "acik"));
    show("oy-kapat", !!o && (o.durum === "kapali" || o.durum === "esitlik"));

    var bl = $("bagli-liste"), key = JSON.stringify(s.bagli || []);
    if (bl.getAttribute("data-key") !== key) {
      bl.setAttribute("data-key", key);
      bl.textContent = "";
      (s.bagli || []).forEach(function (p) {
        var b = el("button", "btn btn-sm", p.rumuz);
        b.type = "button";
        b.addEventListener("click", function () {
          var yeni = window.prompt("“" + p.rumuz + "” yerine yeni rumuz:", "Katılımcı " + Math.floor(Math.random() * 90 + 10));
          if (yeni) act("rumuz", { pid: p.pid, yeni: yeni }).catch(uyar);
        });
        bl.appendChild(b);
      });
      if (!(s.bagli || []).length) bl.appendChild(el("span", null, "Henüz bağlanan yok."));
    }
  }

  if (MODE === "sunucu") {
    $("baslat-btn").addEventListener("click", function () {
      cur = 1; pendingUntil = 0;
      act("baslat", { hafta: $("hafta-sec").value }).catch(uyar);
    });
    $("bitir-btn").addEventListener("click", function () {
      if (window.confirm("Dersi bitirmek istiyor musunuz? Telefonlarda ödev ekranı açılır.")) act("bitir").catch(uyar);
    });
    $("qr-btn").addEventListener("click", function () { act("qr", { goster: S && S.qr ? "" : "1" }).catch(uyar); });
    $("geri").addEventListener("click", function () { git(-1); });
    $("ileri").addEventListener("click", function () { git(1); });
    document.addEventListener("keydown", function (e) {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target.tagName || ""))) return;
      if (e.key === "ArrowRight" || e.key === "PageDown" || e.key === " ") { e.preventDefault(); git(1); }
      if (e.key === "ArrowLeft" || e.key === "PageUp") { e.preventDefault(); git(-1); }
    });
    $("sonuc-btn").addEventListener("click", function () { act("soru_sonuc").catch(uyar); });
    $("kapat-btn").addEventListener("click", function () { act("soru_kapat").catch(uyar); });
    $("soru-form").addEventListener("submit", function (ev) {
      ev.preventDefault();
      var f = this;
      act("soru", new FormData(f)).then(function () { f.reset(); f.closest("details").open = false; }).catch(uyar);
    });
    $("oy-baslat").addEventListener("click", function () { act("oylama_baslat").catch(uyar); });
    $("oy-bitir").addEventListener("click", function () { act("oylama_bitir").catch(uyar); });
    $("oy-kapat").addEventListener("click", function () { act("oylama_kapat").catch(uyar); });
    setInterval(function () {
      if (!S || !S.aktif || !S.baslangic) { setText("t-sure", ""); return; }
      var t = Math.max(0, Math.floor(Date.now() / 1000) - S.baslangic);
      var p = function (n) { return (n < 10 ? "0" : "") + n; };
      setText("t-sure", p(Math.floor(t / 3600)) + ":" + p(Math.floor(t / 60) % 60) + ":" + p(t % 60));
    }, 1000);
  }

  /* ================= PROJEKSİYON ================= */
  var qrKey = "", ovKeys = {};
  function qrSvg(url) {
    var q = window.qrcode(0, "M");
    q.addData(url);
    q.make();
    return q.createSvgTag({ cellSize: 6, margin: 2, scalable: true, alt: "Derse katılım kodu" });
  }
  function overlay(id, key, build) {
    var box = $(id);
    if (!key) { show(box, false); ovKeys[id] = ""; return; }
    show(box, true);
    if (ovKeys[id] === key) return;
    ovKeys[id] = key;
    box.textContent = "";
    build(box);
  }
  function renderProj(s) {
    if (s.katil_url && qrKey !== s.katil_url) {
      qrKey = s.katil_url;
      var svg = qrSvg(s.katil_url);
      $("qr-buyuk").innerHTML = svg;
      $("qr-kucuk").innerHTML = svg;
      setText("url-buyuk", s.katil_kisa);
      setText("url-kucuk", s.katil_kisa);
      setText("kod-buyuk", "Kod: " + s.kod);
      setText("kod-kucuk", "Kod: " + s.kod);
    }
    var ders = s.aktif, slayt = ders && !!s.sunum;
    show("karsilama", !slayt);
    show("proj-canvas", slayt);
    show("qr-kose", slayt && s.qr);
    ["qr-buyuk", "url-buyuk", "kod-buyuk"].forEach(function (id) { show(id, s.qr); });
    setText("k-h1", ders ? "Hafta " + s.hafta + " · " + s.baslik : (s.qr ? "Derse katıl" : "Ders birazdan başlıyor"));
    setText("t-sayfa", slayt && s.sayfa_sayisi ? s.sayfa + " / " + s.sayfa_sayisi : "");
    if (slayt && pdfjs) {
      pdfLoad(s.hafta, s.sunum).then(function (doc) {
        renderPage($("proj-canvas"), doc, Math.min(s.sayfa, doc.numPages), window.innerWidth, window.innerHeight);
      }).catch(function () {});
    }

    var q = ders ? s.soru : null;
    overlay("ov-soru", q ? JSON.stringify([q.id, q.sonuc, q.sonuc ? q.sayilar : q.yanit_sayisi]) : "", function (box) {
      box.appendChild(el("span", "kicker", q.sonuc ? "Sonuçlar" : "Telefonundan cevapla · cevaplar anonim"));
      box.appendChild(el("h1", null, q.metin));
      var list = el("div", "stack");
      if (q.sonuc) bars(list, q.secenekler, q.sayilar, null);
      else q.secenekler.forEach(function (t, i) { list.appendChild(el("div", null, String.fromCharCode(65 + i) + ") " + t)); });
      list.style.fontSize = "3.6vh";
      box.appendChild(list);
      if (!q.sonuc) box.appendChild(el("p", "mono", q.yanit_sayisi + " cevap")).style.fontSize = "3vh";
    });

    var o = s.oylama;
    overlay("ov-oylama", o ? JSON.stringify([o.durum, o.oy_sayisi, o.kazanan, o.finalistler.length]) : "", function (box) {
      box.appendChild(el("span", "kicker", "Haftanın haberi · Hafta " + o.hafta));
      if (o.durum === "kapali" && o.kazanan) {
        var k = o.finalistler.filter(function (f) { return f.id === o.kazanan; })[0] || {};
        box.appendChild(el("h1", null, "★ " + (k.baslik || "")));
        box.appendChild(el("p", null, k.yazar || "")).style.fontSize = "4vh";
        return;
      }
      box.appendChild(el("h1", null, o.durum === "acik" ? "Telefonundan oy ver" : "Sonuç birazdan"));
      var list = el("div", "stack");
      list.style.fontSize = "3.6vh";
      o.finalistler.forEach(function (f, i) { list.appendChild(el("div", null, (i + 1) + ". " + f.baslik + " · " + f.yazar)); });
      box.appendChild(list);
      box.appendChild(el("p", "mono", o.oy_sayisi + " oy")).style.fontSize = "3vh";
    });

    var b = !ders ? s.bitti : null;
    overlay("ov-bitti", b ? JSON.stringify(b) : "", function (box) {
      box.appendChild(el("span", "kicker", "Hafta " + b.hafta + " · " + b.baslik));
      box.appendChild(el("h1", null, "Ders bitti. Teşekkürler!"));
      box.appendChild(el("p", null, "Ödev: " + b.odev)).style.fontSize = "3.4vh";
      if (b.sonraki) box.appendChild(el("p", "muted", "Sonraki ders: Hafta " + b.sonraki.hafta + " · " + b.sonraki.baslik)).style.fontSize = "3vh";
    });
  }

  function render(s) {
    if (MODE === "telefon") renderTelefon(s);
    else if (MODE === "sunucu") renderSunucu(s);
    else renderProj(s);
  }
  poll();
})();
