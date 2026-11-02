# Finora

Aplicação web de gestão financeira pessoal desenvolvida em PHP/MySQL como Projeto de Aptidão Profissional.

🔗 **[freecard.infinityfreeapp.com](http://freecard.infinityfreeapp.com/)**

---

## Funcionalidades

- **Autenticação** — registo, login, recuperação de password e verificação por email
- **Cartões** — adicionar, editar e acompanhar limites e saldos em tempo real
- **Transações** — registar despesas por categoria e associar a cartões
- **Orçamentos** — definir limites de gastos com alertas automáticos
- **Lembretes** — notificações de pagamentos recorrentes via email (CRON)
- **Análise** — gráficos de evolução mensal, por categoria e por cartão
- **Relatórios** — exportação de dados para PDF e CSV
- **Assistente IA** — análise automática das finanças com Groq
- **Notificações** — painel de alertas dentro da aplicação
- **Tema claro/escuro** — preferência guardada por utilizador

---

## Tecnologias

| Camada | Tecnologias |
|--------|-------------|
| Backend | PHP 8+, PDO |
| Base de dados | MySQL |
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5, Chart.js |
| Emails | Brevo API, PHPMailer |
| PDF | TCPDF |
| IA | Groq API |

---

## Instalação

### 1. Clonar o repositório
```bash
git clone https://github.com/diogofreire-dev/FreeCard.git
cd FreeCard
```

### 2. Configurar a base de dados
Importa o schema no MySQL:
```bash
mysql -u root -p < database/schema.sql
```

### 3. Configurar ligação à BD
Edita `config/db.php`:
```php
$cfg = [
    'host'    => '127.0.0.1',
    'name'    => 'pap',
    'user'    => 'teu_utilizador',
    'pass'    => 'tua_password',
    'charset' => 'utf8mb4',
];
```

### 4. Iniciar servidor local
```bash
php -S localhost:8000 -t site
```

Acede em: [http://localhost:8000](http://localhost:8000)

### 5. Lembretes automáticos (opcional)
```bash
# Executar a cada hora
0 * * * * php /caminho/para/FreeCard/cron/send_reminders.php
```

---

## Autores

**Diogo Freire** — [@diogofreire-dev](https://github.com/diogofreire-dev)  
**Jandro Antunes** — [@Jandroantunes](https://github.com/Jandroantunes)

---

*Desenvolvido como Projeto de Aptidão Profissional*