<?php

namespace exface\Core\CommonLogic\Model;

use exface\Core\Behaviors\CustomAttributeDefinitionBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\IHaveCategoriesInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Custom attributes are meant to be added to objects at runtime.
 * Apart from some minor convenience features, they work the same as regular attributes.
 * 
 * @see CustomAttributeDefinitionBehavior
 * @see CustomAttributeLoaderInterface
 */
class CustomAttribute extends Attribute implements IHaveCategoriesInterface
{
    private array $categories = [];
    private mixed $source = null;

    public function __construct(MetaObjectInterface $object, mixed $source = null)
    {
        $this->source = $source;
        parent::__construct($object);
    }


    /**
     * @param array $categories
     * @return void
     * @see IHaveCategoriesInterface
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
     * @see IHaveCategoriesInterface
     */
    public function setCategories(UxonObject $categories) : CustomAttribute
    {
        $this->categories = $categories->toArray();
        return $this;
    }

    /**
     * @inheritdoc
     * @see IHaveCategoriesInterface
     */
    public function getCategories() : array
    {
        return $this->categories;
    }

    /**
     * Returns the source of this custom attribute. 
     * 
     * The source represents the caller that initiated the creation of this object.
     * Bear in mind that it might be null.
     * 
     * @return mixed
     */
    public function getSource() : mixed
    {
        return $this->source;
    }
}