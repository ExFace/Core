<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Actions\GoBack;
use exface\Core\Widgets\Button;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Actions\GoToPage;
use exface\Core\Actions\RefreshWidget;
use exface\Core\DataTypes\StringDataType;

trait JqueryButtonTrait {

    protected function buildJsInputRefresh(Button $widget, $input_element)
    {
        $js = ($widget->getRefreshInput() && $input_element->buildJsRefresh() ? $input_element->buildJsRefresh(true) . ";" : "");
        if ($link = $widget->getRefreshWidgetLink()) {
            if ($widget->getPage()->is($link->getPageAlias()) && $linked_element = $this->getTemplate()->getElement($link->getWidget())) {
                $js .= "\n" . $linked_element->buildJsRefresh(true);
            }
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

    protected function buildJsRequestDataCollector(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        if (! is_null($action->getInputRowsMin()) || ! is_null($action->getInputRowsMax())) {
            if ($action->getInputRowsMin() === $action->getInputRowsMax()) {
                $js_check_input_rows = "if (requestData.rows.length < " . $action->getInputRowsMin() . " || requestData.rows.length > " . $action->getInputRowsMax() . ") {" . $this->buildJsShowMessageError('"' . $this->translate("MESSAGE.SELECT_EXACTLY_X_ROWS", array(
                    '%number%' => $action->getInputRowsMax()
                ), $action->getInputRowsMax()) . '"') . " return false;}";
            } elseif (is_null($action->getInputRowsMax())) {
                $js_check_input_rows = "if (requestData.rows.length < " . $action->getInputRowsMin() . ") {" . $this->buildJsShowMessageError('"' . $this->translate("MESSAGE.SELECT_AT_LEAST_X_ROWS", array(
                    '%number%' => $action->getInputRowsMin()
                ), $action->getInputRowsMin()) . '"') . " return false;}";
            } elseif (is_null($action->getInputRowsMin())) {
                $js_check_input_rows = "if (requestData.rows.length > " . $action->getInputRowsMax() . ") {" . $this->buildJsShowMessageError('"' . $this->translate("MESSAGE.SELECT_AT_MOST_X_ROWS", array(
                    '%number%' => $action->getInputRowsMax()
                ), $action->getInputRowsMax()) . '"') . " return false;}";
            } else {
                $js_check_input_rows = "if (requestData.rows.length < " . $action->getInputRowsMin() . " || requestData.rows.length > " . $action->getInputRowsMax() . ") {" . $this->buildJsShowMessageError('"' . $this->translate("MESSAGE.SELECT_X_TO_Y_ROWS", array(
                    '%min%' => $action->getInputRowsMin(),
                    '%max%' => $action->getInputRowsMax()
                )) . '"') . " return false;}";
            }
            $js_check_input_rows = 'if (requestData.rows){' . $js_check_input_rows . '}';
        } else {
            $js_check_input_rows = '';
        }
        
        $js_requestData = "
					var requestData = " . $input_element->buildJsDataGetter($action) . ";
					" . $js_check_input_rows;
        return $js_requestData;
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
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::getWidget()
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
        } elseif ($action->implementsInterface('iRunTemplateScript')) {
            $output = $this->buildJsClickRunTemplateScript($action, $input_element);
        } elseif ($action->implementsInterface('iShowDialog')) {
            $output = $this->buildJsClickShowDialog($action, $input_element);
        } elseif ($action->implementsInterface('iShowUrl')) {
            $output = $this->buildJsClickShowUrl($action, $input_element);
        } elseif ($action->implementsInterface('iShowWidget')) {
            $output = $this->buildJsClickShowWidget($action, $input_element);
        } elseif ($action instanceof GoBack) {
            $output = $this->buildJsClickGoBack($action, $input_element);
        } else {
            $output = $this->buildJsClickCallServerAction($action, $input_element);
        }
        
        return $output;
    }

    protected function buildJsClickCallServerAction(ActionInterface $action, AbstractJqueryElement $input_element)
    {
        $widget = $this->getWidget();
        
        $output = $this->buildJsRequestDataCollector($action, $input_element);
        $output .= "
						if (" . $input_element->buildJsValidator() . ") {
							" . $this->buildJsBusyIconShow() . "
							$.ajax({
								type: 'POST',
								url: '" . $this->getAjaxUrl() . "',
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
				                   	if (response.success){
										" . $this->buildJsCloseDialog($widget, $input_element) . "
										" . $this->buildJsInputRefresh($widget, $input_element) . "
				                       	" . $this->buildJsBusyIconHide() . "
				                       	$('#" . $this->getId() . "').trigger('" . $action->getAliasWithNamespace() . ".action.performed', [requestData, '" . $input_element->getId() . "']);
										if (response.success || response.undoURL){
				                       		" . $this->buildJsShowMessageSuccess("response.success + (response.undoable ? ' <a href=\"" . $this->buildJsUndoUrl($action, $input_element) . "\" style=\"display:block; float:right;\">UNDO</a>' : '')") . "
											if(response.redirect){
												if (response.redirect.indexOf('target=_blank')!==0) {
													window.open(response.redirect.replace('target=_blank',''), '_newtab');
												}
												else {
													window.location.href = response.redirect;
												}
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
            if ($action->getPrefillWithPrefillData()){
                $output = <<<JS
    				{$this->buildJsRequestDataCollector($action, $input_element)}
    				{$input_element->buildJsBusyIconShow()}
    				var prefillRows = [];
    				if (requestData.rows && requestData.rows.length > 0 && requestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]){
    					prefillRows.push({{$widget->getMetaObject()->getUidAttributeAlias()}: requestData.rows[0]["{$widget->getMetaObject()->getUidAttributeAlias()}"]});
    				}
JS;
                $prefill_param = '&prefill={"meta_object_id":"'.$widget->getMetaObject()->getId().'","rows": \' + JSON.stringify(prefillRows) + \'}';
            } 
            
            if ($action instanceof GoToPage){
                /* @var $widgetLink \exface\Core\CommonLogic\WidgetLink */
                $prefix = $this->getTemplate()->getUrlFilterPrefix();
                foreach ($action->getTakeAlongFilters() as $attributeAlias => $widgetLink){
                    $filters_param .= "&{$prefix}{$attributeAlias}='+{$this->getTemplate()->getElement($widgetLink->getWidget())->buildJsValueGetter($widgetLink->getColumnId(), null)}+'";
                }
            }
            
            $output .= <<<JS
            {$input_element->buildJsBusyIconShow()}
			window.location.href = '{$this->getTemplate()->createLinkInternal($action->getPageAlias())}?{$prefill_param}{$filters_param}';
JS;
        }
        return $output;
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

    protected function buildJsClickRunTemplateScript(ActionInterface $action, AbstractJqueryElement $input_element)
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
     * Return the template element corresponding to the input widget of this button
     */
    public function getInputElement()
    {
        return $this->getTemplate()->getElement($this->getWidget()->getInputWidget());
    }
}
?>