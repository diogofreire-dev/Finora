<?php
// config/EmailService.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mail;
    private $fromEmail = 'freecardssuporte@gmail.com';
    private $appPassword = 'aanu cxbs zehl bddy';

    // URL base do site (ajustar conforme o ambiente)
    private $baseUrl = 'http://localhost:8000';

    public function __construct() {
        $this->mail = new PHPMailer(true);

        // Configuração SMTP
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $this->fromEmail;
        $this->mail->Password   = $this->appPassword;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;
        $this->mail->CharSet    = 'UTF-8';

        $this->mail->setFrom($this->fromEmail, 'FreeCard');
    }

    /**
     * Estilos CSS base para todos os emails
     */
    private function getBaseStyles() {
        return <<<CSS
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            padding: 20px;
            min-height: 100vh;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .email-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2c3e50 100%);
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #ffffff;
            text-decoration: none;
            letter-spacing: 1px;
        }

        .email-title {
            color: #ffffff;
            font-size: 20px;
            margin-top: 15px;
            font-weight: 400;
        }

        .email-content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .message {
            color: #5d6778;
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 15px;
        }

        .btn-container {
            text-align: center;
            margin: 30px 0;
        }

        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: #ffffff !important;
            padding: 14px 40px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .link-text {
            color: #7f8c8d;
            font-size: 13px;
            margin-top: 15px;
            word-break: break-all;
            text-align: center;
        }

        .link-text a {
            color: #3498db;
            text-decoration: none;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .info-box.warning {
            background: #fef9e7;
        }

        .info-box.danger {
            background: #fdf2f2;
        }

        .info-box.success {
            background: #f0fdf4;
        }

        .info-box-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .info-box-text {
            color: #5d6778;
            font-size: 13px;
        }

        .data-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            margin: 25px 0;
        }

        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-row:last-child {
            border-bottom: none;
        }

        .data-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .data-value {
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .data-value.highlight {
            color: #3498db;
            font-size: 18px;
        }

        .data-value.danger {
            color: #e74c3c;
        }

        .data-value.warning {
            color: #f39c12;
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        .features-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px 25px;
            margin: 20px 0;
        }

        .feature-item {
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
            color: #2c3e50;
            font-size: 14px;
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        .feature-icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: #3498db;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            color: white;
            font-size: 12px;
            margin-right: 12px;
            vertical-align: middle;
        }

        .email-footer {
            background: #2c3e50;
            padding: 25px;
            text-align: center;
        }

        .footer-logo {
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .footer-text {
            color: #95a5a6;
            font-size: 12px;
            line-height: 1.6;
        }

        .small-text {
            color: #95a5a6;
            font-size: 12px;
            margin-top: 20px;
        }
CSS;
    }

    /**
     * Header HTML para todos os emails
     */
    private function getEmailHeader($title) {
        return <<<HTML
        <div class="email-header">
            <div class="logo">FreeCard</div>
            <div class="email-title">{$title}</div>
        </div>
HTML;
    }

    /**
     * Footer HTML para todos os emails
     */
    private function getEmailFooter() {
        return <<<HTML
        <div class="email-footer">
            <div class="footer-logo">FreeCard</div>
            <div class="footer-text">
                Gestor de Cartoes de Credito<br>
                Este email foi enviado automaticamente. Por favor nao responda.<br>
                &copy; 2026 FreeCard. Todos os direitos reservados.
            </div>
        </div>
HTML;
    }

    /**
     * Enviar email de verificação de conta
     */
    public function sendVerificationEmail($email, $username, $token) {
        try {
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Confirma a tua conta FreeCard';

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $verificationLink = "http://{$host}/auth/verify.php?token=" . urlencode($token);

            $this->mail->Body = $this->getVerificationTemplate($username, $verificationLink);
            $this->mail->AltBody = "Ola {$username}! Clica no link para verificar a tua conta: {$verificationLink} - Este link expira em 24 horas.";

            $this->mail->send();
            $this->mail->clearAddresses();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar email de verificacao: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Enviar email de boas-vindas
     */
    public function sendWelcomeEmail($email, $username) {
        try {
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Bem-vindo ao FreeCard!';

            $this->mail->Body = $this->getWelcomeTemplate($username);
            $this->mail->AltBody = "Bem-vindo ao FreeCard, {$username}! A tua conta foi verificada com sucesso!";

            $this->mail->send();
            $this->mail->clearAddresses();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar email de boas-vindas: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Enviar email de recuperação de palavra-passe
     */
    public function sendPasswordResetEmail($email, $username, $token) {
        try {
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Recuperar palavra-passe - FreeCard';

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $resetLink = "http://{$host}/auth/reset_password.php?token=" . urlencode($token);

            $this->mail->Body = $this->getPasswordResetTemplate($username, $resetLink);
            $this->mail->AltBody = "Ola {$username}! Clica no link para recuperar a tua palavra-passe: {$resetLink} - Este link expira em 1 hora.";

            $this->mail->send();
            $this->mail->clearAddresses();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar email de reset: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Enviar lembrete de pagamento
     */
    public function sendPaymentReminder($email, $username, $reminderData) {
        try {
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);
            $this->mail->Subject = "Lembrete: {$reminderData['name']} vence em {$reminderData['days_until']} dias";

            $this->mail->Body = $this->getPaymentReminderTemplate($username, $reminderData);
            $this->mail->AltBody = "Ola {$username}! Lembrete: {$reminderData['name']} (EUR {$reminderData['amount']}) vence em {$reminderData['days_until']} dias, a {$reminderData['due_date']}.";

            $this->mail->send();
            $this->mail->clearAddresses();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar lembrete de pagamento: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Enviar alerta de orçamento excedido
     */
    public function sendBudgetAlert($email, $username, $budgetData) {
        try {
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);

            $alertType = $budgetData['percentage'] >= 100 ? 'excedido' : 'em alerta';
            $this->mail->Subject = "Orcamento {$alertType}: {$budgetData['name']} ({$budgetData['percentage']}%)";

            $this->mail->Body = $this->getBudgetAlertTemplate($username, $budgetData);
            $this->mail->AltBody = "Ola {$username}! O teu orcamento '{$budgetData['name']}' esta em {$budgetData['percentage']}%. Ja gastaste EUR {$budgetData['amount_spent']} de EUR {$budgetData['limit']}.";

            $this->mail->send();
            $this->mail->clearAddresses();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar alerta de orcamento: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Enviar email de verificação de alteração de email
     */
    public function sendEmailChangeVerification($newEmail, $username, $token) {
        try {
            $this->mail->addAddress($newEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Confirma a alteracao de email - FreeCard';

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
            $verificationLink = "http://{$host}/auth/verify_email_change.php?token=" . urlencode($token);

            $this->mail->Body = $this->getEmailChangeTemplate($username, $newEmail, $verificationLink);
            $this->mail->AltBody = "Ola {$username}! Clica no link para confirmar a alteracao do teu email para {$newEmail}: {$verificationLink} - Este link expira em 1 hora.";

            $this->mail->send();
            $this->mail->clearAddresses();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar verificacao de email: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Enviar notificação de alteração de password
     */
    public function sendPasswordChangedNotification($email, $username) {
        try {
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Password alterada - FreeCard';

            $this->mail->Body = $this->getPasswordChangedTemplate($username);
            $this->mail->AltBody = "Ola {$username}! A tua password foi alterada com sucesso. Se nao foste tu, contacta o suporte imediatamente.";

            $this->mail->send();
            $this->mail->clearAddresses();

            return true;
        } catch (Exception $e) {
            error_log("Erro ao enviar notificacao de password: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Template de verificação de conta
     */
    private function getVerificationTemplate($username, $verificationLink) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Confirmar Conta');
        $footer = $this->getEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>{$styles}</style>
</head>
<body>
    <div class="email-container">
        {$header}

        <div class="email-content">
            <div class="greeting">
                Ola <strong>{$username}</strong>,
            </div>

            <div class="message">
                Obrigado por te registares no FreeCard! Para ativares a tua conta e comecares a gerir os teus cartoes, confirma o teu endereco de email clicando no botao abaixo.
            </div>

            <div class="btn-container">
                <a href="{$verificationLink}" class="btn-primary">Confirmar Conta</a>
            </div>

            <div class="link-text">
                Ou copia e cola este link no teu navegador:<br>
                <a href="{$verificationLink}">{$verificationLink}</a>
            </div>

            <div class="info-box warning">
                <div class="info-box-title">Link temporario</div>
                <div class="info-box-text">Este link e valido durante 24 horas. Apos esse periodo, teras de solicitar um novo email de verificacao.</div>
            </div>

            <div class="small-text">
                Se nao criaste uma conta no FreeCard, podes ignorar este email com seguranca.
            </div>
        </div>

        {$footer}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template de boas-vindas
     */
    private function getWelcomeTemplate($username) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Conta Verificada');
        $footer = $this->getEmailFooter();
        $dashboardUrl = $this->baseUrl . '/dashboard.php';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>{$styles}</style>
</head>
<body>
    <div class="email-container">
        {$header}

        <div class="email-content">
            <div class="greeting">
                Ola <strong>{$username}</strong>,
            </div>

            <div class="message">
                A tua conta foi verificada com sucesso! Estamos muito felizes em te ter connosco. Agora podes comecar a utilizar todas as funcionalidades do FreeCard.
            </div>

            <div class="features-list">
                <div class="feature-item">
                    <span class="feature-icon">1</span>
                    <strong>Gestao de Cartoes</strong> - Organiza todos os teus cartoes num so lugar
                </div>
                <div class="feature-item">
                    <span class="feature-icon">2</span>
                    <strong>Controlo de Gastos</strong> - Regista e categoriza as tuas transacoes
                </div>
                <div class="feature-item">
                    <span class="feature-icon">3</span>
                    <strong>Orcamentos</strong> - Define limites e controla os teus gastos
                </div>
                <div class="feature-item">
                    <span class="feature-icon">4</span>
                    <strong>Lembretes</strong> - Recebe notificacoes de pagamentos
                </div>
            </div>

            <div class="btn-container">
                <a href="{$dashboardUrl}" class="btn-primary">Aceder ao Dashboard</a>
            </div>

            <div class="message">
                Se tiveres duvidas ou precisares de ajuda, nao hesites em contactar o nosso suporte.
            </div>
        </div>

        {$footer}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template de recuperação de password
     */
    private function getPasswordResetTemplate($username, $resetLink) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Recuperar Palavra-passe');
        $footer = $this->getEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>{$styles}</style>
</head>
<body>
    <div class="email-container">
        {$header}

        <div class="email-content">
            <div class="greeting">
                Ola <strong>{$username}</strong>,
            </div>

            <div class="message">
                Recebemos um pedido para recuperar a tua palavra-passe no FreeCard. Se foste tu que solicitaste, clica no botao abaixo para definir uma nova palavra-passe.
            </div>

            <div class="btn-container">
                <a href="{$resetLink}" class="btn-primary">Definir Nova Palavra-passe</a>
            </div>

            <div class="link-text">
                Ou copia e cola este link no teu navegador:<br>
                <a href="{$resetLink}">{$resetLink}</a>
            </div>

            <div class="info-box warning">
                <div class="info-box-title">Link temporario</div>
                <div class="info-box-text">Por motivos de seguranca, este link e valido apenas durante 1 hora.</div>
            </div>

            <div class="info-box">
                <div class="info-box-title">Nao solicitaste esta recuperacao?</div>
                <div class="info-box-text">Se nao foste tu que solicitaste a recuperacao de palavra-passe, ignora este email. A tua conta permanece segura.</div>
            </div>

            <div class="small-text">
                Por seguranca, nunca partilhes este link com ninguem.
            </div>
        </div>

        {$footer}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template de lembrete de pagamento
     */
    private function getPaymentReminderTemplate($username, $reminderData) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Lembrete de Pagamento');
        $footer = $this->getEmailFooter();
        $remindersUrl = $this->baseUrl . '/pages/reminders.php';

        $urgencyClass = $reminderData['days_until'] <= 1 ? 'danger' : ($reminderData['days_until'] <= 3 ? 'warning' : '');
        $cardInfo = isset($reminderData['card_name']) && $reminderData['card_name']
            ? $reminderData['card_name']
            : 'Dinheiro';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>{$styles}</style>
</head>
<body>
    <div class="email-container">
        {$header}

        <div class="email-content">
            <div class="greeting">
                Ola <strong>{$username}</strong>,
            </div>

            <div class="message">
                Tens um pagamento a vencer em breve. Aqui estao os detalhes:
            </div>

            <div class="data-card">
                <div class="data-row">
                    <span class="data-label">Pagamento</span>
                    <span class="data-value">{$reminderData['name']}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Valor</span>
                    <span class="data-value highlight">EUR {$reminderData['amount']}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Data de Vencimento</span>
                    <span class="data-value">{$reminderData['due_date']}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Dias Restantes</span>
                    <span class="data-value {$urgencyClass}">{$reminderData['days_until']} dias</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Categoria</span>
                    <span class="data-value">{$reminderData['category']}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Metodo de Pagamento</span>
                    <span class="data-value">{$cardInfo}</span>
                </div>
            </div>

            <div class="btn-container">
                <a href="{$remindersUrl}" class="btn-primary">Ver Lembretes</a>
            </div>

            <div class="message">
                Podes gerir os teus lembretes e marcar pagamentos como concluidos no teu dashboard.
            </div>
        </div>

        {$footer}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template de alerta de orçamento
     */
    private function getBudgetAlertTemplate($username, $budgetData) {
        $styles = $this->getBaseStyles();
        $footer = $this->getEmailFooter();
        $budgetsUrl = $this->baseUrl . '/pages/budgets.php';

        $isExceeded = $budgetData['percentage'] >= 100;
        $headerTitle = $isExceeded ? 'Orcamento Excedido' : 'Alerta de Orcamento';
        $header = $this->getEmailHeader($headerTitle);

        $progressColor = $isExceeded ? '#e74c3c' : ($budgetData['percentage'] >= 80 ? '#f39c12' : '#27ae60');
        $progressWidth = min($budgetData['percentage'], 100);
        $statusClass = $isExceeded ? 'danger' : 'warning';
        $statusText = $isExceeded ? 'Excedido' : 'Em alerta';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>{$styles}</style>
</head>
<body>
    <div class="email-container">
        {$header}

        <div class="email-content">
            <div class="greeting">
                Ola <strong>{$username}</strong>,
            </div>

            <div class="message">
                O teu orcamento <strong>{$budgetData['name']}</strong> atingiu um nivel que requer a tua atencao.
            </div>

            <div class="data-card">
                <div class="data-row">
                    <span class="data-label">Orcamento</span>
                    <span class="data-value">{$budgetData['name']}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Estado</span>
                    <span class="data-value {$statusClass}">{$statusText}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Limite Definido</span>
                    <span class="data-value">EUR {$budgetData['limit']}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Valor Gasto</span>
                    <span class="data-value {$statusClass}">EUR {$budgetData['amount_spent']}</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Categoria</span>
                    <span class="data-value">{$budgetData['category']}</span>
                </div>

                <div style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span class="data-label">Utilizacao</span>
                        <span class="data-value {$statusClass}">{$budgetData['percentage']}%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: {$progressWidth}%; background: {$progressColor};"></div>
                    </div>
                </div>
            </div>

            <div class="info-box {$statusClass}">
                <div class="info-box-title">O que podes fazer</div>
                <div class="info-box-text">Revisa os teus gastos recentes e considera ajustar o teu orcamento ou reduzir despesas nesta categoria.</div>
            </div>

            <div class="btn-container">
                <a href="{$budgetsUrl}" class="btn-primary">Ver Orcamentos</a>
            </div>
        </div>

        {$footer}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template de verificação de alteração de email
     */
    private function getEmailChangeTemplate($username, $newEmail, $verificationLink) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Confirmar Novo Email');
        $footer = $this->getEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>{$styles}</style>
</head>
<body>
    <div class="email-container">
        {$header}

        <div class="email-content">
            <div class="greeting">
                Ola <strong>{$username}</strong>,
            </div>

            <div class="message">
                Recebemos um pedido para alterar o email da tua conta FreeCard para este endereco: <strong>{$newEmail}</strong>
            </div>

            <div class="message">
                Para confirmar esta alteracao, clica no botao abaixo.
            </div>

            <div class="btn-container">
                <a href="{$verificationLink}" class="btn-primary">Confirmar Novo Email</a>
            </div>

            <div class="link-text">
                Ou copia e cola este link no teu navegador:<br>
                <a href="{$verificationLink}">{$verificationLink}</a>
            </div>

            <div class="info-box warning">
                <div class="info-box-title">Link temporario</div>
                <div class="info-box-text">Por motivos de seguranca, este link e valido apenas durante 1 hora.</div>
            </div>

            <div class="info-box">
                <div class="info-box-title">Nao solicitaste esta alteracao?</div>
                <div class="info-box-text">Se nao foste tu que solicitaste a alteracao de email, ignora este email. A tua conta permanece segura com o email atual.</div>
            </div>
        </div>

        {$footer}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template de notificação de alteração de password
     */
    private function getPasswordChangedTemplate($username) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Password Alterada');
        $footer = $this->getEmailFooter();
        $settingsUrl = $this->baseUrl . '/pages/settings.php';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>{$styles}</style>
</head>
<body>
    <div class="email-container">
        {$header}

        <div class="email-content">
            <div class="greeting">
                Ola <strong>{$username}</strong>,
            </div>

            <div class="message">
                A tua password foi alterada com sucesso.
            </div>

            <div class="info-box success">
                <div class="info-box-title">Alteracao confirmada</div>
                <div class="info-box-text">A tua nova password esta ativa. Usa-a na proxima vez que fizeres login.</div>
            </div>

            <div class="info-box danger">
                <div class="info-box-title">Nao foste tu?</div>
                <div class="info-box-text">Se nao fizeste esta alteracao, a tua conta pode estar comprometida. Contacta o nosso suporte imediatamente.</div>
            </div>

            <div class="btn-container">
                <a href="{$settingsUrl}" class="btn-primary">Ver Definicoes da Conta</a>
            </div>

            <div class="small-text">
                Este email foi enviado automaticamente para te manter informado sobre alteracoes na tua conta.
            </div>
        </div>

        {$footer}
    </div>
</body>
</html>
HTML;
    }
}
?>
