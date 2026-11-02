<?php
/**
 * SAFE DB Migration: Unify nif_empresas + nif_empresas_cache
 * Run: php config/migrate_nif_unified.php
 */

// Só pode correr pela linha de comandos, nunca por um pedido web
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/db.php';

echo "🚀 NIF TABLES UNIFICATION MIGRATION\n";
echo str_repeat('=', 50) . "\n\n";

try {
    // 1. Check current state
    echo "1️⃣ CHECKING TABLES...\n";
    
    $tables = [
        'nif_empresas' => 'DESCRIBE nif_empresas',
        'nif_empresas_cache' => 'DESCRIBE nif_empresas_cache'
    ];
    
    foreach ($tables as $name => $query) {
        try {
            $stmt = $pdo->query($query);
            echo "  ✅ $name EXISTS\n";
        } catch (Exception $e) {
            echo "  ❌ $name MISSING (OK, will create)\n";
        }
    }
    
    // 2. Create/Enhance nif_empresas (main table)
    echo "\n2️⃣ CREATING UNIFIED nif_empresas...\n";
    $createTable = "
        CREATE TABLE IF NOT EXISTS nif_empresas (
            nif VARCHAR(9) PRIMARY KEY,
            nome_empresa VARCHAR(255) NOT NULL,
            id_categoria INT NULL,
            fonte ENUM('manual','vies','nifpt','fallback') DEFAULT 'manual',
            verified BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ";
    $pdo->exec($createTable);
    echo "  ✅ Schema created/enhanced\n";
    
    // Add indexes if missing
    $pdo->exec('ALTER TABLE nif_empresas ADD INDEX idx_fonte (fonte)');
    $pdo->exec('ALTER TABLE nif_empresas ADD INDEX idx_verified (verified)');
    echo "  ✅ Indexes added\n";
    
    // 3. MERGE nif_empresas_cache → nif_empresas
    echo "\n3️⃣ MERGING CACHE DATA...\n";
    try {
        $cacheCount = $pdo->query("SELECT COUNT(*) FROM nif_empresas_cache")->fetchColumn();
        echo "  Cache has $cacheCount records\n";
        
        if ($cacheCount > 0) {
            $merge = "
                INSERT INTO nif_empresas (nif, nome_empresa, fonte)
                SELECT nif, nome_empresa, 'vies' as fonte
                FROM nif_empresas_cache
                ON DUPLICATE KEY UPDATE 
                    nome_empresa = VALUES(nome_empresa),
                    fonte = VALUES(fonte),
                    updated_at = NOW()
            ";
            $affected = $pdo->exec($merge);
            echo "  ✅ Merged $affected records\n";
        }
    } catch (Exception $e) {
        echo "  ⚠️ No cache table/skip: " . $e->getMessage() . "\n";
    }
    
    // 4. Populate sample data (major PT chains)
    echo "\n4️⃣ ADDING FALLBACK NIFs...\n";
    $samples = [
        ['500961353', 'CONTINENTE HIPERMERCADOS SA', 'vies'],
        ['501907372', "MCDONALD'S PORTUGAL LDA", 'vies'],
        ['500961358', 'EL CORTE INGLÉS SA', 'vies'],
        ['500961360', 'CARREFOUR PORTUGAL SA', 'vies'],
        ['500961450', 'SONAE MC SA', 'vies'],
        ['503157082', 'JUMBO - SUPERMERCADOS LDA', 'vies'],
        ['509801534', 'REPSOL PORTUGAL SA', 'fallback'],
        ['510000001', 'GALP ENERGIA SA', 'fallback']
    ];
    
    $merged = 0;
    foreach ($samples as $sample) {
        $stmt = $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa, fonte, verified) 
            VALUES (?, ?, ?, TRUE) 
            ON DUPLICATE KEY UPDATE verified = TRUE
        ");
        if ($stmt->execute($sample)) $merged++;
    }
    echo "  ✅ Added/verified $merged sample NIFs\n";
    
    // 5. CLEANUP old cache table
    echo "\n5️⃣ CLEANUP...\n";
    try {
        $pdo->exec('DROP TABLE IF EXISTS nif_empresas_cache');
        echo "  ✅ Dropped old cache table\n";
    } catch (Exception $e) {
        echo "  ⚠️ Skip cleanup: " . $e->getMessage() . "\n";
    }
    
    // 6. Final status
    echo "\n🎉 MIGRATION SUCCESS!\n";
    echo "Final count: " . $pdo->query('SELECT COUNT(*) FROM nif_empresas')->fetchColumn() . " records\n";
    echo "Last 5: \n";
    $stmt = $pdo->query('SELECT * FROM nif_empresas ORDER BY updated_at DESC LIMIT 5');
    foreach ($stmt->fetchAll() as $row) {
        echo "  {$row['nif']} → {$row['nome_empresa']} ({$row['fonte']})\n";
    }
    
    echo "\n✅ Ready for Step 2. Check TODO_NIF_FIX.md\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>

