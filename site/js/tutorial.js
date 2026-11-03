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
        title: '🗺️ Menu',
        text: 'Abre o menu aqui para navegar entre todas as secções: Transações, Cartões, Orçamentos, Metas, Análise e muito mais.',
        position: 'bottom'
      },
      {
        selector: '.app-header-actions',
        title: '⚡ Ações rápidas',
        text: 'Regista uma nova transação ou lê o QR de uma fatura a partir de qualquer página. O teu nome dá acesso às definições e a terminar sessão.',
        position: 'bottom'
      },
      {
        selector: '.dsh-hero',
        title: '📊 O teu mês',
        text: 'Quanto já gastaste este mês e a comparação com o mesmo ponto do mês passado. A linha dourada é este mês; a tracejada é o anterior.',
        position: 'bottom'
      },
      {
        selector: '#dshCategorias',
        title: '🧭 Para onde foi o dinheiro',
        text: 'A divisão dos gastos do mês por categoria. Ao lado tens os cartões, o orçamento e os próximos pagamentos.',
        position: 'top'
      }
    ],

    cards: [
      {
        selector: '.cd-summary',
        title: '💳 Os teus cartões',
        text: 'Quanto ainda tens disponível no total, somando os limites de todos os cartões ativos.',
        position: 'bottom'
      },
      {
        selector: '.cd-head .btn-s-primary',
        title: '➕ Adicionar cartão',
        text: 'Adiciona um cartão novo: escolhe o banco, a cor e o limite.',
        position: 'bottom'
      },
      {
        selector: '.cd-tile',
        title: '🗂️ Cada cartão',
        text: 'Vês o disponível, a utilização do limite e o número de transações. "Ver transações" abre a lista já filtrada por este cartão.',
        position: 'right'
      }
    ],

    transactions: [
      {
        selector: '.tx-month',
        title: '📅 Mês',
        text: 'Usa as setas para mudar de mês. Por baixo vês quantas transações fizeste, o total e a média.',
        position: 'bottom'
      },
      {
        selector: '.tx-filters',
        title: '🔍 Pesquisa e filtros',
        text: 'Pesquisa pela descrição, escolhe um cartão ou toca numa categoria para veres só esses gastos.',
        position: 'bottom'
      },
      {
        selector: '.tx-day',
        title: '📄 Transações por dia',
        text: 'As transações aparecem agrupadas por dia, com o total de cada dia. Usa o lápis para editar ou o caixote para apagar.',
        position: 'top'
      },
      {
        selector: '.app-header-btn-primary',
        title: '➕ Nova transação',
        text: 'Este botão está sempre no cabeçalho, em qualquer página.',
        position: 'bottom'
      }
    ],

    budgets: [
      {
        selector: '.bg-head .btn-s-primary',
        title: '🐷 Criar orçamento',
        text: 'Define quanto queres gastar por mês, semana ou ano — no total, numa categoria ou num cartão.',
        position: 'bottom'
      },
      {
        selector: '.bg-tile',
        title: '📊 Quanto ainda podes gastar',
        text: 'Em destaque vês quanto te resta. O traço na barra mostra onde devias estar hoje: se a barra já passou o traço, estás a gastar mais depressa do que o previsto.',
        position: 'right'
      },
      {
        selector: '.bg-hint',
        title: '📅 Por dia',
        text: 'Quanto podes gastar por dia até ao fim do período, para não passares o orçamento.',
        position: 'top'
      }
    ],

    reminders: [
      {
        selector: '.page-header',
        title: '🔔 Lembretes',
        text: 'Os lembretes avisam-te de pagamentos, datas de vencimento ou qualquer evento financeiro importante.',
        position: 'bottom'
      },
      {
        selector: '.page-header .btn-s-primary',
        title: '➕ Novo Lembrete',
        text: 'Cria um lembrete para uma data específica. Receberás uma notificação por email na data que escolheres.',
        position: 'bottom'
      },
      {
        selector: '.settings-group',
        title: '📋 Lista de Lembretes',
        text: 'Aqui vês todos os teus lembretes ativos e passados. Podes editar, marcar como concluído ou eliminar cada um.',
        position: 'top'
      }
    ],

    analytics: [
      {
        selector: '.page-header',
        title: '📈 Análise Financeira',
        text: 'A página de Análise oferece gráficos detalhados para perceberes os teus padrões de gasto ao longo do tempo.',
        position: 'bottom'
      },
      {
        selector: '.settings-group:first-of-type',
        title: '📅 Período de Análise',
        text: 'Seleciona o período que queres analisar — semana, mês, trimestre ou ano — para ver a evolução dos teus gastos.',
        position: 'bottom'
      },
      {
        selector: '.settings-group:nth-of-type(2)',
        title: '📊 Gráficos',
        text: 'Os gráficos mostram a distribuição de gastos por categoria, evolução mensal e comparação com períodos anteriores.',
        position: 'top'
      }
    ],

    futuro: [
      {
        selector: '.page-header',
        title: '⏳ Metas Futuras',
        text: 'Define metas de poupança para objetivos futuros: viagem, carro, fundo de emergência — tudo com acompanhamento de progresso.',
        position: 'bottom'
      },
      {
        selector: '.settings-group',
        title: '🎯 As Tuas Metas',
        text: 'Cada meta mostra o progresso atual versus o objetivo. A aplicação calcula automaticamente quanto precisas de poupar por mês.',
        position: 'top'
      }
    ],

    dados: [
      {
        selector: '.page-header',
        title: '🗄️ Dados & Relatórios',
        text: 'Aqui podes exportar, importar e gerir todos os teus dados financeiros. Ideal para fazer backup ou migrar para outra aplicação.',
        position: 'bottom'
      },
      {
        selector: '.settings-group:first-of-type',
        title: '📤 Exportar Dados',
        text: 'Exporta as tuas transações em formato CSV, JSON ou PDF. O PDF gera um relatório formatado pronto a imprimir.',
        position: 'bottom'
      },
      {
        selector: '.settings-group:last-of-type',
        title: '📥 Importar Dados',
        text: 'Importa transações a partir de ficheiros CSV ou JSON. Útil para migrar dados de outras aplicações de gestão financeira.',
        position: 'top'
      }
    ],

    ai: [
      {
        selector: '.page-header',
        title: '🤖 Assistente IA',
        text: 'O teu assistente financeiro inteligente! Faz perguntas sobre os teus gastos, pede sugestões de poupança ou análise de categorias.',
        position: 'bottom'
      },
      {
        selector: '.settings-group',
        title: '💬 Conversa com a IA',
        text: 'Escreve na caixa de texto e o assistente analisa os teus dados reais para dar respostas personalizadas. Experimenta perguntar: "Quanto gastei este mês em alimentação?"',
        position: 'top'
      }
    ],

    persona: [
      {
        selector: '.page-header',
        title: '🧠 Personalidade Financeira',
        text: 'Descobre o teu perfil financeiro com base nos teus hábitos de gasto. A IA analisa as tuas transações e dá-te insights personalizados.',
        position: 'bottom'
      }
    ],

    settings: [
      {
        selector: '.page-header',
        title: '⚙️ Definições',
        text: 'Aqui personalizas a tua conta: foto de perfil, tema claro/escuro, notificações, segurança e muito mais.',
        position: 'bottom'
      },
      {
        selector: '.settings-group:first-of-type',
        title: '👤 Perfil',
        text: 'Actualiza o teu nome, email e foto de perfil. Podes também ligar a tua conta Google para login mais rápido.',
        position: 'bottom'
      },
      {
        selector: '.settings-group:nth-of-type(2)',
        title: '🎨 Aparência',
        text: 'Muda entre tema claro e escuro conforme a tua preferência. A escolha é guardada automaticamente.',
        position: 'bottom'
      },
      {
        selector: '.settings-group:last-of-type',
        title: '🔒 Segurança',
        text: 'Muda a tua palavra-passe e configura preferências de segurança para manter a tua conta protegida.',
        position: 'top'
      }
    ],

    scan_fatura: [
      {
        selector: '.page-header',
        title: '📷 Ler Fatura QR',
        text: 'Digitaliza o QR code de faturas portuguesas (AT) para importar automaticamente os dados da despesa sem precisar de introduzir nada à mão.',
        position: 'bottom'
      },
      {
        selector: '.settings-group',
        title: '📋 Como Funciona',
        text: 'Aponta a câmara para o QR code na fatura, ou faz upload de uma imagem. A aplicação extrai automaticamente o valor, data, NIF e descrição.',
        position: 'top'
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
    var fab = el('button', 'hidden', '<i class="bi bi-question-lg"></i>');
    fab.id = 'tutorial-fab';
    fab.title = 'Tutorial da página';
    document.body.appendChild(fab);

    // Prompt card
    var prompt = el('div', '', '');
    prompt.id = 'tutorial-prompt';
    prompt.innerHTML =
      '<div class="tut-prompt-icon">🎓</div>' +
      '<h4>Quer um guia desta página?</h4>' +
      '<p>Mostramos-te como funciona cada secção em menos de 1 minuto.</p>' +
      '<div class="tut-prompt-btns">' +
        '<button class="tut-btn-yes" id="tut-yes">Sim, mostrar!</button>' +
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
    tip.innerHTML =
      '<div class="tut-tooltip-header">' +
        '<div class="tut-tooltip-step-badge" id="tut-step-badge"></div>' +
        '<div class="tut-tooltip-title"    id="tut-step-title"></div>' +
      '</div>' +
      '<div class="tut-tooltip-body" id="tut-step-body"></div>' +
      '<div class="tut-tooltip-footer">' +
        '<div class="tut-progress" id="tut-dots"></div>' +
        '<button class="tut-nav-btn skip" id="tut-skip">Saltar</button>' +
        '<button class="tut-nav-btn"      id="tut-prev">‹</button>' +
        '<button class="tut-nav-btn primary" id="tut-next">Próximo</button>' +
      '</div>';
    document.body.appendChild(tip);

    // Completion toast
    var done = el('div', '');
    done.id = 'tutorial-complete';
    done.innerHTML = '<div class="tut-check">✓</div> Tutorial concluído! Boa sorte 🚀';
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
    dt.style.cssText = 'position:fixed;top:0;left:0;right:0;height:' + Math.max(0, rect.top) + 'px;background:rgba(0,0,0,.6);';

    // Bottom panel
    var db = $('#tut-dim-bottom');
    db.style.cssText = 'position:fixed;bottom:0;left:0;right:0;top:' + Math.min(vh, rect.bottom) + 'px;background:rgba(0,0,0,.6);';

    // Left panel
    var dl = $('#tut-dim-left');
    dl.style.cssText = 'position:fixed;top:' + Math.max(0,rect.top) + 'px;left:0;width:' + Math.max(0,rect.left) + 'px;height:' + Math.max(0,rect.bottom - rect.top) + 'px;background:rgba(0,0,0,.6);';

    // Right panel
    var dr = $('#tut-dim-right');
    dr.style.cssText = 'position:fixed;top:' + Math.max(0,rect.top) + 'px;right:0;left:' + Math.min(vw,rect.right) + 'px;height:' + Math.max(0,rect.bottom - rect.top) + 'px;background:rgba(0,0,0,.6);';

    // Spotlight
    sp.style.cssText = 'position:fixed;top:' + rect.top + 'px;left:' + rect.left + 'px;width:' + rect.width + 'px;height:' + rect.height + 'px;border-radius:10px;pointer-events:none;box-shadow:0 0 0 3px rgba(14,165,233,.9),0 0 0 6px rgba(14,165,233,.2);transition:all .3s cubic-bezier(.4,0,.2,1);';
  }

  function positionTooltip(rect) {
    var tip   = $('#tutorial-tooltip');
    var vw    = window.innerWidth;
    var vh    = window.innerHeight;
    var tipW  = 308;
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
      $('#tut-next').textContent   = idx === tutSteps.length - 1 ? 'Concluir ✓' : 'Próximo';

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
