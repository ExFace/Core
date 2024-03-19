<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Actions\iRenderTemplate;
use exface\Core\Factories\ActionFactory;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\ComparatorDataType;

/**
 * Renders a preview for a provided print action
 * 
 * This action is intended to be used with a data widget, which will provide input data with
 * column for `print_action_selector_attribute` and `preview_label_attribute`. The former
 * defines the action, that the preview should show and the latter allows to define, what
 * data to use as example.
 * 
 * @author Andrej Kabachnik
 *
 */
class PrintPreview extends GoToUrl
{
    private $printActionSelectorAttributeAlias = null;
    
    private $previewLabelAttributeAlias = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::BINOCULARS);
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Actions\ReadData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputSheet = $this->getInputDataSheet($task);
        $printAction = $this->getPrintAction($task, $inputSheet);
        
        if (null !== $previewLabelColName = $this->getPreviewLabelAttributeAlias()) {
            $previewLabelCol = $inputSheet->getColumns()->getByExpression($previewLabelColName);
            $previewDataLabel = $previewLabelCol->getValue(0);
        } else {
            $previewDataLabel = null;
        }
        $previewSheet = DataSheetFactory::createFromObject($printAction->getMetaObject());
        $previewSheet->getColumns()->addFromUidAttribute();
        $labelCol = $previewSheet->getColumns()->addFromLabelAttribute();
        if ($previewDataLabel === null || $previewDataLabel === '') {
            $previewSheet->getSorters()->addFromString($printAction->getMetaObject()->getUidAttributeAlias(), SortingDirectionsDataType::DESC);
        } else {
            $previewSheet->getFilters()->addConditionFromAttribute($labelCol->getAttribute(), $previewDataLabel, ComparatorDataType::EQUALS);
        }
        $previewSheet->dataRead(1);
        
        if ($previewSheet->isEmpty()) {
            if ($previewDataLabel !== null || $previewDataLabel === '') {
                $preview = 'No printable document found with label "' . $previewDataLabel . '"';
            } else {
                $preview = 'Could not find suitable example data for print preview';
            }
        } else {
            $prints = $printAction->renderTemplate($previewSheet);
            $preview = reset($prints);
        }
        
        $result = ResultFactory::createHTMLResult($task, $preview);
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\GoToUrl::buildUrl()
     */
    protected function buildUrl() : ?string
    {
        return "[#facade#]?action=exface.Core.PrintPreview&resource={$this->getWidgetDefinedIn()->getPage()->getAliasWithNamespace()}&element=[#element_id:~self#]&data[oId]={$this->getWidgetDefinedIn()->getInputWidget()->getMetaObject()->getId()}&data[rows][0][{$this->getPrintActionSelectorAttributeAlias()}]=[#{$this->getPrintActionSelectorAttributeAlias()}#]&data[rows][0][{$this->getPreviewLabelAttributeAlias()}]=[#{$this->getPreviewLabelAttributeAlias()}#]";
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param DataSheetInterface $inputData
     * @throws ActionInputError
     * @return iRenderTemplate
     */
    protected function getPrintAction(TaskInterface $task, DataSheetInterface $inputData) : iRenderTemplate
    {
        if ($inputData->getMetaObject()->is('exface.Core.OBJECT_ACTION')) {
            $actionCol = $inputData->getColumns()->get('ALIAS_WITH_NS');
        } else {
            $actionCol = $inputData->getColumns()->getByExpression($this->getPrintActionSelectorAttributeAlias());
        }
        
        foreach ($actionCol->getValues() as $selector) {
            $action = ActionFactory::createFromString($this->getWorkbench(), $selector/*, $this->getWidgetDefinedIn()*/);
            if ($action instanceof iRenderTemplate) {
                return $action;
            }
        }
        
        throw new ActionInputError($this, 'No print actions found in input data!');
    }
    
    /**
     * 
     * @return string
     */
    protected function getPrintActionSelectorAttributeAlias() : string
    {
        return $this->printActionSelectorAttributeAlias;
    }
    
    /**
     * Alias or column name in the input data, that holds the selector of the action to preview
     * 
     * @uxon-property print_action_selector_attribute
     * @uxon-type metamodel:attribute|string
     * @uxon-required true
     * 
     * @param string $value
     * @return PrintPreview
     */
    public function setPrintActionSelectorAttribute(string $value) : PrintPreview
    {
        $this->printActionSelectorAttributeAlias = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getPreviewLabelAttributeAlias() : string
    {
        return $this->previewLabelAttributeAlias;
    }
    
    /**
     * Alias or column name in the input data, that LABEL of the data row to use for preview
     * 
     * @uxon-property preview_label_attribute
     * @uxon-type metamodel:attribute|string
     * 
     * @param string $value
     * @return PrintPreview
     */
    public function setPreviewLabelAttribute(string $value) : PrintPreview
    {
        $this->previewLabelAttributeAlias = $value;
        return $this;
    }
}