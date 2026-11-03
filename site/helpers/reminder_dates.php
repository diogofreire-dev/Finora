<?php
// site/helpers/reminder_dates.php
// Datas dos lembretes que se repetem.
//
// O DateTime::modify('+1 month') do PHP "transborda": 31 jan + 1 mês = 3 mar.
// Um pagamento ao dia 31 ia escorregando de mês para mês. Aqui o dia original
// (âncora) mantém-se e só é encurtado nos meses que não o têm: 31 jan → 28 fev → 31 mar.

const REMINDER_RECURRENCES = ['once', 'weekly', 'monthly', 'yearly'];

function reminder_add_period(DateTimeImmutable $d, string $recurrence, int $anchorDay): DateTimeImmutable {
    if ($recurrence === 'weekly') return $d->modify('+7 days');
    $months = $recurrence === 'monthly' ? 1 : ($recurrence === 'yearly' ? 12 : 0);
    if ($months === 0) return $d;
    $first = $d->modify('first day of this month')->modify("+{$months} months");
    $day   = min($anchorDay, (int) $first->format('t'));
    return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
}

// Próxima data a partir de uma data (usada ao criar, editar e marcar como pago)
// $anchorDay vem da coluna anchor_day (o dia escolhido quando o lembrete foi criado)
function reminder_next_date(string $date, string $recurrence, ?int $anchorDay = null): string {
    $d = new DateTimeImmutable($date);
    return reminder_add_period($d, $recurrence, $anchorDay ?: (int) $d->format('j'))->format('Y-m-d');
}

// Todas as datas em que um lembrete vence entre $from e $to (inclusive), a começar na due_date.
// $anchorDay: dia do mês original (para os mensais/anuais não perderem o 31 depois de fevereiro).
function reminder_occurrences(string $dueDate, string $recurrence, DateTimeImmutable $from, DateTimeImmutable $to, ?int $anchorDay = null): array {
    $d = new DateTimeImmutable($dueDate);
    $anchorDay ??= (int) $d->format('j');
    $out = [];
    for ($i = 0; $i < 400 && $d <= $to; $i++) {        // 400: travão de segurança
        if ($d >= $from) $out[] = $d->format('Y-m-d');
        if ($recurrence === 'once') break;
        $d = reminder_add_period($d, $recurrence, $anchorDay);
    }
    return $out;
}
