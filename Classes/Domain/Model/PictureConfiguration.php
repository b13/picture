<?php

declare(strict_types=1);

namespace B13\Picture\Domain\Model;

/*
 * This file is part of TYPO3 CMS-extension picture by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Resource\FileInterface;

class PictureConfiguration
{
    protected bool $useRetina = false;
    // 2x is default. Use multiple if retina is set in TypoScript settings.
    protected array $retinaSettings = [2 => '2x'];
    protected bool $addWebp = false;
    protected bool $lossless = false;
    protected bool $addBreakpoints = false;
    protected array $breakpoints = [];
    protected array $sources = [];
    protected bool $addSources = false;
    protected bool $addLazyLoading = false;
    protected string $lazyLoading = '';
    protected array $arguments;

    public function __construct(array $arguments, array $typoScriptSettings, FileInterface $image)
    {
        $this->arguments = $arguments;
        $fileExtension = $arguments['fileExtension'] ?? $image->getExtension();
        if ($image->getExtension() !== 'svg') {
            $this->addWebp = (bool)($fileExtension === 'webp' ? false : ($arguments['addWebp'] ?? $typoScriptSettings['addWebp'] ?? false));
            $this->useRetina = (bool)($arguments['useRetina'] ?? $typoScriptSettings['useRetina'] ?? false);
            if (isset($typoScriptSettings['retina.'])) {
                $this->retinaSettings = $typoScriptSettings['retina.'];
            }
            $this->lossless = (bool)($arguments['lossless'] ?? $typoScriptSettings['lossless'] ?? false);
            if (isset($typoScriptSettings['breakpoints.'])) {
                $this->addBreakpoints = true;
                $this->breakpoints = $typoScriptSettings['breakpoints.'];
            }
            if (isset($arguments['sources'])) {
                $this->sources = $arguments['sources'];
                $this->addSources = true;
            }
            if (!empty($typoScriptSettings['lazyLoading']) && !isset($arguments['loading'])) {
                $this->addLazyLoading = true;
                $this->lazyLoading = (string)$typoScriptSettings['lazyLoading'];
            }
        }
    }

    public function getSourceConfiguration(): array
    {
        $configuration = [];
        foreach ($this->sources as $sourceType => $sourceAttributes) {
            $configuration[$sourceType] = $this->arguments;
            // At first check if given type exists in TypoScript settings and use the given media query.
            if ($this->breakPointsShouldBeAdded()) {
                foreach ($this->getBreakPoints() as $breakpointName => $breakpointValue) {
                    if ($breakpointName === $sourceType) {
                        $sourceAttributes['media'] = '(min-width: ' . $breakpointValue . 'px)';
                        break;
                    }
                }
            }
            if (!empty($sourceAttributes)) {
                foreach ($sourceAttributes as $argumentName => $argumentValue) {
                    $configuration[$sourceType][$argumentName] = $argumentValue;
                }
            }
        }
        return $configuration;
    }

    public function lazyLoadingShouldBeAdded(): bool
    {
        return $this->addLazyLoading;
    }

    public function getLazyLoading(): string
    {
        return $this->lazyLoading;
    }

    public function losslessShouldBeUsed(): bool
    {
        return $this->lossless;
    }

    public function retinaShouldBeUsed(): bool
    {
        return $this->useRetina;
    }

    public function getRetinaSettings(): array
    {
        return $this->retinaSettings;
    }

    public function pictureTagShouldBeAdded(): bool
    {
        return $this->addWebp || $this->addSources || !empty($this->arguments['pictureClass']);
    }

    protected function breakPointsShouldBeAdded(): bool
    {
        return $this->addBreakpoints;
    }

    protected function getBreakPoints(): array
    {
        return $this->breakpoints;
    }

    protected function getSources(): array
    {
        return $this->sources;
    }

    public function sourcesShouldBeAdded(): bool
    {
        return $this->addSources;
    }

    public function webpShouldBeAddedBeforeSrcset(): bool
    {
        return $this->addWebp && !$this->addSources;
    }

    public function webpShouldBeAddedAfterSrcset(): bool
    {
        return $this->addWebp || $this->addSources;
    }

    public function webpShouldBeAdded(): bool
    {
        return $this->addWebp;
    }
}
