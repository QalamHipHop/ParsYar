<?php
/**
 * Front Page — landing
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

get_header();
?>
<section class="p-hero p-page-enter">
    <p class="p-hero__eyebrow"><?php esc_html_e('پلتفرم سازمانی فارسی', 'parsyar'); ?></p>
    <h1 class="p-hero__title"><?php esc_html_e('CRM که به فارسی می‌اندیشد، در مقیاس جهانی می‌درخشد.', 'parsyar'); ?></h1>
    <p class="p-hero__subtitle"><?php esc_html_e('پلتفرم یکپارچه مدیریت ارتباط با مشتری، فروش، انبار، حسابداری و منابع انسانی — ساخته‌شده برای بازار ایران، مقیاس‌پذیر برای جهان.', 'parsyar'); ?></p>
    <div class="p-hero__cta">
        <a href="<?php echo esc_url(home_url('/app')); ?>" class="p-btn p-btn--primary p-btn--lg"><?php esc_html_e('ورود به داشبورد', 'parsyar'); ?></a>
        <a href="#features" class="p-btn p-btn--secondary p-btn--lg"><?php esc_html_e('مشاهده ویژگی‌ها', 'parsyar'); ?></a>
    </div>
</section>

<section class="p-section" id="features">
    <div class="p-container">
        <header class="p-page__header">
            <div>
                <h2><?php esc_html_e('۱۹ پایه، یک پلتفرم', 'parsyar'); ?></h2>
                <p class="p-page__subtitle"><?php esc_html_e('از مخاطب تا گزارش‌های مدیریتی، همه‌چیز در یک جا.', 'parsyar'); ?></p>
            </div>
        </header>

        <div class="p-grid p-grid--3 p-stagger">
            <?php
            $features = [
                ['icon' => 'users',     'title' => __('مخاطبین و روابط', 'parsyar'),  'desc' => __('مدیریت سازمان‌ها و افراد با امتیازدهی خودکار، رفع تکرار و تقسیم‌بندی پیشرفته.', 'parsyar')],
                ['icon' => 'briefcase', 'title' => __('معاملات و خط فروش', 'parsyar'), 'desc' => __('چند خط فروش، Kanban با محدودیت WIP، پیش‌بینی و هشدار رکود.', 'parsyar')],
                ['icon' => 'inbox',     'title' => __('صندوق ورودی یکپارچه', 'parsyar'), 'desc' => __('ایمیل، SMS، واتس‌اپ، اینستاگرام، تلگرام، بله، روبیکا و چت وب در یک‌جا.', 'parsyar')],
                ['icon' => 'calculator','title' => __('حسابداری دوطرفه', 'parsyar'), 'desc' => __('دفتر کل ۵ رقمی استاندارد ایران، گزارش تراز و سود و زیان.', 'parsyar')],
                ['icon' => 'package',   'title' => __('انبار چند شعبه‌ای', 'parsyar'), 'desc' => __('مدیریت کالا، موجودی، انتقال بین انبار و سفارش خرید.', 'parsyar')],
                ['icon' => 'workflow',  'title' => __('اتوماسیون بصری', 'parsyar'), 'desc' => __('ساخت گردش‌کار با تریگر، شرط و اکشن — بدون کد.', 'parsyar')],
            ];
            foreach ($features as $f): ?>
                <article class="p-card p-card--hover">
                    <svg class="p-rail__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--p-color-ink); margin-block-end: var(--p-s-3);">
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                    <h3 class="p-card__title"><?php echo esc_html($f['title']); ?></h3>
                    <p class="p-muted"><?php echo esc_html($f['desc']); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="p-section p-section--lg" style="background: var(--p-color-ink); color: var(--p-color-bg);">
    <div class="p-container p-text-center">
        <h2 style="color: var(--p-color-bg); font-size: var(--p-fs-3xl);"><?php esc_html_e('آماده شروع هستید؟', 'parsyar'); ?></h2>
        <p class="p-muted" style="color: rgba(255,255,255,.6); max-width: 600px; margin: var(--p-s-3) auto var(--p-s-5);"><?php esc_html_e('همین الان داشبورد را باز کنید و اولین مخاطب خود را در کمتر از ۳۰ ثانیه اضافه کنید.', 'parsyar'); ?></p>
        <a href="<?php echo esc_url(home_url('/app')); ?>" class="p-btn p-btn--secondary p-btn--lg" style="background: var(--p-color-bg); color: var(--p-color-ink); border-color: var(--p-color-bg);"><?php esc_html_e('ورود رایگان', 'parsyar'); ?></a>
    </div>
</section>
<?php
get_footer();
