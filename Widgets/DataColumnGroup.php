<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Widgets\WidgetHasNoUidColumnError;
use exface\Core\Exceptions\Model\MetaObjectHasNoUidAttributeError;
use exface\Core\CommonLogic\UxonObject;

/**
 * The DataColumnGroup is a group of columns in a data widget from one side and at the same time a full featured data widget on the other.
 * This duality makes it possible to define custom filters and even aggregators for each column group. If not done so, it will just be
 * a group of columns with it's own caption, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataColumnGroup extends AbstractWidget implements iHaveColumns
{

    // children widgets
    /** @var DataColumn[] */
    private $columns = array();

    private $uid_column_id = null;

    public function addColumn(DataColumn $column)
    {
        $column->setMetaObjectId($this->getMetaObjectId());
        if ($column->isEditable()) {
            $this->getParent()->setEditable(true);
            $this->getParent()->addColumnsForSystemAttributes();
            // If an attribute of a related object should be editable, we need it's system attributes as columns -
            // that is, at least a column with the UID of the related object, but maybe also some columns needed for
            // the behaviors of the related object
            if ($column->getAttribute() && $rel_path = $column->getAttribute()->getRelationPath()->toString()) {
                $rel = $this->getMetaObject()->getRelation($rel_path);
                if ($rel->isForwardRelation()) {
                    $this->getParent()->addColumnsForSystemAttributes($rel_path);
                } elseif ($rel->isReverseRelation()) {
                    // TODO Concatennate UIDs here?
                } elseif ($rel->isOneToOneRelation()) {
                    // TODO
                }
            }
        }
        $this->columns[] = $column;
        return $this;
    }

    /**
     * Creates a DataColumn from a meta attribute.
     * For relations the column will automatically show the label of the related object
     *
     * @param attribute $attribute            
     * @return \exface\Core\Widgets\DataColumn
     */
    function createColumnFromAttribute(Attribute $attribute, $caption = null, $hidden = null)
    {
        if ($attribute->isRelation()) {
            $attribute = $this->getMetaObject()->getAttribute(RelationPath::relationPathAdd($attribute->getAlias(), $this->getMetaObject()->getRelatedObject($attribute->getAlias())->getLabelAlias()));
        }
        
        $c = $this->getPage()->createWidget('DataColumn', $this);
        $c->setAttributeAlias($attribute->getAliasWithRelationPath());
        
        if (! is_null($hidden)) {
            $c->setHidden($hidden);
        }
        
        if (! is_null($caption)) {
            $c->setCaption($caption);
        }
        
        return $c;
    }

    /**
     * Returns the id of the column holding the UID of each row.
     * By default it is the column with the UID attribute of
     * the meta object displayed in by the data widget, but this can be changed in the UXON description if required.
     *
     * @return string
     */
    function getUidColumnId()
    {
        // If there is no UID column defined yet, try to generate one automatically
        if (is_null($this->uid_column_id)) {
            try {
                if (! $col = $this->getColumnByAttributeAlias($this->getMetaObject()->getUidAttribute()->getAliasWithRelationPath())) {
                    $col = $this->createColumnFromAttribute($this->getMetaObject()->getUidAttribute(), null, true);
                    $this->addColumn($col);
                }
                $this->uid_column_id = $col->getId();
            } catch (MetaObjectHasNoUidAttributeError $e) {
                // Do nothing. Depending on what the user wants to do with the column group, it might work without
                // a UID column. If not, an error will be generated elsewhere.
            }
        }
        return $this->uid_column_id;
    }

    /**
     * Sets the id of the column to be used as UID for each data row in this column group.
     *
     * This can be usefull if the column group is based on a meta object not directly related to
     * the the object of the parent Data widet. In this case, you can specify which column of the
     * group to use, to join rows to the main data.
     *
     * @uxon-property uid_column_id
     * @uxon-type string
     *
     * @param string $value            
     * @return DataColumnGroup
     */
    public function setUidColumnId($value)
    {
        $this->uid_column_id = $value;
        return $this;
    }

    public function getUidColumn()
    {
        if (! $this->getUidColumnId()) {
            throw new WidgetHasNoUidColumnError($this, 'No UID column found in DataColumnGroup: either set uid_column_id for the column group explicitly or give the object "' . $this->getMetaObject()->getAliasWithNamespace() . '" a UID attribute!');
        }
        if (! $col = $this->getColumn($this->getUidColumnId())) {
            $col = $this->getParent()->getColumn($this->getUidColumnId());
        }
        return $col;
    }

    /**
     * Returns TRUE if this column group has a UID column or FALSE otherwise.
     *
     * @return boolean
     */
    public function hasUidColumn()
    {
        try {
            $this->getUidColumn();
        } catch (WidgetHasNoUidColumnError $e) {
            return false;
        }
        return true;
    }

    /**
     * Returns TRUE if this column group is the main column group of the parent widget
     *
     * @return boolean
     */
    public function isMainColumnGroup()
    {
        if ($this->getParent()->getColumnGroupMain() === $this) {
            return true;
        } else {
            return false;
        }
    }

    public function isEmpty()
    {
        if (count($this->columns) > 0) {
            return false;
        } else {
            return true;
        }
    }

    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Returns the data column matching the given id.
     *
     * @param unknown $column_id            
     * @return \exface\Core\Widgets\DataColumn|boolean
     */
    public function getColumn($column_id, $use_data_column_names_as_fallback = true)
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getId() === $column_id) {
                return $col;
            }
        }
        if ($use_data_column_names_as_fallback) {
            return $this->getColumnByDataColumnName($column_id);
        }
        return false;
    }

    function getColumnByAttributeAlias($alias_with_relation_path)
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getAttributeAlias() === $alias_with_relation_path) {
                return $col;
            }
        }
        return false;
    }

    function getColumnByDataColumnName($data_sheet_column_name)
    {
        foreach ($this->getColumns() as $col) {
            if ($col->getDataColumnName() === $data_sheet_column_name) {
                return $col;
            }
        }
        return false;
    }

    /**
     * Defines the DataColumns within this group: an array of respecitve UXON objects.
     *
     * @uxon-property columns
     * @uxon-type DataColumn[]
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::setColumns()
     */
    public function setColumns(array $columns)
    {
        foreach ($columns as $c) {
            if ($c->attribute_group_alias) {
                foreach ($this->getMetaObject()->getAttributeGroup($c->attribute_group_alias)->getAttributes() as $attr) {
                    $this->addColumn($this->createColumnFromAttribute($attr));
                }
                continue;
            }
            $this->addColumn($this->createColumnFromUxon($c));
        }
        return $this;
    }
    
    /**
     * 
     * 
     * @param UxonObject $uxon
     * @return DataColumn
     */
    public function createColumnFromUxon(UxonObject $uxon)
    {
        $caption = null;
        
        // preset some column properties based on meta attributes
        
        // Set the caption to the attribute name or the relation name, if the attribute is the label of a related object.
        // This preset caption will get overwritten by one specified in UXON once the UXON object is overloaded
        if (! $uxon->caption && $this->getMetaObject()->hasAttribute($uxon->attribute_alias)) {
            $attr = $this->getMetaObject()->getAttribute($uxon->attribute_alias);
            if ($attr->isLabel() && $attr->getRelationPath()->toString()) {
                $caption = $this->getMetaObject()->getRelation($attr->getRelationPath()->toString())->getName();
            } else {
                $caption = $attr->getName();
            }
        }
        
        // Create the column
        $column_type = $uxon->widget_type ? $uxon->widget_type : 'DataColumn';
        $column = $this->getPage()->createWidget($column_type, $this);
        $column->setCaption($caption);
        $column->importUxonObject($uxon);
        
        return $column;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren()
    {
        $children = $this->getColumns();
        return $children;
    }

    /**
     * Returns the number of columns in this group (including hidden columns!)
     *
     * @return integer
     */
    public function countColumns()
    {
        return count($this->getColumns());
    }

    /**
     * Returns the number of visible columns in this group
     *
     * @return integer
     */
    public function countColumnsVisible()
    {
        $result = 0;
        foreach ($this->getColumns() as $column) {
            if (! $column->isHidden()) {
                $result ++;
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO add properties specific to this widget here
        return $uxon;
    }
}
?>