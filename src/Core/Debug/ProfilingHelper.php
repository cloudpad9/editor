<?php
namespace CloudPad\Core\Debug;

/**
 * ProfilingHelper — Công cụ đo elapsed time trong request lifecycle.
 *
 * Tách từ class `ProfilingHelper` trong index.php (Phase 8.3).
 * Chỉ active khi request có param `PROFILING=1`.
 *
 * Usage:
 *   ProfilingHelper::track(__FILE__, __LINE__, 'after DB query');
 *   ProfilingHelper::ellapsed_time('checkpoint');
 */
class ProfilingHelper
{
    public static function track(string $file, int $line, string $desc = ''): void
    {
        static $filenames = [];

        if (!isset($filenames[$file])) {
            $filenames[$file] = basename($file);
        }

        $name = $filenames[$file];
        self::ellapsed_time("{$name}:{$line}" . (!empty($desc) ? ":{$desc}" : ''));
    }

    public static function ellapsed_time(
        string $desc        = '',
        bool   $commented   = false,
        bool   $returnbody  = false
    ): ?string {
        static $latest = null;

        $enabled = \CloudPad\Core\Request::getBool('PROFILING');
        if (!$enabled) {
            return null;
        }

        if ($latest === null) {
            $latest = $_SERVER['REQUEST_TIME_FLOAT'];
        }

        $time             = microtime(true);
        $time_from_start  = $time - $_SERVER['REQUEST_TIME_FLOAT'];
        $time_from_latest = $time - $latest;
        $latest           = $time;

        $msg = sprintf(
            '<pre>[%s] Elapsed: %s, from previous: %s</pre>',
            $desc,
            self::friendly_format($time_from_start, 200),
            self::friendly_format($time_from_latest, 5)
        );

        if ($commented) {
            $msg = "<!-- $desc -->";
        }

        if ($returnbody) {
            return $msg;
        }

        echo $msg;
        return null;
    }

    public static function friendly_format(float $time, float $ms_threshold = 0, string $color = 'red'): string
    {
        $out = $time > 1
            ? round($time, 3) . 's'
            : round($time * 1000, 2) . 'ms';

        if ($ms_threshold > 0 && $time * 1000 > $ms_threshold) {
            $out = "<span style=\"color:{$color}\">{$out}</span>";
        }

        return $out;
    }
}
