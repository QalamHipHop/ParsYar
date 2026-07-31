<?php
/**
 * CLI — دستورات wp-cli برای ParsYar.
 *
 * در صورت نصب wp-cli روی سرور، دستورات زیر در دسترس خواهند بود:
 *
 *   wp parsyar status
 *   wp parsyar cache info|flush
 *   wp parsyar db install|seed|demo
 *   wp parsyar reports trial-balance|income|balance-sheet|journal
 *   wp parsyar ledger post --from-json=file.json
 *   wp parsyar notifications test --to=0912... --message="..."
 *   wp parsyar objects list|create|delete
 *   wp parsyar workflow run --id=N
 *   wp parsyar tax moodian submit --invoice-id=N
 *   wp parsyar backup create|restore
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('parsyar status',           [__NAMESPACE__ . '\\Cli', 'status']);
    \WP_CLI::add_command('parsyar cache info',        [__NAMESPACE__ . '\\Cli', 'cacheInfo']);
    \WP_CLI::add_command('parsyar cache flush',       [__NAMESPACE__ . '\\Cli', 'cacheFlush']);
    \WP_CLI::add_command('parsyar db install',        [__NAMESPACE__ . '\\Cli', 'dbInstall']);
    \WP_CLI::add_command('parsyar db seed',           [__NAMESPACE__ . '\\Cli', 'dbSeed']);
    \WP_CLI::add_command('parsyar reports trial-balance', [__NAMESPACE__ . '\\Cli', 'reportTrialBalance']);
    \WP_CLI::add_command('parsyar reports income',     [__NAMESPACE__ . '\\Cli', 'reportIncome']);
    \WP_CLI::add_command('parsyar reports balance-sheet', [__NAMESPACE__ . '\\Cli', 'reportBalanceSheet']);
    \WP_CLI::add_command('parsyar reports journal',   [__NAMESPACE__ . '\\Cli', 'reportJournal']);
    \WP_CLI::add_command('parsyar ledger post',       [__NAMESPACE__ . '\\Cli', 'ledgerPost']);
    \WP_CLI::add_command('parsyar notifications test',[__NAMESPACE__ . '\\Cli', 'notificationTest']);
    \WP_CLI::add_command('parsyar objects list',      [__NAMESPACE__ . '\\Cli', 'objectsList']);
    \WP_CLI::add_command('parsyar objects create',    [__NAMESPACE__ . '\\Cli', 'objectsCreate']);
    \WP_CLI::add_command('parsyar objects delete',    [__NAMESPACE__ . '\\Cli', 'objectsDelete']);
    \WP_CLI::add_command('parsyar workflow run',      [__NAMESPACE__ . '\\Cli', 'workflowRun']);
    \WP_CLI::add_command('parsyar tax moodian submit',[__NAMESPACE__ . '\\Cli', 'taxSubmit']);
    \WP_CLI::add_command('parsyar backup create',     [__NAMESPACE__ . '\\Cli', 'backupCreate']);
    \WP_CLI::add_command('parsyar backup restore',    [__NAMESPACE__ . '\\Cli', 'backupRestore']);
}

final class Cli
{
    public static function status(): void
    {
        \WP_CLI::line(sprintf('ParsYar %s', \Enterprise\Bootstrap::VERSION));
        \WP_CLI::line(sprintf('Cache backend: %s', \Enterprise\Cache::backend()));
        global $wpdb;
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}ent\\_%'");
        $count = is_array($tables) ? count($tables) : 0;
        $tables2 = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}parsyar\\_%'");
        $count += is_array($tables2) ? count($tables2) : 0;
        \WP_CLI::line(sprintf('Custom tables: %d', $count));
        \WP_CLI::success('OK');
    }

    public static function cacheInfo(): void
    {
        $info = \Enterprise\Cache::info();
        \WP_CLI::line(json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function cacheFlush(): void
    {
        $ok = \Enterprise\Cache::flushAll();
        $ok ? \WP_CLI::success('Cache flushed') : \WP_CLI::error('Flush failed');
    }

    public static function dbInstall(): void
    {
        \Enterprise\Includes\Installer::activate();
        \WP_CLI::success('Tables installed');
    }

    public static function dbSeed(): void
    {
        \Enterprise\Db\DemoSeeder::run();
        \WP_CLI::success('Demo data seeded');
    }

    /**
     * @param array $args
     * @param array $assoc
     */
    public static function reportTrialBalance($args, $assoc): void
    {
        $data = \Enterprise\Modules\Accounting\Reports::trialBalance(self::reportArgs($assoc));
        \WP_CLI::line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function reportIncome($args, $assoc): void
    {
        $data = \Enterprise\Modules\Accounting\Reports::incomeStatement(self::reportArgs($assoc));
        \WP_CLI::line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function reportBalanceSheet($args, $assoc): void
    {
        $asOf = (string) ($assoc['as-of'] ?? gmdate('Y-m-d'));
        $data = \Enterprise\Modules\Accounting\Reports::balanceSheet($asOf, self::reportArgs($assoc));
        \WP_CLI::line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function reportJournal($args, $assoc): void
    {
        $data = \Enterprise\Modules\Accounting\Reports::generalJournal(self::reportArgs($assoc));
        \WP_CLI::line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function ledgerPost($args, $assoc): void
    {
        if (empty($assoc['from-json'])) {
            \WP_CLI::error('--from-json=<file> required');
        }
        $path = (string) $assoc['from-json'];
        if (!is_file($path)) {
            \WP_CLI::error('file not found: ' . $path);
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            \WP_CLI::error('invalid JSON');
        }
        try {
            $id = \Enterprise\Modules\Accounting\Ledger::post($data);
            \WP_CLI::success('Posted entry #' . $id);
        } catch (\Throwable $e) {
            \WP_CLI::error('Post failed: ' . $e->getMessage());
        }
    }

    public static function notificationTest($args, $assoc): void
    {
        $to      = (string) ($assoc['to'] ?? '');
        $message = (string) ($assoc['message'] ?? 'ParsYar CLI test');
        $channel = (string) ($assoc['channel'] ?? 'inapp');
        $results = \Enterprise\Modules\Notification\NotificationService::dispatch([
            'user_id'  => 0,
            'channels' => [$channel],
            'title'    => 'Test',
            'message'  => $message,
            'data'     => ['mobile' => $to, 'email' => $to],
        ]);
        \WP_CLI::line(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function objectsList(): void
    {
        $rows = \Enterprise\Support\Db::getResults('objects', [], 'id ASC', 200, 0);
        foreach ($rows as $r) {
            \WP_CLI::line(sprintf('%3d  %-30s  %s', (int) $r['id'], $r['api_name'], $r['label']));
        }
    }

    public static function objectsCreate($args, $assoc): void
    {
        $api = (string) ($args[0] ?? '');
        $label = (string) ($assoc['label'] ?? $api);
        if ($api === '') {
            \WP_CLI::error('api_name required');
        }
        $id = \Enterprise\Modules\Objects\ObjectEngine::createObject([
            'api_name' => $api,
            'label'    => $label,
            'fields'   => [],
        ]);
        \WP_CLI::success('Created object #' . $id);
    }

    public static function objectsDelete($args, $assoc): void
    {
        $api = (string) ($args[0] ?? '');
        if ($api === '') {
            \WP_CLI::error('api_name required');
        }
        $force = !empty($assoc['force']);
        if ($force) {
            \Enterprise\Support\Db::query("UPDATE " . \Enterprise\Support\Db::table('objects') . " SET is_system=0 WHERE api_name=%s", [$api]);
        }
        $ok = \Enterprise\Modules\Objects\ObjectEngine::deleteObject($api);
        $ok ? \WP_CLI::success('Deleted') : \WP_CLI::error('Delete failed (system object?)');
    }

    public static function workflowRun($args, $assoc): void
    {
        $id = (int) ($assoc['id'] ?? 0);
        if ($id <= 0) {
            \WP_CLI::error('--id=N required');
        }
        \Enterprise\Modules\Workflow\Dispatcher::handle('cli.manual', ['workflow_id' => $id]);
        \WP_CLI::success('Workflow dispatched');
    }

    public static function taxSubmit($args, $assoc): void
    {
        $invoiceId = (int) ($assoc['invoice-id'] ?? 0);
        if ($invoiceId <= 0) {
            \WP_CLI::error('--invoice-id=N required');
        }
        try {
            $res = \Enterprise\Modules\Tax\MoodianClient::submit($invoiceId);
            \WP_CLI::line(json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            \WP_CLI::error('Submit failed: ' . $e->getMessage());
        }
    }

    public static function backupCreate($args, $assoc): void
    {
        $out = (string) ($assoc['output'] ?? '');
        $path = $out !== '' ? $out : sys_get_temp_dir() . '/parsyar-backup-' . gmdate('Ymd-His') . '.json';
        $data = \Enterprise\Includes\Backup::export();
        file_put_contents($path, wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        \WP_CLI::success('Backup written to ' . $path . ' (' . filesize($path) . ' bytes)');
    }

    public static function backupRestore($args, $assoc): void
    {
        $path = (string) ($assoc['from'] ?? '');
        if ($path === '' || !is_file($path)) {
            \WP_CLI::error('--from=<file> required and must exist');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            \WP_CLI::error('invalid JSON');
        }
        \Enterprise\Includes\Backup::import($data);
        \WP_CLI::success('Restored from ' . $path);
    }

    /**
     * @return array<string,mixed>
     */
    private static function reportArgs(array $assoc): array
    {
        return [
            'date_from'        => (string) ($assoc['from'] ?? ''),
            'date_to'          => (string) ($assoc['to']   ?? ''),
            'fiscal_period_id' => isset($assoc['period']) ? (int) $assoc['period'] : null,
            'company_id'       => isset($assoc['company']) ? (int) $assoc['company'] : 1,
            'include_zero'     => !empty($assoc['include-zero']),
        ];
    }
}
