<?php
// config/nif_pt_config.example.php
// 
// INSTRUÇÃO DE USO:
// 1. Pede API key gratuita em: https://www.nif.pt/contactos/api/
// 2. Copia este ficheiro para: config/nif_pt_config.php
// 3. Substitui 'SUA_KEY_AQUI' pela tua key real
// 4. NÃO fagas commit deste ficheiro para Git (adiciona a .gitignore)
//

// Substitui com a tua key do nif.pt
define('NIF_PT_API_KEY', 'SUA_KEY_AQUI');

// Opcional: timeout para consultas à API (segundos)
define('NIF_PT_TIMEOUT', 5);

// Opcional: habilita logs de debug
define('NIF_PT_DEBUG', false);
?>
