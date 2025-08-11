<?php

/**
 * HTMLエスケープ（XSS対策）
 * @param string $s
 * @return string
 */
function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
