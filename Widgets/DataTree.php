<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\FlagTreeFolderDataType;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\DataRowReorder;
use exface\Core\Exceptions\Widgets\WidgetLogicError;

/**
 * A table showing a hierarchical structure (tree).
 * 
 * One of the columns will contain a tree with expandable/collapsible nodes, while the other
 * columns show data for each node.
 * 
 * ## Examples
 * 
 * ### Tree with recursive keys and lazy loading levels
 * 
 * ```
 * {
 *   "object_alias": "exface.Core.PAGE",
 *   "widget_type": "DataTree",
 *   "tree_parent_id_attribute_alias": "MENU_PARENT",
 *   "tree_folder_flag_attribute_alias": "IS_FOLDER_FLAG",
 *   "tree_root_uid": "NULL",
 *   "filters": [
 *     { "attribute_alias": "NAME" }
 *   ],
 *   "columns": [
 *     { "attribute_alias": "NAME" },
 *     { "attribute_alias": "ALIAS" },
 *     {  "attribute_alias": "MENU_PARENT__LABEL" }
 *   ]
 * }
 * 
 * ```
 * 
 * ### Category tree with item count per node
 * 
 * This tree has no lazy loading. It laods all available data and displays all nodes expanded.
 * 
 * ```
 * {
 *   "widget_type": "DataTree",
 *   "object_alias": "my.App.category",
 *   "tree_parent_id_attribute_alias": "parent",
 *   "tree_folder_flag_attribute_alias": "has_subcategories",
 *   "columns": [
 *     {
 *       "attribute_alias": "category_name"
 *     },
 *     {
 *       "attribute_alias": "product__id:COUNT",
 *       "caption": "Products"
 *     }
 *   ]
 * }
 * 
 * ```
 * @author Andrej Kabachnik
 *
 */
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
    
    private $row_reorder = null;
    
    private $lazy_load_tree_levels = null;
    
    private $keepExpandedPathsOnRefresh = null;

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
    public function setTreeColumnId(string $value) : DataTree
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
     * @uxon-type metamodel:attribute
     *
     * @param string $value   
     * @return DataTree         
     */
    public function setTreeFolderFlagAttributeAlias(string $value) : DataTree
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
                    $this->setTreeParentIdAttributeAlias($rel->getAliasWithModifier());
                    $found_one = true;
                }
            }
        }
        return $this->tree_parent_id_attribute_alias;
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
     * @uxon-type metamodel:attribute
     *
     * @param string $value     
     * @return DataTree       
     */
    public function setTreeParentIdAttributeAlias($value) : DataTree
    {
        $this->tree_parent_id_attribute_alias = $value;
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
     * @uxon-type boolean
     *
     * @param bool $value
     * @return DataTree
     */
    public function setTreeExpanded(bool $value) : DataTree
    {
        $this->tree_expanded = $value;
        return $this;
    }

    public function getTreeRootUid()
    {
        if ($this->tree_root_uid === null) {
            // TODO need a method to determine the root node of a tree somehow. Perhaps query for a record with parent = null?
            //$this->tree_root_uid = 1;
        }
        return $this->tree_root_uid;
    }

    /**
     * Set the UID of the root node to make the tree automatically load the first level only.
     * 
     * If not set, the widget will load all data (or a random portion of it if pagination is on)
     * and attempt to build a tree from it automatically.
     * 
     * If loading every level lazily is required, you must set the `tree_root_uid` because there
     * is no way to determine the first level automatically. Set it to `NULL` if nodes of the
     * first tree level do not have a parent in the data source.
     * 
     * On the other hand, setting `tree_root_uid` will automatically turn on lazy loading levels.
     * To still load the entire tree, set `lazy_load_tree_levels` to `false`.
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
        
        // Automatically add a root-filter if the root UID is known and lazy_load_tree_levels is not explicitly off
        if ($this->getTreeRootUid() !== null && $this->getLazyLoadTreeLevels() !== false && $data_sheet->getFilters()->isEmpty(true) === true && $this->getMetaObject()->is($data_sheet->getMetaObject())) {
            $data_sheet->getFilters()->addConditionFromString($this->getTreeParentIdAttributeAlias(), $this->getTreeRootUid(), ComparatorDataType::EQUALS);
        }
        if ($this->getLazyLoadTreeLevels() === true && $this->getTreeRootUid() === null) {
            throw new WidgetConfigurationError($this, 'Cannot use `lazy_load_tree_levels` in a ' . $this->getWidgetType() . ' if no `tree_root_uid` is specified!');
        }
        
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
    
    /**
     * If set, rows can be reordered manualy and their sequence will be saved automatically.
     * 
     * You will be able to reposition nodes within the tree moving the up or down or into 
     * another branch. Depending on the facade used, it may be achieved via drag-and-drop
     * or by pressing buttons. Similarly, changes may be saved to the data source automatically
     * or when pressing a special button.
     * 
     * The reorder-configuration basically specifies where to save the order index
     * (`order_index_attribute_alias`) the order index will be calculated automatically
     * by incrementing by 1 for each node in the curent level starting with 0. 
     * 
     * Example:
     *
     * ```json
     * {
     *  "widget_type": "DataTree",
     *  "row_reorder": {
     *      "order_index_attribute_alias": "MY_ATTRIBUTE",
     *      "order_direction": "ASC"
     *  }
     * }
     *
     * ```
     *
     * @uxon-property row_reorder
     * @uxon-type \exface\Core\Widgets\Parts\DataRowReorder
     * @uxon-template {"order_index_attribute_alias": "", "order_direction": "asc"}
     *
     * @param UxonObject $uxon
     * @return DataTable
     */
    public function setRowReorder(UxonObject $uxon) : DataTree
    {
        $part = new DataRowReorder($this, $uxon);
        $this->row_reorder = $part;
        $this->addSorter($part->getOrderIndexAttributeAlias(), $part->getOrderDirection());
        return $this;
    }
    
    /**
     * Returns the DataRowReorder widget if row reordering is configured or throws exception.
     *
     * @throws WidgetLogicError
     * @return DataRowReorder
     */
    public function getRowReorder() : DataRowReorder
    {
        if (is_null($this->row_reorder)) {
            throw new WidgetLogicError($this, 'Property row_reorder not set prior to reorder initialization!');
        }
        return $this->row_reorder;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasRowReorder() : bool
    {
        return $this->row_reorder !== null;
    }
    
    /**
     * Returns TRUE if row reordering is enabled for this table and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasRowGroups()
    {
        return $this->row_grouper !== null;
    }
    
    /**
     *
     * @return bool|NULL
     */
    public function getKeepExpandedPathsOnRefresh() : ?bool
    {
        return $this->keepExpandedPathsOnRefresh;
    }
    
    /**
     * Set to FALSE to collapse all tree nodes on refresh, search, filter, etc.
     * 
     * @uxon-property keep_expanded_paths_on_refresh
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return DataTree
     */
    public function setKeepExpandedPathsOnRefresh(bool $value) : DataTree
    {
        $this->keepExpandedPathsOnRefresh = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getLazyLoadTreeLevels() : ?bool
    {
        if ($this->lazy_load_tree_levels === null) {
            if ($this->getTreeRootUid() !== null) {
                return true;
            }
        }
        return $this->lazy_load_tree_levels;
    }
    
    /**
     * Set to TRUE to load the tree level-by-level and to FALSE to load everything at once.
     * 
     * By default, the loading strategy is up to to facade used. However, setting a 
     * `tree_root_uid` will automatically turn lazy loading on for performance reasons!
     * 
     * @uxon-property lazy_load_tree_levels
     * @uxon-type boolean
     * 
     * @param bool $value
     * @return DataTree
     */
    public function setLazyLoadTreeLevels(bool $value) : DataTree
    {
        $this->lazy_load_tree_levels = $value;
        return $this;
    }
}