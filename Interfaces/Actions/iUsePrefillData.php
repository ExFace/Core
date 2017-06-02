<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;

interface iUsePrefillData
{

    /**
     *
     * @return DataSheetInterface
     */
    public function getPrefillDataSheet();

    /**
     *
     * @param DataSheetInterface|UxonObject|string $any_data_sheet_source            
     * @return iUsePrefillData
     */
    public function setPrefillDataSheet($any_data_sheet_source);
    
    /**
     * Returns TRUE if the prefill data should be used (default) or FALSE otherwise
     * 
     * @return boolean
     */
    public function getPrefillWithPrefillData();
    
    /**
     * If set to FALSE prevents the passed prefill data from being used. TRUE by default.
     * 
     * @param boolean $prefill_with_prefill_data
     * @return iUsePrefillData
     */
    public function setPrefillWithPrefillData($true_or_false);
}