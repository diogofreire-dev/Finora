<?php
// site/helpers/paths.php
// Caminhos da aplicação e validação do ?next= (proteção contra open redirect).

// Caminho web da pasta site/ (ex: "/Finora/site" no localhost, "/site" em produção).
// Vem do SCRIPT_NAME, definido pelo servidor — nunca do cabeçalho Host, que o cliente controla.
function site_base_path(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $pos    = strpos($script, '/site/');
    return $pos === false ? '/site' : substr($script, 0, $pos) . '/site';
}

// Devolve $next só se for um caminho interno da app; caso contrário null.
// Sem isto, um link como login.php?next=https://site-falso.com levava a vítima,
// já com login feito, para uma página de phishing (open redirect).
function safe_next($next): ?string {
    if (!is_string($next) || $next === '') return null;

    $base = site_base_path() . '/';
    if (strpos($next, $base) !== 0) return null;                  // tem de começar pelo caminho da app
    if (preg_match('#//|\\\\|[\r\n]|/auth/#', $next)) return null; // sem "//", "\", quebras de linha nem páginas de login
    return $next;
}
