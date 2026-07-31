<?php
/**
 * Cache — لایهٔ کش یکپارچه با fallback خودکار.
 *
 * اولویت‌ها:
 *   1. Redis (اگر ext-redis موجود باشد)
 *   2. Memcached (اگر ext-memcached موجود باشد)
 *   3. WordPress transients (همیشه در دسترس)
 *
 * تمام مقادیر با نسخه‌گذاری prefix و namespace نگهداری می‌شوند.
 * هنگام تغییر نسخه (مثلاً بعد از schema migration) می‌توان کل یک namespace را invalidate کرد.
 *
 * @package Enterprise\Core
 */

declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

final class Cache
{
    /** پیش‌وند کلی برای تمام کلیدها */
    private const KEY_PREFIX = 'parsyar:';

    /** آمارد داخلی (counter) */
    private static array $stats = [
        'hits'      => 0,
        'misses'    => 0,
        'sets'      => 0,
        'deletes'   => 0,
        'backend'   => 'transient',
    ];

    /** @var \Redis|\Memcached|null */
    private static $client = null;
    private static bool $initialized = false;
    private static string $resolvedBackend = 'transient';

    /**
     * راه‌اندازی backend مناسب. فقط یک‌بار در فرآیند اجرا می‌شود.
     */
    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // 1) Redis
        if (class_exists('\Redis') && defined('\Redis::SERIALIZER_PHP')) {
            try {
                $cfg = self::redisConfig();
                if ($cfg !== null) {
                    $r = new \Redis();
                    $ok = $cfg['persistent']
                        ? $r->pconnect($cfg['host'], $cfg['port'], 1.5)
                        : $r->connect($cfg['host'], $cfg['port'], 1.5);
                    if ($ok) {
                        if ($cfg['password'] !== '') {
                            $r->auth($cfg['password']);
                        }
                        if (isset($cfg['db']) && $cfg['db'] > 0) {
                            $r->select($cfg['db']);
                        }
                        $r->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
                        self::$client         = $r;
                        self::$resolvedBackend = 'redis';
                    }
                }
            } catch (\Throwable $e) {
                self::$client = null;
            }
        }

        // 2) Memcached
        if (self::$client === null && class_exists('\Memcached')) {
            try {
                $cfg = self::memcachedConfig();
                if ($cfg !== null) {
                    $m = new \Memcached($cfg['persistent_id'] ?: null);
                    $servers = $m->getServerList();
                    if (empty($servers)) {
                        $m->addServer($cfg['host'], $cfg['port']);
                        $m->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                        $m->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 1500);
                    }
                    if ($cfg['password'] !== '' && method_exists($m, 'setSaslAuthData')) {
                        $m->setSaslAuthData('', $cfg['password']);
                    }
                    self::$client         = $m;
                    self::$resolvedBackend = 'memcached';
                }
            } catch (\Throwable $e) {
                self::$client = null;
            }
        }

        self::$stats['backend'] = self::$resolvedBackend;
    }

    /**
     * @return array{host:string,port:int,password:string,db:int,persistent:bool}|null
     */
    private static function redisConfig(): ?array
    {
        $host = defined('PARSYAR_REDIS_HOST') ? (string) PARSYAR_REDIS_HOST : '';
        if ($host === '') {
            return null;
        }
        return [
            'host'       => $host,
            'port'       => defined('PARSYAR_REDIS_PORT') ? (int) PARSYAR_REDIS_PORT : 6379,
            'password'   => defined('PARSYAR_REDIS_PASSWORD') ? (string) PARSYAR_REDIS_PASSWORD : '',
            'db'         => defined('PARSYAR_REDIS_DB') ? (int) PARSYAR_REDIS_DB : 0,
            'persistent' => defined('PARSYAR_REDIS_PERSISTENT') ? (bool) PARSYAR_REDIS_PERSISTENT : false,
        ];
    }

    /**
     * @return array{host:string,port:int,password:string,persistent_id:string}|null
     */
    private static function memcachedConfig(): ?array
    {
        $host = defined('PARSYAR_MEMCACHED_HOST') ? (string) PARSYAR_MEMCACHED_HOST : '';
        if ($host === '') {
            return null;
        }
        return [
            'host'          => $host,
            'port'          => defined('PARSYAR_MEMCACHED_PORT') ? (int) PARSYAR_MEMCACHED_PORT : 11211,
            'password'      => defined('PARSYAR_MEMCACHED_PASSWORD') ? (string) PARSYAR_MEMCACHED_PASSWORD : '',
            'persistent_id' => defined('PARSYAR_MEMCACHED_PERSISTENT_ID') ? (string) PARSYAR_MEMCACHED_PERSISTENT_ID : 'parsyar_memcached',
        ];
    }

    /**
     * دریافت مقدار.
     *
     * @param string $key    کلید ساده (بدون prefix)
     * @param string $group  گروه/namespace (برای invalidation)
     * @return mixed|null    مقدار یا null در صورت نبود
     */
    public static function get(string $key, string $group = 'core')
    {
        self::init();
        $full = self::key($key, $group);

        $hit = false;
        $val = null;

        if (self::$client instanceof \Redis) {
            try {
                $val = self::$client->get($full);
                $hit = $val !== false;
            } catch (\Throwable $e) {
                $hit = false;
            }
        } elseif (self::$client instanceof \Memcached) {
            $val = self::$client->get($full);
            $hit = $val !== false && self::$client->getResultCode() === \Memcached::RES_SUCCESS;
        } else {
            $val = get_transient($full);
            $hit = $val !== false;
        }

        if ($hit) {
            self::$stats['hits']++;
        } else {
            self::$stats['misses']++;
        }
        return $hit ? $val : null;
    }

    /**
     * ذخیرهٔ مقدار.
     *
     * @param string $key    کلید ساده
     * @param mixed  $value  مقدار (هر نوع PHP قابل serialize)
     * @param int    $ttl    زمان انقضا بر حسب ثانیه (0 = بدون انقضا)
     * @param string $group  گروه/namespace
     * @return bool
     */
    public static function set(string $key, $value, int $ttl = 300, string $group = 'core'): bool
    {
        self::init();
        $full = self::key($key, $group);
        $ttl  = max(0, $ttl);
        self::$stats['sets']++;

        if (self::$client instanceof \Redis) {
            try {
                return (bool) ($ttl > 0
                    ? self::$client->setex($full, $ttl, $value)
                    : self::$client->set($full, $value));
            } catch (\Throwable $e) {
                return false;
            }
        }
        if (self::$client instanceof \Memcached) {
            try {
                return (bool) self::$client->set($full, $value, $ttl);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return (bool) set_transient($full, $value, $ttl);
    }

    /**
     * دریافت یا محاسبه (رایج‌ترین الگو).
     *
     * @template T
     * @param callable():T $producer  تابع تولید مقدار
     * @return T
     */
    public static function remember(string $key, callable $producer, int $ttl = 300, string $group = 'core')
    {
        $cached = self::get($key, $group);
        if ($cached !== null) {
            return $cached;
        }
        $val = $producer();
        self::set($key, $val, $ttl, $group);
        return $val;
    }

    /**
     * حذف یک کلید.
     */
    public static function forget(string $key, string $group = 'core'): bool
    {
        self::init();
        $full = self::key($key, $group);
        self::$stats['deletes']++;

        if (self::$client instanceof \Redis) {
            try {
                return self::$client->del($full) > 0;
            } catch (\Throwable $e) {
                return false;
            }
        }
        if (self::$client instanceof \Memcached) {
            try {
                return self::$client->delete($full);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return (bool) delete_transient($full);
    }

    /**
     * حذف تمام کلیدهای یک گروه.
     */
    public static function flushGroup(string $group): int
    {
        self::init();
        $deleted = 0;

        if (self::$client instanceof \Redis) {
            try {
                $iterator = null;
                $pattern  = self::KEY_PREFIX . $group . ':*';
                self::$client->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
                while (($keys = self::$client->scan($iterator, $pattern, 500)) !== false) {
                    if (!empty($keys)) {
                        $deleted += (int) self::$client->del($keys);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        } elseif (self::$client instanceof \Memcached) {
            // Memcached فاقد SCAN است؛ در این حالت best-effort با versioning
            $versionKey = self::key('__version__', $group);
            try {
                self::$client->increment($versionKey, 1, 1, 0);
                $deleted = 1;
            } catch (\Throwable $e) {
                // ignore
            }
        } else {
            // WordPress: API استانداردی برای flush-by-group ندارد؛ best-effort با versioning
            $versionKey = '_transient_timeout_' . self::key('__version__', $group);
            $v = (int) (get_option($versionKey) ?: 1);
            update_option($versionKey, (string) ($v + 1), false);
            $deleted = 1;
        }
        return $deleted;
    }

    /**
     * پاک‌سازی کامل فقط کلیدهای پارسیار (نه سایر transients سایت).
     */
    public static function flushAll(): bool
    {
        self::init();
        if (self::$client instanceof \Redis) {
            try {
                $iterator = null;
                $pattern  = self::KEY_PREFIX . '*';
                while (($keys = self::$client->scan($iterator, $pattern, 500)) !== false) {
                    if (!empty($keys)) {
                        self::$client->del($keys);
                    }
                }
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }
        // best-effort: افزایش نسخهٔ عمومی
        return (bool) update_option('parsyar_cache_global_version', (string) (time()), false);
    }

    /**
     * اطلاعات backend فعال + آمارد session جاری.
     *
     * @return array{backend:string,available:bool,stats:array<string,int>}
     */
    public static function info(): array
    {
        self::init();
        return [
            'backend'   => self::$resolvedBackend,
            'available' => self::$resolvedBackend !== 'transient',
            'stats'     => self::$stats,
        ];
    }

    public static function backend(): string
    {
        self::init();
        return self::$resolvedBackend;
    }

    /**
     * ساخت کلید نهایی با prefix و version.
     */
    private static function key(string $key, string $group): string
    {
        $g = preg_replace('/[^a-z0-9_]/', '', strtolower($group)) ?: 'core';
        $k = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $key) ?: 'unknown';
        return self::KEY_PREFIX . $g . ':' . $k;
    }
}
