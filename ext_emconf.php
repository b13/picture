<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Picture: Extended Image ViewHelper',
    'description' => 'Improved TYPO3 image ViewHelper creating picture elements with support for sizes, sources, additional image formats, etc.',
    'category' => 'fe',
    'author' => 'David Steeb',
    'author_email' => 'typo3@b13.com',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author_company' => 'b13 GmbH, Stuttgart',
    'version' => '1.3.2',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-11.5.99',
        ],
    ],
];
