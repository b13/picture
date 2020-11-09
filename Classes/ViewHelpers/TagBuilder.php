<?php

namespace B13\Picture\ViewHelpers;

/*
 * This file is part of TYPO3 CMS-extension picture by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

/**
 * Class is used for the new image view helper as it extends its functionality.
 */
class TagBuilder extends \TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder
{
    /**
     * TagBuilder constructor.
     *
     * @param string $tagName
     * @param string $tagContent
     */
    public function __construct($tagName = '', $tagContent = '')
    {
        parent::__construct($tagName, $tagContent);
    }

    /**
     * Function to replace an attribute's content.
     *
     * @param string $attribute
     * @param string $newValue
     */
    public function replaceAttributeValue($attribute, $newValue)
    {
        $this->attributes[$attribute] = $newValue;
    }

    /**
     * Function to add a value to an existing value of an attribute.
     *
     * @param string $attribute
     * @param string $additionalValue
     */
    public function addAttributeValue($attribute, $additionalValue)
    {
        $additionalValue = trim($additionalValue);
        $this->attributes[$attribute] .= ' ' . $additionalValue;
        $this->attributes[$attribute] = trim($this->attributes[$attribute]);
    }
}
