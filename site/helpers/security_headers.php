<?php
// site/helpers/security_headers.php — Content-Security-Policy de todas as páginas.
// Carregado pelo i18n.php, que todas as páginas usam. Os outros cabeçalhos de segurança
// (nosniff, Referrer-Policy, X-Frame-Options, Permissions-Policy…) estão no .htaccess.
//
// Como funciona:
//   - Cada pedido recebe um nonce aleatório. Um buffer de saída acrescenta nonce="…" a todas as
//     tags <script> da página, por isso os scripts da Finora correm e um script injetado (XSS)
//     não corre: não sabe o nonce, que muda a cada pedido.
//   - Não há eventos inline (onclick=…): estão em atributos data-* tratados por js com nonce
//     (ver o "Ações sem JavaScript inline" em components/sidebar.php).
//   - style-src mantém 'unsafe-inline' por causa dos atributos style= (o risco de CSS injetado
//     é muito menor do que o de scripts).

function csp_nonce(): string {
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

function csp_policy(): string {
    $n = csp_nonce();
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $rules = [
        "default-src 'self'",
        "script-src 'self' 'nonce-$n' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
        "img-src 'self' data: blob:",                 // fotos de perfil, QR lido de uma imagem, texturas do 3D
        "connect-src 'self'",                          // fetch só para a própria Finora
        "media-src 'self' blob:",
        "worker-src 'self' blob:",
        "object-src 'none'",
        "base-uri 'self'",
        "frame-ancestors 'none'",                      // ninguém põe a Finora num iframe (clickjacking)
        // Depois de um formulário, só se pode ir para a própria Finora ou para o Stripe (pagar / gerir o Pro)
        "form-action 'self' https://checkout.stripe.com https://billing.stripe.com",
    ];
    if ($https) $rules[] = 'upgrade-insecure-requests';
    return implode('; ', $rules);
}

// Acrescenta o nonce às tags <script> das respostas HTML (as JSON, CSV e PDF passam intactas)
function csp_add_nonces(string $out): string {
    $isHtml = true;
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0) { $isHtml = stripos($h, 'text/html') !== false; break; }
    }
    if (!$isHtml || stripos($out, '<script') === false) return $out;
    $attr = ' nonce="' . csp_nonce() . '"';
    return preg_replace('/<script\b(?![^>]*\bnonce=)/i', '<script' . $attr, $out) ?? $out;
}

function send_security_headers(): void {
    static $done = false;
    if ($done || PHP_SAPI === 'cli' || headers_sent()) return;
    $done = true;
    header('Content-Security-Policy: ' . csp_policy());
    ob_start('csp_add_nonces');
}

send_security_headers();
