<?php

declare(strict_types=1);

namespace B13\Picture\Tests\Functional\ViewHelpers;

/*
 * This file is part of TYPO3 CMS-based extension "picture" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\ViewInterface;

class ImageViewHelperUpscalingTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3conf/ext/picture'];
    protected string $fileadmin = 'EXT:picture/Tests/Functional/ViewHelpers/Fixtures/fileadmin';
    protected array $configurationToUseInTestInstance = ['GFX' => ['processor_allowUpscaling' => false]];

    public function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $GLOBALS['BE_USER'] = $this->setUpBackendUser(1);
    }

    #[Test]
    public function variantsLargerThanTheOriginalAreLabelledWithTheWidthTheyReallyHave(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/storage_with_indexed_file.csv');
        $view = $this->getView(__DIR__ . '/Fixtures/VariantsLargerThanOriginal.html');

        // the original is 1498px wide, so 1600 and 2000 both come back at 1498px
        self::assertSame(['750w', '1498w'], $this->srcsetDescriptors($view->render()));
    }

    /**
     * @return array<int, string>
     */
    protected function srcsetDescriptors(string $content): array
    {
        self::assertSame(1, preg_match('/srcset="([^"]*)"/', $content, $matches), 'no srcset rendered');

        return array_map(
            static fn (string $candidate): string => substr(strrchr(trim($candidate), ' ') ?: '', 1),
            explode(',', $matches[1])
        );
    }

    protected function getView(string $template): ViewInterface
    {
        if ((new Typo3Version())->getMajorVersion() < 13) {
            $view = GeneralUtility::makeInstance(StandaloneView::class);
            $view->setTemplatePathAndFilename($template);
            return $view;
        }
        $request = GeneralUtility::makeInstance(ServerRequest::class);
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $viewFactory = GeneralUtility::makeInstance(ViewFactoryInterface::class);
        return $viewFactory->create(new ViewFactoryData(null, null, null, $template));
    }
}
