<?php
// Logos SVG inline — fundo semi-transparente no cartão
$BANK_LOGOS = [
  'millennium' => '<svg viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="32" font-family="Arial Black,Arial" font-weight="900" font-size="28" letter-spacing="-1">BCP</text>
  </svg>',
  'cgd' => '<svg viewBox="0 0 100 60" xmlns="http://www.w3.org/2000/svg">
    <rect x="0" y="10" width="18" height="40" rx="3" fill="white"/>
    <rect x="26" y="10" width="18" height="40" rx="3" fill="white"/>
    <rect x="52" y="10" width="18" height="40" rx="3" fill="white"/>
    <text x="0" y="58" font-family="Arial" font-weight="700" font-size="11" fill="white" letter-spacing="1">CAIXA</text>
  </svg>',
  'revolut' => '<svg viewBox="0 0 130 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="32" font-family="Arial Black,Arial" font-weight="900" font-size="26" letter-spacing="-1">Revolut</text>
  </svg>',
  'n26' => '<svg viewBox="0 0 80 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="34" font-family="Arial Black,Arial" font-weight="900" font-size="36" letter-spacing="-2">N26</text>
  </svg>',
  'santander' => '<svg viewBox="0 0 100 60" xmlns="http://www.w3.org/2000/svg">
    <circle cx="30" cy="30" r="28" fill="none" stroke="white" stroke-width="4"/>
    <circle cx="55" cy="30" r="28" fill="none" stroke="white" stroke-width="4"/>
  </svg>',
  'bpi' => '<svg viewBox="0 0 80 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="34" font-family="Arial Black,Arial" font-weight="900" font-size="36" letter-spacing="2">BPI</text>
  </svg>',
  'novobanco' => '<svg viewBox="0 0 110 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="30" font-family="Arial" font-weight="900" font-size="20" letter-spacing="0">novo</text>
    <text x="0" y="52" font-family="Arial" font-weight="900" font-size="20" letter-spacing="0">banco</text>
  </svg>',
  'montepio' => '<svg viewBox="0 0 130 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="32" font-family="Arial" font-weight="900" font-size="22" letter-spacing="0">Montepio</text>
  </svg>',
  'wise' => '<svg viewBox="0 0 90 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="34" font-family="Arial Black,Arial" font-weight="900" font-size="32" letter-spacing="-1">Wise</text>
  </svg>',
  'activobank' => '<svg viewBox="0 0 130 40" xmlns="http://www.w3.org/2000/svg" fill="white">
    <text x="0" y="30" font-family="Arial" font-weight="900" font-size="18">Activo</text>
    <text x="0" y="50" font-family="Arial" font-weight="900" font-size="18">Bank</text>
  </svg>',
  'mbway' => '<svg viewBox="0 0 110 50" xmlns="http://www.w3.org/2000/svg" fill="white">
    <rect x="0" y="0" width="46" height="30" rx="5" fill="white"/>
    <text x="5" y="22" font-family="Arial Black" font-weight="900" font-size="18" fill="#E30613">MB</text>
    <text x="52" y="22" font-family="Arial Black" font-weight="900" font-size="18" fill="white">WAY</text>
  </svg>',
];

$BANKS = [
  '' => [
    'name'     => '— Selecionar banco —',
    'gradient' => null,
    'color'    => null,
    'label'    => '',
    'bg'       => '',
  ],
  'millennium' => [
    'name'     => 'Millennium BCP',
    'gradient' => 'linear-gradient(135deg, #003087 0%, #001f5b 100%)',
    'color'    => 'indigo',
    'label'    => 'BCP',
    'bg'       => '#003087',
  ],
  'cgd' => [
    'name'     => 'Caixa Geral de Depósitos',
    'gradient' => 'linear-gradient(135deg, #006B3F 0%, #004d2c 100%)',
    'color'    => 'green',
    'label'    => 'CGD',
    'bg'       => '#006B3F',
  ],
  'revolut' => [
    'name'     => 'Revolut',
    'gradient' => 'linear-gradient(135deg, #191C1F 0%, #0f1115 100%)',
    'color'    => 'indigo',
    'label'    => 'Revolut',
    'bg'       => '#191C1F',
  ],
  'n26' => [
    'name'     => 'N26',
    'gradient' => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)',
    'color'    => 'indigo',
    'label'    => 'N26',
    'bg'       => '#1a1a2e',
  ],
  'santander' => [
    'name'     => 'Santander',
    'gradient' => 'linear-gradient(135deg, #EC0000 0%, #a80000 100%)',
    'color'    => 'red',
    'label'    => 'Santander',
    'bg'       => '#EC0000',
  ],
  'bpi' => [
    'name'     => 'BPI',
    'gradient' => 'linear-gradient(135deg, #005baa 0%, #003d7a 100%)',
    'color'    => 'blue',
    'label'    => 'BPI',
    'bg'       => '#005baa',
  ],
  'novobanco' => [
    'name'     => 'Novo Banco',
    'gradient' => 'linear-gradient(135deg, #E30613 0%, #9e0000 100%)',
    'color'    => 'red',
    'label'    => 'NB',
    'bg'       => '#E30613',
  ],
  'montepio' => [
    'name'     => 'Montepio',
    'gradient' => 'linear-gradient(135deg, #00A651 0%, #007a3d 100%)',
    'color'    => 'green',
    'label'    => 'Montepio',
    'bg'       => '#00A651',
  ],
  'wise' => [
    'name'     => 'Wise',
    'gradient' => 'linear-gradient(135deg, #163300 0%, #0a1a00 100%)',
    'color'    => 'green',
    'label'    => 'Wise',
    'bg'       => '#163300',
  ],
  'activobank' => [
    'name'     => 'ActivoBank',
    'gradient' => 'linear-gradient(135deg, #00aeef 0%, #0077a8 100%)',
    'color'    => 'teal',
    'label'    => 'Activo',
    'bg'       => '#00aeef',
  ],
  'mbway' => [
    'name'     => 'MB WAY',
    'gradient' => 'linear-gradient(135deg, #E30613 0%, #ff6b00 100%)',
    'color'    => 'orange',
    'label'    => 'MB WAY',
    'bg'       => '#E30613',
  ],
  'visa' => [
    'name'     => 'Visa',
    'gradient' => 'linear-gradient(135deg, #1A1F71 0%, #0d1140 100%)',
    'color'    => 'indigo',
    'label'    => 'VISA',
    'bg'       => '#1A1F71',
  ],
  'mastercard' => [
    'name'     => 'Mastercard',
    'gradient' => 'linear-gradient(135deg, #252525 0%, #111 100%)',
    'color'    => 'indigo',
    'label'    => 'MC',
    'bg'       => '#252525',
  ],
  'outro' => [
    'name'     => 'Outro banco',
    'gradient' => null,
    'color'    => null,
    'label'    => '',
    'bg'       => '',
  ],
];