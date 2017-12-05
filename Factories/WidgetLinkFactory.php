<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

abstract class WidgetLinkFactory extends AbstractUxonFactory
{

    /**
     * Creates an empty widget link
     *
     * @param Workbench $exface            
     * @return WidgetLinkInterface
     */
    public static function createEmpty(Workbench $exface)
    {
        return new WidgetLink($exface);
    }

    /**
     *
     * @param Workbench $exface            
     * @param string|UxonObject $string_or_object            
     * @param string $id_space            
     * @return WidgetLinkInterface
     */
    public static function createFromAnything(Workbench $exface, $string_or_object, $id_space = null)
    {
        if ($string_or_object instanceof WidgetLinkInterface) {
            return $string_or_object;
        }
        
        $ref = static::createEmpty($exface);
        if (! is_null($id_space)) {
            $ref->setWidgetIdSpace($id_space);
        }
        
        $ref->parseLink($string_or_object);
        return $ref;
    }
}
?>