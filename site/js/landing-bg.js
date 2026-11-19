// site/js/landing-bg.js — as linhas do fundo (finora_backdrop) crescem à medida que se desce a página,
// como o gasto do mês a acumular no gráfico do painel. Cada seta acompanha a ponta da sua linha.
// A dourada é o total; as mais pequenas são categorias. Cada uma diz em data-bd-from quanto já
// aparece no topo da página e em data-bd-size o tamanho da seta.
// Com "reduzir movimento" (ou sem JavaScript) as linhas aparecem inteiras.
(function () {
  var series = [];
  document.querySelectorAll('.ln-backdrop .ln-bd-series').forEach(function (g) {
    var line = g.querySelector('.ln-bd-line'), tip = g.querySelector('.ln-bd-tip');
    if (!line || !tip || !line.getTotalLength) return;
    series.push({
      line: line, tip: tip, len: line.getTotalLength(),
      from: parseFloat(g.getAttribute('data-bd-from')) || 0,
      size: parseFloat(g.getAttribute('data-bd-size')) || 1
    });
  });
  if (!series.length) return;
  var calm = window.matchMedia('(prefers-reduced-motion: reduce)');
  var queued = false;

  function draw() {
    queued = false;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    var progress = calm.matches || max <= 0 ? 1 : Math.min(1, window.scrollY / max);
    series.forEach(function (s) {
      var frac = s.from + (1 - s.from) * progress;
      s.line.style.strokeDasharray = s.len + ' ' + s.len;
      s.line.style.strokeDashoffset = String(s.len * (1 - frac));
      // Seta na ponta, rodada na direção da linha
      var at = s.len * frac, p = s.line.getPointAtLength(at), q = s.line.getPointAtLength(Math.max(0, at - 4));
      var deg = Math.atan2(p.y - q.y, p.x - q.x) * 180 / Math.PI;
      s.tip.setAttribute('transform', 'translate(' + p.x + ' ' + p.y + ') rotate(' + deg + ') scale(' + s.size + ')');
    });
  }
  function queue() { if (!queued) { queued = true; requestAnimationFrame(draw); } }

  addEventListener('scroll', queue, { passive: true });
  addEventListener('resize', queue);
  if (calm.addEventListener) calm.addEventListener('change', queue);
  draw();
})();
