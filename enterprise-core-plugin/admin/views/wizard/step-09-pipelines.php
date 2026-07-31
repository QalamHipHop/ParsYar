<?php
/**
 * Wizard Step 9 — Pipelines.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$pipelines = $state['pipelines'] ?? [];
?>
<div class="pw-banner info">
    خطوط فروش، مراحل، و احتمال موفقیت هر مرحله را تنظیم کنید. سیستم پیش‌فرض ۶ مرحلهٔ سرنخ/واجد شرایط/پیشنهاد/مذاکره/برنده/باخته دارد. می‌توانید خطوط فروش متعدد (مثل فروش مستقیم، فروش آنلاین، فروش سازمانی) با مراحل سفارشی ایجاد کنید.
</div>

<div id="pw-pipelines-list">
    <?php foreach ($pipelines as $pi => $p): ?>
        <div class="pw-pipeline" data-pipeline-index="<?php echo (int) $pi; ?>" style="border:1px solid #e5e5e5; border-radius:12px; padding:14px; margin-bottom:14px;">
            <div class="pw-row">
                <div class="pw-field">
                    <label>نام خط فروش</label>
                    <input type="text" name="pipelines[<?php echo (int) $pi; ?>][name]" value="<?php echo esc_attr($p['name'] ?? ''); ?>">
                </div>
                <div class="pw-field">
                    <label>کد</label>
                    <input type="text" name="pipelines[<?php echo (int) $pi; ?>][id]" value="<?php echo esc_attr($p['id'] ?? ''); ?>" pattern="[a-z0-9_]+">
                </div>
            </div>
            <h4 style="margin:12px 0 8px; font-size:13px;">مراحل</h4>
            <div class="pw-stages">
                <?php foreach (($p['stages'] ?? []) as $si => $s): ?>
                    <div class="pw-stage" data-stage-index="<?php echo (int) $si; ?>" style="display:grid; grid-template-columns: 2fr 3fr 1fr 1fr auto; gap:8px; align-items:end; padding:6px 0;">
                        <input type="text" placeholder="کد" name="pipelines[<?php echo (int) $pi; ?>][stages][<?php echo (int) $si; ?>][id]" value="<?php echo esc_attr($s['id'] ?? ''); ?>">
                        <input type="text" placeholder="نام" name="pipelines[<?php echo (int) $pi; ?>][stages][<?php echo (int) $si; ?>][name]" value="<?php echo esc_attr($s['name'] ?? ''); ?>">
                        <input type="number" placeholder="احتمال %" min="0" max="100" name="pipelines[<?php echo (int) $pi; ?>][stages][<?php echo (int) $si; ?>][probability]" value="<?php echo (int) ($s['probability'] ?? 0); ?>">
                        <input type="number" placeholder="WIP" min="0" name="pipelines[<?php echo (int) $pi; ?>][stages][<?php echo (int) $si; ?>][wip_limit]" value="<?php echo (int) ($s['wip_limit'] ?? 0); ?>">
                        <button type="button" class="button pw-del-stage">حذف</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button pw-add-stage" style="margin-top:8px;">+ افزودن مرحله</button>
        </div>
    <?php endforeach; ?>
</div>

<button type="button" class="button" id="pw-add-pipeline">+ افزودن خط فروش</button>
