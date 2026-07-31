<?php
/**
 * ParsYar Theme — Footer
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$is_dashboard = parsyar_is_dashboard();
?>
<?php if ($is_dashboard): ?>
        </main><!-- .p-main -->

        <?php if (is_active_sidebar('sidebar-dashboard')): ?>
            <aside class="p-rail-r" id="parsyar-rail-r" aria-label="<?php esc_attr_e('جزییات رکورد', 'parsyar'); ?>">
                <?php dynamic_sidebar('sidebar-dashboard'); ?>
            </aside>
        <?php endif; ?>

        <?php get_template_part('template-parts/components/command-palette'); ?>
        <?php get_template_part('template-parts/components/toast-stack'); ?>
    </div><!-- .p-app -->
<?php else: ?>
    </main><!-- .p-main -->

    <footer class="p-footer" role="contentinfo">
        <div class="p-container">
            <div class="p-footer__grid">
                <?php if (is_active_sidebar('footer-1')): ?>
                    <?php dynamic_sidebar('footer-1'); ?>
                <?php else: ?>
                    <div class="p-footer__col">
                        <h4 class="p-footer__title"><?php esc_html_e('ParsYar', 'parsyar'); ?></h4>
                        <p class="p-muted"><?php esc_html_e('CRM که به فارسی می‌اندیشد، در مقیاس جهانی می‌درخشد.', 'parsyar'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <hr class="p-divider" />
            <div class="p-cluster p-cluster--between">
                <p class="p-muted p-mono">
                    © <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?> · <?php esc_html_e('ساخته‌شده با', 'parsyar'); ?> ParsYar
                </p>
                <p class="p-muted p-mono">v<?php echo esc_html(PARSYAR_THEME_VERSION); ?></p>
            </div>
        </div>
    </footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
