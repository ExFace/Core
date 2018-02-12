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
    
    private $title_attribute_alias = null;
    
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
    
    /**
     * 
     * @return string
     */
    public function getTitleAttributeAlias()
    {
        if (is_null($this->title_attribute_alias)) {
            $object = $this->getMetaObject();
            if ($object->hasLabelAttribute()) {
                $this->title_attribute_alias = $object->getLabelAttributeAlias();
            } elseif ($object->hasUidAttribute()) {
                $this->title_attribute_alias = $object->getUidAttributeAlias();
            }
        }
        return $this->title_attribute_alias;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
     */
    public function getTitleAttribute()
    {
        return $this->getMetaObject()->getAttribute($this->getTitleAttributeAlias());
    }
    
    /**
     * 
     * @param string $alias
     * @return \exface\Core\Widgets\DialogHeader
     */
    public function setTitleAttributeAlias($alias)
    {
        $this->title_attribute_alias = $alias;
        return $this;
    }
    
}
?>