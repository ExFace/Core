<?php

namespace exface\Core\Interfaces\Model;

use exface\Core\CommonLogic\UxonObject;

/**
 * Categories can be used to organize instances with user defined structure.
 */
interface IHaveCategoriesInterface
{
    /**
     * @param array $categories
     * @return void
     */
    function addCategories(array $categories) : void;

    /**
     * @param UxonObject $categories
     */
    function setCategories(UxonObject $categories);

    /**
     * @return array
     */
    function getCategories() : array;
}