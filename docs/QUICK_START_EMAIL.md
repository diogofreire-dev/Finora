# Quick Start - Email

Passos rápidos para testar o sistema de email localmente:

1. Garante que tens o Composer instalado e as dependências (PHPMailer)

```bash
composer install
```

2. Configura `config/db.php` com as tuas credenciais MySQL
3. Copia `config/brevo.example.php` para `config/brevo.php` e põe a tua chave da Brevo (ver `docs/EMAIL_SYSTEM.md`)
4. Testa envio direto:

```bash
php DIRECT_EMAIL_TEST.php
```

5. Testa o cron (lembretes):

```bash
php cron/send_reminders.php
```

6. Testa o registo (welcome email):

```bash
php SIMULATE_REGISTRATION.php
```

Notas:
- Substitui `seu-email-real@gmail.com` em scripts de teste pelo teu email real.
- Verifica a pasta Spam se não receberes o email.
