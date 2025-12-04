<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iUseData;

/**
 * Renders a dialog to create a copy of the input object.
 * 
 * Dialog rendering works just like in `ShowObjectEditDialog`, but
 * - save-button creates a copy instead of modifying the selected object by calling the `CopyData` action
 * - all other buttons like those for editing related objects are removed similarly to 
 * `ShowObjectInfoDialog`
 * - any data widgets showing related objects are empty if those related objects are not to
 * be copied
 * 
 * By default the object being shown is copied and all related objects, marked for copying in
 * the metamodel of their relation attributes. However, you can also specify the related objects
 * to be copied explicitly by adding their corresponding relations to  `copy_related_objects'.
 * In this case, the `CopyData` action will perform a deep-copy.
 * 
 * Also note, that by default the editors of non-copyable attributes will remain empty (since they
 * should not be copied). However, this behavior is automatically disabled if the action has 
 * `input_mappers`. So, if for any reason, you need a prefill for non-copyable attributes, use 
 * input mappers to define the values explicitly.
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowObjectCopyDialog extends ShowObjectEditDialog
{
    private $copyRelatedObjects = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowObjectEditDialog::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::CLONE_);
        $this->setSaveActionAlias('exface.Core.CopyData');
        $this->setPrefillWithFilterContext(false);
        // Remove any buttons as they often assume, that the UID if present in the dialog
        // is the UID of the edited object - which it is NOT in this case, but rather the
        // UID of the object being copied, which is not to be changed.
        $this->setDisableButtons(true);
    }

    /**
     * 
     * {@inheritdoc} 
     * @see \exface\Core\Actions\ShowWidget::prefillWidget()
     */
    protected function prefillWidget(TaskInterface $task, WidgetInterface $widget) : WidgetInterface
    {
        // If there are no explicit input mappers and the task object is the same as the actions object,
        // create a special input mapper to make sure the prefill data does not contain non-copyable 
        // attributes. This mapper will explicitly empty non-copyable attributes while keeping
        // copyable system attributes.
        // This trick will only work if the meta object has a UID attribute and thus all required data
        // can be loaded from the data source.
        $obj = $this->getMetaObject();
        if (! $this->hasInputMappers() && $obj->hasUidAttribute() && $obj->isReadable() && (! $task->hasMetaObject() || $obj->is($task->getMetaObject()))) {
            $mappings = [];
            foreach ($this->getMetaObject()->getAttributes() as $attr) {
                if ($attr->isUidForObject() || ($attr->isSystem() && $attr->isCopyable())) {
                    $mappings[] = [
                        'from' => $attr->getAlias(),
                        'to' => $attr->getAlias()
                    ];
                    continue;
                }
                if ($attr->isCopyable() === false) {
                    $mappings[] = [
                        'from' => "=''",
                        'to' => $attr->getAlias()
                    ];
                    continue;
                }
            }
            $this->addInputMapper(DataSheetMapperFactory::createFromUxon(
                $this->getWorkbench(), 
                new UxonObject(["column_to_column_mappings" => $mappings]), 
                $task->getMetaObject(), 
                $this->getMetaObject()
            ));
        }
        
        // Now make sure any data widgets inside the dialog, that show related objects
        // are empty if those related objects are not to be copied. If the are to be
        // copied, they need to be shown, but cannot be edited because all the buttons
        // were removed in `init()`
        if ($widget instanceof iContainOtherWidgets) {
            $copyRelAliases = $this->getCopyRelationAliases();
            foreach ($widget->getWidgetsRecursive() as $child) {
                switch (true) {
                    case $child instanceof iShowData:
                        $dataChild = $child;
                        break;
                    case $child instanceof iUseData:
                        $dataChild = $child->getData();
                        break;
                    default:
                        continue 2;
                }
                $relFilters = $dataChild->getConfiguratorWidget()->findFiltersByObject($this->getMetaObject());
                /* @var $relFilter \exface\Core\Widgets\Filter */
                foreach ($relFilters as $relFilter) {
                    if (! $relFilter->isBoundToAttribute()) {
                        continue;
                    }
                    $fltrAttr = $relFilter->getAttribute();
                    $relPath = $fltrAttr->getRelationPath()->copy();
                    if ($fltrAttr->isRelation()) {
                        $relPath->appendRelation($fltrAttr->getRelation());
                        
                    }
                    // Try to reverse the relation to see, if we need to disable the prefill fo the dialog,
                    // but do not bother if anything goes wrong. This did actually happen when copying
                    // object actions when examining the mutation table - that is linked via a custom
                    // attribute with a relation and that did not produce reverse relations a its
                    // right object (the object action).
                    try {
                        $revRelPath = $relPath->reverse();
                        if (!in_array($revRelPath->toString(), $copyRelAliases)) {
                            $dataChild->setDoNotPrefill(true);
                        }
                    } catch (MetaRelationNotFoundError $e) {
                        $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::WARNING);
                    }
                }
            }
        }
        
        return parent::prefillWidget($task, $widget);
    }
    
    /**
     *
     * @return string[]
     */
    protected function getCopyRelationAliases() : array
    {
        $aliases = $this->copyRelatedObjects;
        foreach ($this->getMetaObject()->getRelations() as $rel) {
            if ($rel->isRightObjectToBeCopiedWithLeftObject()) {
                $aliases[] = $rel->getAliasWithModifier();
            }
        }
        return array_unique($aliases);
    }
    
    /**
     * Define an array of action aliases, whose right obects should be copied too.
     * 
     * @uxon-property copy_related_objects
     * @uxon-type metamodel:relation[]
     * @uxon-template [""]
     * 
     * @param UxonObject $relationAliases
     * @return ShowObjectCopyDialog
     */
    public function setCopyRelatedObjects(UxonObject $relationAliases) : ShowObjectCopyDialog
    {
        $this->copyRelatedObjects = $relationAliases->toArray();
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isDeepCopy() : bool
    {
       return ! empty($this->getCopyRelationAliases()); 
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowObjectEditDialog::getSaveActionUxon()
     */
    public function getSaveActionUxon() : UxonObject
    {
        $uxon = parent::getSaveActionUxon();
        if ($this->isDeepCopy() && $uxon->hasProperty('copy_related_objects') === false) {
            $uxon->setProperty('copy_related_objects', $this->getCopyRelationAliases());
        }
        return $uxon;
    }
}