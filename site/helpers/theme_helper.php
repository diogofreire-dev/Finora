<?php
// site/helpers/theme_helper.php
require_once __DIR__ . '/i18n.php';

// Os únicos temas que existem. O valor vai parar a data-theme="…" em todas as páginas,
// por isso nunca devolvemos nada que não esteja nesta lista (whitelist).
const THEMES = ['light', 'dark'];

function valid_theme(?string $theme): string {
    return in_array($theme, THEMES, true) ? $theme : 'light';
}

function getUserTheme($pdo, $user_id) {
    if (!$user_id) {
        return 'light';
    }

    try {
        $stmt = $pdo->prepare("SELECT theme FROM user_settings WHERE user_id = :uid");
        $stmt->execute([':uid' => $user_id]);
        $result = $stmt->fetch();

        if ($result) {
            return valid_theme($result['theme']);
        }

        // Se não existir, criar configurações padrão
        // A língua é a que a pessoa estava a usar quando criou a conta (cookie / browser)
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, theme, language, onboarded) VALUES (:uid, 'light', :lang, 0)");
        $stmt->execute([':uid' => $user_id, ':lang' => current_lang()]);
        return 'light';
    } catch (PDOException $e) {
        return 'light';
    }
}

// Fundos da app (Definições → Aparência). Tal como o tema, só valores desta lista chegam ao HTML.
//   constelacao  — pontos e linhas animados com brilhos suaves (o fundo de sempre)
//   liso         — só a cor de fundo; o mais leve
//   quadriculado — papel quadriculado de livro de contas, como na página inicial
//   linha        — a linha dourada do logo com setas nas cores das categorias, como na página inicial
const BACKGROUNDS = ['constelacao', 'liso', 'quadriculado', 'linha'];

function valid_background(?string $bg): string {
    return in_array($bg, BACKGROUNDS, true) ? $bg : 'constelacao';
}

function getUserBackground($pdo, $user_id): string {
    if (!$user_id) return 'constelacao';
    try {
        $stmt = $pdo->prepare('SELECT background FROM user_settings WHERE user_id = :uid');
        $stmt->execute([':uid' => $user_id]);
        return valid_background($stmt->fetchColumn() ?: null);
    } catch (PDOException $e) {
        return 'constelacao';     // base ainda sem a coluna (migration_background.sql): fica o fundo de sempre
    }
}
