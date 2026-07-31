<?php
/**
 * 404 page
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

get_header();
?>
<div class="p-page p-page--narrow p-page-enter p-text-center" style="padding-block: var(--p-s-9);">
    <p class="p-hero__eyebrow"><?php esc_html_e('خطای ۴۰۴', 'parsyar'); ?></p>
    <h1 class="p-hero__title"><?php esc_html_e('صفحه‌ای که دنبال آن می‌گردید پیدا نشد.', 'parsyar'); ?></h1>
    <p class="p-hero__subtitle"><?php esc_html_e('ممکن است صفحه منتقل شده یا حذف شده باشد. می‌توانید به خانه برگردید.', 'parsyar'); ?></p>
    <div class="p-hero__cta">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="p-btn p-btn--primary"><?php esc_html_e('بازگشت به خانه', 'parsyar'); ?></a>
        <button type="button" class="p-btn p-btn--secondary" onclick="history.back()"><?php esc_html_e('بازگشت به عقب', 'parsyar'); ?></button>
    </div>
</div>
<?php
get_footer();
