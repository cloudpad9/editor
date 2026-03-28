<?php
/**
 * Action: diff
 * R2.4: Inlined Builder::diff() logic here — Builder no longer owns this business logic.
 * FIX: mb_convert_encoding (deprecated PHP 8.2+) → htmlspecialchars.
 */
function diff($builder) {
    $from_text = \CloudPad\Core\Request::getString('DIFF_FROM');
    $to_text   = \CloudPad\Core\Request::getString('DIFF_TO');

    if (empty($from_text) || empty($to_text)) {
        $builder->getOutput()->flushLine("[ERROR] Please specify both texts to compare\n", true);
        return;
    }

    \CloudPad\Core\Session\NativeSession::getInstance()->set('DIFF_FROM', $from_text);
    \CloudPad\Core\Session\NativeSession::getInstance()->set('DIFF_TO', $to_text);

    // FIX: mb_convert_encoding deprecated PHP 8.2+ → htmlspecialchars
    $from_text = htmlspecialchars($from_text, ENT_QUOTES, 'UTF-8');
    $to_text   = htmlspecialchars($to_text,   ENT_QUOTES, 'UTF-8');

    $appDir = defined('BUILDER_DIR') ? BUILDER_DIR : dirname(dirname(dirname(__DIR__)));
    include $appDir . '/finediff.php';

    $opcodes       = FineDiff::getDiffOpcodes($from_text, $to_text);
    $rendered_diff = FineDiff::renderDiffToHTMLFromOpcodes($from_text, $opcodes);

    echo '<div class="diff-response">' . $rendered_diff . '</div>';
}
