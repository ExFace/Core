<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

class DataTree extends DataTable
{

    private $tree_column_id = null;

    private $tree_parent_id_attribute_alias = null;

    private $tree_folder_flag_attribute_alias = null;

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
     * Set the id of the column, that is supposed to display the tree
     * 
     * @param string $value            
     */
    public function setTreeColumnId($value)
    {
        $this->tree_column_id = $value;
    }

    /**
     * Returns the alias of the attribute, that indicates, wether the node has children (= is a folder)
     */
    public function getTreeFolderFlagAttributeAlias()
    {
        if (! $this->tree_folder_flag_attribute_alias) {
            $flags = $this->getMetaObject()
                ->getAttributes()
                ->getByDataTypeAlias(EXF_DATA_TYPE_FLAG_TREE_FOLDER);
            if ($flags->count() == 1) {
                $flag = $flags->getFirst();
                $this->setTreeFolderFlagAttributeAlias($flag->getAlias());
            } else {
                throw new WidgetConfigurationError($this, 'More than one tree folder flag found for the treeGrid "' . $this->getId() . '". Please specify "tree_folder_flag_attribute_alias" in the description of the widget!', '6T91BRG');
            }
        }
        return $this->tree_folder_flag_attribute_alias;
    }

    /**
     * Sets the alias of the attribute, that indicates, wether the node has children (= is a folder)
     * The attribute is also automatically added as a hidden column!
     * 
     * @param string $value            
     */
    public function setTreeFolderFlagAttributeAlias($value)
    {
        $this->tree_folder_flag_attribute_alias = $value;
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
            foreach ($this->getMetaObject()->getRelationsArray() as $rel) {
                if ($rel->getRelatedObjectId() == $this->getMetaObjectId() && $rel->isForwardRelation()) {
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
     * Sets the alias of the relation to the parent object (same as the alias of the corresponding attribute).
     * The attribute is also automatically added as a hidden column!
     * 
     * @param string $value            
     */
    public function setTreeParentIdAttributeAlias($value)
    {
        $this->parent_id_attribute_alias = $value;
    }

    public function getTreeExpanded()
    {
        return $this->tree_expanded;
    }

    public function setTreeExpanded($value)
    {
        $this->tree_expanded = $value;
    }

    public function getTreeRootUid()
    {
        // TODO need a method to determine the root node of a tree somehow. Perhaps query for a record with parent = null?
        if (! $this->tree_root_uid) {
            $this->tree_root_uid = 1;
        }
        return $this->tree_root_uid;
    }

    public function setTreeRootUid($value)
    {
        $this->tree_root_uid = $value;
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
        
        $data_sheet->getColumns()->addFromExpression($this->getTreeFolderFlagAttributeAlias());
        $data_sheet->getColumns()->addFromExpression($this->getTreeParentIdAttributeAlias());
        
        return $data_sheet;
    }
}
?>