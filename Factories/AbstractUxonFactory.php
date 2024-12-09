<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class AbstractUxonFactory extends AbstractStaticFactory
{

    /**
     * Creates a business object from it's UXON description.
     * If the business object implements iCanBeConvertedToUxon, this method
     * will work automatically. Otherwise it needs to be overridden in the specific factory.
     *
     * @param WorkbenchInterface $exface            
     * @param UxonObject $uxon            
     * @throws UnexpectedValueException
     */
    public static function createFromUxon(WorkbenchInterface $exface, UxonObject $uxon)
    {
        $result = static::createEmpty($exface);
        if ($result instanceof iCanBeConvertedToUxon) {
            $result->importUxonObject($uxon);
        } else {
            throw new UnexpectedValueException('Cannot create "' . get_class($result) . '" from UXON automatically! It should either implement the interface iCanBeConvertedToUxon or the create_from_uxon() method must be overridden in "' . get_class(self) . '"!');
        }
        return $result;
    }
}
?>