<?php
require_once __DIR__ . '/config/db.php';

echo "=== Adicionando coluna id_categoria ===\n\n";

try {
    $pdo->exec('ALTER TABLE nif_empresas ADD COLUMN id_categoria INT DEFAULT NULL');
    echo "✅ Coluna id_categoria adicionada com sucesso!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "✅ Coluna id_categoria já existe!\n";
    } else {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
}

// Verificar a estrutura
echo "\nEstrutura atual:\n";
$stmt = $pdo->query("DESCRIBE nif_empresas");
foreach ($stmt->fetchAll() as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\nFeito!\n";
?>
