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

/**
 * 
 * @method Button getWidget()
 * @method AbstractAjaxFacade getFacade()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryButtonTrait {
    
    use JqueryDisableConditionTrait;
    
    private $onSuccessJs = [];
    
    /**
     * Returns the JS code to refresh all neccessary widgets after the button's action succeeds.
     * 
     * @param Button $widget
     * @return string
     */
    protected function buildJsRefreshWidgets(Button $widget) : string
    {
        $js = '';
        foreach ($widget->getRefreshWidgetIds() as $widgetId) {
            $refreshEl = $this->getFacade()->getElementByWidgetId($widgetId, $widget->getPage());
            $js .=  $refreshEl->buildJsRefresh(true) . "\n";
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
    protected function buildJsResetWidgets(Button $widget) : string
    {
        $js = '';
        foreach ($widget->getResetWidgetIds() as $id) {
            $resetElem = $this->getFacade()->getElementByWidgetId($id, $widget->getPage());
            $js .= $resetElem->buildJsResetter() . "\n";
        }
        return $js;
    }

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
     * Produces the JS variable `requestData` containing the input data for the action
     * 
     * @param ActionInterface $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsRequestDataCollector(ActionInterface $action, AbstractJqueryElement $input_element)
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
                $js_check_input_rows = "if (requestData.rows.length < " . $min . " || requestData.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_EXACTLY_X_ROWS", array(
                    '%number%' => $max
                ), $max) . '"') . " return false;}";
            } elseif (is_null($max)) {
                $js_check_input_rows = "if (requestData.rows.length < " . $min . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_LEAST_X_ROWS", array(
                    '%number%' => $min
                ), $min) . '"') . " return false;}";
            } elseif (is_null($min)) {
                $js_check_input_rows = "if (requestData.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_MOST_X_ROWS", array(
                    '%number%' => $max
                ), $max) . '"') . " return false;}";
            } else {
                $js_check_input_rows = "if (requestData.rows.length < " . $min . " || requestData.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_X_TO_Y_ROWS", array(
                    '%min%' => $min,
                    '%max%' => $max
                )) . '"') . " return false;}";
            }
            $js_check_input_rows = 'if (requestData.rows){' . $js_check_input_rows . '}';
        } else {
            $js_check_input_rows = '';
        }
        
        if (($conditionalProperty = $this->getWidget()->getDisabledIf()) !== null) {
            $js_check_button_state = <<<JS
            
                    if ({$this->buildJsConditionalPropertyIf($conditionalProperty)}) {
                        return false;
                    }

JS;
        } else {
            $js_check_button_state = $this->getWidget()->isDisabled() === true ? 'return false;' : '';
        }
        
        return <<<JS

                    $js_check_button_state
					var requestData = {$input_element->buildJsDataGetter($action)};
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
    public function buildJsClickFunction()
    {
        $widget = $this->getWidget();
        $input_element = $this->getInputElement();
        $action = $widget->getAction();
        
        switch (true) {
            case ! $action:
                return $this->buildJsClickNoAction($widget, $input_element);
            case $action instanceof RefreshWidget:
                return $this->buildJsClickRefreshWidget($action, $input_element);
            case $action->implementsInterface('iRunFacadeScript'):
                return $this->buildJsClickRunFacadeScript($action, $input_element);
            case $action->implementsInterface('iShowDialog'):
                return $this->buildJsClickShowDialog($action, $input_element);
            case $action->implementsInterface('iShowUrl'):
                return $this->buildJsClickShowUrl($action, $input_element);
            case $action->implementsInterface('iShowWidget'):
                return $this->buildJsClickShowWidget($action, $input_element);
            case $action instanceof GoBack:
                return $this->buildJsClickGoBack($action, $input_element);
            case $action instanceof SendToWidget:
                return $this->buildJsClickSendToWidget($action, $input_element);
            case $action instanceof ResetWidget:
                return $this->buildJsResetWidgets($widget);
            default: 
                return $this->buildJsClickCallServerAction($action, $input_element);
        }
    }
    
    /**
     * Returns the JS code triggered by a button without an action.
     * 
     * @param WidgetInterface $trigger
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickNoAction(WidgetInterface $trigger, AbstractJqueryElement $input_element) : string
    {
        return $this->buildJsCloseDialog($trigger, $input_element)
        . $this->buildJsRefreshWidgets($trigger)
        . $this->buildJsResetWidgets($trigger);
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
            $effectsJs .= $effect->exportUxonObject()->toJson() . ',';
        }
        
        $refreshIds = '';
        $refreshNotIds = $widget->getRefreshInput() === false ? $widget->getId() : '';
        foreach ($widget->getRefreshWidgetIds(false) as $refreshId) {
            $refreshIds .= '"' . $refreshId . '", ';
        }
        
        $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;
        return <<<JS

                {$this->buildJsResetWidgets($this->getWidget())}
                
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
    protected function buildJsClickCallServerAction(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        $widget = $this->getWidget();
        
        $headers = ! empty($this->getAjaxHeaders()) ? 'headers: ' . json_encode($this->getAjaxHeaders()) . ',' : '';
        
        $output = $this->buildJsRequestDataCollector($action, $input_element);
        $output .= "
						if (" . $input_element->buildJsValidator() . ") {
							" . $this->buildJsBusyIconShow() . "
							$.ajax({
								type: 'POST',
								url: '" . $this->getAjaxUrl() . "',
                                {$headers} 
								data: {	
									{$this->buildJsRequestCommonParams($widget, $action)}
									data: requestData
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
										{$this->buildJsCloseDialog($widget, $input_element)}
				                       	{$this->buildJsBusyIconHide()}
				                       	{$this->buildJsTriggerActionEffects($action)}
										if (response.success !== undefined || response.undoURL){
				                       		" . $this->buildJsShowMessageSuccess("response.success + (response.undoable ? ' <a href=\"" . $this->buildJsUndoUrl($action, $input_element) . "\" style=\"display:block; float:right;\">UNDO</a>' : '')") . "
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
										}
				                    } else {
										" . $this->buildJsBusyIconHide() . "
										" . $this->buildJsShowMessageError('response.error', '"Server error"') . "
				                    }
								},
								error: function(jqXHR, textStatus, errorThrown){ 
									" . $this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText') . " 
									" . $this->buildJsBusyIconHide() . "
								}
							});
						} else {
							" . $input_element->buildJsValidationError() . "
						}
					";
        
        return $output;
    }

    protected function buildJsClickShowWidget(iShowWidget $action, AbstractJqueryElement $input_element)
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
            
            $output = <<<JS

            {$this->buildJsRequestDataCollector($action, $input_element)}	

JS;
            if ($action->getPrefillWithPrefillData()) {
                if ($prefillPreset = $action->getPrefillDataPreset()) {
                    $prefill_param = '&prefill=\' + JSON.stringify(' . $this->buildJsPrefillDataFromPreset($prefillPreset) . ') + \'';
                } else {
                    $output .= <<<JS

			var prefillRows = [];
			if (requestData.rows && requestData.rows.length > 0 && requestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]){
				prefillRows.push({{$widget->getMetaObject()->getUidAttributeAlias()}: requestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]});
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

            {$input_element->buildJsBusyIconShow()}
			{$this->buildJsNavigateToPage($action->getPageAlias(), $prefill_param . $filters_param, $input_element, $newWindow)}

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
    protected function buildJsClickGoBack(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        return $input_element->buildJsBusyIconShow() . 'parent.history.back(); return false;';
    }

    /**
     * Returns the JS code to navigate to the actions URL - eventually opening a new browser tab.
     * 
     * @param ActionInterface $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickShowUrl(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        /* @var $action \exface\Core\Interfaces\Actions\iShowUrl */
        $output = $this->buildJsRequestDataCollector($action, $input_element) . "
					var " . $action->getAlias() . "Url='" . $action->getUrl() . "';
					" . $this->buildJsPlaceholderReplacer($action->getAlias() . "Url", "requestData.rows[0]", $action->getUrl(), ($action->getUrlencodePlaceholders() ? 'encodeURIComponent' : null));
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
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickRunFacadeScript(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        $widget = $this->getWidget();
        
        return <<<JS
        
                {$action->buildScript($input_element->getId())};
                {$this->buildJsTriggerActionEffects($action)};
                {$this->buildJsCloseDialog($widget, $input_element)};

JS;

    }
    
    /**
     * Returns the JS code to refresh the input widget of the action
     * 
     * @param ActionInterface $action
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickRefreshWidget(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        return <<<JS

                {$input_element->buildJsRefresh()};
                {$this->buildJsTriggerActionEffects($action)};
                {$this->buildJsCloseDialog($this->getWidget(), $input_element)};
        
JS;
    }

    protected function buildJsUndoUrl(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        $widget = $this->getWidget();
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
    public function buildJsOnSuccessScript() : string
    {
        return implode("\n\n", array_unique($this->onSuccessJs));
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
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsClickSendToWidget(SendToWidget $action, AbstractJqueryElement $input_element)
    {
        $widget = $this->getWidget();
        $targetElement = $this->getFacade()->getElementByWidgetId($action->getTargetWidgetId(), $this->getWidget()->getPage());
        
        return <<<JS

                        {$this->buildJsRequestDataCollector($action, $input_element)}
						if ({$input_element->buildJsValidator()}) {
                            {$targetElement->buildJsDataSetter('requestData')}
                            {$this->buildJsCloseDialog($widget, $input_element)}
                            {$this->buildJsTriggerActionEffects($action)}
                        }

JS;
    }
    
    /**
     * If it's a `DialogButton` returns the JS code to close the dialog after the action succeeds.
     * 
     * @param WidgetInterface $widget
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    abstract protected function buildJsCloseDialog($widget, $input_element);
}
?>