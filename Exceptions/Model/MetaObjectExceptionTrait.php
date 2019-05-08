<?php
namespace exface\Core\Exceptions\Model;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Widgets\Filter;

/**
 * This trait enables an exception to output meta object specific debug information: properties, attributes, behaviors, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
trait MetaObjectExceptionTrait {
    
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $meta_object = null;

    public function __construct(MetaObjectInterface $meta_object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setMetaObject($meta_object);
    }

    /**
     *
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function getMetaObject()
    {
        return $this->meta_object;
    }

    /**
     *
     * @param MetaObjectInterface $object            
     * @return \exface\Core\Exceptions\Model\MetaObjectExceptionTrait
     */
    public function setMetaObject(MetaObjectInterface $object)
    {
        $this->meta_object = $object;
        return $this;
    }
    
    /**
     * 
     * @param array $uxon
     * @param string $keyName
     * @return array
     */
    private function removeKeysFromUxon(array $uxon, string $keyName) : array
    {
        if (empty($uxon)) {
            return $uxon;
        }
        
        $result = [];
        $keyName = strtolower($keyName);
        foreach ($uxon as $key => $value) {
            if (strtolower($key) !== $keyName) {
                if (is_array($value)) {
                    $result[$key] = $this->removeKeysFromUxon($value, $keyName);
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}