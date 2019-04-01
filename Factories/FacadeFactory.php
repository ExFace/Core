<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\FacadeSelector;

abstract class FacadeFactory extends AbstractSelectableComponentFactory
{

    /**
     *
     * @param FacadeSelectorInterface $name_resolver            
     * @return FacadeInterface
     */
    public static function create(FacadeSelectorInterface $selector) : FacadeInterface
    {
        return parent::createFromSelector($selector);
    }

    /**
     *
     * @param string $aliasOrPathOrClassname            
     * @param WorkbenchInterface $exface            
     * @return FacadeInterface
     */
    public static function createFromString(string $aliasOrPathOrClassname, WorkbenchInterface $exface) : FacadeInterface
    {
        $selector = new FacadeSelector($exface, $aliasOrPathOrClassname);
        return static::create($selector);
    }

    /**
     *
     * @param string|FacadeSelectorInterface|FacadeInterface $selectorOrString            
     * @param WorkbenchInterface $exface            
     * @return \exface\Core\Interfaces\Facades\FacadeInterface
     */
    public static function createFromAnything($selectorOrString, WorkbenchInterface $exface) : FacadeInterface
    {
        if ($selectorOrString instanceof FacadeInterface) {
            $facade = $selectorOrString;
        } elseif ($selectorOrString instanceof FacadeSelectorInterface) {
            $facade = static::create($selectorOrString);
        } elseif (is_string($selectorOrString)) {
            $facade = static::createFromString($selectorOrString, $exface);
        } else {
            throw new InvalidArgumentException('Cannot create facade from "' . get_class($selectorOrString) . '": expecting "FacadeSelector" or valid selector string!');
        }
        return $facade;
    }
}
?>