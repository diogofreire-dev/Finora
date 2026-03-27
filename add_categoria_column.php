<?php
require 'config/db.php';
try {
    $pdo->exec('ALTER TABLE nif_empresas ADD COLUMN id_categoria INT DEFAULT NULL');
    echo 'Coluna id_categoria adicionada com sucesso!';
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo 'Coluna id_categoria já existe';
    } else {
        echo 'Erro: ' . $e->getMessage();
    }
}
?>