<?php
/**
 * Search results
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

get_header();
?>
<div class="p-page p-page-enter">
    <header class="p-page__header">
        <div>
            <h1 class="p-page__title">
                <?php printf(esc_html__('نتایج جستجو برای: «%s»', 'parsyar'), '<span class="p-mono">' . esc_html(get_search_query()) . '</span>'); ?>
            </h1>
            <p class="p-page__subtitle">
                <?php printf(esc_html(_n('%s نتیجه', '%s نتیجه', (int) $GLOBALS['wp_query']->found_posts, 'parsyar')), esc_html(number_format_i18n((int) $GLOBALS['wp_query']->found_posts))); ?>
            </p>
        </div>
    </header>

    <?php if (have_posts()): ?>
        <div class="p-stack p-stagger">
            <?php while (have_posts()): the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('p-card'); ?>>
                    <header class="p-card__header">
                        <h2 class="p-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <span class="p-badge p-badge--outline"><?php echo esc_html(get_post_type_object(get_post_type())->labels->singular_name); ?></span>
                    </header>
                    <p><?php the_excerpt(); ?></p>
                </article>
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else: ?>
        <div class="p-empty">
            <h2 class="p-empty__title"><?php esc_html_e('نتیجه‌ای یافت نشد', 'parsyar'); ?></h2>
            <p class="p-empty__msg"><?php esc_html_e('لطفاً کلیدواژه دیگری امتحان کنید.', 'parsyar'); ?></p>
            <?php get_search_form(); ?>
        </div>
    <?php endif; ?>
</div>
<?php
get_footer();
