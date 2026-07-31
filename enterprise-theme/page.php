<?php
/**
 * Default page template
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

get_header();
?>
<div class="p-page p-page-enter">
    <?php while (have_posts()): the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="p-page__header">
                <div>
                    <h1 class="p-page__title"><?php the_title(); ?></h1>
                </div>
            </header>
            <div class="p-stack">
                <?php the_content(); ?>
                <?php
                wp_link_pages([
                    'before' => '<nav class="p-page__pagination p-cluster" aria-label="' . esc_attr__('صفحه‌ها', 'parsyar') . '">',
                    'after'  => '</nav>',
                    'link_before' => '<span class="p-badge">',
                    'link_after'  => '</span>',
                ]);
                ?>
            </div>
        </article>

        <?php if (comments_open() || get_comments_number()): ?>
            <section class="p-section">
                <?php comments_template(); ?>
            </section>
        <?php endif; ?>
    <?php endwhile; ?>
</div>
<?php
get_footer();
