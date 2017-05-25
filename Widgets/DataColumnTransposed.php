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

    private $label_sort_direction = null;

    public function getLabelAttributeAlias()
    {
        return $this->label_attribute_alias;
    }

    public function setLabelAttributeAlias($value)
    {
        $this->label_attribute_alias = $value;
        return $this;
    }

    public function getLabelSortDirection()
    {
        return $this->label_sort_direction;
    }

    public function setLabelSortDirection($value)
    {
        $this->label_sort_direction = $value;
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
        $uxon->setProperty('label_sort_direction', $this->getLabelSortDirection());
        return $uxon;
    }
}
?>