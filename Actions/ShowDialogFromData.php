<?php
namespace exface\Core\Actions;

use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Actions\ActionInputError;

/**
 * Instantiates and displays a widget from UXON contained in an a metamodel attribute.
 *
 * @author Thomas Walter
 *        
 */
class ShowDialogFromData extends ShowDialog
{
    private $uxonAttributeAlias = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
    }
    
    protected function getUxonAttributeAlias() : string
    {
        return $this->uxonAttributeAlias;
    }
    
    protected function getUxonAttribute() : MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getUxonAttributeAlias());
    }
    
    /**
     * The attribute that contains the UXON to be shown.
     * 
     * @uxon-property uxon_attribute
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return ShowDialogFromData
     */
    public function setUxonAttribute(string $value) : ShowDialogFromData
    {
        $this->uxonAttributeAlias = $value;
        return $this;
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputSheet = $this->getInputDataSheet($task);
        
        if (! $col = $inputSheet->getColumns()->getByAttribute($this->getUxonAttribute())) {
            if ($inputSheet->hasUidColumn(true) || ! $inputSheet->getFilters()->isEmpty(true)) {
                if ($inputSheet->hasUidColumn()) {
                    $inputSheet->getFilters()->addConditionFromColumnValues($inputSheet->getUidColumn());
                }
                $col = $inputSheet->getColumns()->addFromAttribute($this->getUxonAttribute());
                $inputSheet->dataRead();
            } else {
                throw new ActionInputMissingError($this, 'Cannot show widget from UXON data: UXON column "' . $this->getUxonAttributeAlias() . '" not found in input data!');
            }
        }
        
        if ($inputSheet->countRows() !== 1) {
            throw new ActionInputError($this, 'Cannot show widget from UXON data: got ' . $inputSheet->countRows() . ' rows instead of a single one!');
        }
        
        $uxon = UxonObject::fromJson($col->getValue(0));
        
        if ($uxon->isEmpty()) {
            throw new ActionInputMissingError($this, 'Cannot show widget from UXON data: No valid UXON found in data column "' . $this->getUxonAttributeAlias() . '"!');
        }
        
        $dialog = $this->getDialogWidget();
        $dialog->addWidget(WidgetFactory::createFromUxonInParent($dialog, $uxon));
        
        return parent::perform($task, $transaction);
    }
}