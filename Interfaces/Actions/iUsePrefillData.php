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
}