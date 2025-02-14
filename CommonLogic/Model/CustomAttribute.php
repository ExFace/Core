<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;

class CustomAttribute extends Attribute
{
    private array $categories = [];

    /**
     * 
     * @uxon-property categories
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $categories
     * @return $this
     */
    public function setCategories(UxonObject $categories) : CustomAttribute
    {
        $this->categories = $categories->toArray();
        return $this;
    }

    /**
     * @return array
     */
    public function getCategories() : array
    {
        return $this->categories;
    }
}