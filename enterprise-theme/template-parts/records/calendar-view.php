<?php
/**
 * Calendar View — month grid (Jalali-aware)
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'year'   => (int) parsyar_jalali_date('Y') ?: (int) gmdate('Y'),
    'month'  => (int) parsyar_jalali_date('n') ?: (int) gmdate('n'),
    'events' => [],
]);

$month_names = [
    1  => 'فروردین', 2  => 'اردیبهشت', 3  => 'خرداد',
    4  => 'تیر',     5  => 'مرداد',    6  => 'شهریور',
    7  => 'مهر',     8  => 'آبان',     9  => 'آذر',
    10 => 'دی',      11 => 'بهمن',     12 => 'اسفند',
];

$weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];
?>
<div class="p-card" data-component="calendar-view">
    <header class="p-card__header">
        <h2 class="p-card__title"><?php echo esc_html($month_names[$args['month']] . ' ' . $args['year']); ?></h2>
        <div class="p-cluster">
            <button type="button" class="p-btn p-btn--ghost p-btn--icon" data-cal-nav="prev" aria-label="<?php esc_attr_e('ماه قبل', 'parsyar'); ?>">‹</button>
            <button type="button" class="p-btn p-btn--ghost p-btn--sm" data-cal-nav="today"><?php esc_html_e('امروز', 'parsyar'); ?></button>
            <button type="button" class="p-btn p-btn--ghost p-btn--icon" data-cal-nav="next" aria-label="<?php esc_attr_e('ماه بعد', 'parsyar'); ?>">›</button>
        </div>
    </header>
    <div class="p-calendar">
        <div class="p-calendar__weekdays">
            <?php foreach ($weekdays as $d): ?>
                <div class="p-calendar__weekday"><?php echo esc_html($d); ?></div>
            <?php endforeach; ?>
        </div>
        <div class="p-calendar__grid">
            <?php
            // Approximate day count for Jalali month = 31; would use real Jalali lib in production
            $days_in_month = 31;
            for ($d = 1; $d <= $days_in_month; $d++):
                $key = sprintf('%04d-%02d-%02d', $args['year'], $args['month'], $d);
                $day_events = $args['events'][$key] ?? [];
            ?>
                <div class="p-calendar__day" data-date="<?php echo esc_attr($key); ?>">
                    <span class="p-calendar__day-num"><?php echo esc_html(parsyar_format_number_fa($d)); ?></span>
                    <?php foreach (array_slice($day_events, 0, 3) as $ev): ?>
                        <div class="p-calendar__event" title="<?php echo esc_attr((string) ($ev['title'] ?? '')); ?>">
                            <?php echo esc_html((string) ($ev['title'] ?? '')); ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($day_events) > 3): ?>
                        <div class="p-calendar__more">+<?php echo esc_html(parsyar_format_number_fa(count($day_events) - 3)); ?></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<style>
.p-calendar__weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-bottom: 1px solid var(--p-color-line);
    font-size: var(--p-fs-2xs);
    text-transform: uppercase;
    letter-spacing: var(--p-ls-xwide);
    color: var(--p-color-ink-4);
}
.p-calendar__weekday { padding: var(--p-s-2); text-align: center; }
.p-calendar__grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: minmax(80px, auto);
    gap: 1px;
    background: var(--p-color-line-soft);
    border: 1px solid var(--p-color-line-soft);
    border-top: 0;
}
.p-calendar__day {
    background: var(--p-color-bg);
    padding: var(--p-s-2);
    display: flex;
    flex-direction: column;
    gap: 2px;
    cursor: pointer;
    transition: background var(--p-dur-1) var(--p-ease-out);
    min-height: 80px;
}
.p-calendar__day:hover { background: var(--p-color-surface); }
.p-calendar__day-num { font-size: var(--p-fs-xs); color: var(--p-color-ink-3); font-family: var(--p-font-num); }
.p-calendar__event {
    background: var(--p-color-ink);
    color: var(--p-color-bg);
    padding: 2px 4px;
    border-radius: var(--p-r-xs);
    font-size: var(--p-fs-2xs);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.p-calendar__more { font-size: var(--p-fs-2xs); color: var(--p-color-ink-4); }
</style>
