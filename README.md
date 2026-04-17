# FreeCard
Sistema completo de gestão de cartões de crédito e transações pessoais desenvolvido em PHP/MySQL como Projeto de Aptidão Profissional.
Link: http://freecard.infinityfreeapp.com/

## Funcionalidades

### Autenticação e Segurança
- Sistema completo de autenticação (Login/Registo/Logout)
- Passwords encriptadas com bcrypt
- Prepared statements (PDO) para prevenir SQL Injection
- Proteção CSRF através de sessions
- Validação server-side de todos os inputs
- Verificação de conta por código via email
- Recuperação de palavra-passe por email
- Alteração de email com verificação
- Notificação de alteração de password

### Gestão de Cartões
- Adicionar, editar, ativar/desativar e eliminar cartões
- Escolha de 8 cores diferentes para personalização
- Acompanhamento de limites e saldos em tempo real
- Alertas automáticos quando perto do limite (>80%)
- Pré-visualização ao vivo ao criar/editar cartões
- Estatísticas por cartão (total gasto, número de transações)

### Gestão de Transações
- Registar despesas com descrição, valor e categoria
- 8 categorias pré-definidas (Compras, Alimentação, Transporte, Saúde, Entretenimento, Educação, Casa, Outros)
- Associar transações a cartões específicos ou dinheiro
- Editar e eliminar transações
- Atualização automática dos saldos dos cartões
- Filtrar por mês, categoria e cartão
- Exportação de transações para PDF

### Gestão de Orçamentos
- Criar orçamentos mensais, semanais ou anuais
- Definir limite de gastos por categoria ou cartão
- Acompanhamento em tempo real do progresso
- Alertas automáticos quando atingir 80% do orçamento
- Alertas quando orçamento for excedido
- Histórico de alertas enviados

### Lembretes de Pagamento
- Criar lembretes para pagamentos recorrentes
- Notificações por email automáticas
- Configurar dias de antecedência paraavisos
- Histórico de lembretes enviados
- Sistema automatizado via CRON

### Sistema de Notificações
- Notificações dentro da aplicação
- Alertas de limites de cartões
- Alertas de orçamentos
- Lembretes de pagamentos
- Marcar como lido
- Painel de notificações

### Dashboard Intuitivo
- Resumo mensal de gastos com tendência vs. mês anterior
- Visualização de cartões e limites
- Últimas transações
- Categoria top do mês
- Maior despesa do mês
- Sistema de alertas para limites dos cartões
- Gráficos de gastos por categoria

### Análise e Relatórios
- Gráfico de evolução mensal por categoria
- Gráfico de barras de total por categoria
- Gráfico de pizza de distribuição por cartão
- Top 5 maiores gastos
- Estatísticas detalhadas (total gasto, média, número de transações)
- Filtros por ano e cartão
- Exportação de relatórios em PDF

### Sistema de Emails
- Email de boas-vindas após verificação
- Email de verificação de conta com código
- Email de recuperação de palavra-passe
- Email de confirmação de alteração de email
- Email de notificação de alteração de password
- Emails de lembretes de pagamento
- Emails de alertas de orçamento
- Template profissional com branding

### Personalização
- **Tema Claro/Escuro**: Sistema completo de temas com transições suaves
- Notificações configuráveis
- Interface totalmente responsiva

## Tecnologias

### Backend
- **PHP 8+** - Linguagem de programação server-side
- **PDO (PHP Data Objects)** - Acesso unificado à base de dados
- **bcrypt** - Encriptação de passwords segura

### Base de Dados
- **MySQL** - Sistema de gestão de base de dados relacional

### Frontend
- **HTML5** - Linguagem de marcação
- **CSS3** - Estilos e design responsivo
- **JavaScript (Vanilla)** - Interatividade client-side (validação, animações, preview, Chart.js)
- **Bootstrap JS** - Componentes interativos (modais, dropdowns, tooltips)

### Frameworks & Bibliotecas
- **Bootstrap 5** - Framework CSS para design responsivo
- **Bootstrap Icons** - Biblioteca de ícones
- **Chart.js** - Biblioteca para gráficos interativos

### APIs & Serviços
- **Brevo API** (anteriormente Sendinblue) - Serviço de emails transacionais
- **PHPMailer** - Biblioteca para envio de emails via SMTP

### Bibliotecas PHP
- **TCPDF** - Geração de documentos PDF

### Segurança
- **Prepared Statements (PDO)** - Prevenção de SQL Injection
- **Session Security** - Gestão segura de sessões
- **CSRF Protection** - Proteção contra ataques CSRF
- **XSS Prevention** - Proteção contra Cross-Site Scripting

### Ferramentas de Desenvolvimento
- **PHP Built-in Server** - Servidor de desenvolvimento
- **CRON** - Automação de tarefas (lembretes)
- **phpMyAdmin** (opcional) - Gestão da base de dados

## Instalação

### 1. Clonar o Repositório
```
bash
git clone https://github.com/diogofreire-dev/FreeCard.git
cd FreeCard
```

### 2. Configurar Base de Dados

Cria a base de dados executando o schema:
```
bash
mysql -u root -p < database/schema.sql
```

Ou importa manualmente através do phpMyAdmin/Adminer.

O schema inclui todas as tabelas:
- users
- cards
- transactions
- user_settings
- budgets
- budget_alerts
- payment_reminders
- reminder_history
- notifications

### 3. Configurar Ligação à BD

Edita `config/db.php` com as tuas credenciais:
```
php
<?php
return [
    'host' => '127.0.0.1',
    'name' => 'pap',
    'user' => 'teu_utilizador',
    'pass' => 'tua_password',
    'charset' => 'utf8mb4',
];
```

### 4. Configurar Email (Opcional)

O sistema de emails já vem pré-configurado com a API Brevo. Para alterar, edita `config/EmailService.php`:
```
php
private $apiKey = 'tua_api_key_aqui';
private $fromEmail = 'teu_email@dominio.com';
```

### 5. OCR de Faturas (Gratuito)

O sistema inclui OCR automático para extrair dados de faturas:

- **QR Code**: Detecção prioritária de códigos QR (instantâneo)
- **Fallback OCR**: Tesseract.js para texto quando não há QR
- **Suporte PDF**: Renderização automática da primeira página
- **100% Browser**: Sem custos, sem servidor, sem APIs externas

Funciona com faturas portuguesas (JPG, PNG, PDF) e extrai automaticamente:
- NIF da empresa
- Valor total
- Data da fatura

### 6. Configurar Lembretes Automáticos (Opcional)

Para enviar lembretes automaticamente, configura uma tarefa CRON:
```
bash
# Executar a cada hora
0 * * * * php /caminho/para/FreeCard/cron/send_reminders.php
```

### 7. Iniciar Servidor

```
bash
php -S localhost:8000 -t site
```

Acede a: http://localhost:8000

## Estrutura do Projeto

```
FreeCard/
├── config/
│   ├── db.php              # Ligação à BD (não incluído no Git)
│   └── EmailService.php    # Serviço de emails (Brevo API)
├── cron/
│   └── send_reminders.php # Script para envio automático de lembretes
├── database/
│   ├── schema.sql          # Schema completo da base de dados
│   ├── sample_data.sql    # Dados de exemplo
│   └── tables/             # Tabelas individuais
├── docs/
│   └── email/              # Documentação do sistema de emails
├── imgs/                   # Imagens para README
├── site/
│   ├── assets/             # Imagens e recursos
│   ├── auth/               # Scripts de autenticação
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── register.php
│   │   ├── verify.php
│   │   ├── verify_email_change.php
│   │   ├── forgot_password.php
│   │   └── reset_password.php
│   ├── components/         # Componentes reutilizáveis
│   ├── css/                # Stylesheets (temas)
│   ├── helpers/            # Funções auxiliares
│   ├── pages/              # Páginas principais
│   │   ├── dashboard.php
│   │   ├── cards.php
│   │   ├── add_card.php
│   │   ├── edit_card.php
│   │   ├── transactions.php
│   │   ├── create_transaction.php
│   │   ├── edit_transaction.php
│   │   ├── analytics.php
│   │   ├── budgets.php
│   │   ├── reminders.php
│   │   ├── settings.php
│   │   └── export_pdf.php
│   ├── index.php           # Homepage
│   └── dashboard.php       # Dashboard principal
├── .gitignore
├── LICENSE
└── README.md
```

## Base de Dados

### Tabelas Principais

**users**
- `id` - Identificador único (INT)
- `username` - Nome de utilizador (VARCHAR, único)
- `email` - Email (VARCHAR, único)
- `password_hash` - Password encriptada (VARCHAR)
- `role` - Papel do utilizador (ENUM: user/admin)
- `email_verified` - Email verificado (TINYINT)
- `verification_code` - Código de verificação (VARCHAR)
- `verification_expires` - Expira do código (DATETIME)
- `password_reset_token` - Token de recuperação (VARCHAR)
- `password_reset_expires` - Expira do token (DATETIME)
- `created_at` - Data de registo (DATETIME)

**cards**
- `id` - Identificador único (INT)
- `user_id` - Referência ao utilizador (INT)
- `name` - Nome do cartão (VARCHAR)
- `last4` - Últimos 4 dígitos (CHAR)
- `limit_amount` - Limite do cartão (DECIMAL)
- `balance` - Saldo atual/gasto acumulado (DECIMAL)
- `color` - Cor do cartão (VARCHAR) - 8 opções disponíveis
- `active` - Estado ativo/inativo (TINYINT)
- `created_at` - Data de criação (DATETIME)

**transactions**
- `id` - Identificador único (INT)
- `user_id` - Referência ao utilizador (INT)
- `card_id` - Referência ao cartão (INT, opcional)
- `amount` - Valor da transação (DECIMAL)
- `description` - Descrição (VARCHAR)
- `category` - Categoria (VARCHAR) - 8 categorias disponíveis
- `created_at` - Data/hora da transação (DATETIME)

**user_settings**
- `id` - Identificador único (INT)
- `user_id` - Referência ao utilizador (INT, único)
- `theme` - Tema da interface (VARCHAR: light/dark)
- `currency` - Moeda (VARCHAR, default: EUR)
- `language` - Idioma (VARCHAR, default: pt-PT)
- `notifications` - Notificações ativas (TINYINT)
- `created_at` - Data de criação (DATETIME)
- `updated_at` - Última atualização (DATETIME)

**budgets**
- `id` - Identificador único (INT)
- `user_id` - Referência ao utilizador (INT)
- `name` - Nome do orçamento (VARCHAR)
- `amount` - Valor limite (DECIMAL)
- `period` - Período (ENUM: monthly, weekly, yearly)
- `category` - Categoria associada (VARCHAR, opcional)
- `card_id` - Cartão associado (INT, opcional)
- `start_date` - Data de início (DATE)
- `end_date` - Data de fim (DATE, opcional)
- `active` - Estado ativo (TINYINT)
- `created_at` - Data de criação (DATETIME)
- `updated_at` - Última atualização (DATETIME)

**budget_alerts**
- `id` - Identificador único (INT)
- `budget_id` - Referência ao orçamento (INT)
- `user_id` - Referência ao utilizador (INT)
- `percentage` - Percentagem atingida (INT)
- `alert_type` - Tipo de alerta (VARCHAR)
- `sent_email` - Email enviado (TINYINT)
- `created_at` - Data de criação (DATETIME)

**payment_reminders**
- `id` - Identificador único (INT)
- `user_id` - Referência ao utilizador (INT)
- `name` - Nome do pagamento (VARCHAR)
- `amount` - Valor esperado (DECIMAL)
- `due_date` - Data de vencimento (DATE)
- `days_before` - Dias de antecedência (INT)
- `last_reminder` - Último lembrete enviado (DATETIME)
- `active` - Estado ativo (TINYINT)
- `created_at` - Data de criação (DATETIME)

**reminder_history**
- `id` - Identificador único (INT)
- `reminder_id` - Referência ao lembrete (INT)
- `user_id` - Referência ao utilizador (INT)
- `sent_at` - Data de envio (DATETIME)

**notifications**
- `id` - Identificador único (INT)
- `user_id` - Referência ao utilizador (INT)
- `type` - Tipo de notificação (VARCHAR)
- `title` - Título (VARCHAR)
- `message` - Mensagem (TEXT)
- `read` - Marcada como lida (TINYINT)
- `created_at` - Data de criação (DATETIME)

### Relacionamentos

- Um utilizador pode ter múltiplos cartões (1:N)
- Um utilizador pode ter múltiplas transações (1:N)
- Uma transação pode estar associada a um cartão ou ser paga em dinheiro (N:1 opcional)
- Um utilizador tem uma configuração de settings (1:1)
- Um utilizador pode ter múltiplos orçamentos (1:N)
- Um orçamento pode ter múltiplos alertas (1:N)
- Um utilizador pode ter múltiplos lembretes (1:N)
- Um lembrete tem histórico de envios (1:N)
- Um utilizador pode ter múltiplas notificações (1:N)

## Funcionalidades de UI/UX

### Sistema de Temas
- **Tema Claro**: Interface limpa e moderna com cores claras
- **Tema Escuro**: Interface elegante com fundo escuro para reduzir fadiga visual
- Transições suaves entre temas
- Persistência da escolha do tema por utilizador

### Categorias de Transações
8 categorias com ícones intuitivos:
- Compras
- Alimentação
- Transporte
- Saúde
- Entretenimento
- Educação
- Casa
- Outros

### Responsividade
- Design totalmente responsivo
- Funciona perfeitamente em desktop, tablet e mobile
- Grid system adaptativo do Bootstrap 5

## Gráficos e Análises

### Chart.js Implementation
- **Gráfico de Linhas**: Evolução mensal de gastos por categoria
- **Gráfico de Pizza**: Distribuição de gastos por cartão
- **Gráfico de Barras**: Total gasto por categoria
- Interatividade com tooltips informativos
- Suporte para tema claro e escuro

## Segurança

### Medidas Implementadas
- **Passwords**: Hash usando `password_hash()` com bcrypt
- **SQL Injection**: Prepared statements em todas as queries
- **Session Security**: Regeneração de session ID após login
- **CSRF Protection**: Validação de origem das requisições
- **XSS Prevention**: Uso de `htmlspecialchars()` em todas as saídas
- **Input Validation**: Validação server-side rigorosa
- **Sensitive Data**: Configurações fora do controlo de versão (.gitignore)
- **Email Verification**: Verificação de identidade por código
- **Password Reset Tokens**: Tokens com tempo de expiração

## Capturas de Ecrã

### Homepage
![Homepage](imgs/homepage.png)
*Landing page com apresentação das funcionalidades*

### Dashboard
![Dashboard](imgs/dashboard.png)
*Dashboard principal com resumo de gastos e cartões*

### Gestão de Cartões
![Cartões](imgs/cards.png)
*Interface de gestão de cartões com visualização de limites*

### Transações
![Transações](imgs/transactions.png)
*Listagem completa de transações com filtros*

### Análise
![Análise](imgs/analytics.png)
*Página de análise com gráficos interativos*

## Desenvolvimento

### Requisitos
- PHP 8.0 ou superior
- MySQL 5.7+ ou MariaDB 10.3+
- Extensão PDO MySQL habilitada
- Extensão JSON habilitada
- Extensão CURL habilitada (para emails)

### Configuração para Desenvolvimento
```
bash
# Iniciar servidor de desenvolvimento
php -S localhost:8000 -t site

# Ou com porta personalizada
php -S localhost:3000 -t site
```

### Configurar Tarefa CRON (Linux/macOS)
```
bash
# Abrir crontab
crontab -e

# Adicionar linha para executar a cada hora
0 * * * * php /caminho/completo/para/FreeCard/cron/send_reminders.php > /dev/null 2>&1
```

### Configurar Tarefa CRON (Windows - Task Scheduler)
```
Program: php.exe
Arguments: "C:\caminho\para\FreeCard\cron\send_reminders.php"
Trigger: Hourly
```

### Estrutura de Dados de Exemplo
O ficheiro `schema.sql` já inclui:
- Estrutura completa de todas as tabelas
- Exemplos de cartões (comentados)
- Exemplos de transações (comentados)
- Queries úteis para dashboard

## Funcionalidades Futuras (Roadmap)

- [ ] Importação de transações via CSV
- [ ] Gestão de múltiplas moedas
- [ ] Dashboard com mais gráficos (comparações anuais)
- [ ] Sistema de tags para transações
- [ ] Previsões de gastos baseadas no histórico
- [ ] API REST para integrações externas
- [ ] App móvil (React Native/Flutter)
- [ ] Exportação para Excel/CSV

## Contribuir

Contribuições são bem-vindas! Sente-te à vontade para:

1. Fazer fork do projeto
2. Criar uma branch (`git checkout -b feature/nova-funcionalidade`)
3. Commit das alterações (`git commit -m 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abrir um Pull Request

### Guidelines para Contribuições
- Segue o estilo de código existente
- Testa todas as funcionalidades antes de submeter
- Documenta novas funcionalidades
- Atualiza o README se necessário

## Changelog

### Versão 1.2.0 (Janeiro 2025)
- Sistema completo de emails com API Brevo
  - Email de boas-vindas
  - Verificação de conta por código
  - Recuperação de palavra-passe
  - Alteração de email com verificação
  - Notificação de alteração de password
  - Templates profissionais com branding
- Sistema de orçamentos completo
  - Orçamentos mensais, semanais, anuais
  - Alertas automáticos (80% e 100%)
  - Emails de alertas de orçamento
- Sistema de lembretes de pagamento
  - Lembretes personalizáveis
  - Envio automático via CRON
  - Histórico de lembretes enviados
- Sistema de notificações interno
  - Notificações em tempo real
  - Marcar como lido
  - Múltiplos tipos de notificação
- Exportação de transações para PDF
- Melhorias na安全管理

### Versão 1.1.2 (Dezembro 2024)
- Sistema completo de autenticação
- Gestão de cartões com 8 cores personalizáveis
- Gestão de transações com 8 categorias
- Dashboard interativo com estatísticas
- Página de análise com 3 tipos de gráficos
- Sistema de temas claro/escuro
- Interface totalmente responsiva
- Sistema de alertas para limites de cartões
- Filtros avançados de transações
- Configurações de utilizador

## Licença

Este projeto está sob a licença MIT - vê o ficheiro [LICENSE](LICENSE) para mais detalhes.

## Autores

**Diogo Freire** - Desenvolvimento Principal
- GitHub: [@diogofreire-dev](https://github.com/diogofreire-dev)

**Jandro Antunes** - Colaborador
- GitHub: [@Jandroantunes](https://github.com/Jandroantunes)

## Suporte

Se encontrares algum problema ou tiveres sugestões:
- Abre uma [issue](https://github.com/diogofreire-dev/FreeCard/issues) no GitHub
- Contacta através do GitHub

## Agradecimentos

- Bootstrap 5 pela framework CSS
- Bootstrap Icons pelo conjunto de ícones
- Chart.js pela biblioteca de gráficos
- Brevo (Sendinblue) pelo serviço de emails
- Comunidade PHP pela documentação e recursos

---

**Desenvolvido como Projeto de Aptidão Profissional**

*FreeCard - Gestão financeira simples, rápida e gratuita*
