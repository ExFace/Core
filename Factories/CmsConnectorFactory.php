<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\Selectors\CmsConnectorSelectorInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class CmsConnectorFactory extends AbstractSelectableComponentFactory
{

    /**
     * Creates a new CMS connector
     *
     * @param CmsConnectorSelectorInterface $name_resolver            
     * @return CmsConnectorInterface
     */
    public static function create(CmsConnectorSelectorInterface $selector) : CmsConnectorInterface
    {
        return static::createFromSelector($selector);
    }
}
?>