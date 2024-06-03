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

class DemonstrationPageTest extends FunctionalTestCase
{
    protected array $pathsToLinkInTestInstance = ['typo3conf/ext/picture/Build/sites' => 'typo3conf/sites'];
    protected array $testExtensionsToLoad = ['typo3conf/ext/picture'];

    /**
     * @test
     */
    public function callingPageReturns200ResponseCode(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/demonstration_page.csv');
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => [],
                'setup' => ['EXT:picture/Configuration/TypoScript/test.typoscript'],
            ]
        );
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/?type=1573387706874'));
        $status = $response->getStatusCode();
        self::assertSame(200, $status);
    }
}
