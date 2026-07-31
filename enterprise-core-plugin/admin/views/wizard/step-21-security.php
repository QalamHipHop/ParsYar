<?php
/**
 * Wizard Step 21 — Security.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$s = $state['security'] ?? [];
?>
<div class="pw-banner info">
    تنظیمات امنیتی. توصیه می‌شود 2FA برای نقش‌های مدیریتی الزامی باشد.
</div>

<div class="pw-row">
    <div class="pw-field">
        <label>2FA برای مدیران</label>
        <label class="pw-chip <?php echo !empty($s['two_factor_required']) ? 'is-on' : ''; ?>" data-toggle="security[two_factor_required]"><?php echo !empty($s['two_factor_required']) ? 'الزامی' : 'اختیاری'; ?></label>
    </div>
    <div class="pw-field">
        <label>رمزگذاری داده در حالت سکون</label>
        <label class="pw-chip <?php echo !empty($s['encrypt_at_rest']) ? 'is-on' : ''; ?>" data-toggle="security[encrypt_at_rest]"><?php echo !empty($s['encrypt_at_rest']) ? 'فعال' : 'غیرفعال'; ?></label>
    </div>
</div>

<div class="pw-row-3">
    <div class="pw-field">
        <label>حداقل طول گذرواژه</label>
        <input type="number" name="security[password_min_length]" value="<?php echo (int) ($s['password_min_length'] ?? 12); ?>" min="6" max="64">
    </div>
    <div class="pw-field">
        <label>عمر نشست (ساعت)</label>
        <input type="number" name="security[session_lifetime]" value="<?php echo (int) (($s['session_lifetime'] ?? 28800) / 3600); ?>" min="1" max="168">
    </div>
    <div class="pw-field">
        <label>نگهداری لاگ حسابرسی (روز)</label>
        <input type="number" name="security[audit_retention_days]" value="<?php echo (int) ($s['audit_retention_days'] ?? 365); ?>" min="30" max="3650">
    </div>
</div>

<div class="pw-field">
    <label>IP Allowlist (یک IP در هر خط)</label>
    <textarea name="security[ip_allowlist_text]" rows="4" style="width:100%; font-family:monospace;"><?php echo esc_textarea(implode("\n", $s['ip_allowlist'] ?? [])); ?></textarea>
    <span class="desc">در صورت خالی بودن، همهٔ IPها مجازند.</span>
</div>
