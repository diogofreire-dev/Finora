<?php
/**
 * Configuração da base de dados — EXEMPLO
 * Copia este ficheiro para db.php e preenche com os teus dados.
 */
$cfg = [
    'host'    => '127.0.0.1',
    'name'    => 'pap',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];

$dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";

try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Erro de ligação à base de dados: ' . $e->getMessage());
}

// Groq API key (Assistente IA) — https://console.groq.com/keys
define('GROQ_API_KEY', 'SUA_CHAVE_GROQ');
