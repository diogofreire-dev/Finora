#!/usr/bin/env bash
# tests/smoke.sh — entra na Finora como um utilizador real e abre todas as páginas.
# Falha se alguma não responder 200 ou mostrar um erro/aviso de PHP.
#
# Uso:  tests/smoke.sh <url-da-pasta-site> <email> <palavra-passe>
#   ex: tests/smoke.sh http://localhost/Finora/site demo@finora.test 'Demo-…'
set -euo pipefail

BASE="${1:?url da pasta site}"; EMAIL="${2:?email}"; PASS="${3:?palavra-passe}"
JAR="$(mktemp)"; trap 'rm -f "$JAR"' EXIT
ERRORS='Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught|SQLSTATE'
fail=0

check() {   # check <caminho> [estado esperado]
  local path="$1" want="${2:-200}" body code
  body="$(curl -s -b "$JAR" -c "$JAR" -w '\n%{http_code}' "$BASE/$path")"
  code="${body##*$'\n'}"; body="${body%$'\n'*}"
  if [[ "$code" != "$want" ]]; then
    echo "✗ $path → HTTP $code (esperado $want)"; fail=1
  elif grep -qE "$ERRORS" <<<"$body"; then
    echo "✗ $path → erro de PHP:"; grep -oE "($ERRORS).{0,160}" <<<"$body" | head -2; fail=1
  else
    echo "✓ $path"
  fi
}

# Páginas públicas
for p in index.php privacidade.php auth/login.php auth/register.php auth/forgot_password.php; do check "$p"; done

# Sem sessão, as páginas da app redirecionam para o login
check dashboard.php 302

# Login com o token CSRF do formulário, como faria o browser
token="$(curl -s -b "$JAR" -c "$JAR" "$BASE/auth/login.php" | grep -oE 'name="csrf_token" value="[a-f0-9]+"' | head -1 | sed -E 's/.*value="([a-f0-9]+)"/\1/')"
[[ -n "$token" ]] || { echo "✗ não encontrei o token CSRF no login"; exit 1; }
loc="$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{redirect_url}' \
  --data-urlencode "csrf_token=$token" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" \
  "$BASE/auth/login.php")"
[[ "$loc" == *dashboard.php* ]] || { echo "✗ login falhou (redirecionou para: ${loc:-nada})"; exit 1; }
echo "✓ login"

# Páginas com sessão
for p in dashboard.php pages/transactions.php pages/create_transaction.php pages/cards.php pages/add_card.php \
         pages/budgets.php pages/add_budget.php pages/reminders.php pages/savings.php pages/futuro.php \
         pages/analytics.php pages/investments.php pages/bolsa.php pages/notifications.php pages/settings.php \
         pages/dados.php pages/ai.php pages/persona.php pages/scan_fatura.php; do
  check "$p"
done

exit $fail
