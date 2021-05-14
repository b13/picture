<?php

namespace B13\Picture\ViewHelpers;

/*
 * This file is part of TYPO3 CMS-extension picture by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

class ImageViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\ImageViewHelper
{
    /**
     * Specifies if a nested picture element was already built.
     *
     * @var bool
     */
    protected $renderPictureElement = false;

    /**
     * The complete output as array (single src tag or picture tag).
     * Each element is a closing or an opening tag or both.
     *
     * @var array
     */
    protected $output = [];

    /**
     * The crop variant collection.
     *
     * @var CropVariantCollection
     */
    protected $cropVariantCollection = null;

    /**
     * The crop variant.
     *
     * @var string
     */
    protected $cropVariant = '';

    /**
     * The image.
     *
     * @var FileReference
     */
    protected $image = null;

    /**
     * The processing instructions.
     *
     * @var array
     */
    protected $processingInstructions = [];

    /**
     * Settings from TypoScript.
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Storage of all checks needed.
     *
     * @var array
     */
    protected $checks = [];

    /**
     * ImageViewHelper constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->tag = GeneralUtility::makeInstance(TagBuilder::class, 'img');
    }

    /**
     * Function to initialize the needed arguments.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        if (version_compare(TYPO3_branch, '10.3', '<')) {
            $this->registerArgument(
                'fileExtension',
                'string',
                'File extension to use.'
            );
        }

        $this->registerArgument(
            'useRetina',
            'string',
            'Specifies if image should be displayed for retina as well.'
        );

        $this->registerArgument(
            'addWebp',
            'string',
            'Specifies if a picture element with an additional webp image should be rendered.'
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
        $this->output = [];
        $this->setImageAndProcessingInstructions();
        $this->evaluateTypoScriptSetup();

        // build the image tag
        $this->buildSingleTag('img');
        $imageTag = $this->tag->render();

        // Add a webp source tag and activate nesting within a picture element only if no sources are set.
        if (!$this->checks['sources'] && $this->checks['addWebp']) {
            $this->addWebpImage();
            $this->output[] = $this->tag->render();
        }
        $this->tag->reset();

        // Build source tags by given information from sources attribute.
        $defaultArguments = $this->arguments;
        $defaultProcessingInstructions = $this->processingInstructions;
        if ($this->checks['sources']) {
            $this->renderPictureElement = true;
            foreach ($this->arguments['sources'] as $sourceType => $sourceAttributes) {
                // At first check if given type exists in TypoScript settings and use the given media query.
                if ($this->checks['breakpoints']) {
                    foreach ($this->settings['breakpoints.'] as $breakpointName => $breakpointValue) {
                        if ($breakpointName === $sourceType) {
                            $sourceAttributes['media'] = '(min-width: ' . $breakpointValue . 'px)';
                            break;
                        }
                    }
                }

                $singleOutput = [];
                $this->setImageAndProcessingInstructions($sourceAttributes);
                $this->buildSingleTag('source');
                $singleOutput[] = $this->tag->render();

                // Build additional source with type webp if attribute addWebp is set and previously build tag is not type of webp already.
                $type = $this->tag->getAttribute('type');
                if ($type !== 'image/webp' && $this->checks['addWebp']) {
                    $this->addWebpImage();
                    array_unshift($singleOutput, $this->tag->render());
                }
                $this->tag->reset();

                // Restore default arguments and processing instructions as only changes from sources attribute should be applied.
                $this->arguments = $defaultArguments;
                $this->processingInstructions = $defaultProcessingInstructions;

                foreach ($singleOutput as $output) {
                    $this->output[] = $output;
                }
            }
            // add a webp fallback for the default/non-sources image if addWebp is set
            if ($this->checks['addWebp']) {
                $this->addWebpImage();
                $this->output[] = $this->tag->render();
            }
        }

        $this->output[] = $imageTag;

        if ($this->renderPictureElement) {
            $this->wrapWithPictureElement();
        }

        return $this->buildOutput();
    }

    /**
     * Function to build a single image or source tag.
     *
     * @param string $tag
     */
    protected function buildSingleTag(string $tag = 'img'): void
    {
        $this->tag->setTagName($tag);
        $this->removeForbiddenAttributes($tag);

        // generate a srcset containing a list of images if that is what we need
        if (!empty($this->arguments['variants'])) {
            $variants = explode(",", $this->arguments['variants']);
            sort($variants);
            // determine the ratio
            if ($this->arguments['width'] && $this->arguments['height']) {
                $width = preg_replace("/[^0-9]/", "", $this->arguments['width']);
                $height = preg_replace("/[^0-9]/", "", $this->arguments['height']);
                $ratio = $width/$height;
            }
            foreach ($variants as $variant) {
                // build processing instructions for each srcset variant
                $srcsetWidth = $variant;
                $srcsetHeight = ($ratio ? $variant * (1/$ratio) : NULL);
                $srcsetProcessingInstructions = [
                    'width' => $srcsetWidth,
                    'height' => $srcsetHeight,
                    'minWidth' => NULL,
                    'minHeight' => NULL,
                    'maxWidth' => NULL,
                    'maxHeight' => NULL,
                    'fileExtension' => $this->processingInstructions['fileExtension'],
                    'crop' => $this->processingInstructions['crop']
                ];
                $srcsetImage = $this->imageService->applyProcessingInstructions($this->image, $srcsetProcessingInstructions);
                $srcsetValue .= ($srcsetValue ? ', ' : '');
                $srcsetValue .= $this->imageService->getImageUri($srcsetImage, $this->arguments['absolute']) . ' ' . $srcsetWidth . 'w';
            }
            // set the default processed image (we might need the width and height of this image later on)
            $processedImage = $this->imageService->applyProcessingInstructions($this->image, $this->processingInstructions);
        } else {
            // generate a single image uri as the src
            $processedImage = $this->imageService->applyProcessingInstructions($this->image, $this->processingInstructions);
            $imageUri = $this->imageService->getImageUri($processedImage, $this->arguments['absolute']);
        }


        $sourceTagRendered = false;
        switch ($tag) {
            case 'img':

                if (!$this->tag->hasAttribute('data-focus-area')) {
                    $focusArea = $this->cropVariantCollection->getFocusArea($this->cropVariant);
                    if (!$focusArea->isEmpty()) {
                        $this->tag->addAttribute('data-focus-area', $focusArea->makeAbsoluteBasedOnFile($this->image));
                    }
                }
                if ($srcsetValue) {
                    $this->tag->addAttribute('srcset', $srcsetValue);
                } else {
                    $this->tag->addAttribute('src', $imageUri);
                }
                if (!empty($this->arguments['sizes'])) {
                    $this->tag->addAttribute('sizes', $this->arguments['sizes']);
                }
                $this->tag->addAttribute('width', $processedImage->getProperty('width'));
                $this->tag->addAttribute('height', $processedImage->getProperty('height'));

                if (!empty($this->arguments['class'])) {
                    $this->tag->addAttribute('class', $this->arguments['class']);
                }
                if (!empty($this->settings['lazyLoading']) && !$this->hasArgument('loading')) {
                    $this->tag->addAttribute('loading', $this->settings['lazyLoading']);
                }

                $alt = $this->arguments['alt'] ?: $this->image->getProperty('alternative');
                $title = $this->arguments['title'] ?: $this->image->getProperty('title');

                // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
                $this->tag->addAttribute('alt', $alt);
                if (!empty($title)) {
                    $this->tag->addAttribute('title', $title);
                }
                break;

            case 'source':

                // Add content of src attribute to srcset attribute as the source element has no src attribute.
                if ($srcsetValue) {
                    $this->tag->addAttribute('srcset', $srcsetValue);
                } else {
                    $this->tag->addAttribute('srcset', $imageUri);
                }

                // Add attributes.
                if (!empty($this->arguments['media'])) {
                    $media = $this->arguments['media'];
                    // Braces should be added to media expression if they are missing.
                    if (!(stripos($media, '(') === 0)) {
                        $media = '(' . $media . ')';
                    }
                    $this->tag->addAttribute('media', $media);
                }
                if (!empty($this->arguments['sizes'])) {
                    $this->tag->addAttribute('sizes', $this->arguments['sizes']);
                }
                if (!empty($this->arguments['type'])) {
                    $this->tag->addAttribute('type', $this->arguments['type']);
                }

                $sourceTagRendered = true;
        }

        if ($this->checks['useRetina']) {
            $this->addRetina();
        }

    }

    /**
     * Function to remove the forbidden attributes before rendering a certain tag.
     *
     * @param string $tag
     */
    protected function removeForbiddenAttributes(string $tag = 'img'): void
    {
        switch ($tag) {
            case 'img':
                $forbiddenAttributes = ['media', 'sizes', 'type'];
                foreach ($this->tag->getAttributes() as $attributeName => $value) {
                    if (in_array($attributeName, $forbiddenAttributes)) {
                        $this->tag->removeAttribute($attributeName);
                    }
                }
                break;
            case 'source':
                // for source we remove all attributes except these three ones
                $attributesToKeep = ['media', 'sizes', 'type'];
                foreach ($this->tag->getAttributes() as $attributeName => $value) {
                    if (!in_array($attributeName, $attributesToKeep)) {
                        $this->tag->removeAttribute($attributeName);
                    }
                }
                break;
        }
    }

    /**
     * Function to render images for given retina resolutions and add to rendering tag.
     */
    protected function addRetina(): void
    {
        // 2x is default. Use multiple if retina is set in TypoScript settings.
        $retinaSettings = $this->checks['retinaSettings'] ? $this->settings['retina.'] : [2 => '2x'];

        // Process regular image.
        $processedImageRegular = $this->imageService->applyProcessingInstructions($this->image, $this->processingInstructions);
        $imageUriRegular = $this->imageService->getImageUri($processedImageRegular, $this->arguments['absolute']);

        // Process additional retina images. Tag value can be gathered for source tags from srcset value as there it
        // was to be set already because adding retina is not mandatory.
        if ($this->tag->hasAttribute('srcset')) {
            $tagValue = $this->tag->getAttribute('srcset');
            $this->tag->removeAttribute('srcset');
        } else {
            $tagValue = $imageUriRegular;
        }

        foreach ($retinaSettings as $retinaMultiplyer => $retinaString) {
            // Set processing instructions.
            $retinaProcessingInstructions = $this->processingInstructions;

            if (strpos($retinaProcessingInstructions['width'], 'c') === false) {
                $retinaProcessingInstructions['width'] = $retinaProcessingInstructions['width'] * $retinaMultiplyer;
            } else {
                $retinaProcessingInstructions['width'] = $retinaProcessingInstructions['width'] * $retinaMultiplyer;
                $retinaProcessingInstructions['width'] .= 'c';
            }
            if (strpos($retinaProcessingInstructions['height'], 'c') === false) {
                $retinaProcessingInstructions['height'] = $retinaProcessingInstructions['height'] * $retinaMultiplyer;
            } else {
                $retinaProcessingInstructions['height'] = $retinaProcessingInstructions['height'] * $retinaMultiplyer;
                $retinaProcessingInstructions['height'] .= 'c';
            }

            // Process image with new settings.
            $processedImageRetina = $this->imageService->applyProcessingInstructions($this->image, $retinaProcessingInstructions);
            $imageUriRetina = $this->imageService->getImageUri($processedImageRetina, $this->arguments['absolute']);

            // Add string for tag.
            $tagValue .= ', ' . $imageUriRetina . ' ' . $retinaString;
        }

        $this->tag->addAttribute('srcset', $tagValue);
    }

    /**
     * Function to add a webp element nested by a picture element.
     */
    protected function addWebpImage(): void
    {
        $this->processingInstructions['fileExtension'] = 'webp';
        $this->renderPictureElement = true;
        $this->buildSingleTag('source');
        $this->tag->addAttribute('type', 'image/webp');
        $this->processingInstructions['fileExtension'] = $this->arguments['fileExtension'];
    }

    /**
     * Function to wrap all built elements with the picture tag if necessary.
     */
    protected function wrapWithPictureElement(): void
    {
        if ($this->arguments['pictureClass']) {
            array_unshift($this->output, '<picture class="' . $this->arguments['pictureClass'] . '">');
        } else {
            array_unshift($this->output, '<picture>');
        }
        $this->output[] = '</picture>';
    }

    /**
     * Function to create the image and change arguments if new ones given. Those are applied to generate new
     * processing instructions.
     *
     * @param array $arguments
     */
    protected function setImageAndProcessingInstructions(array $arguments = []): void
    {
        if (!empty($this->arguments['fileExtension'])) {
            $this->processingInstructions['fileExtension'] = $this->arguments['fileExtension'];
        }

        // Replace current arguments with given from sources argument if passed.
        if (!empty($arguments)) {
            foreach ($arguments as $argumentName => $argumentValue) {
                $this->arguments[$argumentName] = $argumentValue;
            }
        }

        $this->image = $this->imageService->getImage($this->arguments['src'], $this->arguments['image'],
            $this->arguments['treatIdAsReference']);
        $cropString = $this->arguments['crop'];
        if ($cropString === null && $this->image->hasProperty('crop') && $this->image->getProperty('crop')) {
            $cropString = $this->image->getProperty('crop');
        }
        $this->cropVariantCollection = CropVariantCollection::create((string)$cropString);
        $this->cropVariant = $this->arguments['cropVariant'] ?: 'default';
        $cropArea = $this->cropVariantCollection->getCropArea($this->cropVariant);

        $this->processingInstructions = [
            'width' => $this->arguments['width'],
            'height' => $this->arguments['height'],
            'minWidth' => $this->arguments['minWidth'],
            'minHeight' => $this->arguments['minHeight'],
            'maxWidth' => $this->arguments['maxWidth'],
            'maxHeight' => $this->arguments['maxHeight'],
            'fileExtension' => $this->arguments['fileExtension'] ? $this->arguments['fileExtension'] : null,
            'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($this->image),
        ];
    }

    /**
     * Function to build the HTML output string.
     *
     * @return string
     */
    protected function buildOutput(): string
    {
        $outputString = '';
        foreach ($this->output as $element) {
            $outputString .= $element;
        }
        return $outputString;
    }

    /**
     * Function to get the TypoScript setup configuration and evaluate.
     */
    public function evaluateTypoScriptSetup(): void
    {
        // Get TypoScript configuration.
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configurationManager = $objectManager->get(ConfigurationManager::class);
        $extbaseFrameworkConfiguration = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $this->settings = $extbaseFrameworkConfiguration['plugin.']['tx_picture.'];

        if ($this->image->getExtension() !== 'svg') {
            // Set checks needed later on for additional options, not needed if we're dealing with an SVG file
            $this->checks['addWebp'] = (!empty($this->arguments['fileExtension']) && $this->arguments['fileExtension'] === 'webp') ? 0 : ($this->arguments['addWebp'] ?? $this->settings['addWebp'] ?? 0);
            $this->checks['useRetina'] = $this->arguments['useRetina'] ?? $this->settings['useRetina'] ?? 0;
            $this->checks['breakpoints'] = isset($this->settings['breakpoints.']) ? 1 : 0;
            $this->checks['sources'] = isset($this->arguments['sources']) ? 1 : 0;
            $this->checks['retinaSettings'] = isset($this->settings['retina.']) ? 1 : 0;
        }
    }
}
