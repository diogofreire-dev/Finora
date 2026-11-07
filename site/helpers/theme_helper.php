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
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, theme, language) VALUES (:uid, 'light', :lang)");
        $stmt->execute([':uid' => $user_id, ':lang' => current_lang()]);
        return 'light';
    } catch (PDOException $e) {
        return 'light';
    }
}
