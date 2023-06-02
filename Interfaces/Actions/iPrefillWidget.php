<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * Interface for actions that fetch prefill data for widgets.
 * 
 * @triggers \exface\Core\Events\Widget\OnPrefillDataLoadedEvent
 * 
 * @author andrej.kabachnik
 *
 */
interface iPrefillWidget extends ActionInterface
{
    const REFRESH_AUTO = 'auto';
    
    const REFRESH_ALWAYS = 'always';
    
    const REFRESH_NEVER = 'never';
    
    const REFRESH_ONLY_MISSING_VALUES = 'only_missing_values';
    
    /**
     * Returns TRUE, if the input data of the action should be used to prefill the widget shown, or FALSE otherwise
     *
     * @return boolean
     */
    public function getPrefillWithInputData() : bool;
    
    /**
     * Set to TRUE, if the input data of the action should be used to prefill the widget shown, or FALSE otherwise.
     *
     * @param boolean $value
     * @return iShowWidget
     */
    public function setPrefillWithInputData($true_or_false) : iPrefillWidget;
    
    /**
     * Returns FALSE, if the values of the currently registered context filters should be used to attempt to prefill the widget
     *
     * @return boolean
     */
    public function getPrefillWithFilterContext() : bool;
    
    /**
     * If set to TRUE, the values of the filters registered in the window context scope will be used to prefill the widget (if possible)
     *
     * @param boolean $value
     * @return iShowWidget
     */
    public function setPrefillWithFilterContext($true_or_false) : iPrefillWidget;
    
    /**
     * Disables the prefill for this action entirely if TRUE is passed.
     *
     * @return iShowWidget
     */
    public function setPrefillDisabled(bool $value) : iPrefillWidget;
    
    /**
     * 
     * @return DataSheetInterface|NULL
     */
    public function getPrefillDataPreset() : ?DataSheetInterface;
    
    /**
     *
     * @param DataSheetInterface $dataSheet
     * @return iPrefillWidget
     */
    public function setPrefillDataPreset(DataSheetInterface $dataSheet) : iPrefillWidget;
    
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
    public function setPrefillDataSheet(UxonObject $uxon) : iPrefillWidget;
    
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
     * @return iPrefillWidget
     */
    public function setPrefillWithPrefillData($true_or_false) : iPrefillWidget;
    
    /**
     *
     * @param string $value
     * @throws ActionConfigurationError
     * @return iPrefillWidget
     */
    public function setPrefillDataRefresh(string $value) : iPrefillWidget;
    
    /**
     *
     * @return string
     */
    public function getPrefillDataRefresh() : string;
    
    /**
     *
     * @return bool|NULL
     */
    public function getPrefillWithDefaults() : ?bool;
    
    /**
     * Set to TRUE to include default values of widgets in prefill data
     *
     * If not set explicitly, this option will be up to the facade: some will set defaults via
     * prefill, others - when generating the widget.
     *
     * @param bool $value
     * @return iPrefillWidget
     */
    public function setPrefillWithDefaults(bool $value) : iPrefillWidget;
}