<?php

declare(strict_types=1);

namespace B13\Picture\ViewHelpers;

/*
 * This file is part of TYPO3 CMS-extension picture by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\Picture\Domain\Model\PictureConfiguration;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class ImageViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\ImageViewHelper
{
    protected PictureConfiguration $pictureConfiguration;

    /**
     * Function to initialize the needed arguments.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument(
            'useRetina',
            'bool',
            'Specifies if image should be displayed for retina as well.'
        );

        $this->registerArgument(
            'addWebp',
            'bool',
            'Specifies if a picture element with an additional webp image should be rendered.'
        );

        $this->registerArgument(
            'lossless',
            'bool',
            'Specifies whether webp images should use lossless compression'
        );

        $this->registerArgument(
            'variants',
            'string',
            'Specifies a list of variants for a srcset on a single image tag.'
        );

        $this->registerArgument(
            'sizes',
            'string',
            'Specifies the value for the sizes attribute, used with img-tags only.'
        );

        $this->registerArgument(
            'pictureClass',
            'string',
            'Specifies the CSS class used for the picture element (if a picture tag is rendered).'
        );

        $this->registerArgument(
            'sources',
            'array',
            'Array for rendering of multiple images.'
        );
    }

    /**
     * Renders img tag or picture tag.
     *
     * @return string
     * @throws Exception
     */
    public function render(): string
    {
        /**
         * The complete output as array (single src tag or picture tag).
         * Each element is a closing or an opening tag or both.
         */
        $output = [];
        if (isset($this->arguments['src'])) {
            $this->arguments['src'] = (string)$this->arguments['src'];
        }

        $image = $this->getImage($this->arguments);
        $settings = $this->getTypoScriptSettings();
        $this->pictureConfiguration = GeneralUtility::makeInstance(PictureConfiguration::class, $this->arguments, $settings, $image);

        // build the image tag
        $tag = $this->buildSingleTag('img', $image, $this->arguments);
        $imageTag = $tag->render();

        // Add a webp source tag and activate nesting within a picture element only if no sources are set.
        if ($this->pictureConfiguration->webpShouldBeAddedBeforeSrcset()) {
            $tag = $this->addWebpImage($image, $this->arguments);
            $output[] = $tag->render();
        }

        // Build source tags by given information from sources attribute.
        if ($this->pictureConfiguration->sourcesShouldBeAdded()) {
            foreach ($this->pictureConfiguration->getSourceConfiguration() as $sourceConfiguration) {
                $sourceOutputs = [];
                $sourceImage = $this->getImage($sourceConfiguration);
                $tag = $this->buildSingleTag('source', $sourceImage, $sourceConfiguration);
                $sourceOutputs[] = $tag->render();

                // Build additional source with type webp if attribute addWebp is set and previously build tag is not type of webp already.
                $type = $tag->getAttribute('type');
                if ($type !== 'image/webp' && $this->pictureConfiguration->webpShouldBeAdded()) {
                    $tag = $this->addWebpImage($sourceImage, $sourceConfiguration);
                    array_unshift($sourceOutputs, $tag->render());
                }

                foreach ($sourceOutputs as $sourceOutput) {
                    $output[] = $sourceOutput;
                }
            }
            // add a webp fallback for the default/non-sources image if addWebp is set
            if ($this->pictureConfiguration->webpShouldBeAddedAfterSrcset()) {
                $tag = $this->addWebpImage($image, $this->arguments);
                $output[] = $tag->render();
            }
        }

        $output[] = $imageTag;

        if ($this->pictureConfiguration->pictureTagShouldBeAdded()) {
            $output = $this->wrapWithPictureElement($output);
        }

        return $this->buildOutput($output);
    }

    protected function buildVariantsIfNeeded(FileInterface $image, array $configuration): string
    {
        $srcsetValue = '';
        // generate a srcset containing a list of images if that is what we need
        if (!empty($configuration['variants'])) {
            $processingInstructions = $this->getProcessingInstructions($image, $configuration);
            $ratio = null;
            $variants = GeneralUtility::intExplode(',', $configuration['variants']);
            sort($variants);
            // determine the ratio
            if (!empty($configuration['width']) && !empty($configuration['height'])) {
                $width = (int)preg_replace('/[^0-9]/', '', $configuration['width']);
                $height = (int)preg_replace('/[^0-9]/', '', $configuration['height']);
                $ratio = $width / $height;
            }
            foreach ($variants as $variant) {
                // build processing instructions for each srcset variant
                $srcsetWidth = $variant;
                $srcsetHeight = ($ratio ? $variant * (1 / $ratio) : null);
                $srcsetProcessingInstructions = [
                    'width' => $srcsetWidth . (strpos((string)$configuration['width'], 'c') ? 'c' : ''),
                    'height' => $srcsetHeight . (strpos((string)$configuration['height'], 'c') ? 'c' : ''),
                    'minWidth' => null,
                    'minHeight' => null,
                    'maxWidth' => null,
                    'maxHeight' => null,
                    'crop' => $processingInstructions['crop'],
                ];
                if (!empty($processingInstructions['fileExtension'] ?? '')) {
                    $srcsetProcessingInstructions['fileExtension'] = $processingInstructions['fileExtension'];
                }
                $srcsetImage = $this->applyProcessingInstructions($image, $srcsetProcessingInstructions);
                $srcsetValue .= ($srcsetValue ? ', ' : '');
                $srcsetValue .= $this->imageService->getImageUri($srcsetImage, $this->arguments['absolute']) . ' ' . $srcsetWidth . 'w';
            }
        }
        return $srcsetValue;
    }

    /**
     * Function to build a single image or source tag.
     */
    protected function buildSingleTag(string $tagName, FileInterface $image, array $configuration): TagBuilder
    {
        $tag = clone $this->tag;
        $tag->setTagName($tagName);
        $tag = $this->removeForbiddenAttributes($tag);
        // generate a srcset containing a list of images if that is what we need
        $srcsetValue = $this->buildVariantsIfNeeded($image, $configuration);
        $processingInstructions = $this->getProcessingInstructions($image, $configuration);

        // generate a single image uri as the src
        // or
        // set the default processed image (we might need the width and height of this image later on) and generate a single image uri as the src fallback
        $processedImage = $this->applyProcessingInstructions($image, $processingInstructions);
        $imageUri = $this->imageService->getImageUri($processedImage, $this->arguments['absolute']);

        switch ($tagName) {
            case 'img':

                if (!$tag->hasAttribute('data-focus-area')) {
                    $cropVariantCollection = $this->getCropVariantCollection($image, $configuration);

                    $focusArea = $cropVariantCollection->getFocusArea($this->getCropVariant($configuration));
                    if (!$focusArea->isEmpty()) {
                        $tag->addAttribute('data-focus-area', (string)$focusArea->makeAbsoluteBasedOnFile($image));
                    }
                }
                if ($srcsetValue ?? false) {
                    $tag->addAttribute('srcset', $srcsetValue);
                }
                $tag->addAttribute('src', $imageUri);
                if (!empty($configuration['sizes'])) {
                    $tag->addAttribute('sizes', $configuration['sizes']);
                }
                $tag->addAttribute('width', $processedImage->getProperty('width'));
                $tag->addAttribute('height', $processedImage->getProperty('height'));

                if (!empty($configuration['class'])) {
                    $tag->addAttribute('class', $configuration['class']);
                }
                if ($this->pictureConfiguration->lazyLoadingShouldBeAdded()) {
                    $tag->addAttribute('loading', $this->pictureConfiguration->getLazyLoading());
                }

                $alt = $configuration['alt'] ?: $image->getProperty('alternative');
                $title = $configuration['title'] ?: $image->getProperty('title');

                // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
                $tag->addAttribute('alt', $alt);
                if (!empty($title)) {
                    $tag->addAttribute('title', $title);
                }
                break;

            case 'source':

                // Add content of src attribute to srcset attribute as the source element has no src attribute.
                if ($srcsetValue ?? false) {
                    $tag->addAttribute('srcset', $srcsetValue);
                } else {
                    $tag->addAttribute('srcset', $imageUri);
                }

                // Add attributes.
                if (!empty($configuration['media'])) {
                    $media = $configuration['media'];
                    // Braces should be added to media expression if they are missing.
                    if (!(stripos($media, '(') === 0)) {
                        $media = '(' . $media . ')';
                    }
                    $tag->addAttribute('media', $media);
                }
                if (!empty($configuration['sizes'])) {
                    $tag->addAttribute('sizes', $configuration['sizes']);
                }
                if (!empty($configuration['type'])) {
                    $tag->addAttribute('type', $configuration['type']);
                }
        }

        if ($this->pictureConfiguration->retinaShouldBeUsed()) {
            $this->addRetina($image, $processingInstructions, $tag);
        }
        return $tag;
    }

    /**
     * Function to remove the forbidden attributes before rendering a certain tag.
     */
    protected function removeForbiddenAttributes(TagBuilder $tag): TagBuilder
    {
        switch ($tag->getTagName()) {
            case 'img':
                $forbiddenAttributes = ['media', 'sizes', 'type'];
                foreach ($tag->getAttributes() as $attributeName => $value) {
                    if (in_array($attributeName, $forbiddenAttributes)) {
                        $tag->removeAttribute($attributeName);
                    }
                }
                break;
            case 'source':
                // for source we remove all attributes except these three ones
                $attributesToKeep = ['media', 'sizes', 'type'];
                foreach ($tag->getAttributes() as $attributeName => $value) {
                    if (!in_array($attributeName, $attributesToKeep)) {
                        $tag->removeAttribute($attributeName);
                    }
                }
                break;
        }
        return $tag;
    }

    /**
     * Function to render images for given retina resolutions and add to rendering tag.
     */
    protected function addRetina(FileInterface $image, array $processingInstructions, TagBuilder $tag): void
    {
        // 2x is default. Use multiple if retina is set in TypoScript settings.
        $retinaSettings = $this->pictureConfiguration->getRetinaSettings();

        // Process regular image.
        $processedImageRegular = $this->applyProcessingInstructions($image, $processingInstructions);
        $imageUriRegular = $this->imageService->getImageUri($processedImageRegular, $this->arguments['absolute']);

        // Process additional retina images. Tag value can be gathered for source tags from srcset value as there it
        // was to be set already because adding retina is not mandatory.
        if ($tag->hasAttribute('srcset')) {
            $tagValue = $tag->getAttribute('srcset');
            $tag->removeAttribute('srcset');
        } else {
            $tagValue = $imageUriRegular;
        }

        foreach ($retinaSettings as $retinaMultiplyer => $retinaString) {
            // Set processing instructions.
            $retinaProcessingInstructions = $processingInstructions;

            // upscale all dimensions settings
            foreach (['width', 'minWidth', 'maxWidth', 'height', 'minHeight', 'maxHeight'] as $property) {
                if (isset($retinaProcessingInstructions[$property])) {
                    $retinaProcessingInstructions[$property] = (int)$retinaProcessingInstructions[$property] * $retinaMultiplyer;
                    if ($property === 'height' || $property === 'width') {
                        $retinaProcessingInstructions[$property] .= 'c';
                    }
                }
            }

            // Process image with new settings.
            $processedImageRetina = $this->applyProcessingInstructions($image, $retinaProcessingInstructions);
            $imageUriRetina = $this->imageService->getImageUri($processedImageRetina, $this->arguments['absolute']);

            // Add string for tag.
            $tagValue .= ', ' . $imageUriRetina . ' ' . $retinaString;
        }

        $tag->addAttribute('srcset', $tagValue);
    }

    /**
     * Function to add a webp element nested by a picture element.
     */
    protected function addWebpImage(FileInterface $image, array $configuration): TagBuilder
    {
        $configuration['fileExtension'] = 'webp';
        $tag = $this->buildSingleTag('source', $image, $configuration);
        $tag->addAttribute('type', 'image/webp');
        return $tag;
    }

    /**
     * Function to wrap all built elements with the picture tag if necessary.
     */
    protected function wrapWithPictureElement(array $output): array
    {
        if ($this->arguments['pictureClass'] ?? false) {
            array_unshift($output, '<picture class="' . $this->arguments['pictureClass'] . '">');
        } else {
            array_unshift($output, '<picture>');
        }
        $output[] = '</picture>';
        return $output;
    }

    protected function getImage(array $configuration): FileInterface
    {
        $image = $this->imageService->getImage(
            $configuration['src'],
            $configuration['image'],
            (bool)$configuration['treatIdAsReference']
        );
        return $image;
    }

    protected function getProcessingInstructions(FileInterface $image, array $configuration): array
    {
        $cropVariantCollection = $this->getCropVariantCollection($image, $configuration);
        $cropVariant = $this->getCropVariant($configuration);
        $cropArea = $cropVariantCollection->getCropArea($cropVariant);
        $processingInstructions = [
            'width' => $configuration['width'],
            'height' => $configuration['height'],
            'minWidth' => $configuration['minWidth'],
            'minHeight' => $configuration['minHeight'],
            'maxWidth' => $configuration['maxWidth'],
            'maxHeight' => $configuration['maxHeight'],
            'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
        ];
        if (!empty($configuration['fileExtension'] ?? '')) {
            $processingInstructions['fileExtension'] = $configuration['fileExtension'];
        }
        return $processingInstructions;
    }

    protected function getCropVariant(array $configuration): string
    {
        return $configuration['cropVariant'] ?: 'default';
    }

    protected function getCropVariantCollection(FileInterface $image, array $configuration): CropVariantCollection
    {
        $cropString = $configuration['crop'];
        if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
            $cropString = $image->getProperty('crop');
        }
        $cropVariantCollection = CropVariantCollection::create((string)$cropString);
        return $cropVariantCollection;
    }

    /**
     * Function to build the HTML output string.
     */
    protected function buildOutput(array $output): string
    {
        $outputString = '';
        foreach ($output as $element) {
            $outputString .= $element;
        }
        return $outputString;
    }

    protected function getTypoScriptSettings(): array
    {
        $settings = [];
        $frontendController = $this->getFrontendController();
        if ($frontendController instanceof TypoScriptFrontendController) {
            $settings = $frontendController->tmpl->setup['plugin.']['tx_picture.'] ?? [];
        }
        return $settings;
    }

    protected function getFrontendController(): ?TypoScriptFrontendController
    {
        if (($GLOBALS['TSFE'] ?? null) instanceof TypoScriptFrontendController) {
            return $GLOBALS['TSFE'];
        }
        return null;
    }

    /**
     * Wrapper for creating a processed file. In case the target file extension
     * is webp, the source is not and lossless compression is enabled for webp,
     * add the corresponding encoding option to the processing instructions.
     *
     * @param File|FileInterface|FileReference $image
     * @param array $processingInstructions
     * @return ProcessedFile
     */
    protected function applyProcessingInstructions($image, array $processingInstructions): ProcessedFile
    {
        if (($processingInstructions['fileExtension'] ?? '') === 'webp'
            && $image->getExtension() !== 'webp'
        ) {
            if ($this->pictureConfiguration->losslessShouldBeUsed()) {
                $processingInstructions['additionalParameters'] = '-define webp:lossless=true';
            } else {
                $jpegQuality = MathUtility::forceIntegerInRange($GLOBALS['TYPO3_CONF_VARS']['GFX']['jpg_quality'], 10, 100, 85);
                $processingInstructions['additionalParameters'] = '-quality ' . $jpegQuality;
            }
        }

        return $this->imageService->applyProcessingInstructions($image, $processingInstructions);
    }
}
