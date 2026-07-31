<?php
/**
 * Employee Service — مدیریت پرونده کارمندان.
 *
 * @package Enterprise\Modules\Hrm
 */

declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;
use Enterprise\Str;
use Enterprise\Validator;

final class EmployeeService
{
    /** @var string[] */
    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'intern', 'apprentice'];

    /** @var string[] */
    public const GENDERS = ['male', 'female', 'other'];

    /** @var string[] */
    public const MARITAL_STATUSES = ['single', 'married', 'divorced', 'widowed'];

    public static function all(): array
    {
        return Db::getResults('employees', [], 'id DESC', 200, 0);
    }

    public static function find(int $id): ?array
    {
        $row = Db::getRow('employees', ['id' => $id]);
        return $row ?: null;
    }

    public static function findByNationalCode(string $code): ?array
    {
        $row = Db::getRow('employees', ['national_code' => $code]);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        $code = sanitize_text_field((string) ($data['national_code'] ?? ''));
        if (strlen($code) < 8) {
            throw new \InvalidArgumentException('national_code required (min 8 chars)');
        }
        if (!Validator::nationalCode($code)) {
            throw new \InvalidArgumentException('national_code is not a valid Iranian code');
        }
        if (self::findByNationalCode($code) !== null) {
            throw new \InvalidArgumentException('national_code already exists');
        }

        $employmentType = (string) ($data['employment_type'] ?? 'full_time');
        if (!in_array($employmentType, self::EMPLOYMENT_TYPES, true)) {
            throw new \InvalidArgumentException('invalid employment_type');
        }

        $gender = (string) ($data['gender'] ?? '');
        if ($gender !== '' && !in_array($gender, self::GENDERS, true)) {
            throw new \InvalidArgumentException('invalid gender');
        }

        $marital = (string) ($data['marital_status'] ?? '');
        if ($marital !== '' && !in_array($marital, self::MARITAL_STATUSES, true)) {
            throw new \InvalidArgumentException('invalid marital_status');
        }

        $insert = [
            'uuid'              => Str::uuid(),
            'user_id'           => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'national_code'     => $code,
            'full_name'         => sanitize_text_field((string) ($data['full_name'] ?? '')),
            'first_name'        => sanitize_text_field((string) ($data['first_name'] ?? '')),
            'last_name'         => sanitize_text_field((string) ($data['last_name'] ?? '')),
            'father_name'       => sanitize_text_field((string) ($data['father_name'] ?? '')),
            'gender'            => $gender,
            'birth_date'        => self::normalizeDate($data['birth_date'] ?? null),
            'national_id_serial'=> sanitize_text_field((string) ($data['national_id_serial'] ?? '')),
            'place_of_birth'    => sanitize_text_field((string) ($data['place_of_birth'] ?? '')),
            'marital_status'    => $marital,
            'children_count'    => max(0, (int) ($data['children_count'] ?? 0)),
            'military_status'   => sanitize_text_field((string) ($data['military_status'] ?? '')),
            'phone'             => sanitize_text_field((string) ($data['phone'] ?? '')),
            'mobile'            => sanitize_text_field((string) ($data['mobile'] ?? '')),
            'email'             => sanitize_email((string) ($data['email'] ?? '')),
            'address'           => sanitize_textarea_field((string) ($data['address'] ?? '')),
            'postal_code'       => sanitize_text_field((string) ($data['postal_code'] ?? '')),
            'emergency_contact' => sanitize_text_field((string) ($data['emergency_contact'] ?? '')),
            'bank_sheba'        => sanitize_text_field((string) ($data['bank_sheba'] ?? '')),
            'bank_card'         => sanitize_text_field((string) ($data['bank_card'] ?? '')),
            'bank_account'      => sanitize_text_field((string) ($data['bank_account'] ?? '')),
            'insurance_number'  => sanitize_text_field((string) ($data['insurance_number'] ?? '')),
            'employment_type'   => $employmentType,
            'department'        => sanitize_text_field((string) ($data['department'] ?? '')),
            'position'          => sanitize_text_field((string) ($data['position'] ?? '')),
            'job_title'         => sanitize_text_field((string) ($data['job_title'] ?? '')),
            'manager_id'        => isset($data['manager_id']) ? (int) $data['manager_id'] : null,
            'hire_date'         => self::normalizeDate($data['hire_date'] ?? gmdate('Y-m-d'), gmdate('Y-m-d')),
            'termination_date'  => self::normalizeDate($data['termination_date'] ?? null),
            'probation_end_date'=> self::normalizeDate($data['probation_end_date'] ?? null),
            'base_salary'       => (float) ($data['base_salary'] ?? 0),
            'daily_wage'        => (float) ($data['daily_wage'] ?? 0),
            'hourly_wage'       => (float) ($data['hourly_wage'] ?? 0),
            'currency'          => sanitize_text_field((string) ($data['currency'] ?? 'IRT')),
            'work_schedule'     => sanitize_text_field((string) ($data['work_schedule'] ?? 'sat-wed')),
            'weekly_hours'      => max(0, (int) ($data['weekly_hours'] ?? 44)),
            'leave_balance'     => (float) ($data['leave_balance'] ?? 26),
            'sick_balance'      => (float) ($data['sick_balance'] ?? 10),
            'skill_tags'        => sanitize_text_field((string) ($data['skill_tags'] ?? '')),
            'avatar_url'        => esc_url_raw((string) ($data['avatar_url'] ?? '')),
            'notes'             => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'status'            => in_array(($data['status'] ?? 'active'), ['active', 'on_leave', 'suspended', 'terminated'], true)
                                    ? (string) $data['status']
                                    : 'active',
            'company_id'        => (int) ($data['company_id'] ?? 1),
            'branch_id'         => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            'meta'              => wp_json_encode((array) ($data['meta'] ?? [])),
            'created_by'        => get_current_user_id() ?: null,
        ];

        return (int) Db::insert('employees', $insert);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function update(int $id, array $data): bool
    {
        $existing = self::find($id);
        if (!$existing) {
            return false;
        }
        $updatable = [
            'full_name', 'first_name', 'last_name', 'father_name', 'gender',
            'birth_date', 'place_of_birth', 'marital_status', 'children_count',
            'military_status', 'phone', 'mobile', 'email', 'address', 'postal_code',
            'emergency_contact', 'bank_sheba', 'bank_card', 'bank_account',
            'insurance_number', 'employment_type', 'department', 'position',
            'job_title', 'manager_id', 'hire_date', 'termination_date',
            'probation_end_date', 'base_salary', 'daily_wage', 'hourly_wage',
            'currency', 'work_schedule', 'weekly_hours', 'leave_balance',
            'sick_balance', 'skill_tags', 'avatar_url', 'notes', 'status',
            'branch_id', 'meta',
        ];
        $patch = [];
        foreach ($updatable as $k) {
            if (array_key_exists($k, $data)) {
                $v = $data[$k];
                if (in_array($k, ['email'], true)) {
                    $v = sanitize_email((string) $v);
                } elseif (in_array($k, ['avatar_url'], true)) {
                    $v = esc_url_raw((string) $v);
                } elseif (in_array($k, ['notes', 'address'], true)) {
                    $v = sanitize_textarea_field((string) $v);
                } elseif ($k === 'meta') {
                    $v = wp_json_encode((array) $v);
                } else {
                    $v = sanitize_text_field((string) $v);
                }
                $patch[$k] = $v;
            }
        }
        if (empty($patch)) {
            return true;
        }
        return Db::update('employees', $patch, ['id' => $id]) !== false;
    }

    public static function delete(int $id): bool
    {
        return Db::delete('employees', ['id' => $id]) !== false;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public static function search(string $q, int $limit = 50): array
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($q) . '%';
        $sql  = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ent_employees
             WHERE full_name LIKE %s OR national_code LIKE %s OR mobile LIKE %s OR email LIKE %s
             ORDER BY id DESC LIMIT %d",
            $like, $like, $like, $like, $limit
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function count(?string $status = null): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_employees';
        if ($status === null) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status));
    }

    public static function averageTenureMonths(): float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ent_employees';
        $avg   = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(MONTH, hire_date, COALESCE(termination_date, CURDATE())))
             FROM {$table} WHERE status != 'terminated'"
        );
        return (float) ($avg ?? 0);
    }

    private static function normalizeDate($value, ?string $fallback = null): ?string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($ts === false) {
            return $fallback;
        }
        return gmdate('Y-m-d', $ts);
    }
}
