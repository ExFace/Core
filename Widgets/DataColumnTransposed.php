<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class DataColumnTransposed extends DataColumn
{
    private $label_attribute_alias = null;
    
    private $hiddenIfEmpty = false;

    /**
     * 
     * @return string
     */
    public function getLabelAttributeAlias() : string
    {
        return $this->label_attribute_alias;
    }
    
    /**
     * 
     * @throws WidgetConfigurationError
     * @return DataColumn
     */
    public function getLabelColumn() : DataColumn
    {
        $col = $this->getDataWidget()->getColumnByAttributeAlias($this->getLabelAttributeAlias());
        if ($col === null) {
            throw new WidgetConfigurationError($this, 'Cannot transpose column "' . $this->getAttributeAlias() . '": `label_attribute_alias` not found in data widget!');
        }
        return $col;
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
    
    /**
     * 
     * @return bool
     */
    public function getHiddenIfEmpty() : bool
    {
        return $this->hiddenIfEmpty;
    }
    
    /**
     * Set to TRUE to hide subrows resulting from this column, that do not have any values
     * 
     * This is usefull if you transpose multiple columns, which results in a "subrow" for
     * each of them. Now if not every row needs every subrow, you can turn this on to make
     * sure, subrows are only visible if they have values. 
     * 
     * For example, if you have KPIs per department transposed along the timeline, you will 
     * have a subrow for each transposed KPI column. But if some KPIs are not meaingfull for 
     * every department, hide their empty rows to make them appear only if they do have data.
     * 
     * @uxon-property hidden_if_empty
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return DataColumnTransposed
     */
    public function setHiddenIfEmpty(bool $value) : DataColumnTransposed
    {
        $this->hiddenIfEmpty = $value;
        return $this;
    }
}