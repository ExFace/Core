<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\UxonObject;

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
    }

    /**
     * In the case of the dublicate-action we need to remove the UID column from the data sheet to ensure, that the
     * duplicated object will get new ids.
     *
     * {@inheritdoc} 
     * @see \exface\Core\Actions\ShowWidget::prefillWidget()
     */
    protected function prefillWidget(TaskInterface $task, WidgetInterface $widget) : WidgetInterface
    {
        // If it is a deep copy, we need to make sure, widget eventually displaying related objects are 
        // prefilled just like they are in ShowObjectEditDialog.
        if ($this->isDeepCopy()) {
            return parent::prefillWidget($task, $widget);
        }
        
        $data_sheet = $this->getInputDataSheet($task);
        
        if ($data_sheet->getUidColumn()) {
            $data_sheet = $this->getWidget()->prepareDataSheetToPrefill($data_sheet);
            if (! $data_sheet->isFresh() && $data_sheet->hasUidColumn(true)) {
                $data_sheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
                $data_sheet->dataRead();
            }
            // TODO #copy-data Really remove the UID column here? The CopyData action might
            // need it to load additional values... Various disablers for child object tables
            // in default editors count on the UID field being empty. 
            $data_sheet->getColumns()->removeByKey($data_sheet->getUidColumn()->getName());
        }
        
        $widget->prefill($data_sheet);
        
        return $widget;
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