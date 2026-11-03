<?php
// site/helpers/categories.php
// Cor e ícone de cada categoria, partilhados por todas as páginas.
//
// As cores são variáveis CSS (--cat-1 … --cat-8, definidas no sidebar.css) com um valor
// para o modo claro e outro para o escuro. A paleta foi validada para daltonismo e
// contraste (skill dataviz); a ordem dos slots é fixa — uma categoria nunca muda de cor.

const CATEGORY_STYLES = [
    'Compras'        => [1, 'bag'],
    'Casa'           => [2, 'house-door'],
    'Transporte'     => [3, 'bus-front'],
    'Alimentação'    => [4, 'cup-straw'],
    'Educação'       => [5, 'book'],
    'Saúde'          => [6, 'heart-pulse'],
    'Entretenimento' => [7, 'controller'],
    'Outros'         => [8, 'three-dots'],
    'Sem Categoria'  => [8, 'receipt'],
];

function category_slot(?string $category): int {
    return CATEGORY_STYLES[$category ?? ''][0] ?? 8;
}

// Cor sólida (para pontos, barras e ícones)
function category_color(?string $category): string {
    return 'var(--cat-' . category_slot($category) . ')';
}

// Fundo suave da mesma cor (para o quadrado dos ícones)
function category_tint(?string $category): string {
    return 'color-mix(in srgb, var(--cat-' . category_slot($category) . ') 15%, transparent)';
}

function category_icon(?string $category): string {
    return CATEGORY_STYLES[$category ?? ''][1] ?? 'receipt';
}
