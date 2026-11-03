# Finora

Aplicação web de finanças pessoais, feita em PHP e MySQL: cartões, gastos, orçamentos, lembretes de pagamento, poupanças e metas num só sítio.

Começou como Prova de Aptidão Profissional de Diogo Freire e Jandro Antunes. Depois da PAP, continua a ser desenvolvida como projeto de portefólio e de aprendizagem, com especial atenção à segurança.

---

## O que faz

| | |
|---|---|
| **Dashboard** | Quanto gastaste este mês comparado com o mês anterior, para onde foi o dinheiro e os próximos pagamentos. |
| **Transações** | Registo por categoria e cartão, pesquisa, filtros por mês, e importação e exportação em CSV e JSON. |
| **Cartões** | Limite e saldo de cada cartão, com uma carteira 3D interativa (three.js, modelos feitos em Blender). |
| **Ler fatura** | Lê o QR code de uma fatura portuguesa com a câmara. Preenche valor, data e nome da empresa (pelo NIF, via VIES). |
| **Orçamentos** | Limite por categoria e por período, com o ritmo de gasto e alertas a 80% e a 100%. |
| **Lembretes** | Pagamentos recorrentes num calendário, com aviso no site ou por email antes de cada data. |
| **Poupanças e metas** | Um mealheiro de vidro que vai enchendo e metas com o valor que devias ter juntado até hoje. |
| **Investimentos e Bolsa** | Carteira com cotações automáticas, pesquisa de títulos e uma lista de títulos a seguir. |
| **Assistente** | Perguntas sobre os teus gastos, respondidas por um modelo de linguagem com os teus totais do mês. |
| **Notificações** | Sino no cabeçalho com avisos diários: pagamentos a vencer, orçamentos, metas e resumo do mês. |
| **Relatórios** | Exportação em PDF e CSV. |
| **Conta** | Login com email ou Google, foto de perfil, tema claro e escuro, tutorial em cada página. |
| **Privacidade** | Política de privacidade, exportação dos dados e opção de apagar a conta (RGPD). |

## Segurança

A aplicação passou por uma auditoria baseada no OWASP Top 10. As correções estão documentadas nos pull requests.

- **SQL injection:** só prepared statements (PDO, com `EMULATE_PREPARES` desligado).
- **CSRF:** token em todos os pedidos POST, verificado num único sítio (`helpers/auth.php`).
- **Acesso a dados de outros (IDOR):** cada consulta é limitada ao utilizador da sessão, e os ids que vêm do browser são validados contra as listas do próprio utilizador.
- **Força bruta:** limite de tentativas no login, no registo, na recuperação de palavra-passe e na verificação de email. Os IPs são guardados só como hash.
- **Palavras-passe e tokens:** `password_hash`; os tokens de recuperação e de verificação são guardados como hash e expiram.
- **Sessões:** cookie `HttpOnly` e `SameSite`, novo ID de sessão no login, e as sessões de contas apagadas são terminadas.
- **Reautenticação:** as ações irreversíveis, como apagar a conta, pedem a palavra-passe outra vez.
- **Segredos:** nenhuma chave no código. Ficam em `config/*.php`, que está fora do git, com ficheiros `.example.php` como modelo.
- **APIs externas:** são chamadas só a partir do servidor (a chave da IA nunca chega ao browser), com verificação de certificado TLS.
- **Prompt injection:** os dados do utilizador vão para o modelo separados das instruções, e o modelo não tem acesso a ferramentas.
- **Ficheiros internos:** o `.htaccess` devolve 404 a `config/`, `database/`, `vendor/`, `cron/` e a ficheiros escondidos.

## Tecnologias

| Camada | |
|---|---|
| Servidor | PHP 8.2, Apache (XAMPP), Composer |
| Base de dados | MySQL / MariaDB 10.4 |
| Interface | HTML, CSS, JavaScript, Bootstrap 5, Bootstrap Icons, Chart.js |
| 3D | three.js 0.170 (local em `site/js/lib/three`), modelos e renders em Blender |
| PDF | TCPDF |
| Serviços externos | Brevo (email), Groq (assistente), Google OAuth, VIES (nomes de empresas), Yahoo Finance e Frankfurter/BCE (cotações e câmbios) |

## Instalação local

Precisas do [XAMPP](https://www.apachefriends.org/) (Apache + PHP 8.2 + MariaDB) e do [Composer](https://getcomposer.org/).

> Usa o Apache do XAMPP e não o servidor embutido do PHP (`php -S`). As regras de segurança estão no `.htaccess`, que só o Apache lê. Sem elas, ficheiros como `config/db.php` ficariam acessíveis pelo browser.

**1. Clonar para a pasta do XAMPP**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs   # no Windows: C:\xampp\htdocs
git clone https://github.com/diogofreire-dev/Finora.git
cd Finora
composer install
```

**2. Criar os ficheiros de configuração a partir dos exemplos**

```bash
cp config/db.example.php           config/db.php
cp config/brevo.example.php        config/brevo.php
cp config/google_oauth.example.php config/google_oauth.php
```

O `db.php` já vem pronto para o XAMPP (utilizador `root` sem palavra-passe). Os outros dois podem ficar com os valores de exemplo: a app funciona, mas sem emails nem login com Google (vê [Serviços opcionais](#serviços-opcionais)).

**3. Criar a base de dados**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database/schema.sql
```

O `schema.sql` pode correr-se mais do que uma vez: cria o que falta e nunca apaga dados.

**4. Criar uma conta de demonstração com dados**

```bash
/Applications/XAMPP/xamppfiles/bin/php database/seed_demo.php
```

Cria a conta `demo@finora.test` com 14 meses de transações, cartões, orçamentos, lembretes e poupanças. A palavra-passe aparece no fim (é nova em cada execução).

**5. Abrir**

Liga o Apache e o MySQL no XAMPP e abre <http://localhost/Finora/>.

### Serviços opcionais

| Serviço | Para quê | Onde configurar |
|---|---|---|
| [Brevo](https://app.brevo.com/settings/keys/api) | Emails de verificação, recuperação de palavra-passe e alertas | `config/brevo.php` (`BREVO_API_KEY`, `APP_URL`) |
| [Groq](https://console.groq.com/keys) | Assistente | `config/db.php` (`GROQ_API_KEY`) |
| [Google Cloud](https://console.cloud.google.com/apis/credentials) | Entrar com Google | `config/google_oauth.php` |

Sem a Brevo, as contas novas não recebem o email de verificação. Para testar localmente usa a conta demo.

Os lembretes por email são enviados por `cron/send_reminders.php`, que só corre no terminal:

```bash
0 8 * * * php /caminho/para/Finora/cron/send_reminders.php
```

As notificações dentro da app não precisam de cron: são geradas uma vez por dia, quando o utilizador abre a app.

## Estrutura

```
config/        ligação à BD e chaves (os .php reais estão fora do git)
cron/          envio de lembretes por email
database/      schema.sql, migrações e seed_demo.php
site/
  auth/        login, registo, Google, recuperação e verificação
  pages/       uma página por funcionalidade
  helpers/     autenticação, CSRF, limites de tentativas, NIF, cotações, notificações…
  components/  cabeçalho, menu e notificações (comuns a todas as páginas)
  css/ js/     estilos e scripts; three.js em js/lib/three
  assets/      logótipos, modelos 3D (.glb) e renders
design/        ficheiros .blend dos modelos 3D
```

## Autores

**Diogo Freire** — [@diogofreire-dev](https://github.com/diogofreire-dev)
**Jandro Antunes** — [@Jandroantunes](https://github.com/Jandroantunes)

## Licença

[MIT](LICENSE)
