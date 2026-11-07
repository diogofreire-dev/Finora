# Sistema de email — Finora

A Finora envia os emails pela **API da Brevo** (HTTP, sem SMTP nem PHPMailer). Todo o envio passa por
`config/EmailService.php`.

## Que emails existem

| Email | Quem o pede | Quando |
|---|---|---|
| Código de verificação | `site/auth/register.php`, `site/auth/verify.php` | Ao criar conta / pedir outro código |
| Boas-vindas | `site/auth/verify.php` | Depois de confirmar o email |
| Recuperar palavra-passe | `site/auth/forgot_password.php` | Link válido 1 hora |
| Confirmar email novo | `site/pages/settings.php` | Mudança de email |
| Palavra-passe alterada / conta apagada | `site/pages/settings.php` | Avisos de segurança |
| Alerta de orçamento (80 % / 100 %) | `site/helpers/budget_alert_helper.php` | Ao abrir o dashboard |
| Lembrete de pagamento | `cron/send_reminders.php` | Todos os dias às 08:00 (cron) |

Os emails saem na língua de cada utilizador (`t()` / `$this->L()`, ver `site/helpers/i18n.php`) e os
nomes vindos da base de dados são escapados antes de entrarem no HTML.

## Configuração

Tudo em `config/brevo.php` (ignorado pelo git; o modelo é `config/brevo.example.php`):

```php
define('BREVO_API_KEY', '…');                      // chave restrita: só "E-mails transacionais"
define('APP_URL', 'https://<dominio>/site');        // base dos links nos emails
// Opcional — senão usa o remetente por omissão de EmailService.php:
// define('MAIL_FROM_EMAIL', 'remetente@exemplo.com');
// define('MAIL_FROM_NAME', 'Finora');
```

### O que a conta Brevo precisa de ter

1. **Remetente validado** — *Settings → Remetentes, domínio, IPs → Remetentes*. Tem de ser o mesmo
   endereço que a Finora usa. Se não for, a API responde 201 mas a Brevo recusa o envio depois
   ("sender … is not valid") e o email nunca chega.
2. **IP do servidor autorizado** — *Settings → Segurança → IPs autorizados*. Sem isto a API responde
   401 "unrecognised IP address".
3. **Chave restrita** — *SMTP & API → Chaves API*, só com acesso a e-mails transacionais.

Um remetente `@gmail.com` funciona, mas parte dos emails pode ir para o spam (o domínio não está
autenticado). O ideal é autenticar um domínio próprio na Brevo (DKIM/SPF).

## Diagnóstico

- Erros do pedido à API: log do Apache (`/var/log/apache2/finora-error.log`), linhas `Brevo API Error`.
- O que aconteceu depois (entregue, recusado, bounce): na Brevo, *Transacional → Logs*, ou pela API
  `GET /v3/smtp/statistics/events`.
- Lembretes do cron: `/var/log/finora/cron.log` no servidor.
