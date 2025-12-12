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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class ImageViewHelper extends AbstractTagBasedViewHelper
{
    protected PictureConfiguration $pictureConfiguration;
    protected ImageService $imageService;

    public function __construct()
    {
        parent::__construct();
        $this->imageService = GeneralUtility::makeInstance(ImageService::class);
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        // attributes from fluid VH
        $this->registerTagAttribute('alt', 'string', 'Specifies an alternate text for an image', false);
        $this->registerTagAttribute('ismap', 'string', 'Specifies an image as a server-side image-map. Rarely used. Look at usemap instead', false);
        $this->registerTagAttribute('longdesc', 'string', 'Specifies the URL to a document that contains a long description of an image', false);
        $this->registerTagAttribute('usemap', 'string', 'Specifies an image as a client-side image-map', false);
        $this->registerTagAttribute('loading', 'string', 'Native lazy-loading for images property. Can be "lazy", "eager" or "auto"', false);
        $this->registerTagAttribute('decoding', 'string', 'Provides an image decoding hint to the browser. Can be "sync", "async" or "auto"', false);

        $this->registerArgument('src', 'string', 'a path to a file, a combined FAL identifier or an uid (int). If $treatIdAsReference is set, the integer is considered the uid of the sys_file_reference record. If you already got a FAL object, consider using the $image parameter instead', false, '');
        $this->registerArgument('treatIdAsReference', 'bool', 'given src argument is a sys_file_reference record', false, false);
        $this->registerArgument('image', 'object', 'a FAL object (\\TYPO3\\CMS\\Core\\Resource\\File or \\TYPO3\\CMS\\Core\\Resource\\FileReference)');
        $this->registerArgument('crop', 'string|bool', 'overrule cropping of image (setting to FALSE disables the cropping set in FileReference)');
        $this->registerArgument('cropVariant', 'string', 'select a cropping variant, in case multiple croppings have been specified or stored in FileReference', false, 'default');
        $this->registerArgument('fileExtension', 'string', 'Custom file extension to use');

        $this->registerArgument('width', 'string', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('height', 'string', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('minWidth', 'int', 'minimum width of the image');
        $this->registerArgument('minHeight', 'int', 'minimum height of the image');
        $this->registerArgument('maxWidth', 'int', 'maximum width of the image');
        $this->registerArgument('maxHeight', 'int', 'maximum height of the image');
        $this->registerArgument('absolute', 'bool', 'Force absolute URL', false, false);

        // picture attributes
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
            'onlyWebp',
            'bool',
            'Specifies if image should be rendered only in webp.'
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

        // the standard image without any processing
        $image = $this->imageService->getImage(
            $this->arguments['src'],
            $this->arguments['image'],
            (bool)$this->arguments['treatIdAsReference']
        );
        $settings = $this->getTypoScriptSettings();
        $this->pictureConfiguration = GeneralUtility::makeInstance(PictureConfiguration::class, $this->arguments, $settings, $image);

        // build the image tag
        if ($this->pictureConfiguration->webpShouldBeAddedOnly()) {
            $this->arguments['fileExtension'] = 'webp';
        }
        $tag = $this->buildSingleTag('img', $this->arguments, $image);
        $imageTag = $tag->render();

        // Add a webp source tag and activate nesting within a picture element only if no sources are set.
        if ($this->pictureConfiguration->webpShouldBeAddedBeforeSrcset()) {
            $tag = $this->addWebpImage($this->arguments, $image);
            $output[] = $tag->render();
        }

        // Build source tags by given information from sources attribute.
        if ($this->pictureConfiguration->sourcesShouldBeAdded()) {
            foreach ($this->pictureConfiguration->getSourceConfiguration() as $sourceConfiguration) {
                $sourceOutputs = [];
                // use src from sourceConfiguration, if set, otherwise use the main image
                if ((string)($sourceConfiguration['src'] ?? '') !== '' || isset($sourceConfiguration['image'])) {
                    $imageSrc = $this->imageService->getImage(
                        (string)($sourceConfiguration['src'] ?? ''),
                        $sourceConfiguration['image'] ?? null,
                        (bool)($sourceConfiguration['treatIdAsReference'] ?? false)
                    );
                } else {
                    $imageSrc = $image;
                }

                // Force webp rendering if onlyWebp is set
                if ($this->pictureConfiguration->webpShouldBeAddedOnly() && $imageSrc->getExtension() !== 'svg') {
                    $sourceConfiguration['fileExtension'] = 'webp';
                }
                $tag = $this->buildSingleTag('source', $sourceConfiguration, $imageSrc);
                $sourceOutputs[] = $tag->render();

                // Build additional source with type webp if attribute addWebp is set and previously build tag is not type of webp already.
                $type = htmlspecialchars_decode($tag->getAttribute('type') ?? '');
                if ($type !== 'image/webp' && $this->pictureConfiguration->webpShouldBeAdded() && $imageSrc->getExtension() !== 'svg') {
                    $tag = $this->addWebpImage($sourceConfiguration, $imageSrc);
                    array_unshift($sourceOutputs, $tag->render());
                }

                foreach ($sourceOutputs as $sourceOutput) {
                    $output[] = $sourceOutput;
                }
            }
            // add a webp fallback for the default/non-sources image if addWebp is set
            if ($this->pictureConfiguration->webpShouldBeAddedAfterSrcset()) {
                $tag = $this->addWebpImage($this->arguments, $image);
                $output[] = $tag->render();
            }
        }

        $output[] = $imageTag;

        if ($this->pictureConfiguration->pictureTagShouldBeAdded()) {
            $output = $this->wrapWithPictureElement($output);
        }

        return $this->buildOutput($output);
    }

    protected function buildVariantsIfNeeded(array $configuration, FileInterface $image): string
    {
        $srcsetValue = '';
        // generate a srcset containing a list of images if that is what we need
        if (!empty($configuration['variants'])) {
            $processingInstructions = $this->getProcessingInstructions($configuration, $image);
            $ratio = null;
            $variants = GeneralUtility::intExplode(',', (string)$configuration['variants']);
            sort($variants);
            // determine the ratio
            if (!empty($configuration['width']) && !empty($configuration['height'])) {
                $width = (int)preg_replace('/[^0-9]/', '', (string)$configuration['width']);
                $height = (int)preg_replace('/[^0-9]/', '', (string)$configuration['height']);
                $ratio = $width / $height;
            }
            $useWidthHeight = $ratio !== null || empty($configuration['maxWidth']);
            $useMaxWidth = !empty($configuration['maxWidth']);
            foreach ($variants as $variant) {
                // build processing instructions for each srcset variant
                $srcsetWidth = $variant;
                $srcsetHeight = ($ratio ? $variant * (1 / $ratio) : null);
                $srcsetProcessingInstructions = [
                    'width' => $useWidthHeight ? ($srcsetWidth . (strpos((string)($configuration['width'] ?? ''), 'c') ? 'c' : '')) : null,
                    'height' => $useWidthHeight && $srcsetHeight ? ($srcsetHeight . (strpos((string)($configuration['height'] ?? ''), 'c') ? 'c' : '')) : null,
                    'minWidth' => null,
                    'minHeight' => null,
                    'maxWidth' => $useMaxWidth ? $srcsetWidth : null,
                    'maxHeight' => null,
                    'crop' => $processingInstructions['crop'] ?? null,
                ];
                if (!empty($configuration['fileExtension'] ?? '')) {
                    $srcsetProcessingInstructions['fileExtension'] = $configuration['fileExtension'];
                }
                $srcsetImage = $this->applyProcessingInstructions($srcsetProcessingInstructions, $image);
                $srcsetValue .= ($srcsetValue ? ', ' : '');
                $srcsetValue .= $this->imageService->getImageUri($srcsetImage, $this->arguments['absolute']) . ' ' . $srcsetWidth . 'w';
            }
        }
        return $srcsetValue;
    }

    /**
     * Function to build a single image or source tag.
     */
    protected function buildSingleTag(string $tagName, array $configuration, FileInterface $image): TagBuilder
    {
        $tag = clone $this->tag;
        $tag->setTagName($tagName);
        $tag = $this->removeForbiddenAttributes($tag);
        // generate a srcset containing a list of images if that is what we need
        $srcsetValue = $this->buildVariantsIfNeeded($configuration, $image);
        $processingInstructions = $this->getProcessingInstructions($configuration, $image);

        // generate a single image uri as the src
        // or
        // set the default processed image (we might need the width and height of this image later on) and generate a single image uri as the src fallback
        $processedImage = $this->applyProcessingInstructions($processingInstructions, $image);
        $imageUri = $this->imageService->getImageUri($processedImage, $this->arguments['absolute']);

        switch ($tagName) {
            case 'img':

                if (!$tag->hasAttribute('data-focus-area')) {
                    $cropVariantCollection = $this->getCropVariantCollection($image);
                    $focusArea = $cropVariantCollection->getFocusArea($this->getImageCropVariant());
                    if (!$focusArea->isEmpty()) {
                        $tag->addAttribute('data-focus-area', (string)$focusArea->makeAbsoluteBasedOnFile($image));
                    }
                }
                if ($srcsetValue !== '') {
                    $tag->addAttribute('srcset', $srcsetValue);
                }
                $tag->addAttribute('src', $imageUri);
                if (!empty($configuration['sizes'])) {
                    $tag->addAttribute('sizes', $configuration['sizes']);
                }
                $tag->addAttribute('width', $processedImage->getProperty('width'));
                $tag->addAttribute('height', $processedImage->getProperty('height'));

                if (!empty($this->arguments['class'] ?? null)) {
                    $tag->addAttribute('class', $this->arguments['class']);
                }
                if ($this->pictureConfiguration->lazyLoadingShouldBeAdded()) {
                    $tag->addAttribute('loading', $this->pictureConfiguration->getLazyLoading());
                }

                $alt = $this->arguments['alt'] ?? $image->getProperty('alternative');
                $title = $this->arguments['title'] ?? $image->getProperty('title');

                // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
                $tag->addAttribute('alt', $alt);
                if (!empty($title)) {
                    $tag->addAttribute('title', $title);
                }
                break;

            case 'source':

                // Add content of src attribute to srcset attribute as the source element has no src attribute.
                if ($srcsetValue !== '') {
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
                // add a type value if there potentially is more than one source with the same media/sizes value.
                if (empty($configuration['media']) && empty($configuration['type'])) {
                    $tag->addAttribute('type', $processedImage->getOriginalFile()->getMimeType());
                }
        }

        if ($this->pictureConfiguration->retinaShouldBeUsed()) {
            $this->addRetina($processingInstructions, $tag, $image);
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
    protected function addRetina(array $processingInstructions, TagBuilder $tag, FileInterface $image): void
    {
        // 2x is default. Use multiple if retina is set in TypoScript settings.
        $retinaSettings = $this->pictureConfiguration->getRetinaSettings();

        // Process regular image.
        $processedImageRegular = $this->applyProcessingInstructions($processingInstructions, $image);
        $imageUriRegular = $this->imageService->getImageUri($processedImageRegular, $this->arguments['absolute']);

        // Process additional retina images. Tag value can be gathered for source tags from srcset value as there it
        // was to be set already because adding retina is not mandatory.
        if ($tag->hasAttribute('srcset')) {
            $tagValue = htmlspecialchars_decode($tag->getAttribute('srcset') ?? '');
            $tag->removeAttribute('srcset');
        } else {
            $tagValue = $imageUriRegular;
        }

        // add "1x" for the default size
        $tagValue .= ' 1x';

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
            $processedImageRetina = $this->applyProcessingInstructions($retinaProcessingInstructions, $image);
            $imageUriRetina = $this->imageService->getImageUri($processedImageRetina, $this->arguments['absolute']);

            // Add string for tag.
            $tagValue .= ', ' . $imageUriRetina . ' ' . $retinaString;
        }

        $tag->addAttribute('srcset', $tagValue);
    }

    /**
     * Function to add a webp element nested by a picture element.
     */
    protected function addWebpImage(array $configuration, FileInterface $image): TagBuilder
    {
        $configuration['fileExtension'] = 'webp';
        $tag = $this->buildSingleTag('source', $configuration, $image);
        $tag->addAttribute('type', 'image/webp');
        return $tag;
    }

    /**
     * Function to wrap all built elements with the picture tag if necessary.
     */
    protected function wrapWithPictureElement(array $output): array
    {
        $attributes = '';
        if ($this->pictureConfiguration->hasPictureClass()) {
            $attributes = ' ' . GeneralUtility::implodeAttributes([
                'class' => $this->pictureConfiguration->getPictureClass(),
            ]);
        }
        array_unshift($output, '<picture' . $attributes . '>');
        $output[] = '</picture>';
        return $output;
    }

    protected function getProcessingInstructions(array $configuration, FileInterface $image): array
    {
        $cropVariantCollection = $this->getCropVariantCollection($image);
        $cropVariant = $configuration['cropVariant'] ?? $this->getImageCropVariant();
        $cropArea = $cropVariantCollection->getCropArea($cropVariant);
        $processingInstructions = [
            'width' => $configuration['width'] ?? null,
            'height' => $configuration['height'] ?? null,
            'minWidth' => $configuration['minWidth'] ?? null,
            'minHeight' => $configuration['minHeight'] ?? null,
            'maxWidth' => $configuration['maxWidth'] ?? null,
            'maxHeight' => $configuration['maxHeight'] ?? null,
            'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
        ];
        if (!empty($configuration['fileExtension'] ?? '')) {
            $processingInstructions['fileExtension'] = $configuration['fileExtension'];
        }
        return $processingInstructions;
    }

    protected function getImageCropVariant(): string
    {
        return $this->arguments['cropVariant'] ?? 'default';
    }

    protected function getCropVariantCollection(FileInterface $image): CropVariantCollection
    {
        $cropString = $this->arguments['crop'];
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
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $frontendController = $this->getFrontendController();
            if ($frontendController instanceof TypoScriptFrontendController) {
                $settings = $frontendController->tmpl->setup['plugin.']['tx_picture.'] ?? [];
            }
            return $settings;
        }
        $request = $this->getServerRequest();
        if ($request === null) {
            return $settings;
        }
        /** @var FrontendTypoScript $typoScript */
        $typoScript = $request->getAttribute('frontend.typoscript');
        if ($typoScript !== null) {
            $setup = $typoScript->getSetupArray();
        }
        $settings = $setup['plugin.']['tx_picture.'] ?? [];
        return $settings;
    }

    protected function getServerRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
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
     */
    protected function applyProcessingInstructions(array $processingInstructions, FileInterface $image): ProcessedFile
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
