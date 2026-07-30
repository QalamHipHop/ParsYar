<?php
declare(strict_types=1);

namespace Enterprise\Modules\Hrm;

defined('ABSPATH') || exit;

use Enterprise\Support\Db;

final class EmployeeService
{
    public static function all(): array
    {
        return Db::getResults('employees', [], 'id DESC', 200, 0);
    }

    public static function create(array $data): int
    {
        $code = sanitize_text_field((string) ($data['national_code'] ?? ''));
        if (strlen($code) < 8) {
            throw new \InvalidArgumentException('national_code required');
        }
        $id = Db::insert('employees', [
            'user_id'      => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'national_code'=> $code,
            'full_name'    => sanitize_text_field((string) ($data['full_name'] ?? '')),
            'base_salary'  => (float) ($data['base_salary'] ?? 0),
            'hire_date'    => sanitize_text_field((string) ($data['hire_date'] ?? gmdate('Y-m-d'))),
            'position'     => sanitize_text_field((string) ($data['position'] ?? '')),
        ]);
        \Enterprise\Modules\Audit\Logger::log('employee', $id, 'create', $data);
        return $id;
    }
}
