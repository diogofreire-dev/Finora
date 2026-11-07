<?php
// config/EmailService.php

// A chave fica em brevo.php (ignorado pelo git) — ver brevo.example.php
require_once __DIR__ . '/brevo.php';
require_once __DIR__ . '/../site/helpers/format.php';

class EmailService {
    private $apiKey    = BREVO_API_KEY;
    private $apiUrl    ='https://api.brevo.com/v3/smtp/email';
    // O remetente tem de estar validado na conta Brevo da chave (Settings → Remetentes, domínio, IPs);
    // senão a Brevo aceita o pedido mas recusa enviar ("sender … is not valid") e o email nunca sai.
    // Muda-se em config/brevo.php com MAIL_FROM_EMAIL / MAIL_FROM_NAME, sem mexer aqui.
    private $fromEmail = 'freecardssuporte@gmail.com';
    private $fromName  = 'Finora';
    private $baseUrl   = APP_URL;

    public function __construct() {
        if (defined('MAIL_FROM_EMAIL') && filter_var(MAIL_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) $this->fromEmail = MAIL_FROM_EMAIL;
        if (defined('MAIL_FROM_NAME') && MAIL_FROM_NAME !== '') $this->fromName = MAIL_FROM_NAME;
    }

    private function sendMail($to, $subject, $htmlBody, $textBody = '') {
        $data = [
            'sender'      => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $htmlBody,
        ];
        if (!empty($textBody)) $data['textContent'] = $textBody;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL        => $this->apiUrl,
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'api-key: ' . $this->apiKey,
                'content-type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) { error_log("Brevo CURL Error: $error"); return false; }
        if ($httpCode >= 200 && $httpCode < 300) return true;
        error_log("Brevo API Error: HTTP $httpCode - $response");
        return false;
    }

    // ── Envio público ────────────────────────────────────────────────────

    public function sendVerificationEmail($email, $username, $code) {
        try { return $this->sendMail($email, $this->L('Código de verificação — Finora'), $this->tplVerification($username, $code)); }
        catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    public function sendWelcomeEmail($email, $username) {
        try { return $this->sendMail($email, $this->L('Bem-vindo à Finora!'), $this->tplWelcome($username)); }
        catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    public function sendPasswordResetEmail($email, $username, $token) {
        try {
            $link = $this->baseUrl . '/auth/reset_password.php?token=' . urlencode($token);
            return $this->sendMail($email, $this->L('Recuperar palavra-passe — Finora'), $this->tplPasswordReset($username, $link));
        } catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    public function sendPaymentReminder($email, $username, $reminderData) {
        try {
            $subject = t('Lembrete: {name} vence em {n} dias', ['name' => $reminderData['name'], 'n' => $reminderData['days_until']]);
            return $this->sendMail($email, $subject, $this->tplPaymentReminder($username, $reminderData));
        } catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    public function sendBudgetAlert($email, $username, $budgetData) {
        try {
            $subject = t($budgetData['percentage'] >= 100 ? 'Orçamento excedido: {name} ({pct}%)' : 'Orçamento em alerta: {name} ({pct}%)', ['name' => $budgetData['name'], 'pct' => $budgetData['percentage']]);
            return $this->sendMail($email, $subject, $this->tplBudgetAlert($username, $budgetData));
        } catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    public function sendEmailChangeVerification($newEmail, $username, $token) {
        try {
            $link = $this->baseUrl . '/auth/verify_email_change.php?token=' . urlencode($token);
            return $this->sendMail($newEmail, t('Confirma a alteração de email — Finora'), $this->tplEmailChange($username, $newEmail, $link));
        } catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    public function sendPasswordChangedNotification($email, $username) {
        try { return $this->sendMail($email, t('Palavra-passe alterada — Finora'), $this->tplPasswordChanged($username)); }
        catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    public function sendAccountDeletedNotification($email, $username) {
        try { return $this->sendMail($email, t('Conta apagada — Finora'), $this->tplAccountDeleted($username)); }
        catch (Exception $e) { error_log($e->getMessage()); return false; }
    }

    // ── Texto na língua de quem recebe ─────────────────────────────────
    // t() já escapado para o HTML do email. Os nomes (utilizador, lembrete, orçamento) vêm da base de dados
    // e são escritos por quem usa a app, por isso também passam por htmlspecialchars antes de entrar no HTML.
    private function L(string $s, array $v = []): string {
        return htmlspecialchars(t($s, $v), ENT_QUOTES, 'UTF-8');
    }
    private function e($s): string {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
    // "Olá, <nome>!" com o nome a azul
    private function hi(string $username, string $end = '!'): string {
        $name = '<span style="color:#c48a12">' . $this->e($username) . '</span>';
        return $end === '!' ? t('Olá, {name}!', ['name' => $name]) : t('Olá, {name}.', ['name' => $name]);
    }
    private function lang(): string { return html_lang(); }

    // ── Base HTML ────────────────────────────────────────────────────────

    // Identidade da app: azul-marinho #14213d, dourado #c48a12, creme #f3e7c8, papel #f4f6fa.
    // O "logótipo" é texto serifado: muitos clientes de email bloqueiam imagens e o Gmail não mostra SVG.
    private function base(string $preheader, string $body): string {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="{$this->lang()}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Finora</title>
  <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
    table,td{mso-table-lspace:0;mso-table-rspace:0}
    body{margin:0;padding:0;background-color:#f4f6fa;font-family:'Figtree',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
    a{color:#14213d}
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f6fa">
  <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;color:#f4f6fa;line-height:1px">{$preheader}&nbsp;‌​‍‎‏‌​‌​‍‎‏</div>

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6fa;padding:32px 16px;font-family:'Figtree',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:18px;border:1px solid #dfe4ee;overflow:hidden">

        {$body}

      </table>

      <!-- Rodapé, fora do cartão -->
      <table width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%">
        <tr><td style="padding:22px 8px 0;text-align:center;font-size:12px;line-height:1.7;color:#8a93a8">
          <span style="font-family:Georgia,'Times New Roman',serif;font-size:15px;color:#14213d">Finora</span><br>
          {$this->L('Gestor de finanças pessoais')} · {$this->L('Este email foi enviado automaticamente — não respondas.')}<br>
          © {$year} Finora
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    // Faixa azul-marinho com o nome Finora em creme e o título do email. O $emoji fica como pequeno símbolo.
    private function header(string $emoji, string $title, string $subtitle = ''): string {
        $title = $this->L($title); $subtitle = $subtitle !== '' ? $this->L($subtitle) : '';
        $sub = $subtitle ? "<div style='font-size:15px;line-height:1.5;color:#c9cfdc;margin-top:8px'>{$subtitle}</div>" : '';
        return <<<HTML
        <tr><td style="background:#14213d;padding:28px 32px 30px;border-radius:18px 18px 0 0">
          <div style="font-family:Georgia,'Times New Roman',serif;font-size:26px;line-height:1;color:#f3e7c8;letter-spacing:0.2px">Finora<span style="color:#e2b04a">&#8599;</span></div>
          <div style="height:2px;width:40px;background:#e2b04a;margin:16px 0 18px;border-radius:2px"></div>
          <div style="font-size:24px;font-weight:700;line-height:1.25;color:#ffffff;letter-spacing:-0.3px">{$title}</div>
          {$sub}
        </td></tr>
HTML;
    }

    private function divider(): string {
        return '<tr><td style="padding:0 32px"><div style="height:1px;background:#e2e8f0"></div></td></tr>';
    }

    // ── Templates ────────────────────────────────────────────────────────

    private function tplVerification(string $username, string $code): string {
        $h = $this->header('✉️', 'Confirma a tua conta', 'Introduz o código abaixo para ativares a tua conta');
        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username)}</p>
          <p style="margin:0 0 28px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('Obrigado por te registares. Para ativares a tua conta, usa o código de verificação abaixo:')}</p>

          <!-- Código -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="padding:8px 0 28px">
            <div style="display:inline-block;background:#14213d;color:#f3e7c8;font-size:36px;font-weight:700;letter-spacing:12px;padding:20px 36px;border-radius:14px;font-family:'Courier New',monospace">{$code}</div>
          </td></tr></table>

          <!-- Info box -->
          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef9e7;border:1px solid #f59e0b;border-radius:10px;margin-bottom:24px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#92400e;margin-bottom:3px">{$this->L('⏱ Código temporário')}</div>
            <div style="font-size:13px;color:#b45309">{$this->L('Este código é válido durante 15 minutos. Não o partilhes com ninguém.')}</div>
          </td></tr></table>

          <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6">{$this->L('Se não criaste uma conta no Finora, podes ignorar este email com segurança.')}</p>
        </td></tr>
HTML;
        return $this->base($this->L('O teu código de verificação Finora é {code}', ['code' => $code]), $body);
    }

    private function tplWelcome(string $username): string {
        $dashUrl = $this->baseUrl . '/dashboard.php';
        $h = $this->header('🎉', 'Bem-vindo à Finora!', 'A tua conta está verificada e pronta a usar');
        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username)}</p>
          <p style="margin:0 0 28px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('A tua conta foi verificada com sucesso. Podes começar agora a gerir os teus cartões, transações e orçamentos.')}</p>

          <!-- Feature list -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:32px">
            <tr><td style="padding:10px 0;border-bottom:1px solid #eef1f6">
              <table cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="width:36px;height:36px;background:#fbf5e6;border-radius:9px;text-align:center;vertical-align:middle;font-size:16px">💳</td>
                <td style="padding-left:14px;font-size:14px;color:#5f6982">{$this->L('Adiciona os teus cartões e contas')}</td>
              </tr></table>
            </td></tr>
            <tr><td style="padding:10px 0;border-bottom:1px solid #eef1f6">
              <table cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="width:36px;height:36px;background:#fbf5e6;border-radius:9px;text-align:center;vertical-align:middle;font-size:16px">📊</td>
                <td style="padding-left:14px;font-size:14px;color:#5f6982">{$this->L('Regista transações e analisa os teus gastos')}</td>
              </tr></table>
            </td></tr>
            <tr><td style="padding:10px 0">
              <table cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="width:36px;height:36px;background:#fbf5e6;border-radius:9px;text-align:center;vertical-align:middle;font-size:16px">🔔</td>
                <td style="padding-left:14px;font-size:14px;color:#5f6982">{$this->L('Define orçamentos e lembretes de pagamento')}</td>
              </tr></table>
            </td></tr>
          </table>

          <!-- CTA -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
            <a href="{$dashUrl}" style="display:inline-block;background:#14213d;color:#f3e7c8;text-decoration:none;font-size:15px;font-weight:600;padding:14px 40px;border-radius:10px;letter-spacing:0.2px">{$this->L('Ir para o meu Finora →')}</a>
          </td></tr></table>
        </td></tr>
HTML;
        return $this->base($this->L('Bem-vindo à Finora, {name}! A tua conta está ativa.', ['name' => $username]), $body);
    }

    private function tplPasswordReset(string $username, string $resetLink): string {
        $h = $this->header('🔑', 'Recuperar palavra-passe', 'Recebemos um pedido para redefinir a tua palavra-passe');
        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username)}</p>
          <p style="margin:0 0 28px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('Recebemos um pedido para redefinir a palavra-passe da tua conta Finora. Clica no botão abaixo para definires uma nova:')}</p>

          <!-- CTA -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px"><tr><td align="center">
            <a href="{$resetLink}" style="display:inline-block;background:#14213d;color:#f3e7c8;text-decoration:none;font-size:15px;font-weight:600;padding:14px 40px;border-radius:10px">{$this->L('Definir nova palavra-passe')}</a>
          </td></tr></table>

          <!-- Info boxes -->
          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef9e7;border:1px solid #f59e0b;border-radius:10px;margin-bottom:12px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#92400e;margin-bottom:3px">{$this->L('⏱ Link temporário')}</div>
            <div style="font-size:13px;color:#b45309">{$this->L('Este link é válido durante 1 hora e só pode ser usado uma vez.')}</div>
          </td></tr></table>

          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fdf2f2;border:1px solid #fca5a5;border-radius:10px;margin-bottom:24px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#991b1b;margin-bottom:3px">{$this->L('🚫 Não foste tu?')}</div>
            <div style="font-size:13px;color:#b91c1c">{$this->L('Se não pediste esta recuperação, ignora este email. A tua palavra-passe não será alterada.')}</div>
          </td></tr></table>

          <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6">{$this->L('Se o botão não funcionar, copia e cola este link no teu browser:')}<br><span style="color:#c48a12;word-break:break-all">{$resetLink}</span></p>
        </td></tr>
HTML;
        return $this->base($this->L('Pedido de recuperação de palavra-passe Finora'), $body);
    }

    private function tplPaymentReminder(string $username, array $r): string {
        $isToday   = $r['days_until'] == 0;
        $isOverdue = $r['days_until'] < 0;
        $urgency   = $isOverdue ? '🔴 ' . $this->L('Pagamento em atraso') : ($isToday ? '🟠 ' . $this->L('Vence hoje') : '🔔 ' . $this->L('Vence em {n} dias', ['n' => $r['days_until']]));
        $emoji     = $isOverdue ? '⚠️' : '🔔';
        $subtitle  = $isOverdue ? 'Tens um pagamento em atraso' : 'Tens um pagamento a vencer em breve';
        $boxBg     = $isOverdue ? '#fdf2f2' : ($isToday ? '#fef9e7' : '#f4f6fa');
        $boxBorder = $isOverdue ? '#fca5a5' : ($isToday ? '#f59e0b' : '#dfe4ee');
        $boxColor  = $isOverdue ? '#991b1b'  : ($isToday ? '#92400e'  : '#14213d');

        $h = $this->header($emoji, 'Lembrete de pagamento', $subtitle);
        $dashUrl = $this->baseUrl . '/pages/reminders.php';

        // Valor em número e a moeda da conta (o cron corre sem sessão, por isso a moeda vem nos dados)
        $amount    = fmt_money($r['amount'], 2, $r['currency'] ?? 'EUR');
        // O cron já manda a data como dd/mm/aaaa; o strtotime() leria "05/11" como 11 de maio
        $dueDate   = preg_match('#^\d{2}/\d{2}/\d{4}$#', (string) $r['due_date']) ? $r['due_date'] : date('d/m/Y', strtotime($r['due_date']));

        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username)}</p>
          <p style="margin:0 0 24px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('Tens um pagamento que requer a tua atenção:')}</p>

          <!-- Payment card -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{$boxBg};border:1px solid {$boxBorder};border-radius:12px;margin-bottom:24px"><tr><td style="padding:20px 24px">
            <div style="font-size:11px;font-weight:600;color:{$boxColor};text-transform:uppercase;letter-spacing:0.8px;margin-bottom:10px">{$urgency}</div>
            <div style="font-size:20px;font-weight:700;color:#14213d;margin-bottom:4px">{$this->e($r['name'])}</div>
            <div style="font-size:28px;font-weight:800;color:#c48a12;margin-bottom:12px">{$amount}</div>
            <table cellpadding="0" cellspacing="0" border="0"><tr>
              <td style="font-size:13px;color:#5f6982;padding-right:24px">📅 <strong>{$this->L('Data:')}</strong> {$dueDate}</td>
HTML;
        if (!empty($r['category'])) {
            $body .= "<td style='font-size:13px;color:#5f6982'>🏷 <strong>" . $this->L('Categoria:') . '</strong> ' . $this->L($r['category']) . '</td>';
        }
        $body .= <<<HTML
            </tr></table>
          </td></tr></table>

          <!-- CTA -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
            <a href="{$dashUrl}" style="display:inline-block;background:#14213d;color:#f3e7c8;text-decoration:none;font-size:15px;font-weight:600;padding:13px 36px;border-radius:10px">{$this->L('Ver lembretes')}</a>
          </td></tr></table>
        </td></tr>
HTML;
        return $this->base($this->L('Lembrete de pagamento: {name} — {amount}', ['name' => $r['name'], 'amount' => $amount]), $body);
    }

    private function tplBudgetAlert(string $username, array $b): string {
        $exceeded = $b['percentage'] >= 100;
        $emoji    = $exceeded ? '🚨' : '⚡';
        $title    = $exceeded ? 'Orçamento excedido!' : 'Alerta de orçamento';
        $subtitle = $exceeded ? 'Ultrapassaste o limite definido' : t('Já usaste {pct}% do teu orçamento', ['pct' => $b['percentage']]);

        $pct     = min((int)$b['percentage'], 100);
        $barColor = $exceeded ? '#ef4444' : ($pct >= 80 ? '#f59e0b' : '#14213d');
        $boxBg    = $exceeded ? '#fdf2f2' : '#fef9e7';
        $boxBorder = $exceeded ? '#fca5a5' : '#f59e0b';
        $boxColor  = $exceeded ? '#991b1b' : '#92400e';

        $spent  = fmt_money($b['amount_spent'], 2, $b['currency'] ?? 'EUR');
        $limit  = fmt_money($b['limit'], 2, $b['currency'] ?? 'EUR');
        $dashUrl = $this->baseUrl . '/pages/budgets.php';

        $h = $this->header($emoji, $title, $subtitle);
        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username)}</p>
          <p style="margin:0 0 24px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('O teu orçamento «{name}» atingiu {pct}% do limite definido.', ['name' => $b['name'], 'pct' => $b['percentage']])}</p>

          <!-- Budget card -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{$boxBg};border:1px solid {$boxBorder};border-radius:12px;margin-bottom:24px"><tr><td style="padding:20px 24px">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:{$boxColor};margin-bottom:12px">{$this->e($b['name'])}</div>

            <!-- Barra de progresso -->
            <div style="height:8px;background:rgba(0,0,0,0.08);border-radius:4px;margin-bottom:14px">
              <div style="height:8px;width:{$pct}%;background:{$barColor};border-radius:4px"></div>
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
              <td style="font-size:14px;color:#5f6982">💸 {$this->L('Gasto:')} <strong style="color:{$barColor}">{$spent}</strong></td>
              <td style="font-size:14px;color:#5f6982;text-align:right">🎯 {$this->L('Limite:')} <strong style="color:#14213d">{$limit}</strong></td>
            </tr></table>
          </td></tr></table>

          <!-- CTA -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
            <a href="{$dashUrl}" style="display:inline-block;background:#14213d;color:#f3e7c8;text-decoration:none;font-size:15px;font-weight:600;padding:13px 36px;border-radius:10px">{$this->L('Ver orçamentos')}</a>
          </td></tr></table>
        </td></tr>
HTML;
        return $this->base($this->L('Alerta de orçamento: {name} a {pct}%', ['name' => $b['name'], 'pct' => $b['percentage']]), $body);
    }

    private function tplEmailChange(string $username, string $newEmail, string $link): string {
        $h = $this->header('📧', 'Confirmar novo email', 'Verifica que és tu antes de alterarmos o email');
        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username)}</p>
          <p style="margin:0 0 16px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('Recebemos um pedido para alterar o email da tua conta para:')}</p>
          <div style="background:#f4f6fa;border:1px solid #dfe4ee;border-radius:10px;padding:14px 20px;margin-bottom:24px;font-size:16px;font-weight:600;color:#14213d;text-align:center">{$this->e($newEmail)}</div>
          <p style="margin:0 0 24px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('Clica no botão abaixo para confirmares a alteração:')}</p>

          <!-- CTA -->
          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px"><tr><td align="center">
            <a href="{$link}" style="display:inline-block;background:#14213d;color:#f3e7c8;text-decoration:none;font-size:15px;font-weight:600;padding:14px 40px;border-radius:10px">{$this->L('Confirmar novo email')}</a>
          </td></tr></table>

          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef9e7;border:1px solid #f59e0b;border-radius:10px;margin-bottom:12px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#92400e;margin-bottom:3px">{$this->L('⏱ Link temporário')}</div>
            <div style="font-size:13px;color:#b45309">{$this->L('Este link é válido durante 1 hora.')}</div>
          </td></tr></table>

          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fdf2f2;border:1px solid #fca5a5;border-radius:10px;margin-bottom:24px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#991b1b;margin-bottom:3px">{$this->L('🚫 Não foste tu?')}</div>
            <div style="font-size:13px;color:#b91c1c">{$this->L('Se não pediste esta alteração, o teu email atual continua ativo. Não precisas de fazer nada.')}</div>
          </td></tr></table>

          <p style="margin:0;font-size:12px;color:#9ca3af">{$this->L('Se o botão não funcionar:')} <span style="color:#c48a12;word-break:break-all">{$link}</span></p>
        </td></tr>
HTML;
        return $this->base($this->L('Confirma o teu novo email na Finora'), $body);
    }

    private function tplPasswordChanged(string $username): string {
        $h = $this->header('🔒', 'Palavra-passe alterada', 'A tua palavra-passe foi atualizada com sucesso');
        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username)}</p>
          <p style="margin:0 0 24px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('A palavra-passe da tua conta Finora foi alterada com sucesso. Podes entrar normalmente com a nova palavra-passe.')}</p>

          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;margin-bottom:12px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#166534;margin-bottom:3px">{$this->L('✅ Alteração confirmada')}</div>
            <div style="font-size:13px;color:#15803d">{$this->L('A tua nova palavra-passe está ativa imediatamente.')}</div>
          </td></tr></table>

          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fdf2f2;border:1px solid #fca5a5;border-radius:10px;margin-bottom:28px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#991b1b;margin-bottom:3px">{$this->L('🚫 Não foste tu?')}</div>
            <div style="font-size:13px;color:#b91c1c">{$this->L('Se não alteraste a palavra-passe, a tua conta pode ter sido comprometida. Usa a opção «Esqueci a palavra-passe» imediatamente e contacta o suporte.')}</div>
          </td></tr></table>

          <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6">{$this->L('Este email é um aviso de segurança automático.')}</p>
        </td></tr>
HTML;
        return $this->base($this->L('A tua palavra-passe da Finora foi alterada'), $body);
    }

    private function tplAccountDeleted(string $username): string {
        $h = $this->header('👋', 'Conta apagada', 'A tua conta e todos os dados foram apagados');
        $body = $h . <<<HTML
        <tr><td style="padding:40px 32px 32px">
          <p style="margin:0 0 12px;font-size:16px;color:#14213d;font-weight:600">{$this->hi($username, '.')}</p>
          <p style="margin:0 0 24px;font-size:15px;color:#5f6982;line-height:1.7">{$this->L('A tua conta Finora foi apagada, com todos os cartões, transações, orçamentos, lembretes, poupanças e metas. Não guardámos cópia.')}</p>

          <table class="info-box" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fdf2f2;border:1px solid #fca5a5;border-radius:10px;margin-bottom:28px"><tr><td style="padding:14px 18px">
            <div style="font-size:13px;font-weight:600;color:#991b1b;margin-bottom:3px">{$this->L('🚫 Não foste tu?')}</div>
            <div style="font-size:13px;color:#b91c1c">{$this->L('Alguém com acesso à tua sessão apagou a conta. Muda a palavra-passe do teu email e de outras contas onde usavas a mesma.')}</div>
          </td></tr></table>

          <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6">{$this->L('Este é o último email que recebes da Finora.')}</p>
        </td></tr>
HTML;
        return $this->base($this->L('A tua conta Finora foi apagada'), $body);
    }
}
?>