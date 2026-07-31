<?php
/**
 * Wizard Step 20 — AI assistant.
 *
 * @var array $state
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$a = $state['ai'] ?? [];
$providers = [
    'openai'       => 'OpenAI (GPT-4o, GPT-4o-mini)',
    'anthropic'    => 'Anthropic (Claude)',
    'google'       => 'Google (Gemini)',
    'mistral'      => 'Mistral AI',
    'local'        => 'محلی (Ollama / LM Studio)',
    'huggingface'  => 'Hugging Face Inference',
    'rasa'         => 'Rasa (چت‌بات سازمانی)',
];
$features = [
    'lead_scoring'   => 'امتیازدهی خودکار سرنخ',
    'email_drafting' => 'پیش‌نویس ایمیل',
    'summarization'  => 'خلاصه‌سازی فعالیت‌ها',
    'sentiment'      => 'تحلیل احساسات',
    'translation'    => 'ترجمهٔ خودکار',
];
?>
<div class="pw-banner info">
    اتصال به یک دستیار هوش مصنوعی. کلید API در پایگاه‌داده با رمزنگاری ذخیره می‌شود.
</div>

<div class="pw-field">
    <label>فعال‌سازی</label>
    <label class="pw-chip <?php echo !empty($a['enabled']) ? 'is-on' : ''; ?>" data-toggle="ai[enabled]"><?php echo !empty($a['enabled']) ? 'فعال' : 'غیرفعال'; ?></label>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label>سرویس‌دهنده</label>
        <select name="ai[provider]">
            <?php foreach ($providers as $k => $v): ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($a['provider'] ?? 'openai', $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pw-field">
        <label>مدل</label>
        <input type="text" name="ai[model]" value="<?php echo esc_attr($a['model'] ?? 'gpt-4o-mini'); ?>" dir="ltr">
    </div>
</div>

<div class="pw-row">
    <div class="pw-field">
        <label>کلید API</label>
        <input type="password" name="ai[api_key]" value="<?php echo esc_attr($a['api_key'] ?? ''); ?>" dir="ltr" autocomplete="new-password">
    </div>
    <div class="pw-field">
        <label>Endpoint (برای سرویس‌های محلی)</label>
        <input type="url" name="ai[endpoint]" value="<?php echo esc_attr($a['endpoint'] ?? ''); ?>" dir="ltr" placeholder="http://localhost:11434">
    </div>
</div>

<div class="pw-row-3">
    <div class="pw-field">
        <label>حداکثر توکن</label>
        <input type="number" name="ai[max_tokens]" value="<?php echo (int) ($a['max_tokens'] ?? 2048); ?>" min="64" max="32000">
    </div>
    <div class="pw-field">
        <label>دما (Temperature)</label>
        <input type="number" name="ai[temperature]" value="<?php echo (float) ($a['temperature'] ?? 0.2); ?>" step="0.1" min="0" max="2">
    </div>
</div>

<div class="pw-field">
    <label>System Prompt</label>
    <textarea name="ai[system_prompt]" rows="4" style="width:100%;"><?php echo esc_textarea($a['system_prompt'] ?? ''); ?></textarea>
</div>

<h4>قابلیت‌ها</h4>
<div class="pw-actions-row">
    <?php foreach ($features as $k => $v): $on = !empty($a['features'][$k]); ?>
        <span class="pw-chip <?php echo $on ? 'is-on' : ''; ?>" data-toggle-array="ai[features][<?php echo esc_attr($k); ?>]"><?php echo esc_html($v); ?></span>
    <?php endforeach; ?>
</div>
