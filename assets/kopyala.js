/* data-kopyala düğmeleri: değeri panoya kopyalar */
document.querySelectorAll("[data-kopyala]").forEach(function (b) {
  b.addEventListener("click", function () {
    var t = b.getAttribute("data-kopyala"), eski = b.textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () {
      b.textContent = "Kopyalandı";
      setTimeout(function () { b.textContent = eski; }, 1500);
    }, function () { window.prompt("Bağlantı:", t); });
  });
});
