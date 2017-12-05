<?php
namespace exface\Core\Interfaces\Model;

/**
 * Model prototypes are PHP classes, that are meant to be filled by a model description
 * in order to obtain a meaningfull model instance: e.g. an action class is the prototype
 * for an action defined in a widget or in the model (object actions), a data type class
 * is the prototype for a modeled data type base on it, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
interface MetaModelPrototypeInterface
{
    /**
     * Returns the qualified name of the prototype class (with namespace starting from "\").
     * 
     * @return string
     */
    public static function getPrototypeClassName();
}