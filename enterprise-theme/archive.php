<?php
/**
 * Archive template
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
            <h1 class="p-page__title"><?php the_archive_title(); ?></h1>
            <?php if (get_the_archive_description()): ?>
                <div class="p-page__subtitle"><?php the_archive_description(); ?></div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (have_posts()): ?>
        <div class="p-grid p-grid--3 p-stagger">
            <?php while (have_posts()): the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('p-card p-card--hover'); ?>>
                    <?php if (has_post_thumbnail()): ?>
                        <a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                            <?php the_post_thumbnail('parsyar-card', ['loading' => 'lazy']); ?>
                        </a>
                    <?php endif; ?>
                    <h2 class="p-card__title" style="margin-top: var(--p-s-3);">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <p class="p-muted"><?php the_excerpt(); ?></p>
                    <footer class="p-cluster p-cluster--between" style="margin-top: var(--p-s-3);">
                        <span class="p-mono p-muted"><?php echo esc_html(get_the_date()); ?></span>
                        <a href="<?php the_permalink(); ?>" class="p-btn p-btn--ghost p-btn--sm"><?php esc_html_e('ادامه', 'parsyar'); ?> →</a>
                    </footer>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else: ?>
        <div class="p-empty">
            <h2 class="p-empty__title"><?php esc_html_e('محتوایی یافت نشد', 'parsyar'); ?></h2>
        </div>
    <?php endif; ?>
</div>
<?php
get_footer();
