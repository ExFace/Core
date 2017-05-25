<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\UxonObject;

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
}
?>