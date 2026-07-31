<?php
/**
 * Comments template
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;
?>
<section id="comments" class="p-comments">
    <?php if (have_comments()): ?>
        <h2 class="p-page__title" style="font-size: var(--p-fs-xl);">
            <?php
            $count = (int) get_comments_number();
            if (1 === $count) {
                printf(esc_html__('یک دیدگاه برای «%s»', 'parsyar'), esc_html(get_the_title()));
            } else {
                printf(esc_html(_n('%s دیدگاه برای «%s»', '%s دیدگاه برای «%s»', $count, 'parsyar')), esc_html(number_format_i18n($count)), esc_html(get_the_title()));
            }
            ?>
        </h2>

        <ol class="p-comments__list p-stack p-stagger" style="list-style: none; padding: 0;">
            <?php wp_list_comments([
                'style'      => 'ol',
                'short_ping' => true,
                'avatar_size'=> 48,
            ]); ?>
        </ol>

        <?php the_comments_pagination([
            'prev_text' => '←',
            'next_text' => '→',
        ]); ?>

        <?php if (!comments_open()): ?>
            <p class="p-alert p-alert--info"><?php esc_html_e('دیدگاه‌ها بسته شده‌اند.', 'parsyar'); ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    comment_form([
        'class_form'  => 'p-comments__form p-stack',
        'class_submit'=> 'p-btn p-btn--primary',
        'title_reply' => esc_html__('دیدگاه خود را بنویسید', 'parsyar'),
        'label_submit'=> esc_html__('ارسال دیدگاه', 'parsyar'),
        'comment_field' => '<p class="p-field"><label class="p-field__label" for="comment">' . esc_html__('دیدگاه', 'parsyar') . '</label><textarea id="comment" name="comment" rows="6" class="p-input" required></textarea></p>',
    ]);
    ?>
</section>
