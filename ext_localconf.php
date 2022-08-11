<?php

defined('TYPO3_MODE') or die('Access denied.');

(function () {
    if (!str_contains($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] ?? '', 'webp')) {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] .= ',webp';
    }
})();
