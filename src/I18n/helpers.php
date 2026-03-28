<?php
/**
 * I18n helper functions — autoloaded via composer.
 *
 * R4: Removed `global $builder` — lazy-key-append now uses NativeSession
 * and BUILDER_DIR constant directly, matching what Translator already does.
 */

if (!function_exists('_t')) {
    function _t(string $key, bool $escape = false): string
    {
        global $_L;

        if (empty($key)) {
            return '';
        }

        if (!isset($_L[$key])) {
            // Lazy-load: append missing key to the active language file.
            // BUILDER_DIR is defined by index.php before any autoloaded code runs.
            if (defined('BUILDER_DIR')) {
                $lang     = \CloudPad\Core\Session\NativeSession::getInstance()->get('lang', '');
                $langfile = BUILDER_DIR . "/locales/{$lang}.php";

                if ($lang && file_exists($langfile)) {
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
