<?php

defined('TYPO3_MODE') or die('Access denied.');

(function () {
    if (strpos($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], 'webp') === false) {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] .= ',webp';
    }
})();
