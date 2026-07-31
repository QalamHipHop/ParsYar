<?php
/**
 * Main index template (fallback)
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

get_header();
?>
<div class="p-page p-page-enter">
    <?php if (have_posts()): ?>
        <header class="p-page__header">
            <div>
                <h1 class="p-page__title"><?php single_post_title(); ?></h1>
                <?php if (is_home() && !is_front_page()): ?>
                    <p class="p-page__subtitle"><?php esc_html_e('آخرین نوشته‌ها', 'parsyar'); ?></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="p-stack p-stagger">
            <?php while (have_posts()): the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('p-card p-card--hover'); ?>>
                    <header class="p-card__header">
                        <h2 class="p-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        <span class="p-muted p-mono"><?php echo esc_html(get_the_date()); ?></span>
                    </header>
                    <div class="p-card__body"><?php the_excerpt(); ?></div>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination([
            'prev_text' => '&rarr;',
            'next_text' => '&larr;',
            'mid_size'  => 2,
        ]); ?>

    <?php else: ?>
        <div class="p-empty">
            <svg class="p-empty__art" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <h2 class="p-empty__title"><?php esc_html_e('چیزی پیدا نشد', 'parsyar'); ?></h2>
            <p class="p-empty__msg"><?php esc_html_e('متأسفانه محتوایی برای نمایش وجود ندارد. می‌توانید جستجو کنید یا به خانه برگردید.', 'parsyar'); ?></p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="p-btn p-btn--primary"><?php esc_html_e('بازگشت به خانه', 'parsyar'); ?></a>
        </div>
    <?php endif; ?>
</div>
<?php
get_footer();
