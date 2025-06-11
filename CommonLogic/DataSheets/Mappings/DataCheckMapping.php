<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\CommonLogic\DataSheets\DataCheck;
use exface\Core\Interfaces\Exceptions\DataCheckExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class DataCheckMapping extends AbstractDataSheetMapping 
{
    private $inputChecks = [];
    private $disabled = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        if ($this->isDisabled()) {
            return $toSheet;
        }
        $checks = $this->getFromDataChecks();
        if (! empty ($checks) && $logbook !== null) {
            $logbook->addLine('Checking input data:');
        }
        foreach ($this->getFromDataChecks() as $check) {
            try {
                if ($logbook !== null) $logbook->addLine('Checking `' . $check->getConditionGroup()->__toString() . '`', +1);
                $check->check($fromSheet);
            } catch (DataCheckExceptionInterface $e) {
                if ($logbook !== null) $logbook->addLine('**Check failed:** ' . $e->getMessage() . ' (condition `' . $check->getConditionGroup()->__toString() . '` was `true`)', -1);
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, $e->getMessage(), null, $e, $logbook);
            }
        }
        return $toSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        return [];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getFromDataChecks()
     */
    public function getFromDataChecks() : array
    {
        return $this->inputChecks;
    }
    
    /**
     * Check from-data against these conditions before applying the mapper
     *
     * If any of these conditions are not met, the mapper will through an error. Each check may
     * contain it's own error message to make the errors better understandable for the user.
     *
     * @uxon-property from_data_invalid_if
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     *
     * @param UxonObject $arrayOfDataChecks
     * @return DataCheckMapping
     */
    protected function setFromDataInvalidIf(UxonObject $arrayOfDataChecks) : DataCheckMapping
    {
        $this->inputChecks = [];
        foreach($arrayOfDataChecks as $uxon) {
            $this->inputChecks[] = new DataCheck($this->getWorkbench(), $uxon, $this->getMapper()->getFromMetaObject());
        }
        return $this;
    }

    /**
     * Disable the checks completely.
     * 
     * This is used for exampke in PrefillModel::emulatePrefill() to bypass the checks intitionally.
     * 
     * @param bool $value
     * @return DataCheckMapping
     */
    public function disable(bool $value) : DataCheckMapping
    {
        $this->disabled = $value;
        return $this;
    }

    protected function isDisabled() : bool
    {
        return $this->disabled;
    }       
}