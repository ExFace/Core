<?php
namespace exface\Core\Actions;

use exface\Core\Calculations\AbstractCalculation;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;

/**
 * Performs calculations on the input data 
 * 
 * Calculations can be defined in different ways:
 * 
 * - Use `input_mapper` or `output_mapper`
 * - TODO
 * 
 * @author Andrej Kabachnik
 *
 */
class CalculateData extends AbstractAction implements iReadData
{
    private ?UxonObject $calculationUxon = null;
    private ?AbstractCalculation $calculation = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data_sheet = $this->getInputDataSheet($task);
        
        $calculation = $this->getCalculation();
        if($calculation !== null) {
            $data_sheet = $calculation->perform($data_sheet, $this->getLogBook($task));
        }
        
        $result = ResultFactory::createDataResult($task, $data_sheet);
        if (null !== $message = $this->getResultMessageText()) {
            $message =  str_replace('%number%', $data_sheet->countRows(), $message);
        } else {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CALCULATEDATA.RESULT', ['%number%' => $data_sheet->countRows()], $data_sheet->countRows());
        }
        $result->setMessage($message);
        
        return $result;
    }

    public function getCalculation(): ?AbstractCalculation
    {
        if($this->calculation === null && $this->calculationUxon !== null) {
            $this->calculation = AbstractCalculation::fromUxon($this->getWorkbench(), $this->calculationUxon);
        }
        
        return $this->calculation;
    }


    /**
     * @uxon-property calculation
     * @uxon-type \exface\core\Calculations\Prototypes\ForEachCalculation
     * @uxon-template {"name":"","alias":"","instructions":[{"output_attribute_alias":"","expression":""}],"variable_definitions":{"variables":{"":""},"source_sheet":{"object_alias":"","columns":[{"attribute_alias":""}]}},"subject_data":{"object_alias":"geb.testing.testing_geb","columns":[{"attribute_alias":""}],"filters":{"operator":"AND","conditions":[{"expression":"","comparator":"=","value":""}]}}}
     *
     * @param UxonObject|null $calculationUxon
     * @return $this
     */
    public function setCalculation(?UxonObject $calculationUxon): CalculateData
    {
        $this->calculationUxon = $calculationUxon;
        $this->calculation = null;
        return $this;
    }


}