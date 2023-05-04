<?php

declare(strict_types=1);

namespace B13\Picture\Tests\Unit\ViewHelpers;

/*
 * This file is part of TYPO3 CMS-based extension "picture" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\Picture\ViewHelpers\ImageViewHelper;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ImageViewHelperTest extends UnitTestCase
{
    /**
     * @test
     */
    public function getProcessingInstructionsUseCropVariantFromConfigurationIfSet(): void
    {
        $imageViewHelper = $this->getMockBuilder($this->buildAccessibleProxy(ImageViewHelper::class))
            ->onlyMethods(['getCropVariantCollection', 'getImageCropVariant'])
            ->disableOriginalConstructor()
            ->getMock();
        $image = $this->getMockBuilder(File::class)->disableOriginalConstructor(true)->getMock();
        $cropVariantCollection = $this->getMockBuilder(CropVariantCollection::class)->disableOriginalConstructor(true)->getMock();
        $cropVariantCollection->expects(self::once())->method('getCropArea')->with('foo');
        $imageViewHelper->expects(self::once())->method('getCropVariantCollection')->willReturn($cropVariantCollection);
        $imageViewHelper->expects(self::never())->method('getImageCropVariant');
        $imageViewHelper->_set('image', $image);
        $configuration = [
            'width' => null,
            'height' => null,
            'minWidth' => null,
            'minHeight' => null,
            'maxWidth' => null,
            'maxHeight' => null,
            'cropVariant' => 'foo',
        ];
        $imageViewHelper->_call('getProcessingInstructions', $configuration);
    }

    /**
     * @test
     */
    public function getProcessingInstructionsCallsGetImageCropVariantIfNotConfigured(): void
    {
        $imageViewHelper = $this->getMockBuilder($this->buildAccessibleProxy(ImageViewHelper::class))
            ->onlyMethods(['getCropVariantCollection', 'getImageCropVariant'])
            ->disableOriginalConstructor()
            ->getMock();
        $image = $this->getMockBuilder(File::class)->disableOriginalConstructor(true)->getMock();
        $cropVariantCollection = $this->getMockBuilder(CropVariantCollection::class)->disableOriginalConstructor(true)->getMock();
        $cropVariantCollection->expects(self::once())->method('getCropArea')->with('bar');
        $imageViewHelper->expects(self::once())->method('getCropVariantCollection')->willReturn($cropVariantCollection);
        $imageViewHelper->expects(self::once())->method('getImageCropVariant')->willReturn('bar');
        $imageViewHelper->_set('image', $image);
        $configuration = [
            'width' => null,
            'height' => null,
            'minWidth' => null,
            'minHeight' => null,
            'maxWidth' => null,
            'maxHeight' => null,
        ];
        $imageViewHelper->_call('getProcessingInstructions', $configuration);
    }

    /**
     * @test
     */
    public function getImageCropVariantReturnsCropVariantFromArguments(): void
    {
        $imageViewHelper = $this->getMockBuilder($this->buildAccessibleProxy(ImageViewHelper::class))
            ->onlyMethods([])
            ->disableOriginalConstructor()
            ->getMock();
        $imageViewHelper->_set('arguments', ['cropVariant' => 'bar']);
        $cropVariant = $imageViewHelper->_call('getImageCropVariant');
        self::assertSame('bar', $cropVariant);
    }

    /**
     * @test
     */
    public function getImageCropVariantReturnsDefaultIfNotSet(): void
    {
        $imageViewHelper = $this->getMockBuilder($this->buildAccessibleProxy(ImageViewHelper::class))
            ->onlyMethods([])
            ->disableOriginalConstructor()
            ->getMock();
        $cropVariant = $imageViewHelper->_call('getImageCropVariant');
        self::assertSame('default', $cropVariant);
    }
}
