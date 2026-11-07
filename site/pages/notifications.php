<?php
// site/pages/notifications.php — histórico de notificações + ações (marcar como lida, apagar)
require_once __DIR__ . '/../helpers/auth.php';          // sessão + CSRF em todos os POST
require_once __DIR__ . '/../../config/db.php';
$uid = (int) ($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/notifications.php';
$currentTheme = getUserTheme($pdo, $uid);

// ── Ações (só as notificações do próprio utilizador: WHERE user_id em todas) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    if ($action === 'read' && $id) {
        $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :u AND is_read = 0')->execute([':id' => $id, ':u' => $uid]);
    } elseif ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :u AND is_read = 0')->execute([':u' => $uid]);
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :u')->execute([':id' => $id, ':u' => $uid]);
    } elseif ($action === 'delete_read') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = :u AND is_read = 1')->execute([':u' => $uid]);
    }
    // Pedido do sino (fetch): responde em JSON; formulário: volta à página (PRG)
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'unread' => notif_unread_count($pdo, $uid)]);
        exit;
    }
    $f = ($_POST['f'] ?? '') === 'unread' ? '?f=unread' : '';
    header('Location: notifications.php' . $f);
    exit;
}

// ── Lista ───────────────────────────────────────────────────────────────────
$filter = ($_GET['f'] ?? '') === 'unread' ? 'unread' : 'all';
$pages  = max(1, min(20, (int) ($_GET['n'] ?? 1)));
$limit  = 30 * $pages;
$where  = 'user_id = :u' . ($filter === 'unread' ? ' AND is_read = 0' : '');
$st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
$st->execute([':u' => $uid]);
$total = (int) $st->fetchColumn();
$st = $pdo->prepare("SELECT id, type, title, message, data, is_read, created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS mins
                     FROM notifications WHERE $where ORDER BY created_at DESC, id DESC LIMIT $limit");
$st->execute([':u' => $uid]);
$items  = $st->fetchAll();
$unread = notif_unread_count($pdo, $uid);
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Notificações - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <link rel="stylesheet" href="../css/finora.css?v=9">
  <style>
    .nt { max-width: 760px; margin: 0 auto; padding-bottom: 56px; }
    .nt-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
    .nt h1 { margin: 0; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .nt-head p { margin: 4px 0 0; color: var(--fn-muted); font-size: 15px; }
    .nt-acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .nt-acts form { margin: 0; }
    .nt-tabs { display: inline-flex; padding: 4px; border-radius: 12px; background: var(--fn-paper); margin-bottom: 14px; }
    .nt-tabs a { padding: 7px 14px; border-radius: 9px; font-weight: 600; font-size: 14px; color: var(--fn-muted); text-decoration: none; }
    .nt-tabs a[aria-current="page"] { background: var(--fn-surface); color: var(--fn-text); box-shadow: 0 1px 3px rgba(20, 33, 61, .15); }
    .nt-list { list-style: none; margin: 0; padding: 6px; background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; }
    .nt-list li { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; }
    .nt-list li + li { border-top: 1px solid var(--fn-line); border-radius: 0; }
    .nt-list li.unread { background: color-mix(in srgb, var(--fn-gold) 6%, transparent); }
    .nt-link { flex: 1; min-width: 0; display: flex; gap: 12px; text-decoration: none; color: inherit; }
    .nt-link:hover b { text-decoration: underline; text-decoration-color: var(--fn-gold); }
    .nt-txt { min-width: 0; display: grid; gap: 1px; }
    .nt-txt b { font-size: 15px; color: var(--fn-text); }
    .nt-list li:not(.unread) .nt-txt b { font-weight: 600; color: var(--fn-muted); }
    .nt-txt span { font-size: 14px; color: var(--fn-muted); }
    .nt-txt small { font-size: 12.5px; color: var(--fn-muted); }
    .nt-list form { margin: 0; }
    .nt-x { width: 34px; height: 34px; border: 0; border-radius: 9px; background: transparent; color: var(--fn-muted); cursor: pointer; }
    .nt-x:hover { background: var(--fn-paper); color: var(--fn-text); }
    .nt-empty { padding: 48px 20px; text-align: center; color: var(--fn-muted); background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 18px; }
    .nt-empty i { display: block; font-size: 32px; color: #12b76a; margin-bottom: 8px; }
    .nt-more { text-align: center; margin-top: 14px; }
    .nt-note { margin-top: 14px; font-size: 13px; color: var(--fn-muted); }
  </style>
</head>
<body>
<div class="app-layout">
<?php $activePage = 'notifications'; $sidebarPrefix = '../'; include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="nt">
  <div class="nt-head">
    <div>
      <h1><?= te('Notificações') ?></h1>
      <p><?= $unread ? te('{n} por ler', ['n' => $unread]) : te('Tudo lido') ?></p>
    </div>
    <div class="nt-acts">
      <?php if ($unread): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><input type="hidden" name="f" value="<?= $filter ?>">
          <button type="submit" class="btn-s"><i class="bi bi-check2-all"></i> <?= te('Marcar todas como lidas') ?></button></form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Apagar todas as notificações já lidas?')) ?>)"><?= csrf_field() ?><input type="hidden" name="action" value="delete_read"><input type="hidden" name="f" value="<?= $filter ?>">
        <button type="submit" class="btn-s"><i class="bi bi-trash3"></i> <?= te('Limpar lidas') ?></button></form>
      <a class="btn-s" href="settings.php#notificacoes"><i class="bi bi-sliders"></i> <?= te('Preferências') ?></a>
    </div>
  </div>

  <nav class="nt-tabs" aria-label="<?= te('Filtrar') ?>">
    <a href="notifications.php" <?= $filter === 'all' ? 'aria-current="page"' : '' ?>><?= te('Todas') ?></a>
    <a href="notifications.php?f=unread" <?= $filter === 'unread' ? 'aria-current="page"' : '' ?>><?= te('Por ler') ?><?= $unread ? " ($unread)" : '' ?></a>
  </nav>

  <?php if (!$items): ?>
    <div class="nt-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i><?= $filter === 'unread' ? te('Não tens notificações por ler.') : te('Ainda não há notificações. Avisamos-te de pagamentos, orçamentos, metas e mais.') ?></div>
  <?php else: ?>
    <ul class="nt-list">
      <?php foreach ($items as $n): [$lbl, $ic, $tone] = NOTIF_TYPES[$n['type']] ?? ['', 'bell', 'cat-1']; ?>
        <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
          <a class="nt-link" href="../<?= htmlspecialchars(notif_link($n)) ?>" data-notif-id="<?= (int) $n['id'] ?>">
            <span class="app-notif-ico tone-<?= $tone ?>" aria-hidden="true"><i class="bi bi-<?= $ic ?>"></i></span>
            <span class="nt-txt">
              <b><?= htmlspecialchars($n['title']) ?></b>
              <span><?= htmlspecialchars($n['message']) ?></span>
              <small><?= htmlspecialchars($lbl) ?> · <?= notif_ago((int) $n['mins']) ?></small>
            </span>
          </a>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="f" value="<?= $filter ?>">
            <button type="submit" class="nt-x" aria-label="<?= te('Apagar notificação') ?>"><i class="bi bi-x-lg"></i></button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (count($items) < $total): ?>
      <div class="nt-more"><a class="btn-s" href="notifications.php?<?= http_build_query(['f' => $filter === 'unread' ? 'unread' : null, 'n' => $pages + 1]) ?>"><?= te('Ver mais') ?></a></div>
    <?php endif; ?>
  <?php endif; ?>
  <p class="nt-note"><?= te('A Finora verifica uma vez por dia, quando abres a app. As notificações lidas são apagadas ao fim de 6 meses.') ?></p>
</div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Abrir uma notificação por ler: marca como lida e segue
document.querySelectorAll('.nt-list li.unread .nt-link').forEach(a => a.addEventListener('click', (e) => {
  e.preventDefault();
  fetch('notifications.php', { method: 'POST', keepalive: true, credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': <?= json_encode(csrf_token()) ?>, 'Accept': 'application/json' },
    body: new URLSearchParams({ action: 'read', id: a.dataset.notifId }) }).finally(() => { location.href = a.href; });
}));
</script>
</body>
</html>
