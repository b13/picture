<?php

declare(strict_types=1);

namespace B13\Picture\Tests\Functional\Frontend;

/*
 * This file is part of TYPO3 CMS-based extension "picture" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ImageViewHelperTest extends FunctionalTestCase
{
    protected $pathsToLinkInTestInstance = [
        'typo3conf/ext/picture/Build/sites' => 'typo3conf/sites',
    ];

    protected $testExtensionsToLoad = [
        'typo3conf/ext/picture',
    ];

    /**
     * @test
     */
    public function callingPageReturns200ResponseCode(): void
    {
        $this->importDataSet(ORIGINAL_ROOT . 'typo3conf/ext/picture/Tests/Functional/Frontend/Fixtures/demonstration_page.xml');
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => [],
                'setup' => ['EXT:picture/Configuration/TypoScript/test.typoscript'],
            ]
        );
        $response = $this->executeFrontendRequest(new InternalRequest('/?type=1573387706874'));
        $status = $response->getStatusCode();
        self::assertSame(200, $status);
    }
}
