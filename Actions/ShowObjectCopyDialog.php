<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetMapperFactory;
use exface\Core\Events\Widget\OnBeforePrefillEvent;
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * Renders a dialog to create a copy of the input object.
 * 
 * Dialog rendering works just like in `ShowObjectEditDialog`, but the save-button creates a copy
 * instead of modifying the selected object by calling the `CopyData` action.
 * 
 * By default the object being shown is copied and all related objects, marked for copying in
 * the metamodel of their relation attributes. However, you can also specify the related objects
 * to be copied explicitly by adding their corresponding relations to  `copy_related_objects'.
 * In this case, the `CopyData` action will perform a deep-copy.
 * 
 * Also note, that by default the editors of non-copyable attributes will remain empty (since they
 * should not be copied). However, this behavior is automatically disable if the action has `input_mappers`.
 * So, if for any reason, you need a prefill for non-copyable attributes, use input mappers to define the
 * values explicitly.
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
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforePrefillEvent::getEventName(), [$this, 'onBeforePrefillCheckIfCopyable']);
        
        return parent::prefillWidget($task, $widget);
    }
    
    public function onBeforePrefillCheckIfCopyable(OnBeforePrefillEvent $event)
    {
        $widget = $event->getWidget();
        if ($widget instanceof iShowData) {
            foreach ($widget->getConfiguratorWidget()->findFiltersByObject($this->getMetaObject()) as $filter) {
                
            }
        }
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