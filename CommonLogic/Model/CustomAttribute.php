<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\UxonObject;

class CustomAttribute extends Attribute
{
    private array $categories = [];

    /**
     * @param array $categories
     * @return void
     */
    public function addCategories(array $categories) : void
    {
        if(empty($this->categories)) {
            $this->categories = $categories;
        } else {
            $this->categories = array_merge($this->categories, $categories);
        }
    }
    
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