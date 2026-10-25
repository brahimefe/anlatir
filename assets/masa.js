/* Anlatır — Haber Masası etkileşimleri */
(function () {
  "use strict";

  var STATUS = {
    destekleniyor: { label: "Kaynakta var", cls: "ok" },
    kismen: { label: "Bağlam eksik", cls: "warn" },
    carpitilmis: { label: "Çarpıtılmış", cls: "bad" },
    kaynak_yok: { label: "Kaynakta yok", cls: "none" }
  };
  var ORDER = ["destekleniyor", "kismen", "carpitilmis", "kaynak_yok"];
  var DRAFT_KEY = "wd-taslak";

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function chip(cls, text) { return el("span", "chip " + cls, text); }
  function store(key, value) { try { sessionStorage.setItem(key, JSON.stringify(value)); } catch (e) {} }
  function take(key) {
    try { var v = sessionStorage.getItem(key); sessionStorage.removeItem(key); return v ? JSON.parse(v) : null; }
    catch (e) { return null; }
  }

  var page = document.querySelector(".tool-page");
  if (!page) return;
  var csrf = page.getAttribute("data-csrf");
  var result = page.querySelector(".result");

  function submitButtonsDisabled(form, on) {
    form.querySelectorAll("button").forEach(function (b) { b.disabled = on; });
  }

  page.querySelectorAll("form[data-islem]").forEach(function (form) {
    form.addEventListener("submit", async function (ev) {
      ev.preventDefault();
      var islem = form.getAttribute("data-islem");
      var st = form.querySelector(".status");
      var fd = new FormData(form);
      fd.append("islem", islem);
      fd.append("csrf", csrf);
      submitButtonsDisabled(form, true);
      st.classList.remove("err");
      var started = Date.now();
      st.textContent = islem === "dokum" ? "Kayıt yazıya dökülüyor…" : "Hazırlanıyor…";
      var tick = setInterval(function () {
        st.textContent = (islem === "dokum" ? "Kayıt yazıya dökülüyor… " : "Hazırlanıyor… ") + Math.round((Date.now() - started) / 1000) + " sn";
      }, 1000);
      try {
        var r = await fetch("api.php", { method: "POST", body: fd, credentials: "same-origin" });
        var j = null;
        try { j = await r.json(); } catch (e) {}
        if (!j) throw new Error("Sunucu beklenmeyen bir yanıt verdi. Tekrar deneyin.");
        if (!j.ok) throw new Error(j.message || "Bir sorun oluştu. Tekrar deneyin.");
        st.textContent = "";
        handle(islem, j.data, form);
      } catch (e) {
        st.textContent = e && e.message ? e.message : "Sunucuya ulaşılamadı. Bağlantınızı kontrol edin.";
        st.classList.add("err");
      } finally {
        clearInterval(tick);
        submitButtonsDisabled(form, false);
      }
    });
  });

  function handle(islem, data, form) {
    if (islem === "karsilastir") renderChecks(data, "soru", form);
    else if (islem === "denetle") renderChecks(data, "aciklama", null);
    else if (islem === "dokum") fillTranscript(data);
  }

  /* ---------- Karşılaştırma ve denetim ---------- */
  function renderChecks(data, extraKey, form) {
    result.replaceChildren();
    if (data.ozet) result.appendChild(el("p", "verdict", data.ozet));
    var counts = {};
    var list = el("ol", "checks");
    (data.cumleler || []).forEach(function (c) {
      var key = STATUS[c.durum] ? c.durum : "kaynak_yok";
      counts[key] = (counts[key] || 0) + 1;
      var li = el("li");
      var head = el("div"); head.appendChild(chip(STATUS[key].cls, STATUS[key].label));
      li.appendChild(head);
      li.appendChild(el("p", "stmt", c.metin));
      if (c.kaynak) {
        var p = el("p", "src", "Kaynak: “" + c.kaynak + "”");
        if (!c.dogrulandi) { p.appendChild(document.createTextNode(" ")); p.appendChild(chip("warn", "Alıntı kaynakta birebir bulunamadı")); }
        li.appendChild(p);
      }
      if (c[extraKey]) {
        var why = el("p", "why");
        if (extraKey === "soru") why.appendChild(el("strong", null, "Soru: "));
        why.appendChild(document.createTextNode(c[extraKey]));
        li.appendChild(why);
      }
      list.appendChild(li);
    });
    var sum = el("div", "summary");
    ORDER.forEach(function (k) { if (counts[k]) sum.appendChild(chip(STATUS[k].cls, counts[k] + " · " + STATUS[k].label)); });
    result.appendChild(sum);
    result.appendChild(list);
    if (form) {
      var actions = el("div", "row");
      var send = el("button", "btn btn-primary", "Haberimi düzeltip gönder");
      send.type = "button";
      send.addEventListener("click", function () {
        store(DRAFT_KEY, { metin: form.querySelector("[name=taslak]").value });
        location.href = "gonder.php";
      });
      actions.appendChild(send);
      result.appendChild(actions);
    }
  }

  /* ---------- Ses kaydı ---------- */
  function fillTranscript(data) {
    result.replaceChildren();
    var ta = el("textarea");
    ta.rows = 14;
    ta.setAttribute("aria-label", "Döküm");
    ta.value = (data.satirlar || []).map(function (s) {
      return (s.zaman ? "[" + s.zaman + "] " : "") + s.metin;
    }).join("\n");
    result.appendChild(el("p", "help", "Yanlış duyulan kelimeleri düzeltebilirsiniz. Haberinizi bu dökümden kendiniz yazın; alıntıların kaynağına kaydın dakikasını ekleyin (ör. \u201cMuhtarla röportaj, ses kaydı 01:05\u201d)."));
    result.appendChild(ta);
    var actions = el("div", "row");
    var copy = el("button", "btn", "Dökümü kopyala");
    copy.type = "button";
    copy.addEventListener("click", async function () {
      try { await navigator.clipboard.writeText(ta.value); copy.textContent = "Kopyalandı"; }
      catch (e) { ta.select(); copy.textContent = "Seçildi, kopyalayın"; }
      setTimeout(function () { copy.textContent = "Dökümü kopyala"; }, 1800);
    });
    var go = el("a", "btn btn-primary", "Haberini yazmaya geç");
    go.href = "gonder.php";
    go.target = "_blank";
    actions.appendChild(copy);
    actions.appendChild(go);
    result.appendChild(actions);
  }
})();
