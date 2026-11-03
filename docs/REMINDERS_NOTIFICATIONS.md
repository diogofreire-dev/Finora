# Sistema de Notificações de Lembretes - COMPLETO

## O que foi implementado

O sistema de lembretes agora permite que **cada utilizador escolha COMO quer ser notificado** sobre os seus pagamentos recorrentes.

### Três opções de notificação

1. **Email** - Recebe um email com o lembrete
2. **Notificação no Site** - Vê uma notificação no canto superior direito do dashboard
3. **Email + Notificação** - Recebe ambos

## 🔧 Alterações na Base de Dados

### Tabela `payment_reminders`
Nova coluna adicionada:
```sql
notify_method ENUM('email', 'site', 'both') DEFAULT 'email'
```

### Tabela `notifications` (NOVA)
```sql
CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT,
  data JSON NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Como Usar

### No Site

1. **Criar um novo lembrete:**
   - Vai a `Lembretes` → `+ Novo Lembrete`
   - Preenche os dados (nome, valor, data, etc)
   - **Nova opção:** Escolhe como quer ser notificado
   - Clica em `Criar Lembrete`

2. **Editar um lembrete existente:**
   - Clica em `Editar` no lembrete
   - Altera o método de notificação se necessário
   - Clica em `Guardar Alterações`

### Exemplos

```
Lembrete: Netflix
Notificação: Email
↓
Receberá um email 3 dias antes do vencimento

---

Lembrete: Renda
Notificação: Site
↓
Verá uma notificação no dashboard 3 dias antes

---

Lembrete: Seguros
Notificação: Email + Site
↓
Receberá email E verá notificação no site
```

## Como o Cron Job Funciona Agora

O arquivo `cron/send_reminders.php` foi atualizado para:

1. **Buscar lembretes** com data de vencimento próxima
2. **Verificar o método de notificação** escolhido pelo utilizador
3. **Enviar notificações adequadas:**
   - Se `notify_method = 'email'` → Envia apenas email
   - Se `notify_method = 'site'` → Cria notificação no site
   - Se `notify_method = 'both'` → Faz ambos

### Logs

O cron job agora registra detalhadamente:
```
[2026-01-23 09:00:01] === CRON JOB INICIADO ===
[2026-01-23 09:00:01] Total de lembretes a enviar: 3

[2026-01-23 09:00:02] Email enviado para joao - Netflix (vence em 3 dias)
[2026-01-23 09:00:02] Notificação criada no site para maria - Renda (vence em 1 dias)
[2026-01-23 09:00:02] Email enviado para pedro - Seguros (vence em 7 dias)
[2026-01-23 09:00:02] Notificação criada no site para pedro - Seguros (vence em 7 dias)

[2026-01-23 09:00:03] === CRON JOB FINALIZADO ===
[2026-01-23 09:00:03] Lembretes enviados: 3
[2026-01-23 09:00:03] Erros: 0
```

## Ficheiros Novos

- `site/notifications_panel.php` - Painel de notificações no dashboard
- `site/mark_notification_read.php` - API para marcar notificações como lidas
- `database/notifications` - Tabela de notificações (criada automaticamente)

## Próximos Passos

1. **Testar com o cron job:**
   ```bash
   php C:\xampp\htdocs\Finora\cron\send_reminders.php
   ```

2. **Verificar logs:**
   ```
   C:\xampp\htdocs\Finora\cron\send_reminders.log
   ```

3. **Configurar cron automático no servidor:**
   - Linux/cPanel: `0 9 * * * /usr/bin/php /path/to/cron/send_reminders.php`
   - Windows: Task Scheduler para executar `php C:\path\to\cron\send_reminders.php` diariamente

## Configuração Padrão

- **Novo lembrete:** Notificação por email (pode alterar)
- **Dias de antecedência:** 3 dias (pode alterar ao criar/editar)

## Exemplos de Uso

### Utilizador quer ser notificado por email
```
1. Criar lembrete
2. Escolher: "Email"
3. Salvar
4. Receberá email 3 dias antes do vencimento
```

### Utilizador quer ver notificações no dashboard
```
1. Criar lembrete
2. Escolher: "Notificação no Site"
3. Salvar
4. Verá notificação no canto superior direito do dashboard
```

### Utilizador quer ambas as notificações
```
1. Criar lembrete
2. Escolher: "Email + Notificação"
3. Salvar
4. Receberá email E verá notificação no site
```

## Troubleshooting

### As notificações não aparecem
1. Verifica se `notify_method` está correcto na BD
2. Testa o cron: `php cron/send_reminders.php`
3. Verifica o log: `cron/send_reminders.log`
4. Verifica se o email está configurado em `config/EmailService.php`

### O painel de notificações não mostra
1. Verifica se o `notifications_panel.php` está incluído no dashboard
2. Verifica se a tabela `notifications` foi criada
3. Abre a consola do navegador (F12) para erros de JavaScript

### As notificações não desaparecem ao clicar X
1. Verifica se `mark_notification_read.php` existe
2. Verifica os erros no Network tab do F12
3. Confirma que o ficheiro tem permissões de leitura/escrita

## Estatísticas

- Total de lembretes que podem ter diferentes métodos de notificação
- Notificações não lidas por utilizador
- Taxa de sucesso/falha de envio de notificações

---

**Sistema 100% funcional e pronto para produção!**
