# Pôr a Finora online no Azure (Azure for Students)

Guia passo a passo para instalar a Finora numa máquina Linux do Azure, com HTTPS, firewall e backups.
Cada passo diz **o que fazer** e **porquê** — a segurança do servidor faz parte do projeto.

> Os comandos com `<...>` têm valores que escolhes tu (domínio, palavras-passe). Nunca os ponhas no git.

---

## 0. Antes de começar

| O quê | Onde |
|---|---|
| Conta **Azure for Students** (sem cartão, com o email da escola) | <https://azure.microsoft.com/free/students> |
| **GitHub Student Pack** (domínio grátis no 1.º ano: `.me`, `.tech` ou Name.com) | <https://education.github.com/pack> |
| Chave SSH no teu Mac | passo 1 |

No portal do Azure, cria já um **alerta de orçamento** (Cost Management → Budgets → 10 $/mês).
A conta de estudante não tem cartão, por isso nunca há cobranças — mas o alerta mostra se algo está a gastar crédito sem querer.

---

## 1. Chave SSH (no teu Mac)

```bash
ssh-keygen -t ed25519 -C "finora-azure"      # Enter para o caminho por defeito; põe uma frase-passe
cat ~/.ssh/id_ed25519.pub                   # esta é a chave PÚBLICA: é a que vai para o Azure
```

**Porquê:** entrar com chave em vez de palavra-passe torna inútil adivinhar palavras-passe (os bots tentam milhares por dia em qualquer servidor com a porta 22 aberta).
A chave **privada** (`id_ed25519`, sem `.pub`) nunca sai do teu Mac.

---

## 2. Criar a máquina virtual

Portal do Azure → **Virtual machines → Create**:

| Campo | Valor | Porquê |
|---|---|---|
| Resource group | `finora` (novo) | tudo junto: apagar o grupo apaga tudo |
| Region | West Europe ou North Europe | dados na UE (RGPD) — se der erro de região, experimenta outra europeia |
| Image | **Ubuntu Server 24.04 LTS** | suporte até 2029, PHP 8.3 e MariaDB 10.11 |
| Size | **B1s** (1 vCPU, 1 GB) | é o tamanho incluído nos serviços grátis |
| Authentication | **SSH public key**, utilizador `diogo`, cola a chave do passo 1 | sem palavra-passe no SSH |
| Inbound ports | SSH (22), HTTP (80), HTTPS (443) | o resto fica fechado |
| OS disk | Standard SSD | |

Depois de criada, em **Networking** muda a regra do SSH: *Source* → **My IP address**.
Assim a porta 22 só aceita ligações do teu IP (se o IP de casa mudar, atualizas a regra).

Em **Configuration** confirma que o IP público é **Static** (senão muda ao reiniciar).

---

## 3. Primeira entrada e atualizações

```bash
ssh diogo@<ip-publico>
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y unattended-upgrades fail2ban ufw
sudo dpkg-reconfigure -plow unattended-upgrades      # "Yes": atualizações de segurança automáticas
```

**Swap** — a B1s tem só 1 GB de RAM; sem swap, o MariaDB pode ser morto por falta de memória:

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

**Firewall** (segunda camada, além das regras do Azure):

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'      # 80 e 443 (o perfil aparece depois de instalar o Apache; repete no passo 5 se der erro)
sudo ufw enable
```

**fail2ban** já protege o SSH com a configuração por defeito: IPs com muitas falhas ficam bloqueados.
Ver: `sudo fail2ban-client status sshd`.

---

## 5. Apache, PHP e MariaDB

```bash
sudo apt install -y apache2 libapache2-mod-php php-mysql php-mbstring php-gd php-curl php-xml php-zip php-intl \
                    mariadb-server composer git unzip
sudo ufw allow 'Apache Full'
sudo mysql_secure_installation     # Enter (root usa unix_socket), remove anonymous users, disallow remote root, remove test db
```

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
sudo mysql_tzinfo_to_sql /usr/share/zoneinfo | sudo mysql mysql
```

Depois `sudo nano /etc/mysql/mariadb.conf.d/99-finora.cnf`:

```ini
[mysqld]
default-time-zone = 'Europe/Lisbon'   ; a Finora faz as contas de datas no MySQL (CURDATE())
bind-address      = 127.0.0.1         ; só aceita ligações da própria máquina
```

**Porquê o mesmo fuso nos dois:** o PHP e o MySQL a discordar sobre "que dia é hoje" dava avisos e pagamentos com a data errada perto da meia-noite.

```bash
sudo systemctl restart mariadb apache2
```

---

## 6. Base de dados (utilizador com o mínimo de permissões)

```bash
sudo mysql
```

```sql
CREATE DATABASE IF NOT EXISTS pap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'finora'@'localhost' IDENTIFIED BY '<palavra-passe-longa-e-aleatoria>';
GRANT SELECT, INSERT, UPDATE, DELETE ON pap.* TO 'finora'@'localhost';
EXIT;
```

**Porquê só estas quatro:** a app só lê e escreve dados. Se alguém encontrasse uma falha (ex.: SQL injection),
não conseguia apagar tabelas, criar utilizadores nem ler outras bases. O esquema é instalado pelo `root` (passo 7).

Gera a palavra-passe com `openssl rand -base64 24`.

---

## 7. Instalar a Finora

```bash
sudo git clone https://github.com/diogofreire-dev/Finora.git /var/www/finora
cd /var/www/finora
sudo composer install --no-dev --no-interaction
sudo mysql < database/schema.sql            # seguro: nunca apaga nada

sudo cp config/db.example.php           config/db.php
sudo cp config/brevo.example.php        config/brevo.php
sudo cp config/google_oauth.example.php config/google_oauth.php
sudo nano config/db.php                     # user 'finora', a palavra-passe do passo 6, GROQ_API_KEY
sudo nano config/brevo.php                  # BREVO_API_KEY e APP_URL = 'https://<dominio>/site'
sudo nano config/google_oauth.php           # redirect: https://<dominio>/site/auth/google_callback.php
```

**Permissões** — o Apache (`www-data`) lê o código mas só escreve na pasta das fotos:

```bash
sudo chown -R root:www-data /var/www/finora
sudo find /var/www/finora -type d -exec chmod 750 {} \;
sudo find /var/www/finora -type f -exec chmod 640 {} \;
sudo chown -R www-data:www-data /var/www/finora/site/assets/avatars
sudo chmod 600 /var/www/finora/config/*.php && sudo chown www-data:root /var/www/finora/config/*.php
```

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

1. No fornecedor do domínio (ou no Cloudflare), cria um registo **A** para `<dominio>` → IP público da VM.
2. Quando `http://<dominio>` abrir a Finora:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d <dominio> --redirect     # certificado grátis + redireciona HTTP → HTTPS
```

O certbot renova o certificado sozinho (`systemctl list-timers | grep certbot`).

**Cabeçalho HSTS** (o browser passa a usar sempre HTTPS) — dentro do `<VirtualHost *:443>` em `finora-le-ssl.conf`:

```apache
Header always set Strict-Transport-Security "max-age=31536000"
```

---

## 10. Tarefas agendadas e backups

`sudo nano /etc/cron.d/finora`:

```cron
# Lembretes por email, todos os dias às 8h
0 8 * * *  www-data  php /var/www/finora/cron/send_reminders.php >> /var/log/finora-cron.log 2>&1
# Backup diário da base de dados às 3h, guarda 14 dias
0 3 * * *  root  mkdir -p /var/backups/finora && mysqldump --single-transaction pap | gzip > /var/backups/finora/pap-$(date +\%F).sql.gz && find /var/backups/finora -name 'pap-*.sql.gz' -mtime +14 -delete
```

**Testa o restauro** (um backup que nunca foi restaurado não é um backup):

```bash
sudo mysql -e "CREATE DATABASE pap_teste"
zcat /var/backups/finora/pap-<data>.sql.gz | sudo mysql pap_teste
sudo mysql -e "SELECT COUNT(*) FROM pap_teste.users; DROP DATABASE pap_teste"
```

De vez em quando copia um backup para fora do servidor:
`scp diogo@<ip>:/var/backups/finora/pap-<data>.sql.gz ~/Backups/`

---

## 11. Verificar

```bash
# No servidor: tudo o que é interno deve dar 404/403
curl -s -o /dev/null -w "%{http_code}\n" https://<dominio>/config/db.php     # 404
curl -s -o /dev/null -w "%{http_code}\n" https://<dominio>/.git/config      # 404
curl -s -o /dev/null -w "%{http_code}\n" https://<dominio>/vendor/          # 404
curl -sI https://<dominio>/site/ | grep -iE "strict-transport|server|x-powered"   # HSTS sim, sem versões
```

No teu Mac, com uma conta criada no site:

```bash
tests/smoke.sh https://<dominio>/site <email> '<palavra-passe>'
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
cd /var/www/finora
sudo mysqldump --single-transaction pap | gzip > /var/backups/finora/antes-update-$(date +%F).sql.gz
sudo git pull
sudo composer install --no-dev --no-interaction
sudo mysql < database/schema.sql              # cria tabelas/colunas novas, não apaga nada
sudo chown -R root:www-data /var/www/finora && sudo chown -R www-data:www-data site/assets/avatars
```

Se houver uma `database/migration_*.sql` nova que altere colunas existentes, lê-a antes e corre-a com `sudo mysql pap < …`.

---

## Quando o crédito acabar

A conta de estudante renova todos os anos enquanto fores estudante. Se deixar de dar, o caminho mais simples é
um VPS barato (ex.: Hetzner): faz um backup (passo 10), cria a nova máquina com este mesmo guia e restaura.
