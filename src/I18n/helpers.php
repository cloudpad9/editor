<?php
/**
 * I18n helper functions — autoloaded qua composer.
 *
 * Tách từ index.php (Phase 8.5).
 * Hàm _t() vẫn dùng global $builder để lấy lang — sẽ được refactor
 * hoàn toàn ở Phase 10 khi Builder không còn global nữa.
 */

if (!function_exists('_t')) {
    function _t(string $key, bool $escape = false): string
    {
        global $_L;
        global $builder;

        if (empty($key)) {
            return '';
        }

        if (!isset($_L[$key])) {
            // Lazy-load: thêm key vào language file nếu chưa có
            if (isset($builder) && is_object($builder)) {
                $lang     = $builder->get_lang();
                $langfile = BUILDER_DIR . "/locales/{$lang}.php";

                if (file_exists($langfile)) {
                    $safeKey = addslashes($key);
                    $content = file_get_contents($langfile);
                    $content .= "\$_L['{$safeKey}'] = '{$safeKey}';\n";
                    file_put_contents($langfile, $content);
                    $_L[$key] = $key;
                }
            }

            $text = $key;
        } else {
            $text = $_L[$key];
        }

        if ($escape) {
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8', true);
        }

        return $text;
    }
}
