<?php
// site/helpers/password_rules.php
// Regras da palavra-passe, iguais no registo, na recuperação e nas definições.

// Devolve o que falta (lista vazia = palavra-passe aceite).
// Máximo 72 bytes porque o bcrypt ignora tudo o que vem depois: "Abc!1234" + 70 letras
// e "Abc!1234" + outras 70 letras davam o mesmo hash se o início fosse igual.
function password_problems(string $p): array {
    $out = [];
    if (strlen($p) < 8)                    $out[] = 'pelo menos 8 caracteres';
    if (strlen($p) > 72)                   $out[] = 'no máximo 72 caracteres';
    if (!preg_match('/[A-Z]/', $p))        $out[] = 'uma maiúscula';
    if (!preg_match('/[a-z]/', $p))        $out[] = 'uma minúscula';
    if (!preg_match('/[0-9]/', $p))        $out[] = 'um número';
    if (!preg_match('/[^A-Za-z0-9]/', $p)) $out[] = 'um símbolo';
    return $out;
}

function password_problem_message(string $p): ?string {
    $missing = password_problems($p);
    return $missing ? 'A palavra-passe precisa de ' . implode(', ', $missing) . '.' : null;
}
