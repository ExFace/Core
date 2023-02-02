<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Actions\GoBack;
use exface\Core\Widgets\Button;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Actions\GoToPage;
use exface\Core\Actions\RefreshWidget;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Actions\iRunFacadeScript;
use exface\Core\Actions\SendToWidget;
use exface\Core\Actions\ResetWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\Actions\iCallWidgetFunction;
use exface\Core\Interfaces\Actions\iCallOtherActions;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Interfaces\Actions\iShowUrl;
use exface\Core\Interfaces\Actions\iShowDialog;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Actions\CallAction;

/**
 * 
 * @method Button getWidget()
 * @method AbstractAjaxFacade getFacade()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryButtonTrait {
    
    use JsConditionalPropertyTrait;
    
    use JqueryDisableConditionTrait;
    
    private $onSuccessJs = [];
    
    private $onErrorJs = [];
    
    /**
     * Returns the JS code to refresh all neccessary widgets after the button's action succeeds.
     * 
     * @param Button $widget
     * @return string
     */
    protected function buildJsRefreshWidgets() : string
    {
        $js = '';
        $widget = $this->getWidget();
        $page = $widget->getPage();
        $idSpace = $widget->getIdSpace();
        foreach ($widget->getRefreshWidgetIds() as $widgetId) {
            $idSpaceProvided = StringDataType::substringBefore($widgetId, UiPage::WIDGET_ID_SPACE_SEPARATOR, '', false, true);
            if ($idSpaceProvided === '' && $idSpace !== null && $idSpace !== '') {
                $widgetId = $idSpace . UiPage::WIDGET_ID_SPACE_SEPARATOR . $widgetId;
            }
            $refreshEl = $this->getFacade()->getElementByWidgetId($widgetId, $page);
            $js .=  $refreshEl->buildJsRefresh(true) . ";\n";
        }
        return $js;
    }
    
    /**
     * Returns the JS code to reset all neccessary widgets after the button's action succeeds.
     *
     * @param Button $widget
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsResetWidgets() : string
    {
        $js = '';
        $btn = $this->getWidget();
        $page = $btn->getPage();
        $idSpace = $btn->getIdSpace();
        foreach ($btn->getResetWidgetIds() as $id) {
            $idSpaceProvided = StringDataType::substringBefore($id, UiPage::WIDGET_ID_SPACE_SEPARATOR, '', false, true);
            if ($idSpaceProvided === '' && $idSpace !== null && $idSpace !== '') {
                $id = $idSpace . UiPage::WIDGET_ID_SPACE_SEPARATOR . $id;
            }
            $resetElem = $this->getFacade()->getElementByWidgetId($id, $page);
            $js .= $resetElem->buildJsResetter() . ";\n";
        }
        return $js;
    }

    /**
     * 
     * @return string
     */
    public function buildJsClickFunctionName()
    {
        return $this->buildJsFunctionPrefix() . 'click';
    }

    /**
     * Returns a javascript snippet, that replaces all placholders in a give string by values from a given javascript object.
     * Placeholders must be in the general ExFace syntax [#placholder#], while the value object must have a property for every
     * placeholder with the same name (without "[#" and "#]"!).
     *
     * @param string $js_var
     *            - e.g. result (the variable must be already instantiated!)
     * @param string $js_values_array
     *            - e.g. values = {placeholder = "someId"}
     * @param string $string_with_placeholders
     *            - e.g. http://localhost/pages/[#placeholder#]
     * @param string $js_sanitizer_function
     *            - a Javascript function to be applied to each value (e.g. encodeURIComponent) - without parentheses!!!
     * @return string - e.g. result = result.replace('[#placeholder#]', values['placeholder']);
     */
    protected function buildJsPlaceholderReplacer($js_var, $js_values_object, $string_with_placeholders, $js_sanitizer_function = null)
    {
        $output = '';
        $placeholders = StringDataType::findPlaceholders($string_with_placeholders);
        foreach ($placeholders as $ph) {
            $value = $js_values_object . "['" . $ph . "']";
            if ($js_sanitizer_function) {
                $value = $js_sanitizer_function . '(' . $value . ')';
            }
            $output .= $js_var . " = " . $js_var . ".replace('[#" . $ph . "#]', " . $value . ");";
        }
        return $output;
    }

    /**
     * Produces the JS variable named by $jsVariable parameter containing the input data for the action
     * 
     * @param ActionInterface $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsRequestDataCollector(ActionInterface $action, AbstractJqueryElement $input_element, string $jsVariable = 'requestData') : string
    {
        $min = $action->getInputRowsMin();
        $max = $action->getInputRowsMax();
        
        // If the action has built-in input data, let the server handle the checks.
        if ($action->hasInputDataPreset() && ! $action->getInputDataPreset()->isEmpty()) {
            $min = null;
            $max = null;
        }
        
        if ($min !== null || $max !== null) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            if ($min === $max) {
                $js_check_input_rows = "if ({$jsVariable}.rows.length < " . $min . " || {$jsVariable}.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_EXACTLY_X_ROWS", array(
                    '%number%' => $max
                ), $max) . '"') . " return false;}";
            } elseif (is_null($max)) {
                $js_check_input_rows = "if ({$jsVariable}.rows.length < " . $min . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_LEAST_X_ROWS", array(
                    '%number%' => $min
                ), $min) . '"') . " return false;}";
            } elseif (is_null($min)) {
                $js_check_input_rows = "if ({$jsVariable}.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_MOST_X_ROWS", array(
                    '%number%' => $max
                ), $max) . '"') . " return false;}";
            } else {
                $js_check_input_rows = "if ({$jsVariable}.rows.length < " . $min . " || {$jsVariable}.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_X_TO_Y_ROWS", array(
                    '%min%' => $min,
                    '%max%' => $max
                )) . '"') . " return false;}";
            }
            $js_check_input_rows = "if ({$jsVariable}.rows){ {$js_check_input_rows} }";
        } else {
            $js_check_input_rows = '';
        }
        
        if (($conditionalProperty = $this->getWidget()->getDisabledIf()) !== null) {
            $js_check_button_state = <<<JS
            
                    if ({$this->buildJsConditionalPropertyIf($conditionalProperty->getConditionGroup())}) {
                        return false;
                    }

JS;
        } else {
            $js_check_button_state = $this->getWidget()->isDisabled() === true ? 'return false;' : '';
        }
        
        if ($customData = $this->getWidget()->getInputData()) {
            $customDataRows = '';
            foreach ($customData->getRows() as $row) {
                $jsRow = '';
                foreach ($row as $colName => $val) {
                    $val = trim($val);
                    if (substr($val, 0, 1) === '=') {
                        $expr = ExpressionFactory::createForObject($customData->getMetaObject(), $val);
                        switch (true) {
                            case $expr->isReference():
                                $jsRow .= $colName . ': ' . $this->getFacade()->getElement($expr->getWidgetLink($this->getWidget())->getTargetWidget())->buildJsValueGetter() . ',';
                                break;
                            case $expr->isConstant():
                                $jsRow .= $colName . ': ' . $expr->__toString() . ',';
                                break;
                            case $expr->isStatic():
                                $jsRow .= $colName . ': "' . $expr->evaluate() . '",';
                                break;
                            default:
                                throw new WidgetConfigurationError($this, 'Invalid row value "' . $val . '" in input_data of ' . $this->getWidget()->getWidgetType());
                        }
                    } else {
                        
                    }
                }
                $customDataRows .= '{' . $jsRow . '},';
            }
            $js_get_data = <<<JS
{
    "oId": "{$customData->getMetaObject()->getId()}",
    "rows": [
        {$customDataRows}
    ]
}

JS;
        } else {
            $js_get_data = $input_element->buildJsDataGetter($action);
        }
        
        return <<<JS

                    $js_check_button_state
					{$jsVariable} = {$js_get_data};
					$js_check_input_rows

JS;
    }

    /**
     *
     * @return ActionInterface
     */
    protected function getAction()
    {
        return $this->getWidget()->getAction();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::getWidget()
     * @return Button
     */
    public function getWidget()
    {
        return parent::getWidget();
    }

    /**
     * Returns the body of the on-click function for the button.
     * 
     * @return string
     */
    public function buildJsClickFunction(ActionInterface $action = null, string $jsRequestData = null)
    {
        $widget = $this->getWidget();
        $input_element = $this->getInputElement();
        $action = $action ?? $widget->getAction();
        
        if ($action && $jsRequestData === null) {
            $jsRequestData = 'requestData';
            $jsRequestDataCollector = "var {$jsRequestData}; \n" . $this->buildJsRequestDataCollector($action, $input_element, $jsRequestData);
        }
        
        switch (true) {
            case ! $action:
                return $this->buildJsClickNoAction();
            case $action instanceof iCallOtherActions:
                if ($action instanceof CallAction) {
                    return $jsRequestDataCollector . $this->buildJsClickDynamicAction($action, $jsRequestData);
                } else {
                    return $jsRequestDataCollector . $this->buildJsClickActionChain($action, $jsRequestData);
                }
            case $action instanceof RefreshWidget:
                return $this->buildJsClickRefreshWidget($action);
            case $action instanceof iRunFacadeScript:
                return $this->buildJsClickRunFacadeScript($action);
            case $action instanceof iShowDialog:
                return $jsRequestDataCollector . $this->buildJsClickShowDialog($action, $jsRequestData);
            case $action instanceof iShowUrl:
                return $jsRequestDataCollector . $this->buildJsClickShowUrl($action, $jsRequestData);
            case $action instanceof iShowWidget:
                return $jsRequestDataCollector . $this->buildJsClickShowWidget($action, $jsRequestData);
            case $action instanceof GoBack:
                return $this->buildJsClickGoBack($action);
            case $action instanceof SendToWidget:
                return $jsRequestDataCollector . $this->buildJsClickSendToWidget($action, $jsRequestData);
            case $action instanceof ResetWidget:
                return $this->buildJsResetWidgets();
            case $action instanceof iCallWidgetFunction:
                return $this->buildJsClickCallWidgetFunction($action);
            default: 
                return $jsRequestDataCollector . $this->buildJsClickCallServerAction($action, $jsRequestData);
        }
    }
    
    /**
     * 
     * @param CallAction $action
     * @param string $jsRequestData
     * @return string
     */
    protected function buildJsClickDynamicAction(CallAction $action, string $jsRequestData) : string
    {
        $js = '';
        if ($action->hasActionsConditions()) {
            foreach ($action->getActions() as $i => $potentialAction) {
                $conditionalProp = $action->getActionsConditions()[$i];
                $js .= $this->buildJsConditionalProperty($conditionalProp, $this->buildJsClickFunction($potentialAction, $jsRequestData), '');
            }
        } else {
            $js = $this->buildJsClickCallServerAction($action, $jsRequestData);
        }
        return $js;
    }
    
    /**
     *
     * @param ActionInterface $action
     * @return bool
     */
    protected function isActionFrontendOnly(ActionInterface $action) : bool
    {
        switch (true) {
            case $action instanceof RefreshWidget:
            case $action instanceof iRunFacadeScript:
            case $action instanceof iShowUrl:
            case $action instanceof GoBack:
            case $action instanceof SendToWidget:
            case $action instanceof ResetWidget:
            case $action instanceof iCallWidgetFunction:
                return true;
        }
        return false;
    }
    
    /**
     * 
     * @param iCallOtherActions $action
     * @param AjaxFacadeElementInterface $input_element
     * @throws FacadeRuntimeError
     * @return string
     */
    protected function buildJsClickActionChain(iCallOtherActions $action, string $jsRequestData) : string
    {
        $firstServerActionIdx = null;
        $lastServerActionIdx = null;
        $steps = $action->getActions();
        $lastActionIdx = count($steps) - 1;
        foreach ($steps as $i => $step) {
            if (! $this->isActionFrontendOnly($step)) {
                if ($firstServerActionIdx !== null && $lastServerActionIdx !== $i-1) {
                    throw new FacadeRuntimeError('Cannot render action "' . $action->getName() . '" (' . $action->getAliasWithNamespace() . '): cannot mix front- and back-end actions!');
                }
                if ($firstServerActionIdx === null) {
                    $firstServerActionIdx = $i;
                }
                $lastServerActionIdx = $i;
            }
        }
        
        if ($firstServerActionIdx === 0 && $lastServerActionIdx === $lastActionIdx) {
            return $this->buildJsClickCallServerAction($action, $jsRequestData);
        }
        
        $js = '';
        for ($i = 0; $i < $firstServerActionIdx; $i++) {
            $js .= $this->buildJsClickFunction($steps[$i], $jsRequestData) . "\n\n";
        }
        $onSuccess = '';
        for ($i = ($lastServerActionIdx + 1); $i <= $lastActionIdx; $i++) {
            $onSuccess .= $this->buildJsClickFunction($steps[$i], $jsRequestData) . "\n\n";
        }
        
        if ($firstServerActionIdx !== $lastServerActionIdx) {
            throw new FacadeRuntimeError('Cannot render action "' . $action->getName() . '" (' . $action->getAliasWithNamespace() . '): action chains with mixed front- and back-end actions can only contain a single back-end action!');
        }
        
        $serverAction = $steps[$firstServerActionIdx];
        if ($serverAction instanceof iShowWidget) {
            throw new FacadeRuntimeError('Cannot use actions that render widgets in mixed action chains!');
        }
        
        $js .= $this->buildJsClickCallServerAction($action, $jsRequestData, $onSuccess);
        
        return $js;
    }
    
    /**
     * Returns the JS code triggered by a button without an action.
     * 
     * @return string
     */
    protected function buildJsClickNoAction() : string
    {
        // Can't use buildJsActionEffects() here sind we don't have an action, so we need to call
        // all required code generator manually.
        return $this->buildJsCloseDialog()
        . $this->buildJsRefreshWidgets()
        . $this->buildJsResetWidgets();
    }
    
    /**
     * Returns the JS code to trigger things to run on action success
     * 
     * 1. Reset the input if needed (before refreshing!!!)
     * 2. Fire the JS even `actionperformed`
     * 3. Run custom on-success scripts added in PHP via `addOnSuccessScript()`
     * 
     * The event `actionperformed` has an additional parameter with the following structure:
     * 
     * ```
     * {
     *  trigger_widget_id: "DataTable_DataToolbar_ButtonGroup_DataButton", // Id of the widget (e.g. button) that triggered the action
     *  action_alias: "exface.Core.SaveData", // Namespaced alias of the action performed
     *  effects: [
     *      {
     *          "name": "Save",
     *          "effected_object": "exface.Core.ATTRIBUTE",
     *      }   
     *  ], 
     *  refresh_widgets: [],
     *  refresh_not_widgets: [],
     * }
     * 
     * ```
     * 
     * @param ActionInterface $action
     * @return string
     */
    public function buildJsTriggerActionEffects(ActionInterface $action) : string
    {
        $effects = $action->getEffects();
        $widget = $this->getWidget();
        
        $effectsJs = '';
        foreach ($effects as $effect) {
            $effectUxon = $effect->exportUxonObject();
            // Make sure the `effected_object` property is set in all cases - even if it was not
            // set in the original UXON or not required by it (e.g. implied by relation path, etc.)
            $effectUxon->setProperty('effected_object', $effect->getEffectedObject()->getAliasWithNamespace());
            $effectsJs .= $effectUxon->toJson() . ',';
        }
        
        $refreshIds = '';
        $refreshNotIds = $widget->getRefreshInput() === false ? '"' . $widget->getId() . '"' : '';
        foreach ($widget->getRefreshWidgetIds(false) as $refreshId) {
            $refreshIds .= '"' . $refreshId . '", ';
        }
        
        $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;
        return <<<JS

                {$this->buildJsResetWidgets()}
                
                $(document).trigger("$actionperformed", [{
                    trigger_widget_id: "{$this->getId()}",
                    action_alias: "{$action->getAliasWithNamespace()}",
                    effects: [ $effectsJs ],
                    refresh_widgets: [ $refreshIds ],
                    refresh_not_widgets: [ $refreshNotIds ],
                }]);
                
                {$this->buildJsOnSuccessScript()}
JS;
    }
    
    /**
     * 
     * @param WidgetInterface $trigger
     * @param ActionInterface $action
     * @return string
     */
    protected function buildJsRequestCommonParams(WidgetInterface $trigger, ActionInterface $action) : string
    {
        if ($trigger->getPage()->hasModel()) {
            $triggerProperties = <<<JS
                                    resource: '{$trigger->getPage()->getAliasWithNamespace()}',
									element: '{$trigger->getId()}',
									
JS;
        } else {
            $triggerProperties = '';
        }
        return <<<JS

                                    action: '{$action->getAliasWithNamespace()}',
									object: '{$trigger->getMetaObject()->getId()}',
                                    {$triggerProperties}
JS;
    }

    /**
     * 
     * @param ActionInterface $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickCallServerAction(ActionInterface $action, string $jsRequestData, string $jsOnSuccess = '') : string
    {
        $widget = $this->getWidget();
        
        $headers = ! empty($this->getAjaxHeaders()) ? 'headers: ' . json_encode($this->getAjaxHeaders()) . ',' : '';
        
        $output .= "
						if (" . $this->getInputElement()->buildJsValidator() . ") {
							" . $this->buildJsBusyIconShow() . "
							$.ajax({
								type: 'POST',
								url: '" . $this->getAjaxUrl() . "',
                                {$headers} 
								data: {	
									{$this->buildJsRequestCommonParams($widget, $action)}
									data: {$jsRequestData}
								},
								success: function(data, textStatus, jqXHR) {
                                    if (typeof data === 'object') {
                                        response = data;
                                    } else {
                                        var response = {};
    									try {
    										response = $.parseJSON(data);
    									} catch (e) {
    										response.error = data;
    									}
                                    }
				                   	if (response.success !== undefined){
										{$this->buildJsCloseDialog()}
				                       	{$this->buildJsBusyIconHide()}
				                       	{$this->buildJsTriggerActionEffects($action)}
										if (response.success !== undefined || response.undoURL){
				                       		{$this->buildJsShowMessageSuccess("response.success + (response.undoable ? ' <a href=\"" . $this->buildJsUndoUrl($action) . "\" style=\"display:block; float:right;\">UNDO</a>' : '')")}
											if(response.redirect !== undefined){
                                                switch (true) {
												    case response.redirect.indexOf('target=_blank') !== -1:
													    window.open(response.redirect.replace('target=_blank',''), '_newtab');
                                                        break;
                                                    case response.redirect === '':
                                                        {$this->getFacade()->getElement($widget->getPage()->getWidgetRoot())->buildJsBusyIconShow()}
                                                        window.location.reload();
                                                        break;
                                                    default: 
                                                        {$this->getFacade()->getElement($widget->getPage()->getWidgetRoot())->buildJsBusyIconShow()}
                                                        window.location.href = response.redirect;
												}
	                       					}
                                            if(response.download){
                                                // Workaround to force the browser to download even if it is a text file!
                                                var a = document.createElement('A');
                                                a.href = response.download;
                                                a.download = response.download.substr(response.download.lastIndexOf('/') + 1);
                                                document.body.appendChild(a);
                                                a.click();
                                                document.body.removeChild(a);
	                       					}
                                            {$jsOnSuccess}
										}
				                    } else {
										{$this->buildJsBusyIconHide()}
                                        {$this->buildJsOnErrorScript()}
										{$this->buildJsShowMessageError('response.error', '"Server error"')}
				                    }
								},
								error: function(jqXHR, textStatus, errorThrown){ 
									{$this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText')} 
									{$this->buildJsBusyIconHide()}
                                    {$this->buildJsOnErrorScript()}
								}
							});
						} else {
							" . $this->getInputElement()->buildJsValidationError() . "
						}
					";
        
        return $output;
    }

    protected function buildJsClickShowWidget(iShowWidget $action, string $jsRequestData) : string
    {
        $widget = $this->getWidget();
        $output = '';
        $prefill_param = '';
        $filters_param = '';
        if (! $widget->getPage()->is($action->getPageAlias())) {
            // Can't have input mappers because the server will not be able to get the mapper
            // as it won't know the caller widget. The widget to show will be attached to the
            // request instead.
            // IDEA pass the caller widget as parameter of the request?
            if ($action->hasInputMappers()) {
                throw new ActionConfigurationError($action, 'Input mappers not supported in navigating actions as "' . $action->getAliasWithNamespace() . '"!');
            }
            
            if ($action->getPrefillWithPrefillData()) {
                if ($prefillPreset = $action->getPrefillDataPreset()) {
                    $prefill_param = '&prefill=\' + JSON.stringify(' . $this->buildJsPrefillDataFromPreset($prefillPreset) . ') + \'';
                } elseif ($widget->getMetaObject()->hasUidAttribute()) {
                    $output .= <<<JS

			var prefillRows = [];
			if ($jsRequestData.rows && $jsRequestData.rows.length > 0 && $jsRequestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]){
				prefillRows.push({{$widget->getMetaObject()->getUidAttributeAlias()}: $jsRequestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]});
			}

JS;
                    $prefill_param = '&prefill={"meta_object_id":"'.$widget->getMetaObject()->getId().'","rows": \' + JSON.stringify(prefillRows) + \'}';
                }
            } 
            
            if ($action instanceof GoToPage){
                /* @var $widgetLink \exface\Core\CommonLogic\WidgetLink */
                $prefix = $this->getFacade()->getUrlFilterPrefix();
                foreach ($action->getTakeAlongFilters() as $attributeAlias => $widgetLink){
                    $filters_param .= "&{$prefix}{$attributeAlias}='+{$this->getFacade()->getElement($widgetLink->getTargetWidget())->buildJsValueGetter($widgetLink->getTargetColumnId(), null)}+'";
                }
                $newWindow = $action->getOpenInNewWindow();
            } else {
                $newWindow = false;
            }
            
            $output .= <<<JS

            {$this->getInputElement()->buildJsBusyIconShow()}
			{$this->buildJsNavigateToPage($action->getPageAlias(), $prefill_param . $filters_param, $this->getInputElement(), $newWindow)}

JS;
        }
        return $output;
    }
    
    /**
     * 
     * @param DataSheetInterface $presetSheet
     * @throws WidgetConfigurationError
     * @return string
     */
    protected function buildJsPrefillDataFromPreset(DataSheetInterface $presetSheet) : string
    {
        if ($presetSheet->getColumns()->isEmpty()) {
            throw new WidgetConfigurationError($this->getWidget(), 'Cannot use empty data sheet as action `prefill_data_sheet`!');
        }
        foreach ($presetSheet->getColumns() as $col) {
            if (! $formula = $col->getFormula()) {
                throw new WidgetConfigurationError($this->getWidget(), 'Only columns with `formula` property currently supported in manually defined prefill data!');
            }
            if (! $col->getAttributeAlias()) {
                throw new WidgetConfigurationError($this->getWidget(), 'Missing `attribute_alias` in manually created prefill data column!');
            }
            if ($formula->isReference()) {
                $link = $formula->getWidgetLink($this->getWidget());
                $linkedEl = $this->getFacade()->getElement($link->getTargetWidget());
                $colsJs .= $col->getAttributeAlias() . ': ' . $linkedEl->buildJsValueGetter($link->getTargetColumnId()) . ',';
            } else {
                throw new WidgetConfigurationError($this->getWidget(), 'Only columns with widget links as `formula` currently supported in manually defined prefill data!');
            }
        }
        
        return '{oId: "' . $presetSheet->getMetaObject()->getId() . '", rows: [{' . $colsJs . '}]}';
    }
    
    /**
     * Generates the JS code to navigate to another UI page - eventually opening a new browser tab
     * 
     * @param string $pageSelector
     * @param string $urlParams
     * 
     * @return string
     */
    protected function buildJsNavigateToPage(string $pageSelector, string $urlParams = '', AbstractJqueryElement $input_element, bool $newWindow = false) : string
    {
        $url = "{$this->getFacade()->buildUrlToPage($pageSelector)}?{$urlParams}";
        if ($newWindow === true) {
            $js = "window.open('{$url}');" . $this->buildJsShowMessageSuccess(json_encode($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.NAVIGATE.OPENING_NEW_WINDOW')));
        } else {
            $js = "window.location.href = '{$url}';";
        }
        return $this->buildJsBusyIconHide() . ';' . $js;
    }

    /**
     * Returns the JS code to call the browsers back-navigation.
     * 
     * @param ActionInterface $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickGoBack(ActionInterface $action) : string
    {
        return $this->getInputElement()->buildJsBusyIconShow() . 'parent.history.back(); return false;';
    }

    /**
     * Returns the JS code to navigate to the actions URL - eventually opening a new browser tab.
     * 
     * @param ActionInterface $action
     * @param string $jsRequestData
     * @return string
     */
    protected function buildJsClickShowUrl(ActionInterface $action, string $jsRequestData) : string
    {
        $input_element = $this->getInputElement();
        /* @var $action \exface\Core\Interfaces\Actions\iShowUrl */
        $output = $this->buildJsRequestDataCollector($action, $input_element) . "
					var " . $action->getAlias() . "Url='" . $action->getUrl() . "';
					" . $this->buildJsPlaceholderReplacer($action->getAlias() . "Url", "{$jsRequestData}.rows[0]", $action->getUrl(), ($action->getUrlencodePlaceholders() ? 'encodeURIComponent' : null));
        if ($action->getOpenInNewWindow()) {
            $output .= $input_element->buildJsBusyIconShow() . "window.open(" . $action->getAlias() . "Url);" . $input_element->buildJsBusyIconHide();
        } else {
            $output .= $input_element->buildJsBusyIconShow() . "window.location.href = " . $action->getAlias() . "Url;";
        }
        return $output;
    }

    /**
     * Returns the JS code to run the javascript stored inside the action
     * 
     * @param ActionInterface $action
     * @return string
     */
    protected function buildJsClickRunFacadeScript(ActionInterface $action) : string
    {
        $inputEl = $this->getInputElement();
        $facade = $this->getFacade();
        $script = $action->buildScript($facade, $inputEl->getWidget());
        $phs = StringDataType::findPlaceholders($script);
        $phVals = [];
        foreach ($phs as $ph) {
            switch (true) {
                case $ph === 'widget_id': $phVals[$ph] = $inputEl->getId(); break;
                case StringDataType::startsWith($ph, 'element_id:', false):
                    $widgetId = StringDataType::substringAfter($ph, 'element_id:', $ph);
                    switch ($widgetId) {
                        case '~input':
                            $phVals[$ph] = $inputEl->getId();
                            break;
                        case '~self':
                            $phVals[$ph] = $this->getId();
                            break;
                        case '~parent':
                            $phVals[$ph] = $facade->getElement($this->getWidget()->getParent())->getId();
                            break;
                        default: 
                            $phVals[$ph] = $facade->getElement($this->getWidget()->getPage()->getWidget($widgetId))->getId();
                    }
            }
        }
        if (! empty($phVals)) {
            $script = StringDataType::replacePlaceholders($script, $phVals);
        }
        
        return <<<JS
        
                {$script};
                {$this->buildJsTriggerActionEffects($action)};
                {$this->buildJsCloseDialog()};

JS;

    }
    
    /**
     * Returns the JS code to refresh the input widget of the action
     * 
     * @param ActionInterface $action
     * @return string
     */
    protected function buildJsClickRefreshWidget(ActionInterface $action) : string
    {
        return <<<JS

                {$this->getInputElement()->buildJsRefresh()};
                {$this->buildJsTriggerActionEffects($action)};
                {$this->buildJsCloseDialog()};
        
JS;
    }

    protected function buildJsUndoUrl(ActionInterface $action) : string
    {
        $widget = $this->getWidget();
        $undo_url = '';
        if ($action->isUndoable()) {
            $undo_url = $this->getAjaxUrl() . "&action=exface.Core.UndoAction&resource=" . $widget->getPage()->getAliasWithNamespace() . "&element=" . $widget->getId();
        }
        return $undo_url;
    }
    
    /**
     * Return the facade element corresponding to the input widget of this button
     */
    public function getInputElement()
    {
        return $this->getFacade()->getElement($this->getWidget()->getInputWidget());
    }
    
    /**
     * 
     * @param string $js
     * @return AbstractJqueryElement
     */
    public function addOnSuccessScript(string $js) : AbstractJqueryElement
    {
        $this->onSuccessJs[] = $js;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsOnSuccessScript() : string
    {
        return implode("\n\n", array_unique($this->onSuccessJs));
    }
    
    /**
     *
     * @param string $js
     * @return AbstractJqueryElement
     */
    public function addOnErrorScript(string $js) : AbstractJqueryElement
    {
        $this->onErrorJs[] = $js;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsOnErrorScript() : string
    {
        return implode("\n\n", array_unique($this->onErrorJs));
    }
    
    /**
     * Returns an array of head tags required for iRunCustomTemplateScript actions, that yield JavaScript.
     * 
     * @return string[]
     */
    protected function buildHtmlHeadTagsForCustomScriptIncludes() : array
    {
        $tags = [];
        if ($action = $this->getAction()) {
            // Actions with facade scripts may contain some helper functions or global variables.
            // Print the here first.
            if ($action instanceof iRunFacadeScript) {
                if (mb_strtolower($action->getScriptLanguage()) === 'javascript' ) {
                    foreach ($action->getIncludes($this->getFacade()) as $path) {
                        if (StringDataType::startsWith($path, '<')) {
                            $tags[] = $path;
                        } else {
                            $tags[] = '<script src="' . $path . '"></script>';
                        }
                    }
                }
            }
        }
        return $tags;
    }
    
    /**
     * Passes the result of the data-getter of the input widget to the data-setter of the target widget
     * 
     * @param SendToWidget $action
     * @param string $jsRequestData
     * 
     * @return string
     */
    protected function buildJsClickSendToWidget(SendToWidget $action, string $jsRequestData) : string
    {
        $targetElement = $this->getFacade()->getElementByWidgetId($action->getTargetWidgetId(), $this->getWidget()->getPage());
        
        return <<<JS

                        if ({$this->getInputElement()->buildJsValidator()}) {
                            {$targetElement->buildJsDataSetter($jsRequestData)}
                            {$this->buildJsTriggerActionEffects($action)}
                            {$this->buildJsCloseDialog()}
                        }

JS;
    }
    
    /**
     * 
     * @param iCallWidgetFunction $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickCallWidgetFunction(iCallWidgetFunction $action) : string
    {
        $targetEl = $this->getFacade()->getElement($action->getWidget($this->getWidget()->getPage()));
        
        //add the onErrorScripts of the calling Button to the error scripts of the Button to be pressed
        if ($action->getFunctionName() === Button::FUNCTION_PRESS && method_exists($targetEl, 'addOnErrorScript')) {
            $targetEl->addOnErrorScript($this->buildJsOnErrorScript());
        }
        return <<<JS

            {$targetEl->buildJsCallFunction($action->getFunctionName())}
            {$this->buildJsTriggerActionEffects($action)}
            {$this->buildJsCloseDialog()}
JS;
    }
    
    /**
     * If it's a `DialogButton` returns the JS code to close the dialog after the action succeeds.
     * 
     * @return string
     */
    abstract protected function buildJsCloseDialog() : string;
    
    /**
     * 
     * @see AbstractJqueryElement::buildJsCallFunction()
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = []) : string
    {
        switch (true) {
            case $functionName === null:
            case $functionName === Button::FUNCTION_PRESS:
                return $this->buildJsClickFunctionName() . '()';
            case $functionName === Button::FUNCTION_FOCUS:
                return "$('#{$this->getId()}').focus()";
        }
        return parent::buildJsCallFunction($functionName, $parameters);
    }
}