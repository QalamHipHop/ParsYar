<?php
/**
 * Front-end sidebar
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;
?>
<aside class="p-sidebar" id="secondary" aria-label="<?php esc_attr_e('سایدبار', 'parsyar'); ?>">
    <?php if (is_active_sidebar('sidebar-primary')): ?>
        <?php dynamic_sidebar('sidebar-primary'); ?>
    <?php else: ?>
        <section class="p-card">
            <h3 class="p-card__title"><?php esc_html_e('درباره', 'parsyar'); ?></h3>
            <p><?php esc_html_e('این یک سایدبار نمونه است. می‌توانید ابزارک‌های خود را از', 'parsyar'); ?> <a href="<?php echo esc_url(admin_url('widgets.php')); ?>"><?php esc_html_e('پیشخوان', 'parsyar'); ?></a> <?php esc_html_e('اضافه کنید.', 'parsyar'); ?></p>
        </section>
    <?php endif; ?>
</aside>
