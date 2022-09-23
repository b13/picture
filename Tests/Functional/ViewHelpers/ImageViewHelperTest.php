<?php

declare(strict_types=1);

namespace B13\Picture\Tests\Functional\ViewHelpers;

/*
 * This file is part of TYPO3 CMS-based extension "doktypemapper" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class ImageViewHelperTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/picture',
    ];

    protected $fileadmin = 'EXT:picture/Tests/Functional/ViewHelpers/Fixtures/fileadmin';

    public function setUp(): void
    {
        parent::setUp();
        $backendUser = $this->setUpBackendUserFromFixture(1);
        $GLOBALS['BE_USER'] = $backendUser;
    }

    /**
     * @test
     */
    public function imageWithSources(): void
    {
        $this->importDataSet(ORIGINAL_ROOT . 'typo3conf/ext/picture/Tests/Functional/ViewHelpers/Fixtures/storage_with_file.xml');
        $template = ORIGINAL_ROOT . 'typo3conf/ext/picture/Tests/Functional/ViewHelpers/Fixtures/ImageWithSources.html';
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename($template);
        $content = $view->render();
        $this->assertProcessedFileExists(150, 150);
        $this->assertProcessedFileExists(1680, 1000);
        self::assertTrue(str_starts_with(trim($content), '<picture><source srcset='));
        self::assertTrue(str_contains(trim($content), '<img src='));
    }

    /**
     * @test
     */
    public function simpleImage(): void
    {
        $this->importDataSet(ORIGINAL_ROOT . 'typo3conf/ext/picture/Tests/Functional/ViewHelpers/Fixtures/storage_with_file.xml');
        $template = ORIGINAL_ROOT . 'typo3conf/ext/picture/Tests/Functional/ViewHelpers/Fixtures/SimpleImage.html';
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename($template);
        $content = $view->render();
        $this->assertProcessedFileExists(200, 100);
        self::assertTrue(!str_contains(trim($content), '<picture>'));
        self::assertTrue(str_starts_with(trim($content), '<img src='));
    }

    protected function assertProcessedFileExists(int $width, int $height): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_processedfile');
        $row = $queryBuilder->select('*')
            ->from('sys_file_processedfile')
            ->where(
                $queryBuilder->expr()->eq(
                    'width',
                    $queryBuilder->createNamedParameter($width, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'height',
                    $queryBuilder->createNamedParameter($height, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAssociative();
        self::assertTrue($row !== false, 'row with width: ' . $width . ' and height: ' . $height . ' not found');
        $filePath = GeneralUtility::getFileAbsFileName($this->fileadmin . $row['identifier']);
        self::assertTrue(is_file($filePath), $filePath . 'not extist');
        $info = GeneralUtility::makeInstance(ImageInfo::class, $filePath);
        self::assertSame($width, $info->getWidth(), 'width ' . $width . ' do not match');
        self::assertSame($height, $info->getHeight(), 'height ' . $height . ' do not match');
    }
}
