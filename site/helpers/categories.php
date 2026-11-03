<?php
// site/helpers/categories.php
// Cor e ícone de cada categoria, partilhados por todas as páginas.
// Cores harmonizadas com o azul-marinho e o dourado da Finora.

const CATEGORY_STYLES = [
    'Alimentação'    => ['#d99a1e', 'cup-straw'],
    'Compras'        => ['#4361a6', 'bag'],
    'Transporte'     => ['#2a9d8f', 'bus-front'],
    'Casa'           => ['#c8553d', 'house-door'],
    'Saúde'          => ['#5a9e6f', 'heart-pulse'],
    'Entretenimento' => ['#8e6bbf', 'controller'],
    'Educação'       => ['#1f7a8c', 'book'],
    'Outros'         => ['#8a93a6', 'three-dots'],
    'Sem Categoria'  => ['#b6bdcc', 'receipt'],
];

function category_color(?string $category): string {
    return CATEGORY_STYLES[$category ?? ''][0] ?? '#8a93a6';
}

function category_icon(?string $category): string {
    return CATEGORY_STYLES[$category ?? ''][1] ?? 'receipt';
}
