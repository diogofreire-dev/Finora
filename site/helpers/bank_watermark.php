<?php
// Logos oficiais dos bancos (Wikimedia Commons / Simple Icons), a cores.
// No cartão ficam brancas com o filtro CSS "brightness(0) invert(1)": assim o ficheiro fica igual ao original
// e funciona também com logos que usam gradientes ou várias cores.
$BANK_LOGO_FILES = [
  'millennium' => 'millennium.svg',
  'cgd'        => 'cgd.svg',
  'revolut'    => 'revolut.svg',
  'n26'        => 'n26.svg',
  'santander'  => 'santander.svg',
  'bpi'        => 'bpi.svg',
  'novobanco'  => 'novobanco.svg',
  'montepio'   => 'montepio.svg',
  'wise'       => 'wise.svg',
  'activobank' => 'activobank.svg',
  'mbway'      => 'mbway.svg',
  'visa'       => 'visa.svg',
  'mastercard' => 'mastercard.svg',
];

// Caminho da logo a partir da página (as páginas em pages/ usam '../')
function bankLogoUrl(string $bankKey, string $prefix = '../'): string {
    global $BANK_LOGO_FILES;
    if (empty($bankKey) || !isset($BANK_LOGO_FILES[$bankKey])) return '';
    $file = $BANK_LOGO_FILES[$bankKey];
    if (!file_exists(__DIR__ . '/../assets/banks/' . $file)) return '';
    return $prefix . 'assets/banks/' . $file;
}

// Todas as logos, para a pré-visualização em JS (add_card / edit_card)
function bankLogoUrls(string $prefix = '../'): array {
    global $BANK_LOGO_FILES;
    $urls = [];
    foreach (array_keys($BANK_LOGO_FILES) as $key) {
        if ($url = bankLogoUrl($key, $prefix)) $urls[$key] = $url;
    }
    return $urls;
}

// Logo no canto de cima do cartão
function bankLogo(string $bankKey, string $label = '', string $prefix = '../'): string {
    $url = bankLogoUrl($bankKey, $prefix);
    if (!$url) return '';
    return '<img class="bank-logo-img" src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($label) . '">';
}

// A mesma logo, grande e quase transparente, no canto de baixo
function bankWatermark(string $bankKey, string $prefix = '../'): string {
    $url = bankLogoUrl($bankKey, $prefix);
    if (!$url) return '';
    return '<div class="bank-wm" aria-hidden="true"><img src="' . htmlspecialchars($url) . '" alt=""></div>';
}
