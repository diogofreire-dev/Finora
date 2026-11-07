/*!
 * Finora Interactive Tutorial System
 * Guides users through each page with highlighted steps and tooltips.
 */
(function () {
  'use strict';

  /* ─── Per-page step definitions ──────────────────────────────── */
  const PAGE_TUTORIALS = {

    dashboard: [
      {
        selector: '#appMenuBtn',
        title: 'Menu',
        text: 'Abre o menu aqui para navegar entre todas as secções: Transações, Cartões, Orçamentos, Metas, Análise e muito mais.',
        position: 'bottom'
      },
      {
        selector: '.app-header-actions',
        title: 'Ações rápidas',
        text: 'Regista uma nova transação ou lê o QR de uma fatura a partir de qualquer página. O teu nome dá acesso às definições e a terminar sessão.',
        position: 'bottom'
      },
      {
        selector: '#appNotifBtn',
        title: 'Notificações',
        text: 'Pagamentos a vencer, orçamentos quase no fim, metas atrasadas e mais. Escolhes o que queres receber nas Definições.',
        position: 'bottom'
      },
      {
        selector: '.dsh-hero',
        title: 'O teu mês',
        text: 'Quanto já gastaste este mês e a comparação com o mesmo ponto do mês passado. A linha dourada é este mês; a tracejada é o anterior.',
        position: 'bottom'
      },
      {
        selector: '#dshCategorias',
        title: 'Para onde foi o dinheiro',
        text: 'A divisão dos gastos do mês por categoria. Ao lado tens os cartões, o orçamento e os próximos pagamentos.',
        position: 'top'
      }
    ],

    cards: [
      {
        selector: '.cd-summary',
        title: 'Os teus cartões',
        text: 'Quanto ainda tens disponível no total, somando os limites de todos os cartões ativos.',
        position: 'bottom'
      },
      {
        selector: '.cd-head .btn-s-primary',
        title: 'Adicionar cartão',
        text: 'Adiciona um cartão novo: escolhe o banco, a cor e o limite.',
        position: 'bottom'
      },
      {
        selector: '.cd-tile',
        title: 'Cada cartão',
        text: 'Vês o disponível, a utilização do limite e o número de transações. "Ver transações" abre a lista já filtrada por este cartão.',
        position: 'right'
      }
    ],

    transactions: [
      {
        selector: '.tx-month',
        title: 'Mês',
        text: 'Usa as setas para mudar de mês. Por baixo vês quantas transações fizeste, o total e a média.',
        position: 'bottom'
      },
      {
        selector: '.tx-filters',
        title: 'Pesquisa e filtros',
        text: 'Pesquisa pela descrição, escolhe um cartão ou toca numa categoria para veres só esses gastos.',
        position: 'bottom'
      },
      {
        selector: '.tx-day',
        title: 'Transações por dia',
        text: 'As transações aparecem agrupadas por dia, com o total de cada dia. Usa o lápis para editar ou o caixote para apagar.',
        position: 'top'
      },
      {
        selector: '.app-header-btn-primary',
        title: 'Nova transação',
        text: 'Este botão está sempre no cabeçalho, em qualquer página.',
        position: 'bottom'
      }
    ],

    budgets: [
      {
        selector: '.bg-head .btn-s-primary',
        title: 'Criar orçamento',
        text: 'Define quanto queres gastar por mês, semana ou ano — no total, numa categoria ou num cartão.',
        position: 'bottom'
      },
      {
        selector: '.bg-tile',
        title: 'Quanto ainda podes gastar',
        text: 'Em destaque vês quanto te resta. O traço na barra mostra onde devias estar hoje: se a barra já passou o traço, estás a gastar mais depressa do que o previsto.',
        position: 'right'
      },
      {
        selector: '.bg-hint',
        title: 'Por dia',
        text: 'Quanto podes gastar por dia até ao fim do período, para não passares o orçamento.',
        position: 'top'
      }
    ],

    reminders: [
      {
        selector: '.lm-head .btn-s-primary',
        title: 'Novo lembrete',
        text: 'Regista um pagamento (renda, luz, subscrições…) e a Finora avisa-te por email antes de vencer.',
        position: 'bottom'
      },
      {
        selector: '#calendario',
        title: 'Calendário',
        text: 'O mês inteiro, com os pagamentos que se repetem já nos dias certos. Os dias mais carregados ficam mais dourados. Toca num dia para o veres em detalhe ou criar um lembrete nessa data.',
        position: 'bottom'
      },
      {
        selector: '.lm-group',
        title: 'Próximos pagamentos',
        text: 'Os pagamentos aparecem por ordem de data: em atraso, nos próximos 7 dias e mais tarde.',
        position: 'top'
      },
      {
        selector: '.lm-pay',
        title: 'Marcar como pago',
        text: 'Quando pagares, marca aqui. Se for recorrente, passa automaticamente para a próxima data e podes criar logo a transação.',
        position: 'left'
      }
    ],

    analytics: [
      {
        selector: '.an-controls',
        title: 'Ano e cartão',
        text: 'Usa as setas para mudar de ano e escolhe um cartão para veres só os gastos dele.',
        position: 'bottom'
      },
      {
        selector: '.an-hero',
        title: 'O teu ano',
        text: 'Quanto gastaste e a comparação com o mesmo período do ano anterior — no ano atual só se comparam os meses que já passaram.',
        position: 'bottom'
      },
      {
        selector: '#monthChart',
        title: 'Mês a mês',
        text: 'Cada mês deste ano ao lado do mesmo mês do ano de comparação. Passa o rato por cima para ver os valores.',
        position: 'top'
      }
    ],

    investments: [
      {
        selector: '.iv-head .btn-s-primary',
        title: 'Adicionar investimento',
        text: 'Regista uma posição com a quantidade e o preço médio de compra: ações, ETF, cripto, PPR, depósitos…',
        position: 'bottom'
      },
      {
        selector: '.iv-hero',
        title: 'A tua carteira',
        text: 'O valor atual de tudo, quanto ganhaste ou perdeste desde a compra e a distribuição por tipo.',
        position: 'bottom'
      },
      {
        selector: '.js-price',
        title: 'Atualizar preço',
        text: 'Os preços são introduzidos por ti. Atualiza-os de vez em quando para os valores estarem certos.',
        position: 'left'
      }
    ],

    bolsa: [
      {
        selector: '.bk-search',
        title: 'Pesquisar',
        text: 'Escreve o nome ou o símbolo de uma ação, ETF ou cripto. A pesquisa usa o Yahoo Finance.',
        position: 'bottom'
      },
      {
        selector: '#bkWatchT',
        title: 'A seguir',
        text: 'Os títulos que segues, com o preço em euros, a variação de hoje e o último mês. Os preços atualizam-se a cada 15 minutos.',
        position: 'bottom'
      }
    ],

    savings: [
      {
        selector: '.sv-hero .piggy',
        title: 'O teu mealheiro',
        text: 'Enche com o dinheiro que pões de lado. Fica cheio quando chegas ao próximo marco (100, 250, 500…).',
        position: 'right'
      },
      {
        selector: '.sv-actions',
        title: 'Pôr de lado ou tirar',
        text: 'Regista cada vez que guardas dinheiro, e também quando o usas. Podes ligar cada movimento a uma meta.',
        position: 'bottom'
      },
      {
        selector: '.sv-grid .pg-card',
        title: 'Movimentos',
        text: 'O histórico, mês a mês. Se te enganares, apagas o movimento e o mealheiro acerta-se.',
        position: 'top'
      }
    ],

    futuro: [
      {
        selector: '.gl-saved',
        title: 'Quanto já tens',
        text: 'O mealheiro da meta enche com o que puseste de lado nas Poupanças. A linha tracejada é onde devias estar hoje para chegares a tempo.',
        position: 'bottom'
      },
      {
        selector: '.gl-form',
        title: 'Nova meta',
        text: 'Diz quanto queres juntar, até quando e quanto ganhas por mês. A Finora usa a tua média de gastos para prever o resultado.',
        position: 'left'
      },
      {
        selector: '.gl-future',
        title: 'O teu eu do futuro',
        text: 'Diz-te se, ao ritmo atual, chegas à meta — e quanto precisas de poupar por mês para lá chegar.',
        position: 'top'
      }
    ],

    dados: [
      {
        selector: '#sec-exp',
        title: 'Exportar',
        text: 'Escolhe o mês, a categoria ou o cartão e exporta em PDF ou numa folha de cálculo (CSV).',
        position: 'bottom'
      },
      {
        selector: '#sec-bak',
        title: 'Cópia de segurança',
        text: 'Descarrega um ficheiro com tudo e guarda-o fora da Finora. Se precisares, restauras a partir dele.',
        position: 'bottom'
      },
      {
        selector: '#dropZone',
        title: 'Importar',
        text: 'Traz transações do extrato do banco num ficheiro CSV. Vê o formato em "Como deve ser o ficheiro".',
        position: 'top'
      }
    ],

    ai: [
      {
        selector: '#insTitle',
        title: 'O teu mês',
        text: 'Carrega em "Gerar" e o assistente aponta três coisas que se destacam nos teus gastos deste mês.',
        position: 'bottom'
      },
      {
        selector: '#savTitle',
        title: 'Onde poupar',
        text: 'Sugestões concretas de poupança, com valores tirados das tuas transações.',
        position: 'bottom'
      },
      {
        selector: '#chatTitle',
        title: 'Pergunta',
        text: 'Escreve uma pergunta ou toca numa das sugestões. As respostas usam só os teus dados.',
        position: 'top'
      }
    ],

    persona: [
      {
        selector: '#psName',
        title: 'A tua personalidade',
        text: 'O teu estilo de gastar, calculado a partir das transações dos últimos 90 dias. Precisa de pelo menos 10 transações.',
        position: 'bottom'
      },
      {
        selector: '.ps-why',
        title: 'Porquê',
        text: 'Cada traço tem uma pontuação de 0 a 100 e os números que a justificam, para veres de onde vem a conclusão.',
        position: 'bottom'
      },
      {
        selector: '#tipsTitle',
        title: 'Para ti',
        text: 'Três ideias pensadas para o teu estilo. Volta no próximo mês: a personalidade muda com os hábitos.',
        position: 'top'
      }
    ],

    settings: [
      {
        selector: '.pg-head',
        title: 'Definições',
        text: 'Aqui tratas da tua conta: perfil, acesso e aparência.',
        position: 'bottom'
      },
      {
        selector: '#sec-perfil',
        title: 'Perfil',
        text: 'Muda o teu nome e a foto que aparecem no topo da Finora.',
        position: 'bottom'
      },
      {
        selector: '#sec-seg',
        title: 'Acesso à conta',
        text: 'Muda a palavra-passe ou o email, e liga a tua conta Google. Por segurança, só podes mudar cada um uma vez a cada 30 dias.',
        position: 'bottom'
      },
      {
        selector: '.st-themes',
        title: 'Aparência',
        text: 'Escolhe o tema claro ou escuro. Fica guardado assim que clicas.',
        position: 'top'
      }
    ],

    scan_fatura: [
      {
        selector: '#scTitle',
        title: 'Ler fatura',
        text: 'Liga a câmara e aponta para o código QR da fatura, ou escolhe "Imagem" para usar uma foto.',
        position: 'bottom'
      },
      {
        selector: '#formTitle',
        title: 'Despesa',
        text: 'A Finora preenche o valor, a data e o nome da loja. Confirma, escolhe a categoria e guarda.',
        position: 'bottom'
      }
    ]

  };

  /* ─── Storage helpers ─────────────────────────────────────────── */
  function markSeen(page) {
    try { localStorage.setItem('tut_seen_' + page, '1'); } catch (e) {}
  }
  function hasSeen(page) {
    try { return !!localStorage.getItem('tut_seen_' + page); } catch (e) { return false; }
  }

  /* ─── DOM helpers ─────────────────────────────────────────────── */
  function $(sel) { return document.querySelector(sel); }
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls)  e.className = cls;
    if (html) e.innerHTML = html;
    return e;
  }

  /* ─── Build HTML structures ───────────────────────────────────── */
  function buildUI() {
    // FAB
    var fab = el('button', 'hidden', '<i class="bi bi-question-lg" aria-hidden="true"></i>');
    fab.id = 'tutorial-fab';
    fab.title = 'Visita guiada a esta página';
    fab.setAttribute('aria-label', 'Visita guiada a esta página');
    document.body.appendChild(fab);

    // Prompt card
    var prompt = el('div', '', '');
    prompt.id = 'tutorial-prompt';
    prompt.innerHTML =
      '<div class="tut-prompt-head">' +
        '<div class="tut-prompt-icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></div>' +
        '<h4>Queres uma visita guiada?</h4>' +
      '</div>' +
      '<p>Mostramos-te cada parte desta página em menos de um minuto.</p>' +
      '<div class="tut-prompt-btns">' +
        '<button class="tut-btn-yes" id="tut-yes">Mostrar</button>' +
        '<button class="tut-btn-no"  id="tut-no">Agora não</button>' +
      '</div>';
    document.body.appendChild(prompt);

    // Overlay (4 dim panels)
    var overlay = el('div', '', '');
    overlay.id = 'tutorial-overlay';
    ['top','bottom','left','right'].forEach(function(side) {
      var d = el('div', 'tut-dim');
      d.id = 'tut-dim-' + side;
      overlay.appendChild(d);
    });
    document.body.appendChild(overlay);

    // Spotlight
    var spot = el('div', '');
    spot.id = 'tutorial-spotlight';
    document.body.appendChild(spot);

    // Tooltip
    var tip = el('div', '');
    tip.id = 'tutorial-tooltip';
    tip.setAttribute('role', 'dialog');
    tip.setAttribute('aria-labelledby', 'tut-step-title');
    tip.setAttribute('aria-describedby', 'tut-step-body');
    tip.innerHTML =
      '<div class="tut-top">' +
        '<span class="tut-tooltip-step-badge" id="tut-step-badge"></span>' +
        '<div class="tut-progress" id="tut-dots" aria-hidden="true"></div>' +
      '</div>' +
      '<h3 class="tut-tooltip-title" id="tut-step-title"></h3>' +
      '<div class="tut-tooltip-body" id="tut-step-body"></div>' +
      '<div class="tut-tooltip-footer">' +
        '<button class="tut-nav-btn skip" id="tut-skip">Saltar</button>' +
        '<span class="tut-grow"></span>' +
        '<button class="tut-nav-btn icon" id="tut-prev" aria-label="Passo anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>' +
        '<button class="tut-nav-btn primary" id="tut-next">Seguinte</button>' +
      '</div>';
    document.body.appendChild(tip);

    // Completion toast
    var done = el('div', '');
    done.id = 'tutorial-complete';
    done.setAttribute('role', 'status');
    done.innerHTML = '<div class="tut-check"><i class="bi bi-check-lg" aria-hidden="true"></i></div> Visita concluída. Já sabes onde está tudo.';
    document.body.appendChild(done);
  }

  /* ─── Tutorial Engine ─────────────────────────────────────────── */
  var tutSteps = [];
  var tutIdx   = 0;
  var active   = false;

  function getRect(target) {
    var r = target.getBoundingClientRect();
    var pad = 8;
    return {
      top:    r.top    - pad,
      left:   r.left   - pad,
      bottom: r.bottom + pad,
      right:  r.right  + pad,
      width:  r.width  + pad * 2,
      height: r.height + pad * 2
    };
  }

  function updateDims(rect) {
    var ov = $('#tutorial-overlay');
    var sp = $('#tutorial-spotlight');
    var vw = window.innerWidth;
    var vh = window.innerHeight;

    ov.classList.add('active');

    // Top panel
    var dt = $('#tut-dim-top');
    dt.style.cssText = 'position:fixed;top:0;left:0;right:0;height:' + Math.max(0, rect.top) + 'px;';

    // Bottom panel
    var db = $('#tut-dim-bottom');
    db.style.cssText = 'position:fixed;bottom:0;left:0;right:0;top:' + Math.min(vh, rect.bottom) + 'px;';

    // Left panel
    var dl = $('#tut-dim-left');
    dl.style.cssText = 'position:fixed;top:' + Math.max(0,rect.top) + 'px;left:0;width:' + Math.max(0,rect.left) + 'px;height:' + Math.max(0,rect.bottom - rect.top) + 'px;';

    // Right panel
    var dr = $('#tut-dim-right');
    dr.style.cssText = 'position:fixed;top:' + Math.max(0,rect.top) + 'px;right:0;left:' + Math.min(vw,rect.right) + 'px;height:' + Math.max(0,rect.bottom - rect.top) + 'px;';

    // Spotlight
    sp.style.cssText = 'position:fixed;top:' + rect.top + 'px;left:' + rect.left + 'px;width:' + rect.width + 'px;height:' + rect.height + 'px;';
  }

  function positionTooltip(rect) {
    var tip   = $('#tutorial-tooltip');
    var vw    = window.innerWidth;
    var vh    = window.innerHeight;
    var tipW  = Math.min(330, window.innerWidth - 24);
    var tipH  = tip.offsetHeight || 220;
    var gap   = 18;
    var left, top, arrowClass;

    // Prefer right if space, else left, then bottom, then top
    if (rect.right + gap + tipW < vw) {
      left       = rect.right + gap;
      top        = rect.top + rect.height / 2 - tipH / 2;
      arrowClass = 'arrow-left';
    } else if (rect.left - gap - tipW > 0) {
      left       = rect.left - gap - tipW;
      top        = rect.top + rect.height / 2 - tipH / 2;
      arrowClass = 'arrow-right';
    } else if (rect.bottom + gap + tipH < vh) {
      top        = rect.bottom + gap;
      left       = rect.left + rect.width / 2 - tipW / 2;
      arrowClass = 'arrow-top';
    } else {
      top        = rect.top - gap - tipH;
      left       = rect.left + rect.width / 2 - tipW / 2;
      arrowClass = 'arrow-bottom';
    }

    // Clamp
    left = Math.max(12, Math.min(vw - tipW - 12, left));
    top  = Math.max(12, Math.min(vh - tipH - 12, top));

    tip.className        = 'active ' + arrowClass;
    tip.style.left       = left + 'px';
    tip.style.top        = top  + 'px';
    tip.style.width      = tipW + 'px';
  }

  function renderStep(idx) {
    var step   = tutSteps[idx];
    var target = document.querySelector(step.selector);
    if (!target) { goNext(); return; }  // skip missing elements

    // Scroll into view
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    setTimeout(function() {
      var rect = getRect(target);
      updateDims(rect);

      // Update tooltip content
      $('#tut-step-badge').textContent = 'Passo ' + (idx + 1) + ' de ' + tutSteps.length;
      $('#tut-step-title').textContent = step.title;
      $('#tut-step-body').textContent  = step.text;

      // Progress dots
      var dots = $('#tut-dots');
      dots.innerHTML = '';
      tutSteps.forEach(function(_, i) {
        var d = el('div', 'tut-dot' + (i < idx ? ' done' : i === idx ? ' active' : ''));
        dots.appendChild(d);
      });

      // Prev/Next buttons
      $('#tut-prev').style.display = idx === 0 ? 'none' : '';
      $('#tut-next').textContent   = idx === tutSteps.length - 1 ? 'Concluir' : 'Seguinte';

      positionTooltip(rect);
    }, 300);
  }

  function goNext() {
    if (tutIdx < tutSteps.length - 1) {
      tutIdx++;
      renderStep(tutIdx);
    } else {
      endTutorial(true);
    }
  }

  function goPrev() {
    if (tutIdx > 0) {
      tutIdx--;
      renderStep(tutIdx);
    }
  }

  function startTutorial() {
    if (!tutSteps.length) return;
    active   = true;
    tutIdx   = 0;
    $('#tutorial-prompt').classList.remove('visible');
    renderStep(0);
  }

  function endTutorial(completed) {
    active = false;
    $('#tutorial-overlay').classList.remove('active');
    $('#tutorial-tooltip').classList.remove('active');
    $('#tutorial-tooltip').className = '';
    $('#tutorial-spotlight').style.cssText = '';
    $('#tut-dim-top').style.cssText    = '';
    $('#tut-dim-bottom').style.cssText = '';
    $('#tut-dim-left').style.cssText   = '';
    $('#tut-dim-right').style.cssText  = '';

    if (completed) {
      var toast = $('#tutorial-complete');
      toast.classList.add('show');
      setTimeout(function() { toast.classList.remove('show'); }, 3500);
      markSeen(window._tutPage || '');
    }

    // Re-show FAB with pulse
    var fab = $('#tutorial-fab');
    fab.classList.remove('hidden');
    setTimeout(function() { fab.classList.add('pulse'); }, 300);
    setTimeout(function() { fab.classList.remove('pulse'); }, 4000);
  }

  /* ─── Keyboard handling ───────────────────────────────────────── */
  document.addEventListener('keydown', function(e) {
    if (!active) return;
    if (e.key === 'ArrowRight' || e.key === 'Enter') goNext();
    if (e.key === 'ArrowLeft')  goPrev();
    if (e.key === 'Escape')     endTutorial(false);
  });

  /* ─── Resize handling ─────────────────────────────────────────── */
  window.addEventListener('resize', function() {
    if (!active) return;
    var step   = tutSteps[tutIdx];
    var target = document.querySelector(step.selector);
    if (!target) return;
    var rect = getRect(target);
    updateDims(rect);
    positionTooltip(rect);
  });

  /* ─── Init ────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function() {
    buildUI();

    var page   = window._tutPage || '';
    tutSteps   = PAGE_TUTORIALS[page] || [];

    if (!tutSteps.length) return;   // no tutorial for this page

    var fab    = $('#tutorial-fab');
    var prompt = $('#tutorial-prompt');

    // Show FAB
    fab.classList.remove('hidden');

    // FAB click: toggle prompt
    fab.addEventListener('click', function() {
      var vis = prompt.classList.contains('visible');
      if (vis) {
        prompt.classList.remove('visible');
      } else {
        prompt.classList.add('visible');
      }
    });

    // Prompt buttons
    $('#tut-yes').addEventListener('click', function() {
      prompt.classList.remove('visible');
      startTutorial();
    });
    $('#tut-no').addEventListener('click', function() {
      prompt.classList.remove('visible');
      markSeen(page);
    });

    // Tooltip navigation
    $('#tut-next').addEventListener('click', goNext);
    $('#tut-prev').addEventListener('click', goPrev);
    $('#tut-skip').addEventListener('click', function() { endTutorial(false); });

    // Click outside overlay = skip
    ['tut-dim-top','tut-dim-bottom','tut-dim-left','tut-dim-right'].forEach(function(id) {
      document.getElementById(id).addEventListener('click', function() {
        endTutorial(false);
      });
    });

    // First visit: auto-show prompt after 1.5s
    if (!hasSeen(page)) {
      setTimeout(function() {
        if (!active) {
          prompt.classList.add('visible');
          fab.classList.add('pulse');
          setTimeout(function() { fab.classList.remove('pulse'); }, 4000);
        }
      }, 1500);
    }
  });

})();
