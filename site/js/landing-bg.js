// site/js/landing-bg.js — a linha dourada do fundo (finora_backdrop) cresce à medida que se desce a página,
// como o gasto do mês a acumular no gráfico do painel. A seta acompanha a ponta da linha.
// Com "reduzir movimento" (ou sem JavaScript) a linha aparece inteira.
(function () {
  var line = document.getElementById('lnBdLine'), arrow = document.getElementById('lnBdArrow');
  if (!line || !arrow || !line.getTotalLength) return;
  var len = line.getTotalLength();
  var calm = window.matchMedia('(prefers-reduced-motion: reduce)');
  var queued = false;

  function draw() {
    queued = false;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    var progress = calm.matches || max <= 0 ? 1 : Math.min(1, window.scrollY / max);
    var frac = 0.3 + 0.7 * progress;              // começa já com um bocado desenhado
    line.style.strokeDasharray = len + ' ' + len;
    line.style.strokeDashoffset = String(len * (1 - frac));
    // Seta na ponta, rodada na direção da linha
    var at = len * frac, p = line.getPointAtLength(at), q = line.getPointAtLength(Math.max(0, at - 4));
    var deg = Math.atan2(p.y - q.y, p.x - q.x) * 180 / Math.PI;
    arrow.setAttribute('transform', 'translate(' + p.x + ' ' + p.y + ') rotate(' + deg + ')');
  }
  function queue() { if (!queued) { queued = true; requestAnimationFrame(draw); } }

  addEventListener('scroll', queue, { passive: true });
  addEventListener('resize', queue);
  if (calm.addEventListener) calm.addEventListener('change', queue);
  draw();
})();
