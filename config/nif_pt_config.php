<?php
/**
 * NIF.PT API Configuration
 * 
 * 1. Key from: https://www.nif.pt/contactos/api/
 * 2. Free quota: 1000 lookups/day
 * 3. Add to .gitignore
 */

// Production key (from verified working implementation)
define('NIF_PT_API_KEY', '69bb070ab64e8b236a96d5d971d82c6c');

// Optional settings
define('NIF_PT_TIMEOUT', 8);
define('NIF_PT_DEBUG', true); // Set false in production
?>

