<?php
// config/EmailService.php
// Versao para InfinityFree - usa Brevo API (HTTP) em vez de SMTP

class EmailService {
    private $apiKey = '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';
    private $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    private $fromEmail = 'freecardssuporte@gmail.com';
    private $fromName = 'FreeCard';

    // URL base do site
    private $baseUrl = 'http://freecard.infinityfreeapp.com/site';

    public function __construct() {
        // Nao precisa de configuracao
    }

    /**
     * Enviar email usando Brevo API
     */
    private function sendMail($to, $subject, $htmlBody, $textBody = '') {
        $data = [
            'sender' => [
                'name' => $this->fromName,
                'email' => $this->fromEmail
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlBody
        ];

        if (!empty($textBody)) {
            $data['textContent'] = $textBody;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $this->apiKey,
            'content-type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Brevo CURL Error: " . $error);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log("Brevo API Error: HTTP $httpCode - $response");
            return false;
        }
    }

    /**
     * Estilos CSS base para todos os emails
     */
    private function getBaseStyles() {
        return '
        body { font-family: Arial, Helvetica, sans-serif; background-color: #f8f9fa; padding: 20px; margin: 0; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #cadef7; border-radius: 12px; }
        .email-header { background-color: #1e3c72; padding: 30px 20px; text-align: center; border-radius: 12px 12px 0 0; }
        .logo { font-size: 28px; font-weight: bold; color: #ffffff; text-decoration: none; letter-spacing: 1px; }
        .email-title { color: #ffffff; font-size: 20px; margin-top: 15px; font-weight: 400; }
        .email-content { padding: 40px 30px; }
        .greeting { font-size: 18px; color: #2c3e50; margin-bottom: 20px; }
        .message { color: #5d6778; line-height: 1.7; margin-bottom: 20px; font-size: 15px; }
        .btn-container { text-align: center; margin: 30px 0; }
        .btn-primary { display: inline-block; background-color: #3498db; color: #ffffff !important; padding: 14px 40px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
        .info-box { background-color: #f8f9fa; padding: 15px 20px; margin: 20px 0; border-radius: 8px; }
        .info-box.warning { background-color: #fef9e7; }
        .info-box.danger { background-color: #fdf2f2; }
        .info-box.success { background-color: #f0fdf4; }
        .info-box-title { font-weight: 600; color: #2c3e50; margin-bottom: 5px; font-size: 14px; }
        .info-box-text { color: #5d6778; font-size: 13px; }
        .email-footer { background: #2c3e50; padding: 25px; text-align: center; border-radius: 0 0 12px 12px; }
        .footer-logo { color: #ffffff; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .footer-text { color: #95a5a6; font-size: 12px; line-height: 1.6; }
        .small-text { color: #95a5a6; font-size: 12px; margin-top: 20px; }
        .code-box { background-color: #3498db; color: #ffffff; font-size: 32px; font-weight: 700; letter-spacing: 8px; padding: 20px 30px; border-radius: 12px; display: inline-block; font-family: monospace; }
        ';
    }

    private function getEmailHeader($title) {
        return '<div class="email-header"><div class="logo">FreeCard</div><div class="email-title">' . $title . '</div></div>';
    }

    private function getEmailFooter() {
        return '<div class="email-footer"><div class="footer-logo">FreeCard</div><div class="footer-text">Gestor de Cartoes de Credito<br>Este email foi enviado automaticamente.<br>&copy; 2025 FreeCard.</div></div>';
    }

    /**
     * Enviar email de verificacao de conta com codigo
     */
    public function sendVerificationEmail($email, $username, $code) {
        try {
            $subject = 'Codigo de verificacao - FreeCard';
            $body = $this->getVerificationTemplate($username, $code);
            return $this->sendMail($email, $subject, $body);
        } catch (Exception $e) {
            error_log("Erro ao enviar email de verificacao: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar email de boas-vindas
     */
    public function sendWelcomeEmail($email, $username) {
        try {
            $subject = 'Bem-vindo ao FreeCard!';
            $body = $this->getWelcomeTemplate($username);
            return $this->sendMail($email, $subject, $body);
        } catch (Exception $e) {
            error_log("Erro ao enviar email de boas-vindas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar email de recuperacao de palavra-passe
     */
    public function sendPasswordResetEmail($email, $username, $token) {
        try {
            $subject = 'Recuperar palavra-passe - FreeCard';
            $resetLink = $this->baseUrl . "/auth/reset_password.php?token=" . urlencode($token);
            $body = $this->getPasswordResetTemplate($username, $resetLink);
            return $this->sendMail($email, $subject, $body);
        } catch (Exception $e) {
            error_log("Erro ao enviar email de reset: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar lembrete de pagamento
     */
    public function sendPaymentReminder($email, $username, $reminderData) {
        try {
            $subject = "Lembrete: {$reminderData['name']} vence em {$reminderData['days_until']} dias";
            $body = $this->getPaymentReminderTemplate($username, $reminderData);
            return $this->sendMail($email, $subject, $body);
        } catch (Exception $e) {
            error_log("Erro ao enviar lembrete de pagamento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar alerta de orcamento excedido
     */
    public function sendBudgetAlert($email, $username, $budgetData) {
        try {
            $alertType = $budgetData['percentage'] >= 100 ? 'excedido' : 'em alerta';
            $subject = "Orcamento {$alertType}: {$budgetData['name']} ({$budgetData['percentage']}%)";
            $body = $this->getBudgetAlertTemplate($username, $budgetData);
            return $this->sendMail($email, $subject, $body);
        } catch (Exception $e) {
            error_log("Erro ao enviar alerta de orcamento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar email de verificacao de alteracao de email
     */
    public function sendEmailChangeVerification($newEmail, $username, $token) {
        try {
            $subject = 'Confirma a alteracao de email - FreeCard';
            $verificationLink = $this->baseUrl . "/auth/verify_email_change.php?token=" . urlencode($token);
            $body = $this->getEmailChangeTemplate($username, $newEmail, $verificationLink);
            return $this->sendMail($newEmail, $subject, $body);
        } catch (Exception $e) {
            error_log("Erro ao enviar verificacao de email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificacao de alteracao de password
     */
    public function sendPasswordChangedNotification($email, $username) {
        try {
            $subject = 'Password alterada - FreeCard';
            $body = $this->getPasswordChangedTemplate($username);
            return $this->sendMail($email, $subject, $body);
        } catch (Exception $e) {
            error_log("Erro ao enviar notificacao de password: " . $e->getMessage());
            return false;
        }
    }

    // =====================
    // TEMPLATES
    // =====================

    private function getVerificationTemplate($username, $code) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Confirmar Conta');
        $footer = $this->getEmailFooter();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $styles . '</style></head><body>
        <div class="email-container">' . $header . '
        <div class="email-content">
            <div class="greeting">Ola <strong>' . $username . '</strong>,</div>
            <div class="message">Obrigado por te registares no FreeCard! Para ativares a tua conta, introduz o codigo de verificacao abaixo:</div>
            <div style="text-align:center;margin:30px 0;"><div class="code-box">' . $code . '</div></div>
            <div class="message" style="text-align:center;color:#7f8c8d;">Introduz este codigo na pagina de verificacao.</div>
            <div class="info-box warning"><div class="info-box-title">Codigo temporario</div><div class="info-box-text">Este codigo e valido durante 24 horas.</div></div>
            <div class="small-text">Se nao criaste uma conta no FreeCard, ignora este email.</div>
        </div>' . $footer . '</div></body></html>';
    }

    private function getWelcomeTemplate($username) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Conta Verificada');
        $footer = $this->getEmailFooter();
        $dashboardUrl = $this->baseUrl . '/dashboard.php';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $styles . '</style></head><body>
        <div class="email-container">' . $header . '
        <div class="email-content">
            <div class="greeting">Ola <strong>' . $username . '</strong>,</div>
            <div class="message">A tua conta foi verificada com sucesso! Bem-vindo ao FreeCard.</div>
            <div class="btn-container"><a href="' . $dashboardUrl . '" class="btn-primary">Ir para o FreeCard</a></div>
        </div>' . $footer . '</div></body></html>';
    }

    private function getPasswordResetTemplate($username, $resetLink) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Recuperar Palavra-passe');
        $footer = $this->getEmailFooter();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $styles . '</style></head><body>
        <div class="email-container">' . $header . '
        <div class="email-content">
            <div class="greeting">Ola <strong>' . $username . '</strong>,</div>
            <div class="message">Recebemos um pedido para recuperar a tua palavra-passe. Clica no botao abaixo:</div>
            <div class="btn-container"><a href="' . $resetLink . '" class="btn-primary">Definir Nova Palavra-passe</a></div>
            <div class="info-box warning"><div class="info-box-title">Link temporario</div><div class="info-box-text">Este link e valido apenas durante 1 hora.</div></div>
        </div>' . $footer . '</div></body></html>';
    }

    private function getPaymentReminderTemplate($username, $reminderData) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Lembrete de Pagamento');
        $footer = $this->getEmailFooter();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $styles . '</style></head><body>
        <div class="email-container">' . $header . '
        <div class="email-content">
            <div class="greeting">Ola <strong>' . $username . '</strong>,</div>
            <div class="message">Tens um pagamento a vencer em breve:</div>
            <div class="info-box"><strong>' . $reminderData['name'] . '</strong><br>Valor: EUR ' . $reminderData['amount'] . '<br>Data: ' . $reminderData['due_date'] . '<br>Dias restantes: ' . $reminderData['days_until'] . '</div>
        </div>' . $footer . '</div></body></html>';
    }

    private function getBudgetAlertTemplate($username, $budgetData) {
        $styles = $this->getBaseStyles();
        $isExceeded = $budgetData['percentage'] >= 100;
        $header = $this->getEmailHeader($isExceeded ? 'Orcamento Excedido' : 'Alerta de Orcamento');
        $footer = $this->getEmailFooter();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $styles . '</style></head><body>
        <div class="email-container">' . $header . '
        <div class="email-content">
            <div class="greeting">Ola <strong>' . $username . '</strong>,</div>
            <div class="message">O teu orcamento <strong>' . $budgetData['name'] . '</strong> atingiu ' . $budgetData['percentage'] . '%.</div>
            <div class="info-box ' . ($isExceeded ? 'danger' : 'warning') . '">Limite: EUR ' . $budgetData['limit'] . '<br>Gasto: EUR ' . $budgetData['amount_spent'] . '</div>
        </div>' . $footer . '</div></body></html>';
    }

    private function getEmailChangeTemplate($username, $newEmail, $verificationLink) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Confirmar Novo Email');
        $footer = $this->getEmailFooter();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $styles . '</style></head><body>
        <div class="email-container">' . $header . '
        <div class="email-content">
            <div class="greeting">Ola <strong>' . $username . '</strong>,</div>
            <div class="message">Recebemos um pedido para alterar o teu email para: <strong>' . $newEmail . '</strong></div>
            <div class="btn-container"><a href="' . $verificationLink . '" class="btn-primary">Confirmar Novo Email</a></div>
            <div class="info-box warning"><div class="info-box-title">Link temporario</div><div class="info-box-text">Este link e valido apenas durante 1 hora.</div></div>
        </div>' . $footer . '</div></body></html>';
    }

    private function getPasswordChangedTemplate($username) {
        $styles = $this->getBaseStyles();
        $header = $this->getEmailHeader('Password Alterada');
        $footer = $this->getEmailFooter();

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $styles . '</style></head><body>
        <div class="email-container">' . $header . '
        <div class="email-content">
            <div class="greeting">Ola <strong>' . $username . '</strong>,</div>
            <div class="message">A tua password foi alterada com sucesso.</div>
            <div class="info-box success"><div class="info-box-title">Alteracao confirmada</div><div class="info-box-text">A tua nova password esta ativa.</div></div>
            <div class="info-box danger"><div class="info-box-title">Nao foste tu?</div><div class="info-box-text">Se nao fizeste esta alteracao, contacta o suporte imediatamente.</div></div>
        </div>' . $footer . '</div></body></html>';
    }
}
?>
