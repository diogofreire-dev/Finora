<?php
// site/helpers/import_rows.php
// Regras partilhadas pela importação de CSV e de backup JSON.
//
// Um ficheiro importado é dado que vem de fora: pode ter sido editado à mão, gerado por outro
// programa ou preparado de propósito. Por isso cada linha é validada com regras apertadas e a
// importação inteira corre numa transação — ou entra tudo, ou não entra nada.

require_once __DIR__ . '/categories.php';

const IMPORT_MAX_BYTES  = 2 * 1024 * 1024; // 2 MB chega para dezenas de milhares de linhas
const IMPORT_MAX_ROWS   = 5000;
const IMPORT_MAX_AMOUNT = 1000000;         // a coluna é DECIMAL(10,2)

// Categorias que a Finora conhece; qualquer outra fica "Outros"
function import_category($raw): string {
    $raw = is_string($raw) ? trim($raw) : '';
    foreach (array_keys(CATEGORY_STYLES) as $known) {
        if ($known !== 'Sem Categoria' && mb_strtolower($known) === mb_strtolower($raw)) return $known;
    }
    return 'Outros';
}

// Aceita 2024-01-15 ou 15/01/2024 e recusa datas que "não existem" (31/02 não passa a 2/03)
function import_date($raw): ?string {
    if (!is_string($raw)) return null;
    $raw = trim($raw);
    foreach (['Y-m-d', 'd/m/Y'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $raw);
        if ($d && $d->format($fmt) === $raw) {
            $y = (int) $d->format('Y');
            return ($y >= 1990 && $y <= (int) date('Y') + 1) ? $d->format('Y-m-d') : null;
        }
    }
    return null;
}

// Aceita "45.90", "45,90" e "1 234,56"; recusa "12abc", negativos e valores absurdos
function import_amount($raw): ?float {
    if (is_int($raw) || is_float($raw)) $raw = (string) $raw;
    if (!is_string($raw)) return null;
    $s = str_replace([' ', "\u{00A0}", '€'], '', trim($raw));
    if (preg_match('/^\d{1,3}(\.\d{3})+,\d{1,2}$/', $s)) $s = str_replace('.', '', $s); // 1.234,56
    $s = str_replace(',', '.', $s);
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) return null;
    $v = (float) $s;
    return ($v > 0 && $v <= IMPORT_MAX_AMOUNT) ? round($v, 2) : null;
}

// Valida uma linha. Devolve [linha pronta a inserir, null] ou [null, motivo do erro].
function import_row(array $r, array $cardMap): array {
    $date = import_date($r['date'] ?? null);
    if (!$date) return [null, 'data inválida'];

    $amount = import_amount($r['amount'] ?? null);
    if ($amount === null) return [null, 'valor inválido'];

    $desc = is_string($r['description'] ?? null) ? trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $r['description'])) : '';
    if ($desc === '') return [null, 'descrição em falta'];
    $desc = mb_substr($desc, 0, 255);

    $card = is_string($r['card'] ?? null) ? mb_strtolower(trim($r['card'])) : '';

    return [[
        'date'     => $date,
        'amount'   => $amount,
        'desc'     => $desc,
        'category' => import_category($r['category'] ?? null),
        'card_id'  => $cardMap[$card] ?? null,
    ], null];
}

// Cartões do utilizador por nome (minúsculas) → id. Só os dele: nunca se aceita um id vindo do ficheiro.
function import_card_map(PDO $pdo, int $uid): array {
    $st = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid");
    $st->execute([':uid' => $uid]);
    $map = [];
    foreach ($st->fetchAll() as $c) $map[mb_strtolower(trim($c['name']))] = (int) $c['id'];
    return $map;
}

// Grava as linhas válidas numa transação. Devolve [importadas, duplicadas ignoradas].
function import_save(PDO $pdo, int $uid, array $rows, bool $skipDuplicates): array {
    $ins = $pdo->prepare("INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at)
                          VALUES (:uid, :cid, :amt, :desc, :cat, :date, NOW())");
    $dup = $pdo->prepare("SELECT 1 FROM transactions
                          WHERE user_id = :uid AND transaction_date = :date AND amount = :amt AND description = :desc LIMIT 1");
    $imported = $skipped = 0;
    $pdo->beginTransaction();
    try {
        foreach ($rows as $r) {
            if ($skipDuplicates) {
                $dup->execute([':uid' => $uid, ':date' => $r['date'], ':amt' => $r['amount'], ':desc' => $r['desc']]);
                if ($dup->fetchColumn()) { $skipped++; continue; }
            }
            $ins->execute([':uid' => $uid, ':cid' => $r['card_id'], ':amt' => $r['amount'], ':desc' => $r['desc'],
                           ':cat' => $r['category'], ':date' => $r['date']]);
            $imported++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [$imported, $skipped];
}

// Guarda a mensagem para a página Dados e volta para lá (sem texto no URL)
function import_finish(string $type, string $text): never {
    $_SESSION['flash_dados'] = [$type, $text];
    header('Location: ../pages/dados.php');
    exit;
}

// Verificações comuns ao ficheiro enviado. Devolve o conteúdo ou termina com erro.
function import_read_upload(?array $file, array $extensions): string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        import_finish('danger', 'Escolhe um ficheiro para importar.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > IMPORT_MAX_BYTES) {
        import_finish('danger', 'O ficheiro tem mais de 2 MB. Divide-o em partes mais pequenas.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        import_finish('danger', 'Não foi possível receber o ficheiro. Tenta outra vez.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions, true)) {
        import_finish('danger', 'Formato errado. Usa um ficheiro .' . implode(' ou .', $extensions) . '.');
    }
    $content = file_get_contents($file['tmp_name']);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // CSV gravado pelo Excel em PT
    }
    return ltrim($content, "\xEF\xBB\xBF");
}

// Resume o resultado numa frase
function import_summary(int $imported, int $skipped, array $errors): array {
    $parts = [$imported === 1 ? '1 transação importada' : "$imported transações importadas"];
    if ($skipped) $parts[] = $skipped === 1 ? '1 repetida ignorada' : "$skipped repetidas ignoradas";
    if ($errors) {
        $parts[] = count($errors) . ' linha(s) com erro (' . implode('; ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? '; …' : '') . ')';
    }
    return [$errors && !$imported ? 'danger' : ($errors ? 'info' : 'success'), implode(', ', $parts) . '.'];
}
