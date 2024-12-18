<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Behaviors\ChecklistingBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataCheckFailedError;
use exface\Core\Exceptions\DataSheets\DataCheckRuntimeError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Functions just like a regular `DataCheck`, with the option of defining an output datasheet, that
 * can be used for further processing.
 * 
 * @see DataCheck
 * @see ChecklistingBehavior
 */
class DataCheckWithOutputData extends DataCheck
{
    private string $affectedUidAlias = 'AFFECTED_UID';
    private ?UxonObject $outputDataSheetUxon = null;
    private ?DataSheetInterface $outputDataSheet = null;

    public function check(DataSheetInterface $sheet, LogBookInterface $logBook = null): DataSheetInterface
    {
        try {
            parent::check($sheet, $logBook);
        } catch (DataCheckFailedError $error) {
            $logBook?->addIndent(1);
            $logBook?->addLine('Generating output sheet...');
            try {
                $outputSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->outputDataSheetUxon);
            } catch (\Throwable $e) {
                throw new DataCheckRuntimeError(
                    $sheet,
                    'Cannot generate output datasheet: Data check has missing or invalid UXON property "output_data_sheet"!',
                    null,
                    $e,
                    $this,
                    $error->getBadData());
            }
            
            $rowTemplate = (array)$outputSheet->getRow();
            $outputSheet->removeRows();
            $outputSheet->getColumns()->addFromSystemAttributes();
            
            $badData = $error->getBadData();
            
            // No output data, throw an error with an empty sheet.
            if(!$rowTemplate) {
                $logBook?->addLine('Cannot generate output sheet: No row template found.');
                $logBook?->addIndent(-1);
                $this->outputDataSheet = $outputSheet;
                throw $error;
            }
            
            if(!$badData->hasUidColumn()) {
                throw new DataCheckRuntimeError(
                    $sheet,
                    'Cannot generate output data: Input data has no UID column!',
                    null,
                    null,
                    $this,
                    $badData);
            }
            
            if(!$outputSheet->getMetaObject()->hasUidAttribute()) {
                throw new DataCheckRuntimeError(
                    $outputSheet,
                    'Cannot generate output data: The MetaObject ('.$outputSheet->getMetaObject()->getAlias().') used for caching has no UID-Attribute!',
                    null,
                    null,
                    $this,
                    $badData);
            }
            
            foreach ($badData->getUidColumn()->getValues() as $affectedUid) {
                $logBook?->addLine('Adding row for affected item with UID "'.$affectedUid.'".');
                $rowTemplate[$this->affectedUidAlias] = $affectedUid;
                $outputSheet->addRow($rowTemplate);
            }
            
            $logBook?->addLine('Successfully generated output sheet with '.$outputSheet->countRows().' rows!');
            $logBook?->addIndent(-1);
            $this->outputDataSheet = $outputSheet;
            throw $error;
        }
        
        return $sheet;
    }


    /**
     * Define the output data that this data check will append to its error message, if it does apply. For every input row 
     * this check applies to, it adds a new row based on this template to the output sheet.
     * 
     * The associated MetaObject must have a UID-Attribute!
     * 
     * NOTE: Auto-suggest does not work for the left-hand side of the `rows` property. If you want to
     * add a custom column to that row, simply write out the attribute alias of that column on the left and the actual
     * value on the right.
     * 
     * @uxon-property output_data_sheet
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSheet
     * @uxon-template {"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"", "ICON":"sap-icon://message-warning"}]}
     * 
     * @param UxonObject|null $uxon
     * @return $this
     */
    protected function setOutputDataSheet(?UxonObject $uxon) : static
    {
        $this->outputDataSheetUxon = $uxon;
        return $this;
    }

    /**
     * @return UxonObject|null
     */
    public function getOutputDataSheetUxon() : ?UxonObject
    {
        return $this->outputDataSheetUxon;
    }
    
    /**
     * @return DataSheetInterface|null
     */
    public function getOutputDataSheet() : ?DataSheetInterface
    {
        return $this->outputDataSheet;
    }

    /**
     * If this data check applied to a given row, the UID of that row will be output
     * to a column with this alias.
     * 
     * Default is `AFFECTED_UID`.
     * 
     * @uxon-property affected_uid_alias
     * @uxon-type string
     * @uxon-default "AFFECTED_UID"
     * 
     * @param string $alias
     * @return $this
     */
    protected function setAffectedUidAlias(string $alias) : static
    {
        if(!empty($alias)) {
            $this->affectedUidAlias = $alias;
        }
        
        return $this;
    }

    /**
     * @return string
     */
    public function getAffectedUidAlias() : string
    {
        return $this->affectedUidAlias;
    }
}