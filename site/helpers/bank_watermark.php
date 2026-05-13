<?php
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

function bankWatermark(string $bankKey, string $prefix = '../'): string {
    global $BANK_LOGO_FILES;
    if (empty($bankKey) || !isset($BANK_LOGO_FILES[$bankKey])) return '';

    $file = __DIR__ . '/../assets/banks/' . $BANK_LOGO_FILES[$bankKey];
    if (!file_exists($file)) return '';

    $svgContent = file_get_contents($file);
    // Garantir fill branco
    $svgContent = preg_replace('/fill="(?!none)[^"]*"/', 'fill="white"', $svgContent);
    $svgContent = preg_replace('/stroke="(?!none|white)[^"]*"/', 'stroke="white"', $svgContent);
    $b64 = base64_encode($svgContent);

    return '<div style="
        position:absolute;
        right:10px;
        bottom:8px;
        width:40%;
        height:75%;
        opacity:0.13;
        display:flex;
        align-items:flex-end;
        justify-content:flex-end;
        pointer-events:none;
        overflow:hidden;
    "><img src="data:image/svg+xml;base64,' . $b64 . '" style="
        max-width:100%;
        max-height:100%;
        width:auto;
        height:auto;
        object-fit:contain;
        object-position:right bottom;
    " alt=""></div>';
}