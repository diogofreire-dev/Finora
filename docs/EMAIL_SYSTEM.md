# Sistema de Email - FreeCard

## Visão Geral

Sistema automático de notificações por email integrado no FreeCard para:
- Lembretes de pagamento antes do vencimento
- Alertas de orçamento excedido
- Recuperação de palavra-passe
- Boas-vindas a novos utilizadores

---

## Ficheiros do Sistema

### **Configuração**
- `config/EmailService.php` - Serviço central de email com PHPMailer
- `vendor/autoload.php` - Autoload do Composer (PHPMailer)

### **Automatização**
- `cron/send_reminders.php` - Cron job para enviar lembretes diários
- `cron/send_reminders.log` - Log de execução do cron

### **Integração no Site**
- `site/register.php` - Envia email de boas-vindas ao registar
- `site/forgot_password.php` - Envia link de reset de password
- `site/reset_password.php` - Valida e processa reset de password

### **Base de Dados**
- `database/schema.sql` - Tabelas incluindo `payment_reminders` com `last_notification_sent`

---

##  Configuração Técnica

### **Credenciais de Email**
```php
// config/EmailService.php
$this->fromEmail = 'freecardadmin@gmail.com';
$this->appPassword = 'aanu cxbs zehl bddy'; // App Password do Gmail
```

**Como gerar App Password (Gmail):**
1. Ir a https://myaccount.google.com/apppasswords
2. Ativar verificação em 2 etapas
3. Gerar App Password para "Mail"
4. Copiar a password para `EmailService.php`

### **Servidor SMTP**
- Host: `smtp.gmail.com`
- Porta: `587`
- Encriptação: `STARTTLS`
- Autenticação: Habilitada

---

##  Como Funciona

### **1. Lembretes de Pagamento**

**Fluxo:**
```
Cron Job (09:00 diariamente)
  ↓
Busca lembretes vencendo em X dias
  ↓
Verifica se já foi enviado email hoje
  ↓
Envia email ao utilizador
  ↓
Atualiza last_notification_sent
```

**Exemplo:**
- Lembrete: Netflix € 15.99 vence em 3 dias
- Se ainda não foi enviado email hoje → Envia notificação
- Utilizador recebe email com detalhes do pagamento

### **2. Alertas de Orçamento**

**Trigger (na página de dashboard ou analytics):**
```php
// Quando orçamento atinge 80% ou 100%
if ($percentage >= 80) {
    $emailService->sendBudgetAlert($email, $username, $budgetData);
}
```

### **3. Recuperação de Password**

**Fluxo em `forgot_password.php`:**
1. Utilizador insere email
2. Gera token único com validade de 1 hora
3. Envia email com link: `reset_password.php?token=...`
4. Utilizador clica no link e redefine password

### **4. Email de Boas-vindas**

**Fluxo em `register.php`:**
1. Utilizador cria conta
2. Imediatamente envia email de boas-vindas
3. Email inclui link para dashboard

---

##  Configurar Cron Job

### **Linux/cPanel:**
```bash
# Editar crontab
crontab -e

# Adicionar linha (executa diariamente às 09:00)
0 9 * * * /usr/bin/php /caminho/para/cron/send_reminders.php >> /var/log/freecard-cron.log 2>&1
```

### **Windows (Agendador de Tarefas):**
```
Programa: C:\xampp\php\php.exe
Argumentos: C:\xampp\htdocs\PAP\FreeCard\cron\send_reminders.php
Repetir diariamente às 09:00
```

### **Testar Localmente:**
```bash
cd C:\xampp\htdocs\PAP\FreeCard
php cron/send_reminders.php
```

**Output esperado:**
```
[2026-01-22 11:10:07] === CRON JOB INICIADO ===
[2026-01-22 11:10:07] Total de lembretes a enviar: 2
[2026-01-22 11:10:10] ✓ Email enviado para user1 - Netflix (vence em 3 dias)
[2026-01-22 11:10:10] ✓ Email enviado para user2 - Renda (vence em 7 dias)
[2026-01-22 11:10:10] === CRON JOB FINALIZADO ===
[2026-01-22 11:10:10] Lembretes enviados: 2
```

---

##  Métodos Disponíveis

### **EmailService::sendPaymentReminder()**
```php
$emailService->sendPaymentReminder(
    $email,           // Email do utilizador
    $username,        // Nome do utilizador
    $reminderData      // Array com dados do lembrete
);
```

**Dados do lembrete:**
```php
[
    'name' => 'Netflix',
    'amount' => '15,99',
    'category' => 'Entretenimento',
    'due_date' => '27/01/2026',
    'days_until' => 3,
    'card_name' => 'Visa Principal' // ou null para dinheiro
]
```

### **EmailService::sendBudgetAlert()**
```php
$emailService->sendBudgetAlert(
    $email,
    $username,
    $budgetData
);
```

**Dados do orçamento:**
```php
[
    'name' => 'Orçamento Mensal',
    'limit' => '1.000,00',
    'amount_spent' => '850,00',
    'percentage' => 85
]
```

### **EmailService::sendPasswordResetEmail()**
```php
$emailService->sendPasswordResetEmail($email, $username, $token);
```

### **EmailService::sendVerificationEmail()**
```php
$emailService->sendVerificationEmail($email, $username, $token);
```

### **EmailService::sendWelcomeEmail()**
```php
$emailService->sendWelcomeEmail($email, $username);
```

---

##  Verificar Status
