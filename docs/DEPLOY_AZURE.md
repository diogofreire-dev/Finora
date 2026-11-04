# Pôr a Finora online no Azure (Azure for Students)

Guia passo a passo para instalar a Finora numa máquina Linux do Azure, com HTTPS, firewall e backups.
Cada passo diz **o que fazer** e **porquê** — a segurança do servidor faz parte do projeto.

> Os comandos com `<...>` têm valores que escolhes tu (domínio, palavras-passe). Nunca os ponhas no git.

---

## 0. Antes de começar

| O quê | Onde |
|---|---|
| Conta **Azure for Students** (sem cartão) | <https://azure.microsoft.com/free/students> |
| Domínio próprio (opcional — o Azure dá um nome grátis, passo 9) | GitHub Student Pack ou is-a.dev |
| Chave SSH no teu Mac | passo 1 |

**Conta Azure:** se o email da escola for gerido pela própria escola (Microsoft 365), o registo de estudante pode recusá-lo.
Nesse caso cria a conta Microsoft com o email pessoal e usa o email da escola só na **verificação de estudante**.
Depois de te inscreveres, entra no portal numa janela anónima e confirma em **Subscriptions** que aparece *Azure for Students — Active*.

No portal do Azure, cria já um **alerta de orçamento** (Cost Management → Budgets → 10 $/mês, alertas a 50 % real e 100 % previsto).
A conta de estudante não tem cartão, por isso nunca há cobranças — mas o alerta mostra se algo está a gastar crédito sem querer.
O saldo do crédito vê-se em <https://www.microsoftazuresponsors.com/balance>.

---

## 1. Chave SSH (no teu Mac)

```bash
ssh-keygen -t ed25519 -f ~/.ssh/finora_azure -C "finora-azure"   # põe uma frase-passe
cat ~/.ssh/finora_azure.pub        # esta é a chave PÚBLICA: é a que vai para o Azure
```

**Porquê:** entrar com chave em vez de palavra-passe torna inútil adivinhar palavras-passe (os bots tentam milhares por dia em qualquer servidor com a porta 22 aberta).
A chave **privada** (`finora_azure`, sem `.pub`) nunca sai do teu Mac. Uma chave só para este servidor pode ser revogada sem mexer no acesso ao GitHub.

Atalho para não escrever o IP nem a frase-passe sempre (`~/.ssh/config`; o macOS guarda a frase-passe no Porta-chaves):

```
Host finora
  HostName <ip-publico>
  User diogo
  IdentityFile ~/.ssh/finora_azure
  IdentitiesOnly yes
  AddKeysToAgent yes
  UseKeychain yes
```

A partir daí entras com `ssh finora`.

---

## 2. Criar a máquina virtual

Portal do Azure → **Virtual machines → Create**:

| Campo | Valor | Porquê |
|---|---|---|
| Resource group | `finora` (novo) | tudo junto: apagar o grupo apaga tudo |
| Region | uma região europeia (a Finora está em **Spain Central**) | dados na UE (RGPD); as contas de estudante só têm alguns tamanhos em cada região |
| Availability | sem redundância de infraestrutura | com uma só máquina as zonas não trazem nada |
| Image | **Ubuntu Server 24.04 LTS – x64 Gen2** | suporte até 2029, PHP 8.3 e MariaDB 10.11 |
| Size | **B1s** ou, se estiver indisponível, **B2ats_v2** (2 vCPU, 1 GB) | ambos nos serviços grátis (750 h/mês). **Atenção:** o portal propõe uma D2s_v3 de ~78 $/mês. Não uses B2pts_v2 (é ARM) |
| Spot | desligado | uma VM Spot pode ser desligada pelo Azure a qualquer momento |
| Authentication | **SSH public key**, utilizador `diogo` (não `azureuser`), *Use existing public key*, cola a chave do passo 1 | sem palavra-passe no SSH; a chave privada nunca passa pelo browser |
| Inbound ports | SSH (22), HTTP (80), HTTPS (443) | o resto fica fechado |
| OS disk | **Premium SSD, 64 GiB (P6)**, *Delete with VM* | os serviços grátis incluem 2 discos P6; outro tipo/tamanho gasta crédito |
| Networking | *Delete public IP and NIC when VM is deleted* | não ficam recursos órfãos a gastar |
| Management | Auto-shutdown e Backup **desligados** | é um servidor (tem de ficar ligado); os backups fazemo-los nós (passo 10) |
| Monitoring | Boot diagnostics **ligado** (conta gerida) | dá acesso à consola série se te trancares fora do SSH |

Na página de revisão, o preço da B2ats_v2 aparece como ~0,012 $/h: é o preço de tabela, coberto pelos créditos.

Depois de criada, em **Networking** muda a regra do SSH: *Source* → **My IP address**.
Assim a porta 22 só aceita ligações do teu IP (se o IP de casa mudar, atualizas a regra).

Em **Configuration** do IP público confirma que é **Static** (os IPs novos já vêm assim).

---

## 3. Primeira entrada e atualizações

```bash
ssh finora                  # ou: ssh -i ~/.ssh/finora_azure diogo@<ip-publico>
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y unattended-upgrades fail2ban ufw
sudo dpkg-reconfigure -plow unattended-upgrades      # "Yes": atualizações de segurança automáticas
```

Confirma sempre onde estás antes de correr um comando: `diogo@finora:~$` é o servidor, `…@MacBook… %` é o teu Mac
(no Mac, o `sudo` pede a palavra-passe do Mac — não a escrevas para comandos do servidor).

Os `SyntaxWarning: invalid escape sequence` ao instalar o fail2ban vêm dos ficheiros de teste dele e são inofensivos.

**Swap** — a máquina tem só 1 GB de RAM; sem swap, o MariaDB pode ser morto por falta de memória:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## 4. Endurecer o servidor

**SSH** — `sudo nano /etc/ssh/sshd_config.d/99-finora.conf`:

```
PasswordAuthentication no
PermitRootLogin no
KbdInteractiveAuthentication no
```

```bash
sudo sshd -t && sudo systemctl reload ssh     # -t testa a configuração antes (não te trancas fora)
```

Antes de fechares a sessão, abre **outra** e confirma que ainda entras. Do Mac, isto tem de responder
`Permission denied (publickey)` — prova que já não aceita palavras-passe:

```bash
ssh -o PubkeyAuthentication=no -o PreferredAuthentications=password diogo@<ip-publico>
```

**Firewall** (segunda camada, além das regras do Azure) — o `allow OpenSSH` vem **antes** do `enable`, senão ficas trancado fora:

```bash
sudo ufw allow OpenSSH && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp
sudo ufw enable && sudo ufw status
```

**fail2ban** já protege o SSH com a configuração por defeito: IPs com muitas falhas ficam bloqueados.
Ver: `sudo fail2ban-client status sshd`.

---

## 5. Apache, PHP e MariaDB

```bash
sudo apt install -y apache2 libapache2-mod-php php-mysql php-mbstring php-gd php-curl php-xml php-zip php-intl \
                    mariadb-server composer git unzip
```

Não é preciso o `mysql_secure_installation`: no Ubuntu o MariaDB já vem sem utilizadores anónimos, sem base de teste
e com o `root` a entrar só por `sudo mysql` (unix_socket). Confirma com
`sudo mysql -e "SELECT user,host FROM mysql.user"` — só `root`, `mysql` e `mariadb.sys`, todos em `localhost`.

**Porquê `libapache2-mod-php` (e não PHP-FPM):** as regras de segurança da Finora estão no `.htaccess`
(`php_flag`, bloqueio de `config/`, pasta de avatares sem PHP). O `php_flag` só funciona com o `mod_php`.

**Definições de PHP para produção** — `sudo nano /etc/php/8.3/apache2/conf.d/99-finora.ini`:

```ini
display_errors = Off          ; erros vão para o log, nunca para o ecrã (fuga de informação)
log_errors = On
expose_php = Off              ; não anunciar a versão do PHP nos cabeçalhos
session.cookie_secure = On    ; o cookie de sessão só viaja por HTTPS
session.cookie_httponly = On
session.use_strict_mode = On
upload_max_filesize = 4M
post_max_size = 8M
date.timezone = Europe/Lisbon
```

E o mesmo fuso no MariaDB. Primeiro carrega as zonas horárias (sem isto o MariaDB não arranca com `Europe/Lisbon`):

```bash
sudo mysql_tzinfo_to_sql /usr/share/zoneinfo 2>/dev/null | sudo mysql mysql
```

Depois `sudo nano /etc/mysql/mariadb.conf.d/99-finora.cnf`:

```ini
[mysqld]
# a Finora faz as contas de datas no MySQL (CURDATE())
default-time-zone = Europe/Lisbon
# só aceita ligações da própria máquina
bind-address = 127.0.0.1
```

**Porquê o mesmo fuso nos dois:** o PHP e o MySQL a discordar sobre "que dia é hoje" dava avisos e pagamentos com a data errada perto da meia-noite.

```bash
sudo systemctl restart mariadb apache2
```

---

## 6. Base de dados (utilizador com o mínimo de permissões)

O esquema é instalado pelo `root` no passo 7. Aqui cria-se o utilizador da app. A palavra-passe é gerada no servidor
e escrita logo no `config/db.php` (passo 7), por isso nunca precisa de ser vista nem copiada:

```bash
P=$(openssl rand -base64 30 | tr -dc 'A-Za-z0-9' | head -c 32)
sudo mysql -e "CREATE USER 'finora'@'localhost' IDENTIFIED BY '$P'; GRANT SELECT, INSERT, UPDATE, DELETE ON pap.* TO 'finora'@'localhost';"
```

**Porquê só estas quatro:** a app só lê e escreve dados. Se alguém encontrasse uma falha (ex.: SQL injection),
não conseguia apagar tabelas, criar utilizadores nem ler outras bases.

---

## 7. Instalar a Finora

Na **mesma sessão** do passo 6 (usa o `$P`):

```bash
sudo git clone https://github.com/diogofreire-dev/Finora.git /var/www/finora
cd /var/www/finora
sudo COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction
sudo mysql < database/schema.sql            # seguro: nunca apaga nada

sudo cp config/db.example.php           config/db.php
sudo cp config/brevo.example.php        config/brevo.php
sudo cp config/google_oauth.example.php config/google_oauth.php
sudo sed -i "s/'user'    => 'root'/'user'    => 'finora'/; s/'pass'    => ''/'pass'    => '$P'/" config/db.php
unset P
sudo nano config/db.php                     # só a GROQ_API_KEY
sudo nano config/brevo.php                  # BREVO_API_KEY e APP_URL = 'https://<dominio>/site'
sudo nano config/google_oauth.php           # redirect: https://<dominio>/site/auth/google_callback.php
```

Para pôr as chaves sem abrir o editor (a chave não aparece no ecrã nem fica no histórico):

```bash
read -rsp "Chave Brevo: " K; echo; sudo sed -i -E "s|(BREVO_API_KEY', ')[^']*'|\1$K'|" config/brevo.php; unset K
```

> **Cuidado com o nano:** se a primeira linha (`<?php`) for apagada, o PHP mostra o ficheiro como texto — **com as chaves** —
> em todas as páginas que o incluem. O `php -l` não apanha isto (texto sem `<?php` é válido). Depois de editar, confirma:
> `sudo sh -c 'head -c 5 config/*.php'` → cada um começa por `<?php`. O `tests/smoke.sh` também falha se alguma página mostrar
> código PHP ou chaves. Se uma chave chegar a aparecer numa página pública, **troca-a**: há bots a visitar todos os sites
> com certificados novos.

Confirma que a app liga com o utilizador certo: `sudo php -r 'require "config/db.php"; echo $pdo->query("SELECT CURRENT_USER()")->fetchColumn();'` → `finora@localhost`.

**Permissões** — o Apache (`www-data`) lê o código mas só escreve na pasta das fotos e no log dos lembretes:

```bash
sudo chown -R root:www-data /var/www/finora
sudo find /var/www/finora -type d -exec chmod 750 {} +
sudo find /var/www/finora -type f -exec chmod 640 {} +
sudo chown -R www-data:www-data /var/www/finora/site/assets/avatars
sudo install -m 640 -o www-data -g www-data /dev/null /var/www/finora/cron/send_reminders.log
sudo sh -c 'chown www-data:root /var/www/finora/config/*.php && chmod 600 /var/www/finora/config/*.php'
```

O último comando vai dentro de `sudo sh -c '…'` porque, depois do `chmod 750`, o teu utilizador já não consegue listar
`config/` — o `*.php` seria expandido por ti (sem acesso) e o comando falhava. Pelo mesmo motivo, a partir daqui
`cd /var/www/finora` dá *Permission denied*: usa `sudo -u www-data sh -c 'cd /var/www/finora && …'` ou `sudo -i`.

**Porquê:** se alguém conseguisse executar código através da app, não podia alterar os ficheiros da própria app.
As chaves (`config/*.php`) só são legíveis pelo Apache.

---

## 8. Site no Apache

`sudo nano /etc/apache2/sites-available/finora.conf`:

```apache
<VirtualHost *:80>
    ServerName <dominio>
    DocumentRoot /var/www/finora

    <Directory /var/www/finora>
        # o .htaccess da Finora tem as regras de segurança (bloqueia config/, .git, vendor…)
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/finora-error.log
    CustomLog ${APACHE_LOG_DIR}/finora-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite finora && sudo a2dissite 000-default
sudo a2enmod headers
sudo sed -i 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf
sudo apachectl configtest && sudo systemctl reload apache2
```

---

## 9. Domínio e HTTPS

Sem HTTPS o login não funciona (o `session.cookie_secure` do passo 5 só deixa o cookie viajar por HTTPS) — e ainda bem.

**Nome grátis do Azure:** no IP público → **Configuration** → *DNS name label* `finora` dá
`finora.<regiao>.cloudapp.azure.com`, que serve para o certificado. Um domínio próprio junta-se depois:
cria um registo **A** para `<dominio>` → IP público da VM e repete o `certbot` com os dois `-d`.

Quando `http://<dominio>/site/` abrir a Finora:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d <dominio> --redirect --agree-tos --register-unsafely-without-email
```

- `--redirect`: HTTP passa a redirecionar para HTTPS.
- `--agree-tos`: aceita os termos do Let's Encrypt (lê-os antes: <https://letsencrypt.org/repository/>).
- `--register-unsafely-without-email`: não entrega email ao Let's Encrypt. Só perderias os avisos de expiração, que já deixaram de existir;
  o certbot renova sozinho (`systemctl list-timers | grep certbot`).

**Cabeçalho HSTS** (o browser passa a usar sempre HTTPS) — dentro do `<VirtualHost *:443>` em
`/etc/apache2/sites-available/finora-le-ssl.conf`, a seguir ao `DocumentRoot`:

```apache
Header always set Strict-Transport-Security "max-age=31536000"
```

```bash
sudo apachectl configtest && sudo systemctl reload apache2
```

---

## 10. Tarefas agendadas e backups

```bash
sudo install -d -m 700 -o root -g root /var/backups/finora       # só o root lê os backups (têm os dados de todos)
sudo install -d -m 750 -o www-data -g adm /var/log/finora
```

`sudo nano /etc/cron.d/finora`:

```cron
# Lembretes por email, todos os dias às 8h
0 8 * * *  www-data  php /var/www/finora/cron/send_reminders.php >> /var/log/finora/cron.log 2>&1
# Backup diário da base de dados às 3h, guarda 14 dias
0 3 * * *  root  mysqldump --single-transaction pap | gzip > /var/backups/finora/pap-$(date +\%F).sql.gz && find /var/backups/finora -name 'pap-*.sql.gz' -mtime +14 -delete
```

**Testa o restauro** (um backup que nunca foi restaurado não é um backup):

```bash
sudo sh -c 'mysqldump --single-transaction pap | gzip > /var/backups/finora/pap-$(date +%F).sql.gz'
sudo mysql -e "CREATE DATABASE pap_teste"
sudo sh -c 'zcat /var/backups/finora/pap-$(date +%F).sql.gz | mysql pap_teste'
sudo mysql -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='pap_teste'; DROP DATABASE pap_teste"
```

De vez em quando copia um backup para fora do servidor (a pasta é só do root, por isso copia-o primeiro):

```bash
ssh finora 'sudo cat /var/backups/finora/pap-<data>.sql.gz' > ~/Backups/pap-<data>.sql.gz
```

---

## 11. Verificar

```bash
# No servidor: tudo o que é interno deve dar 404/403
curl -s -o /dev/null -w "%{http_code}\n" https://<dominio>/config/db.php     # 404
curl -s -o /dev/null -w "%{http_code}\n" https://<dominio>/.git/config      # 404
curl -s -o /dev/null -w "%{http_code}\n" https://<dominio>/vendor/          # 404
curl -s -o /dev/null -w "%{http_code}\n" https://<dominio>/composer.json    # 403
curl -sI https://<dominio>/site/ | grep -iE "strict-transport|server|x-powered"   # HSTS sim, sem versões
```

Todas as páginas com sessão iniciada, numa conta demo que é apagada no fim (a palavra-passe nunca sai do servidor):

```bash
P=$(sudo -u www-data sh -c 'cd /var/www/finora && php database/seed_demo.php' | sed -nE 's/.*Palavra-passe: *(.+)$/\1/p')
sudo bash /var/www/finora/tests/smoke.sh https://<dominio>/site demo@finora.test "$P"; unset P
sudo mysql pap -e "DELETE FROM users WHERE email='demo@finora.test'"
```

E testa a configuração TLS em <https://www.ssllabs.com/ssltest/> (objetivo: A).

---

## 12. Serviços externos

- **Brevo:** em *Senders & Domains* autentica o domínio (registos **SPF** e **DKIM** no DNS), senão os emails vão para spam.
- **Google OAuth:** na Google Cloud Console acrescenta o redirect `https://<dominio>/site/auth/google_callback.php`.
- **Groq:** nada a mudar (a chave vai no `config/db.php`).

---

## Atualizar a Finora mais tarde

```bash
sudo -i                                         # o diogo não entra em /var/www/finora (passo 7)
cd /var/www/finora
mysqldump --single-transaction pap | gzip > /var/backups/finora/antes-update-$(date +%F).sql.gz
git pull
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction
mysql < database/schema.sql                     # cria tabelas/colunas novas, não apaga nada
chown -R root:www-data /var/www/finora && chown -R www-data:www-data site/assets/avatars cron/send_reminders.log
exit
```

Se houver uma `database/migration_*.sql` nova que altere colunas existentes, lê-a antes e corre-a com `sudo mysql pap < …`.

---

## Quando o crédito acabar

A conta de estudante renova todos os anos enquanto fores estudante. Se deixar de dar, o caminho mais simples é
um VPS barato (ex.: Hetzner): faz um backup (passo 10), cria a nova máquina com este mesmo guia e restaura.
