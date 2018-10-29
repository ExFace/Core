<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

interface iUsePrefillData extends ActionInterface
{

    /**
     *
     * @return DataSheetInterface
     */
    public function getPrefillDataPreset() : DataSheetInterface;

    /**
     *
     * @param DataSheetInterface $dataSheet            
     * @return iUsePrefillData
     */
    public function setPrefillDataPreset(DataSheetInterface $dataSheet) : iUsePrefillData;
    
    /**
     * 
     * @return bool
     */
    public function hasPrefillDataPreset() : bool;
    
    /**
     * Sets preset prefill data for the action.
     * 
     * Technically the same as setPrefillDataPreset(), but takes a UXON model of
     * a data sheet as input. Additionally this method provides a better
     * understandable UXON property input_data_sheet to use in UXON models.
     * 
     * @param UxonObject $uxon
     * @return ActionInterface
     */
    public function setPrefillDataSheet(UxonObject $uxon) : iUsePrefillData;
    
    /**
     * Returns TRUE if the prefill data should be used (default) or FALSE otherwise
     * 
     * @return boolean
     */
    public function getPrefillWithPrefillData() : bool;
    
    /**
     * If set to FALSE prevents the passed prefill data from being used. TRUE by default.
     * 
     * @param boolean $prefill_with_prefill_data
     * @return iUsePrefillData
     */
    public function setPrefillWithPrefillData($true_or_false) : iUsePrefillData;
    
    /**
     *
     * @return WidgetLinkInterface|null
     */
    public function getPrefillWithDataFromWidgetLink();
    
    /**
     * If a widget link is defined here, the prefill data for this action will
     * be taken from that widget link and not from the input widget.
     *
     * The value can be either a string ([page_alias]widget_id!optional_column_id)
     * or a widget link defined as an object.
     *
     * @param string|UxonObject|WidgetLinkInterface $string_or_widget_link
     * @return \exface\Core\Actions\ShowWidget
     */
    public function setPrefillWithDataFromWidgetLink($string_or_widget_link) : iUsePrefillData;
}