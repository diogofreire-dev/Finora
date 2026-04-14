<?php
require_once 'config/db.php';
require_once 'site/helpers/nif_lookup.php';

$result = nif_lookup('500762584');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);