<?php
/**
 * Employee Service — سرویس کارمندان
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Modules\Audit\Logger;
use Enterprise\Support\Db;

final class EmployeeService
{
    public const TABLE = 'employees';

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'intern', 'freelance'];
    public const STATUSES         = ['active', 'suspended', 'terminated', 'on_leave'];

    public static function all(array $filters = [], int $limit = 100, int $offset = 0, string $order = 'id DESC'): array
    {
        return Db::getResults(self::TABLE, $filters, $order, max(1, min(500, $limit)), max(0, $offset));
    }

    public static function count(array $filters = []): int
    {
        return Db::count(self::TABLE, $filters);
    }

    public static function find(int $id): ?array
    {
        return Db::getRow(self::TABLE, ['id' => $id]);
    }

    public static function findByNational(string $nationalCode): ?array
    {
        return Db::getRow(self::TABLE, ['national_code' => preg_replace('/\D+/', '', (string) $nationalCode)]);
    }

    public static function create(array $data): int
    {
        $national = preg_replace('/\D+/', '', (string) ($data['national_code'] ?? ''));
        if (strlen($national) < 8 || strlen($national) > 16) {
            throw new \InvalidArgumentException('national_code نامعتبر (باید ۸ تا ۱۶ رقم باشد)');
        }
        if (self::findByNational($national)) {
            throw new \InvalidArgumentException('کارمندی با این کد ملی قبلاً ثبت شده');
        }
        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '') {
            throw new \InvalidArgumentException('نام و نام خانوادگی الزامی است');
        }
        $hireDate = (string) ($data['hire_date'] ?? gmdate('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
            throw new \InvalidArgumentException('تاریخ استخدام نامعتبر');
        }

        $id = Db::insert(self::TABLE, [
            'user_id'         => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'national_code'   => $national,
            'full_name'       => $fullName,
            'email'           => sanitize_email((string) ($data['email'] ?? '')),
            'phone'           => sanitize_text_field((string) ($data['phone'] ?? '')),
            'gender'          => in_array($data['gender'] ?? '', ['male', 'female', 'other'], true) ? $data['gender'] : null,
            'birth_date'      => !empty($data['birth_date']) ? sanitize_text_field((string) $data['birth_date']) : null,
            'department'      => sanitize_text_field((string) ($data['department'] ?? '')),
            'manager_id'      => isset($data['manager_id']) ? (int) $data['manager_id'] : null,
            'position'        => sanitize_text_field((string) ($data['position'] ?? '')),
            'base_salary'     => (float) ($data['base_salary'] ?? 0),
            'employment_type' => in_array($data['employment_type'] ?? 'full_time', self::EMPLOYMENT_TYPES, true)
                ? $data['employment_type'] : 'full_time',
            'status'          => in_array($data['status'] ?? 'active', self::STATUSES, true) ? $data['status'] : 'active',
            'hire_date'       => $hireDate,
            'bank_account'    => sanitize_text_field((string) ($data['bank_account'] ?? '')),
            'bank_sheba'      => preg_replace('/\D+/', '', (string) ($data['bank_sheba'] ?? '')) ?: null,
            'bank_card'       => preg_replace('/\D+/', '', (string) ($data['bank_card'] ?? '')) ?: null,
            'address'         => sanitize_textarea_field((string) ($data['address'] ?? '')),
            'avatar_url'      => esc_url_raw((string) ($data['avatar_url'] ?? '')),
            'branch_id'       => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            'company_id'      => isset($data['company_id']) ? (int) $data['company_id'] : 1,
            'meta'            => !empty($data['meta']) ? wp_json_encode($data['meta']) : null,
        ]);
        Logger::log('employee', $id, 'create', ['national_code' => $national, 'full_name' => $fullName]);
        do_action('enterprise_event', 'employee.hired', ['employee_id' => $id, 'name' => $fullName]);
        return $id;
    }

    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        $patch = [];
        $map = [
            'user_id', 'email', 'phone', 'gender', 'birth_date', 'department',
            'manager_id', 'position', 'base_salary', 'employment_type', 'status',
            'termination_date', 'bank_account', 'bank_sheba', 'bank_card',
            'address', 'avatar_url', 'branch_id', 'company_id',
        ];
        foreach ($map as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            if ($k === 'national_code') {
                $v = preg_replace('/\D+/', '', (string) $v);
                if (strlen($v) < 8 || strlen($v) > 16) {
                    continue;
                }
                $dup = self::findByNational($v);
                if ($dup && (int) $dup['id'] !== $id) {
                    continue;
                }
                $patch[$k] = $v;
            } elseif ($k === 'email') {
                $patch[$k] = sanitize_email((string) $v);
            } elseif ($k === 'bank_sheba' || $k === 'bank_card') {
                $patch[$k] = preg_replace('/\D+/', '', (string) $v) ?: null;
            } elseif (in_array($k, ['base_salary'], true)) {
                $patch[$k] = (float) $v;
            } elseif (in_array($k, ['user_id', 'manager_id', 'branch_id', 'company_id'], true)) {
                $patch[$k] = (int) $v ?: null;
            } else {
                $patch[$k] = sanitize_text_field((string) $v);
            }
        }
        if (isset($data['full_name'])) {
            $patch['full_name'] = sanitize_text_field(trim((string) $data['full_name']));
        }
        if (!empty($data['meta'])) {
            $patch['meta'] = wp_json_encode($data['meta']);
        }
        if (empty($patch)) {
            return true;
        }
        Db::update(self::TABLE, $patch, ['id' => $id]);
        Logger::log('employee', $id, 'update', array_keys($patch));
        do_action('enterprise_event', 'employee.updated', ['employee_id' => $id, 'patch' => array_keys($patch)]);
        return true;
    }

    public static function delete(int $id, bool $hard = false): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        if ($hard) {
            Db::delete(self::TABLE, ['id' => $id]);
            Db::delete('attendance', ['employee_id' => $id]);
        } else {
            Db::update(self::TABLE, [
                'status'           => 'terminated',
                'termination_date' => gmdate('Y-m-d'),
            ], ['id' => $id]);
        }
        Logger::log('employee', $id, $hard ? 'hard_delete' : 'terminate', []);
        return true;
    }

    public static function search(string $q, int $limit = 20): array
    {
        global $wpdb;
        $t = Db::table(self::TABLE);
        $q = '%' . $wpdb->esc_like($q) . '%';
        $sql = $wpdb->prepare(
            "SELECT id, full_name, national_code, position, department, email, phone, status
             FROM {$t}
             WHERE full_name LIKE %s OR national_code LIKE %s OR position LIKE %s OR department LIKE %s
             ORDER BY full_name ASC LIMIT %d",
            $q, $q, $q, $q, max(1, min(50, $limit))
        );
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * آمار پرسنل.
     */
    public static function stats(): array
    {
        global $wpdb;
        $t = Db::table(self::TABLE);
        $total   = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$t}") ?: 0);
        $active  = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status = 'active'") ?: 0);
        $leave   = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status = 'on_leave'") ?: 0);
        $term    = (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status = 'terminated'") ?: 0);
        $byDept  = $wpdb->get_results(
            "SELECT IFNULL(department, '—') AS department, COUNT(*) AS cnt
             FROM {$t} WHERE status = 'active' GROUP BY department ORDER BY cnt DESC LIMIT 20",
            ARRAY_A
        ) ?: [];
        $byType  = $wpdb->get_results(
            "SELECT employment_type AS type, COUNT(*) AS cnt
             FROM {$t} WHERE status = 'active' GROUP BY employment_type",
            ARRAY_A
        ) ?: [];
        return [
            'total'         => $total,
            'active'        => $active,
            'on_leave'      => $leave,
            'terminated'    => $term,
            'by_department' => $byDept,
            'by_type'       => $byType,
        ];
    }
}
