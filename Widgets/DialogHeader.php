<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;

/**
 * A dialog header is a special widget used to display a summary of a dialog - typically the object currently loaded.
 *     
 * @author Andrej Kabachnik
 *        
 */
class DialogHeader extends Form
{
    private $autogenerate = null;
    
    /**
     * @return boolean
     */
    public function getAutogenerate()
    {
        return $this->autogenerate;
    }

    /**
     * @param boolean $autogenerate
     * @return DialogHeader
     */
    public function setAutogenerate($autogenerate)
    {
        $this->autogenerate = BooleanDataType::cast($autogenerate);
        return $this;
    }

    /**
     * 
     * @return Dialog
     */
    public function getDialog()
    {
        return $this->getParent();
    }
    
}
?>