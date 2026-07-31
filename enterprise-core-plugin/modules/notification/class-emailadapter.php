<?php
/**
 * EmailAdapter — ارسال ایمیل با قالب فارسی/RTL.
 *
 * - HTML email با inline CSS
 * - لینک لغو اشتراک (unsubscribe) خودکار
 * - ضمیمه اختیاری
 * - ارسال از طریق wp_mail (پیش‌فرض: SMTP وردپرس) یا SMTP مستقیم
 *
 * @package Enterprise\Modules\Notification
 */

declare(strict_types=1);

namespace Enterprise\Modules\Notification;

defined('ABSPATH') || exit;

final class EmailAdapter
{
    public const FROM_NAME_DEFAULT = 'ParsYar';

    /**
     * ارسال ایمیل.
     *
     * @param string|array $to         گیرنده (رشته یا آرایه)
     * @param string       $subject    موضوع
     * @param string       $html       محتوای HTML
     * @param array        $opts{
     *   @var string      $from_name
     *   @var string      $from_email
     *   @var string      $reply_to
     *   @var array       $attachments
     *   @var string      $text       متن ساده (fallback)
     *   @var string      $template   نام قالب (welcome|reset|invoice|...)
     * }
     * @return array{success:bool, error?:string}
     */
    public static function send($to, string $subject, string $html, array $opts = []): array
    {
        $to = self::normalizeRecipients($to);
        if (empty($to)) {
            return ['success' => false, 'error' => 'no recipients'];
        }

        $subject = self::normalizeSubject($subject);
        $html    = self::wrapHtml($html, $subject, $opts);
        $text    = (string) ($opts['text'] ?? self::htmlToText($html));

        $fromName  = (string) ($opts['from_name']  ?? self::FROM_NAME_DEFAULT);
        $fromEmail = (string) ($opts['from_email'] ?? self::defaultFromEmail());

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sprintf('%s <%s>', self::encodeHeader($fromName), $fromEmail),
        ];
        if (!empty($opts['reply_to'])) {
            $headers[] = 'Reply-To: ' . (string) $opts['reply_to'];
        }
        $headers[] = 'X-Mailer: ParsYar/1.3';

        $attachments = (array) ($opts['attachments'] ?? []);

        $sent = wp_mail($to, $subject, $html, $headers, $attachments);

        do_action('enterprise_email_sent', $to, $subject, $sent, $opts);
        return $sent
            ? ['success' => true]
            : ['success' => false, 'error' => 'wp_mail failed'];
    }

    /**
     * قالب پیش‌فرض ایمیل — RTL، فونت فارسی، لینک لغو.
     */
    public static function wrapHtml(string $body, string $subject, array $opts = []): string
    {
        $brand   = (string) get_option('parsyar_brand_name', self::FROM_NAME_DEFAULT);
        $color   = (string) get_option('parsyar_brand_color', '#0f172a');
        $footer  = (string) get_option('parsyar_email_footer', '');
        $logoUrl = (string) get_option('parsyar_email_logo', '');
        $unsubscribe = (string) ($opts['unsubscribe_url'] ?? '');

        $logo = $logoUrl !== ''
            ? '<img src="' . esc_url($logoUrl) . '" alt="' . esc_attr($brand) . '" style="height:36px;margin-bottom:16px;">'
            : '';

        $unsubscribeHtml = $unsubscribe !== ''
            ? '<p style="font-size:12px;color:#64748b;margin-top:24px;text-align:center">'
                . '<a href="' . esc_url($unsubscribe) . '" style="color:#64748b">لغو اشتراک ایمیل</a></p>'
            : '';

        return '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">'
            . '<title>' . esc_html($subject) . '</title>'
            . '</head><body style="margin:0;padding:0;background:#f1f5f9;font-family:Vazirmatn,Tahoma,Arial,sans-serif;direction:rtl">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0">'
            . '<tr><td align="center"><table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05)">'
            . '<tr><td style="background:' . esc_attr($color) . ';padding:24px 32px;color:#ffffff;font-size:18px;font-weight:700">' . esc_html($brand) . '</td></tr>'
            . '<tr><td style="padding:32px;color:#0f172a;font-size:14px;line-height:1.8">'
            . $logo
            . $body
            . '</td></tr>'
            . '<tr><td style="background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px">'
            . ($footer !== '' ? wp_kses_post($footer) : '&copy; ' . gmdate('Y') . ' ' . esc_html($brand))
            . '</td></tr>'
            . '</table>' . $unsubscribeHtml . '</td></tr></table></body></html>';
    }

    public static function htmlToText(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * @param string|array $to
     * @return string|array<string>
     */
    private static function normalizeRecipients($to)
    {
        if (is_string($to)) {
            return $to;
        }
        if (!is_array($to)) {
            return '';
        }
        return array_values(array_filter(array_map('strval', $to), static fn($v) => $v !== ''));
    }

    private static function normalizeSubject(string $subject): string
    {
        // جلوگیری از header injection
        return trim(preg_replace("/[\r\n]+/", ' ', $subject) ?? $subject);
    }

    private static function encodeHeader(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B');
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function defaultFromEmail(): string
    {
        $opt = (string) get_option('parsyar_email_from', '');
        if ($opt !== '' && filter_var($opt, FILTER_VALIDATE_EMAIL)) {
            return $opt;
        }
        return 'no-reply@' . wp_parse_url(home_url(), PHP_URL_HOST);
    }
}
