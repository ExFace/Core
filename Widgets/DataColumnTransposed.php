<?php
namespace exface\Core\Widgets;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class DataColumnTransposed extends DataColumn
{
    private $label_attribute_alias = null;

    /**
     * 
     * @return string|NULL
     */
    public function getLabelAttributeAlias() : ?string
    {
        return $this->label_attribute_alias;
    }

    /**
     * Which attribute to use for column heading when transposing this columns data.
     * 
     * @uxon-property label_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return \exface\Core\Widgets\DataColumnTransposed
     */
    public function setLabelAttributeAlias(string $value) : DataColumnTransposed
    {
        $this->label_attribute_alias = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\DataColumn::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('label_attribute_alias', $this->getLabelAttributeAlias());
        return $uxon;
    }
}
?>