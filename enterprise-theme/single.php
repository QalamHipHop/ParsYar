<?php
/**
 * Single post template
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

get_header();
?>
<div class="p-page p-page--narrow p-page-enter">
    <?php while (have_posts()): the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <nav class="p-page__breadcrumbs" aria-label="<?php esc_attr_e('مسیر', 'parsyar'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('خانه', 'parsyar'); ?></a>
                <?php if (get_the_category_list('')): ?>
                    <span><?php echo get_the_category_list(' '); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <?php endif; ?>
            </nav>

            <header class="p-page__header">
                <div>
                    <h1 class="p-page__title"><?php the_title(); ?></h1>
                    <p class="p-page__subtitle">
                        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
                        · <?php the_author(); ?>
                    </p>
                </div>
            </header>

            <?php if (has_post_thumbnail()): ?>
                <figure style="margin: 0 0 var(--p-s-5);">
                    <?php the_post_thumbnail('parsyar-hero', ['loading' => 'lazy']); ?>
                </figure>
            <?php endif; ?>

            <div class="p-stack">
                <?php the_content(); ?>
            </div>

            <footer class="p-section">
                <?php if (has_tag()): ?>
                    <div class="p-cluster">
                        <span class="p-mono p-muted"><?php esc_html_e('برچسب‌ها:', 'parsyar'); ?></span>
                        <?php the_tags('', ' ', ''); ?>
                    </div>
                <?php endif; ?>
            </footer>
        </article>

        <nav class="p-section" aria-label="<?php esc_attr_e('نوشته‌های مرتبط', 'parsyar'); ?>">
            <?php
            the_post_navigation([
                'prev_text' => '<span class="p-muted p-mono">' . esc_html__('قبلی', 'parsyar') . '</span> <span>%title</span>',
                'next_text' => '<span class="p-muted p-mono">' . esc_html__('بعدی', 'parsyar') . '</span> <span>%title</span>',
            ]);
            ?>
        </nav>

        <?php if (comments_open() || get_comments_number()): ?>
            <section class="p-section"><?php comments_template(); ?></section>
        <?php endif; ?>
    <?php endwhile; ?>
</div>
<?php
get_footer();
