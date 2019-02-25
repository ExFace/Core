<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\FlagTreeFolderDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

class DataTree extends DataTable
{
    private $tree_column_id = null;

    private $tree_parent_id_attribute_alias = null;
    
    private $tree_folder_filter_attribute_alias = null;

    private $tree_folder_flag_attribute_alias = null;
    
    private $tree_leaf_id_concatenate = null;
    
    private $tree_leaf_id_column_id = null;

    private $tree_expanded = false;

    private $tree_root_uid = null;

    protected function init()
    {
        parent::init();
        $this->setPaginate(false);
    }

    /**
     * Returns the id of the column, that is supposed to display the tree
     */
    public function getTreeColumnId()
    {
        if ($this->tree_column_id === null) {
            foreach ($this->getColumns() as $col) {
                if (! $col->isHidden()) {
                    return $col->getId();
                }
            }
        }
        return $this->tree_column_id;
    }

    /**
     *
     * @return boolean|\exface\Core\Widgets\DataColumn
     */
    public function getTreeColumn()
    {
        if (! $result = $this->getColumn($this->getTreeColumnId())) {
            $result = $this->getColumnByAttributeAlias($this->getTreeColumnId());
        }
        return $result;
    }

    /**
     * Set the id of the column, that is supposed to display the tree.
     * 
     * If not specified, the first visible column will be used automatically.
     * 
     * @uxon-property tree_column_id
     * @uxon-type string
     *
     * @param string $value   
     * @return DataTree         
     */
    public function setTreeColumnId($value) : DataTree
    {
        $this->tree_column_id = $value;
        return $this;
    }

    /**
     * Returns the alias of the attribute, that indicates, wether the node has children (= is a folder)
     */
    public function getTreeFolderFlagAttributeAlias()
    {
        if (! $this->tree_folder_flag_attribute_alias) {
            $flags = $this->getMetaObject()->getAttributes()->filter(function(MetaAttributeInterface $attr){
                return $attr->getDataType()->is(FlagTreeFolderDataType::getPrototypeClassName());
            });
            if ($flags->count() == 1) {
                $flag = $flags->getFirst();
                $this->setTreeFolderFlagAttributeAlias($flag->getAlias());
            } else {
                throw new WidgetConfigurationError($this, 'More than one tree folder flag found for the treeGrid "' . $this->getId() . '". Please specify "tree_folder_flag_attribute_alias" in the description of the widget!', '6T91BRG');
            }
        }
        return $this->tree_folder_flag_attribute_alias;
    }
    
    public function hasTreeFolderFlag() : bool
    {
        return $this->tree_folder_flag_attribute_alias !== null;
    }

    /**
     * Sets the alias of the attribute, that indicates, wether the node has children (= is a folder).
     * 
     * The attribute is also automatically added as a hidden column!
     * 
     * @uxon-property tree_folder_flag_attribute_alias
     * @uxon-type string
     *
     * @param string $value   
     * @return DataTree         
     */
    public function setTreeFolderFlagAttributeAlias($value) : DataTree
    {
        $this->tree_folder_flag_attribute_alias = $value;
        return $this;
    }

    /**
     * Returns the alias of the relation to the parent object (same as the alias of the corresponding attribute).
     * If the relation is not explicitly defined, ExFace tries to determine it automatically by searching for
     * a recursive relation to the object itself.
     *
     * @throws WidgetConfigurationError if more than one recursive relation is found
     */
    public function getTreeParentIdAttributeAlias()
    {
        // If the parent relation is not specified explicitly, we search for a relation to the object itself
        if (! $this->tree_parent_id_attribute_alias) {
            $found_one = false;
            foreach ($this->getMetaObject()->getRelations() as $rel) {
                if ($rel->getRightObject()->is($this->getMetaObject()) && $rel->isForwardRelation()) {
                    if ($found_one === true) {
                        throw new WidgetConfigurationError($this, 'More than one recursive relations found for the treeGrid "' . $this->getId() . '". Please specify "tree_parent_id_attribute_alias" in the description of the widget!', '6T91BRG');
                    }
                    $this->setTreeParentIdAttributeAlias($rel->getAlias());
                    $found_one = true;
                }
            }
        }
        return $this->parent_id_attribute_alias;
    }
    
    /**
     * 
     * @return string
     */
    public function getTreeFolderFilterAttributeAlias() : string
    {
        if ($this->tree_folder_filter_attribute_alias === null) {
            return $this->getUidColumn()->getAttributeAlias();
        }
        
        return $this->tree_folder_filter_attribute_alias;
    }
    
    /**
     * Sets the alias of the attribute, that should be used as a filter when loading the children of a node. 
     * 
     * @uxon-property tree_folder_filter_attribute_alias
     * @uxon-type metamodel:attribute
     *
     * @param string $alias
     * @return DataTree
     */
    public function setTreeFolderFilterAttributeAlias(string $alias) : DataTree
    {
        $this->tree_folder_filter_attribute_alias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return DataColumn
     */
    public function getTreeFolderFilterColumn() : DataColumn
    {
        if (! $col = $this->getColumnByAttributeAlias($this->getTreeFolderFilterAttributeAlias())) {
            $col = $this->createColumnFromAttribute($this->getMetaObject()->getAttribute($this->getTreeFolderFilterAttributeAlias()), null, true);
            $this->addColumn($col);
        }
        
        return $col;
    }

    /**
     * Sets the alias of the relation to the object of the next-higher level (parent).
     * 
     * The attribute is also automatically added as a hidden column!
     *
     * @uxon-property tree_parent_id_attribute_alias
     * @uxon-type string
     *
     * @param string $value     
     * @return DataTree       
     */
    public function setTreeParentIdAttributeAlias($value) : DataTree
    {
        $this->parent_id_attribute_alias = $value;
        return $this;
    }

    public function getTreeExpanded()
    {
        return $this->tree_expanded;
    }

    /**
     * Set to TRUE to auto-expand branches, whose children had been loaded.
     * 
     * @uxon-property tree_expanded
     * @uxon-type string
     *
     * @param bool|string|int $value
     * @return DataTree
     */
    public function setTreeExpanded($value) : DataTree
    {
        $this->tree_expanded = BooleanDataType::cast($value);
        return $this;
    }

    public function getTreeRootUid()
    {
        // TODO need a method to determine the root node of a tree somehow. Perhaps query for a record with parent = null?
        if (! $this->tree_root_uid) {
            $this->tree_root_uid = 1;
        }
        return $this->tree_root_uid;
    }

    /**
     * Set the UID of the root elemen to make the tree automatically load it's children.
     * 
     * If not set, the tree will first show the roots, which is not very helpful if there
     * is just one root element.
     * 
     * @uxon-property tree_root_uid
     * @uxon-type string
     *
     * @param string $value
     * @return DataTree
     */
    public function setTreeRootUid($value) : DataTree
    {
        $this->tree_root_uid = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        if ($this->hasTreeFolderFlag()) {
            $data_sheet->getColumns()->addFromExpression($this->getTreeFolderFlagAttributeAlias());
        }
        $data_sheet->getColumns()->addFromExpression($this->getTreeParentIdAttributeAlias());
        
        return $data_sheet;
    }
    
    /**
     * Specify a delimiter here to make the tree automatically concatenate row UIDs to create unique leaf IDs.
     * 
     * This is important if your row UIDs may occur at multiple places in the hierarchy. Most tree widget
     * implementations will not work then, unless you make sure, the ids are unique by building branch paths.
     * 
     * You can show the calculated leaf id if you specify `tree_leaf_id_column_id` additionally. See 
     * Administration > Metamodel > Objects > Relations for a live example.
     * 
     * @uxon-property tree_leaf_id_concatenate
     * @uxon-type string
     * 
     * @param string $delimiter
     * @return DataTree
     */
    public function setTreeLeafIdConcatenate(string $delimiter) : DataTree
    {
        $this->tree_leaf_id_concatenate = $delimiter;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getTreeLeafIdConcatenate() : ?string
    {
        return $this->tree_leaf_id_concatenate;
    }
    
    /**
     * Makes the column with the given id hold the tree leaf ids.
     * 
     * By default, leafs are identified by values from the table's UID column. However, if you use
     * `tree_leaf_id_concatenate` to create path-like ids, you can show them by creating a special
     * column and referencing it here. 
     * 
     * To have the column show calculated ids, it must not have an attribute alias. For example, the
     * following widget will display a single column (the tree), showing UIDs of the underlying
     * meta object concatenated with dashes.
     * 
     * ```
     * {
     *  "widget_type": "DataTree",
     *  "tree_leaf_id_concatenate": "-",
     *  "tree_leaf_id_column_id": "path_column",
     *  "columns": [
     *      {
     *          "caption": "Path",
     *          "id": "path_column"
     *      }
     *  ]
     * }
     * ```
     * 
     * See Administration > Metamodel > Objects > Relations for a live example.
     * 
     * @uxon-property tree_leaf_id_column_id
     * @uxon-type string
     *
     * @param string $id
     * @return DataTree
     */
    public function setTreeLeafIdColumnId(string $id) : DataTree
    {
        $this->tree_leaf_id_column_id = $id;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getTreeLeafIdColumnId() : ?string
    {
        return $this->tree_leaf_id_column_id;
    }
    
    /**
     * Returns the data column, that will contain unique ids of the tree leafs.
     * 
     * @return DataColumn
     */
    public function getTreeLeafIdColumn() : DataColumn
    {
        if ($this->tree_leaf_id_column_id === null) {
            return $this->getUidColumn();
        }
        
        return $this->getColumn($this->getTreeLeafIdColumnId());
    }
}
?>