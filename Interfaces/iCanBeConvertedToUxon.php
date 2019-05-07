<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\UxonObject;

/**
 * Interface for classes, that can be represented as UXON objects.
 * 
 * Every entity, that should be configurable via UXON must implement this interface!
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanBeConvertedToUxon
{

    /**
     * Returns the UXON representation of the business object.
     * If the UXON is imported back via import_uxon_object(), it should
     * result in the same business object.
     *
     * @return UxonObject
     */
    public function exportUxonObject();

    /**
     * Sets properties of this business object according to the UXON description.
     *
     * @return void
     */
    public function importUxonObject(UxonObject $uxon);
    
    /**
     * Returns the PHP classname of the UXON schema (must implement UxonSchemaInterface!)
     * or NULL if no special schema exists.
     * 
     * If the class does not need a specific UXON schema, this method should return
     * NULL, so the caller can use a generic fallback as appropriate.
     * 
     * @return string|NULL
     */
    public static function getUxonSchemaClass() : ?string;
}