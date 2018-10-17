<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Model\MetaObjectHasNoUidAttributeError;
use exface\Core\Exceptions\Widgets\WidgetHasNoUidColumnError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iHaveColumns;

/**
 * The DataColumnGroup is a group of columns in a data widget from one side and at the same time a full featured data widget on the other.
 * This duality makes it possible to define custom filters and even aggregators for each column group. If not done so, it will just be
 * a group of columns with it's own caption, etc.
 * 
 * @method Data getParent()
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

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::addColumn()
     */
    public function addColumn(DataColumn $column, $position = NULL)
    {
        $column->setMetaObject($this->getMetaObject());
        if ($column->isEditable()) {
            $this->getParent()->setEditable(true);
            $this->getParent()->addColumnsForSystemAttributes();
            // If an attribute of a related object should be editable, we need it's system attributes as columns -
            // that is, at least a column with the UID of the related object, but maybe also some columns needed for
            // the behaviors of the related object
            if ($column->hasAttributeReference() && $rel_path = $column->getAttribute()->getRelationPath()->toString()) {
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
        
        if (is_null($position) || ! is_numeric($position)) {
            $this->columns[] = $column;
        } else {
            array_splice($this->columns, $position, 0, array(
                $column
            ));
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::removeColumn()
     */
    public function removeColumn(DataColumn $column)
    {
        $key = array_search($column, $this->columns);
        if ($key !== false){
            unset($this->columns[$key]);
            // Reindex the array to avoid index gaps
            $this->columns = array_values($this->columns);
        }
        return $this;
    }

    /**
     * Creates a DataColumn from a meta attribute.
     * 
     * The column is not automatically added to the column group - use addColumn() explicitly!
     * 
     * For relations the column will automatically show the label of the related object
     *
     * @param MetaAttributeInterface $attribute            
     * @return \exface\Core\Widgets\DataColumn
     */
    function createColumnFromAttribute(MetaAttributeInterface $attribute, $caption = null, $hidden = null)
    {
        if ($attribute->isRelation()) {
            $attribute = $this->getMetaObject()->getAttribute(RelationPath::relationPathAdd($attribute->getAlias(), $this->getMetaObject()->getRelatedObject($attribute->getAlias())->getLabelAttributeAlias()));
        }
        
        $c = $this->getPage()->createWidget($this->getColumnDefaultWidgetType(), $this);
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
        return empty($this->columns) ? true : false;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Returns the data column matching the given id.
     *
     * @param string $column_id            
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
    public function setColumns(UxonObject $columns)
    {
        foreach ($columns as $c) {
            if ($c->hasProperty('attribute_group_alias')) {
                foreach ($this->getMetaObject()->getAttributeGroup($c->getProperty('attribute_group_alias'))->getAttributes() as $attr) {
                    $this->addColumn($this->createColumnFromAttribute($attr));
                }
            } else {
                $this->addColumn($this->createColumnFromUxon($c));
            }
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
        // Create the column
        $column = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, $this->getColumnDefaultWidgetType());
        
        // Set the caption to the attribute name or the relation name, if the attribute is the label of a related object.
        // This preset caption will get overwritten by one specified in UXON once the UXON object is overloaded
        if (! $uxon->hasProperty('caption') && $this->getMetaObject()->hasAttribute($uxon->getProperty('attribute_alias'))) {
            $attr = $this->getMetaObject()->getAttribute($uxon->getProperty('attribute_alias'));
            if ($attr->isLabelForObject() && $attr->getRelationPath()->toString()) {
                $caption = $this->getMetaObject()->getRelation($attr->getRelationPath()->toString())->getName();
            } else {
                $caption = $attr->getName();
            }
            $column->setCaption($caption);
        }
        
        // Import the UXON object eventually overriding the above presets
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::hasColumns()
     */
    public function hasColumns()
    {
        return empty($this->columns) ? false : true;
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
    
    /**
     * 
     * @return \exface\Core\Widgets\Data
     */
    public function getDataWidget()
    {
        return $this->getParent();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveColumns::getColumnDefaultWidgetType()
     */
    public function getColumnDefaultWidgetType() : string
    {
        return $this->getDataWidget()->getColumnDefaultWidgetType();
    }
}
?>