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
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\Actions\ResetWidget;

/**
 * 
 * @method Button getWidget()
 * @method AbstractAjaxFacade getFacade()
 * 
 * @author tmc
 *
 */
trait JqueryButtonTrait {
    
    use JqueryDisableConditionTrait;
    
    private $onSuccessJs = [];

    /**
     * Returns the JS code to run when refreshing/resetting widgets after the action.
     * 
     * @param Button $widget
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsInputRefresh(Button $widget, $input_element)
    {
        $js = '';
        
        // Reset the input if needed (before refreshing!!!)
        $js .= $this->buildJsResetWidgets($widget, $input_element);
        
        // Refresh the linked widget if needed
        $js .= $this->buildJsRefreshWidgets($widget, $input_element);
        
        return $js;
    }
    
    /**
     * Returns the JS code to refresh all neccessary widgets after the button's action succeeds.
     * 
     * @param Button $widget
     * @param AbstractJqueryElement $input_element
     * @return string
     */
    protected function buildJsRefreshWidgets(Button $widget, $input_element) : string
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
    protected function buildJsResetWidgets(button $widget, $input_element) : string
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
     *            - a Javascript function to be applied to each value (e.g. encodeURIComponent) - without braces!!!
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
        if (! is_null($action->getInputRowsMin()) || ! is_null($action->getInputRowsMax())) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            if ($action->getInputRowsMin() === $action->getInputRowsMax()) {
                $js_check_input_rows = "if (requestData.rows.length < " . $action->getInputRowsMin() . " || requestData.rows.length > " . $action->getInputRowsMax() . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_EXACTLY_X_ROWS", array(
                    '%number%' => $action->getInputRowsMax()
                ), $action->getInputRowsMax()) . '"') . " return false;}";
            } elseif (is_null($action->getInputRowsMax())) {
                $js_check_input_rows = "if (requestData.rows.length < " . $action->getInputRowsMin() . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_LEAST_X_ROWS", array(
                    '%number%' => $action->getInputRowsMin()
                ), $action->getInputRowsMin()) . '"') . " return false;}";
            } elseif (is_null($action->getInputRowsMin())) {
                $js_check_input_rows = "if (requestData.rows.length > " . $action->getInputRowsMax() . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_MOST_X_ROWS", array(
                    '%number%' => $action->getInputRowsMax()
                ), $action->getInputRowsMax()) . '"') . " return false;}";
            } else {
                $js_check_input_rows = "if (requestData.rows.length < " . $action->getInputRowsMin() . " || requestData.rows.length > " . $action->getInputRowsMax() . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_X_TO_Y_ROWS", array(
                    '%min%' => $action->getInputRowsMin(),
                    '%max%' => $action->getInputRowsMax()
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

    public function buildJsClickFunction()
    {
        $output = '';
        $widget = $this->getWidget();
        $input_element = $this->getInputElement();
        
        $action = $widget->getAction();
        
        // if the button does not have a action attached, just see if the attributes of the button
        // will cause some click-behaviour and return the JS for that
        if (! $action) {
            $output .= $this->buildJsCloseDialog($widget, $input_element) . $this->buildJsInputRefresh($widget, $input_element);
            return $output;
        }
        
        if ($action instanceof RefreshWidget) {
            $output = $this->buildJsClickRefreshWidget($action, $input_element);
        } elseif ($action->implementsInterface('iRunFacadeScript')) {
            $output = $this->buildJsClickRunFacadeScript($action, $input_element);
        } elseif ($action->implementsInterface('iShowDialog')) {
            $output = $this->buildJsClickShowDialog($action, $input_element);
        } elseif ($action->implementsInterface('iShowUrl')) {
            $output = $this->buildJsClickShowUrl($action, $input_element);
        } elseif ($action->implementsInterface('iShowWidget')) {
            $output = $this->buildJsClickShowWidget($action, $input_element);
        } elseif ($action instanceof GoBack) {
            $output = $this->buildJsClickGoBack($action, $input_element);
        } elseif ($action instanceof SendToWidget) {
            $output = $this->buildJsClickSendToWidget($action, $input_element);
        } elseif ($action instanceof ResetWidget) {
            $output = $this->buildJsResetWidgets($widget, $input_element);
        } else {
            $output = $this->buildJsClickCallServerAction($action, $input_element);
        }
        
        return $output;
    }

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
									action: '" . $widget->getActionAlias() . "',
									resource: '" . $widget->getPage()->getAliasWithNamespace() . "',
									element: '" . $widget->getId() . "',
									object: '" . $widget->getMetaObject()->getId() . "',
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
										" . $this->buildJsCloseDialog($widget, $input_element) . "
										" . $this->buildJsInputRefresh($widget, $input_element) . "
				                       	" . $this->buildJsBusyIconHide() . "
				                       	$('#" . $this->getId() . "').trigger('" . $action->getAliasWithNamespace() . ".action.performed', [requestData, '" . $input_element->getId() . "']);
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
                                        {$this->buildJsOnSuccessScript()}
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
            $output = <<<JS

            {$this->buildJsRequestDataCollector($action, $input_element)}	

JS;
            if ($action->getPrefillWithPrefillData()){
                $output .= <<<JS

			var prefillRows = [];
			if (requestData.rows && requestData.rows.length > 0 && requestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]){
				prefillRows.push({{$widget->getMetaObject()->getUidAttributeAlias()}: requestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]});
			}

JS;
                $prefill_param = '&prefill={"meta_object_id":"'.$widget->getMetaObject()->getId().'","rows": \' + JSON.stringify(prefillRows) + \'}';
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
     * Generates the JS code to navigate to another UI page.
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
            return "window.open('{$url}');";
        }
        return "window.location.href = '{$url}';";
    }

    protected function buildJsClickGoBack(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        return $input_element->buildJsBusyIconShow() . 'parent.history.back(); return false;';
    }

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

    protected function buildJsClickRunFacadeScript(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        $widget = $this->getWidget();
        
        $output = $action->buildScript($input_element->getId());
        $output .= '
				' . $this->buildJsCloseDialog($widget, $input_element);
        
        return $output;
    }
    
    protected function buildJsClickRefreshWidget(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        $output = $input_element->buildJsRefresh();
        $output .= '
				' . $this->buildJsCloseDialog($this->getWidget(), $input_element);
        
        return $output;
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
                        $tags[] = '<script src="' . $path . '"></script>';
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
                            {$this->buildJsInputRefresh($widget, $input_element)}
                        }

JS;
    }
}
?>