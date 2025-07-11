<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Actions\GoBack;
use exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface;
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
use exface\Core\DataTypes\OfflineStrategyDataType;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Actions\ActionChain;
use exface\Core\DataTypes\ByteSizeDataType;

/**
 *
 * @method \exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade getFacade()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryButtonTrait {
    
    use JsConditionalPropertyTrait;
    
    private $onSuccessJs = [];
    
    private $onErrorJs = [];
    
    private static $sizeErrors = [];
    
    /**
     * Returns the JS code to refresh all neccessary widgets after the button's action succeeds.
     * 
     * @param bool $includeInputWidget
     * @return string
     */
    protected function buildJsRefreshWidgets(bool $includeInputWidget = true, callable $elementFilter = null) : string
    {
        $js = '';
        $widget = $this->getWidget();
        $page = $widget->getPage();
        $idSpace = $widget->getIdSpace();
        foreach ($widget->getRefreshWidgetIds($includeInputWidget) as $widgetId) {
            $idSpaceProvided = StringDataType::substringBefore($widgetId, UiPage::WIDGET_ID_SPACE_SEPARATOR, '', false, true);
            if ($idSpaceProvided === '' && $idSpace !== null && $idSpace !== '') {
                $widgetId = $idSpace . UiPage::WIDGET_ID_SPACE_SEPARATOR . $widgetId;
            }
            
            $refreshEl = $this->getFacade()->getElementByWidgetId($widgetId, $page);
            if ($elementFilter !== null) {
                if ($elementFilter($refreshEl) === false) {
                    continue;
                }
            }
            
            $js .=  $refreshEl->buildJsRefresh(true) . ";\n";
        }
        return $js;
    }
    
    /**
     * Returns the JS code to reset all neccessary widgets after the button's action succeeds.
     * 
     * @param bool $includeInputWidget
     * @return string
     */
    protected function buildJsResetWidgets(bool $includeInputWidget = true, callable $elementFilter = null) : string
    {
        $js = '';
        $btn = $this->getWidget();
        $page = $btn->getPage();
        $idSpace = $btn->getIdSpace();
        foreach ($btn->getResetWidgetIds($includeInputWidget) as $id) {
            $idSpaceProvided = StringDataType::substringBefore($id, UiPage::WIDGET_ID_SPACE_SEPARATOR, '', false, true);
            if ($idSpaceProvided === '' && $idSpace !== null && $idSpace !== '') {
                $id = $idSpace . UiPage::WIDGET_ID_SPACE_SEPARATOR . $id;
            }
            
            $resetElem = $this->getFacade()->getElementByWidgetId($id, $page);
            if ($elementFilter !== null) {
                if ($elementFilter($resetElem) === false) {
                    continue;
                }
            }
            
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
     * @param string $sValJs - e.g. result (the variable must be already instantiated!)
     * @param string $oRowJs - e.g. values = {placeholder = "someId"}
     * @param string $originalStringWithPlaceholders - e.g. http://localhost/pages/[#placeholder#]
     * @param string $fnSanitizrJs - a Javascript function to be applied to each value (e.g. encodeURIComponent) - without parentheses!!!
     * @return string - e.g. result = result.replace('[#placeholder#]', values['placeholder']);
     */
    protected function buildJsPlaceholderReplacer(string $sValJs, string $oRowJs, string $originalStringWithPlaceholders, string $fnSanitizrJs = null)
    {
        $output = '';
        $placeholders = StringDataType::findPlaceholders($originalStringWithPlaceholders);
        $commonPhVals = $this->getCommonPlaceholderValues($placeholders);
        foreach ($placeholders as $ph) {
            switch (true) {
                case array_key_exists($ph, $commonPhVals):
                    $value = "'{$commonPhVals[$ph]}'";
                    break;
                default: 
                    $value = $oRowJs . "['" . $ph . "']";
            }
            if ($fnSanitizrJs !== null && ! array_key_exists($ph, $commonPhVals)) {
                $value = $fnSanitizrJs . '(' . $value . ')';
            }
            $output .= "\n{$sValJs} = {$sValJs}.replace('[#{$ph}#]', {$value});";
        }
        return $output;
    }
    
    /**
     * 
     * @param string[] $phs
     * @return string[]
     */
    protected function getCommonPlaceholderValues(array $phs) : array
    {
        $phVals = [];
        foreach ($phs as $ph) {
            switch (true) {
                case $ph === 'api':
                    $phVals[$ph] = $this->getWorkbench()->getUrl() . 'api';
                    break;
                case $ph === 'facade':
                    $phVals[$ph] = $this->getFacade()->buildUrlToFacade(true);
                    break;
                case StringDataType::startsWith($ph, 'element_id:', false):
                    $widgetId = StringDataType::substringAfter($ph, 'element_id:', $ph);
                    switch ($widgetId) {
                        case '~input':
                            $phVals[$ph] = $this->getInputElement()->getId();
                            break;
                        case '~self':
                            $phVals[$ph] = $this->getId();
                            break;
                        case '~parent':
                            $phVals[$ph] = $this->getFacade()->getElement($this->getWidget()->getParent())->getId();
                            break;
                        default:
                            $phVals[$ph] = $this->getFacade()->getElement($this->getWidget()->getPage()->getWidget($widgetId))->getId();
                    }
            }
        }
        return $phVals;
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
        
        return $js_get_data;
    }

    protected function buildJsRequestDataCheckRows(string $jsRequestData, AbstractAction $action = null) : string
    {
        if ($action === null) {
            return 'true';
        }

        $min = $action->getInputRowsMin();
        $max = $action->getInputRowsMax();
        
        // If the action has built-in input data, let the server handle the checks.
        if ($action->hasInputDataPreset() && ! $action->getInputDataPreset()->isEmpty()) {
            $min = null;
            $max = null;
        }
        
        if (($min ?? 0) === 0 && $max === null) {
            return 'true';
        }

        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        if ($min === $max) {
            $js_check_input_rows = "if (oInputData.rows.length < " . $min . " || oInputData.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_EXACTLY_X_ROWS", array(
                '%number%' => $max
            ), $max) . '"') . " return false;}";
        } elseif (is_null($max)) {
            $js_check_input_rows = "if (oInputData.rows.length < " . $min . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_LEAST_X_ROWS", array(
                '%number%' => $min
            ), $min) . '"') . " return false;}";
        } elseif (is_null($min)) {
            $js_check_input_rows = "if (oInputData.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_AT_MOST_X_ROWS", array(
                '%number%' => $max
            ), $max) . '"') . " return false;}";
        } else {
            $js_check_input_rows = "if (oInputData.rows.length < " . $min . " || oInputData.rows.length > " . $max . ") {" . $this->buildJsShowMessageError('"' . $translator->translate("MESSAGE.SELECT_X_TO_Y_ROWS", array(
                '%min%' => $min,
                '%max%' => $max
            )) . '"') . " return false;}";
        }
        return <<<JS
                    (function(oInputData) {
                        if (! Array.isArray(oInputData.rows)){
                            return false;   
                        }
                        {$js_check_input_rows} 
                        return true;
                    })($jsRequestData)
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
     * Returns an inline JS snippet (IIFE) yielding FALSE if the action cannot be
     * performed or a promise, that will be resolved once the action has been performed
     * 
     * @return string
     */
    public function buildJsClickFunction(ActionInterface $action = null, string $jsRequestData = null)
    {
        $action = $action ?? $this->getWidget()->getAction();
        
        $collectInputDataJs = '{rows: []}';
        if ($jsRequestData === null && $action !== null) {
            $collectInputDataJs = "\n" . $this->buildJsRequestDataCollector($action, $this->getInputElement(), 'requestData');
        } 

        switch (true) {
            // Buttons without an action don't do anything
            case ! $action:
                $performActionJs = $this->buildJsClickNoAction(); break;
            // CallAction needs som extra logic because its action is different depending on the input data
            case $action instanceof CallAction:
                $performActionJs = $this->buildJsClickDynamicAction($action, 'requestData'); break;
            // Action chains and other action proxies
            case $action instanceof iCallOtherActions:
                $performActionJs = $this->buildJsClickActionChain($action, 'requestData'); break;
            // Refresh input or other widget - don't need input data here
            case $action instanceof RefreshWidget:
                $performActionJs = $this->buildJsClickRefreshWidget($action); break;
            // Run custom JS - don't need input data here
            case $action instanceof iRunFacadeScript:
                $performActionJs = $this->buildJsClickRunFacadeScript($action); break;
            // Show Dialog
            case $action instanceof iShowDialog:
                $performActionJs = $this->buildJsClickShowDialog($action, 'requestData'); break;
            // Navigate to URL
            case $action instanceof iShowUrl:
                $performActionJs = $this->buildJsClickShowUrl($action, 'requestData'); break;
            // Other show-widget actions (not simple navigating)
            case $action instanceof iShowWidget:
                $performActionJs = $this->buildJsClickShowWidget($action, 'requestData'); break;
            // Back-button - don't need input data here
            case $action instanceof GoBack:
                $performActionJs = $this->buildJsClickGoBack($action); break;
            // Send data to widget
            case $action instanceof SendToWidget:
                $performActionJs = $this->buildJsClickSendToWidget($action, 'requestData'); break;
            // Reset input or other widget - don't need input data here
            case $action instanceof ResetWidget:
                $performActionJs = $this->buildJsClickResetWidget($action); break;
            // Call a widget function - e.g. click another button
            case $action instanceof iCallWidgetFunction:
                $performActionJs = $this->buildJsClickCallWidgetFunction($action, 'requestData'); break;
            // Send all other acitons to the server
            default: 
                $performActionJs = $this->buildJsClickCallServerAction($action, 'requestData'); break;
        }

        if (null !== $conditionalProperty = $this->getWidget()->getDisabledIf()) {
            $checkButtonDisabledJs = $this->buildJsConditionalPropertyIf($conditionalProperty->getConditionGroup());
        } else {
            $checkButtonDisabledJs = $this->escapeBool($this->getWidget()->isDisabled() ?? false);
        }

        $showConfirmationAndPerformActionJs = "fnAction();";
        if ($action) {
            if ((null !== $cnfWidget = $action->getConfirmations()->getConfirmationsForAction()->getFirst()) && ! $cnfWidget->isDisabled()) {
                $showConfirmationAndPerformActionJs = $this->buildJsConfirmation($cnfWidget, 'requestData', 'fnAction()');
            }
            if ((null !== $cnfWidget = $action->getConfirmations()->getConfirmationsForUnsavedChanges()->getFirst()) && ! $cnfWidget->isDisabled()) {
                $inputEl = $this->getInputElement();
                $showConfirmationAndPerformActionJs = <<<JS
                
                (function(fnConfirmAndPerform, fnCancel){
                    var aChanges = {$inputEl->buildJsChangesGetter(true)};
                    if (aChanges.length === 0) {
                        fnConfirmAndPerform();
                    } else {
                        {$this->buildJsConfirmation($cnfWidget, '{}', 'fnConfirmAndPerform();')}
                    }
                })(function(){ $showConfirmationAndPerformActionJs });

JS;
            }
        }

        // Make sure to start the JS without a blank line to make sure it can be
        // used with assignments: e.g. `return {$this->buildJsClickFunction()}`
        $js = <<<JS
            (function(requestData){
                var self = this;
                var fnAction;
                var bButtonDisabled = {$checkButtonDisabledJs};

                if (bButtonDisabled === true) {
                    return false;
                }

                if (requestData === undefined) {
                    requestData = {$collectInputDataJs};
                }

                if (false === {$this->buildJsRequestDataCheckRows('requestData', $action)}) {
                    return Promise.resolve({});
                }
                
                fnAction = (function() {
                    {$performActionJs};
                }).bind(self);

                {$showConfirmationAndPerformActionJs}

            })({$jsRequestData})
JS;
        
        // In any case, wrap some offline-logic around the action
        if ($action !== null) {
            $js = $this->buildJsClickOfflineWrapper($action, $js);
        }
        
        return $js;
    }

    /**
     * Returns JS code to show a confirmation popup
     * 
     * The confirmation will have at least two buttons: continue and cancel. Both will close it and
     * perform their respective callbacks passed to this method.
     * 
     * The confirmation may use the provided input data to fill placeholders.
     * 
     * @param \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface $widget
     * @param string $jsRequestData
     * @param string $onContinueJs
     * @param string $onCancelJs
     * @return string
     */
    protected function buildJsConfirmation(ConfirmationWidgetInterface $widget, string $jsRequestData, string $onContinueJs, string $onCancelJs = null)
    {
        return <<<JS

            if (window.confirm({$this->escapeString($widget->getQuestionText())})) {
                {$onContinueJs};
            } else {
                {$onCancelJs};
            }
JS;
    }
    
    /**
     * Executes the provided $regularJs snippet depending of the offline strategy of the $action. 
     * 
     * If the action is configured to be skipped offline, $ifNotExcecutedJs will be run instead.
     * 
     * @param ActionInterface $action
     * @param string $regularJs
     * @param string $ifNotExecutedJs
     * @return string
     */
    protected function buildJsClickOfflineWrapper(ActionInterface $action, string $regularJs, string $ifNotExecutedJs = 'null') : string
    {
        if ($action->getOfflineStrategy() === OfflineStrategyDataType::SKIP) {
            $regularJs = <<<JS
            (function(){
                console.log('Offline Wrapper');
                if(navigator.onLine !== false) {
                    return (function() { $regularJs })();
                } else {
                    return (function() { $ifNotExecutedJs })();
                }
            })()
JS;
        }
        return $regularJs;
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
        $firstDialogActionIdx = null;
        $steps = $action->getActions();
        $lastActionIdx = count($steps) - 1;
        foreach ($steps as $i => $step) {
            // For front-end action their JS code will be called directly
            if ($this->isActionFrontendOnly($step)) {
                continue;
            }
            // For actions showing dialogs, this can be done too, but only if they use the input data
            // of the chain and not that of some step in the middle
            if ($step instanceof iShowDialog) {
                if ($firstDialogActionIdx !== null) {
                    throw new FacadeRuntimeError('Cannot render action chain with multiple actions showing dialogs!');
                }
                if ($i > 0 && ($action instanceof ActionChain) && $action->getUseInputDataOfAction() !== 0) {
                    throw new FacadeRuntimeError('Cannot render action chain with multiple actions showing dialogs!');
                }
                $firstDialogActionIdx = $i;
                continue;
            } 
            // ShowWidget actions other than ShowDialog cannot be used in chains - its not clear, what
            // they would do.
            if ($step instanceof iShowWidget) {
                throw new FacadeRuntimeError('Cannot render action chain with ShowWidget actions!');
            }
            // Make sure there are no client-side action between two server actions
            if ($firstServerActionIdx !== null && $lastServerActionIdx !== $i-1) {
                throw new FacadeRuntimeError('Cannot render action "' . $action->getName() . '" (' . $action->getAliasWithNamespace() . '): cannot mix front- and back-end actions!');
            }
            // Since we know, this is a server action it must be the first one if we did not see
            // any other so far.
            if ($firstServerActionIdx === null) {
                $firstServerActionIdx = $i;
            }
            $lastServerActionIdx = $i;
        }
        
        // If the chain consists of server actions only - just pass it to the server
        if ($firstServerActionIdx === 0 && $lastServerActionIdx === $lastActionIdx) {
            return $this->buildJsClickCallServerAction($action, $jsRequestData);
        }
        
        // Render JS for the chain
        // Starting with the front-end-actions BEFORE the first server-action
        $js = 'var oChainThis = this; ';
        for ($i = 0; $i < $firstServerActionIdx; $i++) {
            $js .= $this->buildJsClickFunction($steps[$i], $jsRequestData) . "\n\n";
        }
        // Now prepare the front-end-actions AFTER the last server-action and save their
        // code into $onSuccess in order to perform it after the server request
        $onSuccess = '';
        for ($i = ($lastServerActionIdx + 1); $i <= $lastActionIdx; $i++) {
            // Make sure the on-success code has the same `this` in the JS as the code
            // executed immediately. After all, the action handlers cannot know, that
            // they are called within a chain.
            $onSuccess .= "(function() { {$this->buildJsClickFunction($steps[$i], $jsRequestData)} }).call(oChainThis); \n\n";
        }
        
        // TODO Multiple server actions in the middle are not supported yet
        if ($firstServerActionIdx !== $lastServerActionIdx) {
            throw new FacadeRuntimeError('Cannot render action "' . $action->getName() . '" (' . $action->getAliasWithNamespace() . '): action chains with mixed front- and back-end actions can only contain a single back-end action!');
        }
        
        // Now send the server-action stuff to the server and do the remaining JS-part of the chain
        // after a successful response was received.
        $serverAction = $steps[$firstServerActionIdx];
        $js .= $this->buildJsClickOfflineWrapper($serverAction, $this->buildJsClickCallServerAction($action, $jsRequestData, $onSuccess), $onSuccess);
        
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
     * @param bool $returnEventParamsOnly
     * @return string
     */
    public function buildJsTriggerActionEffects(ActionInterface $action, bool $returnEventParamsOnly = false) : string
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
        foreach ($widget->getRefreshWidgetIds($widget->getRefreshInput() ?? false) as $refreshId) {
            $refreshIds .= '"' . $refreshId . '", ';
        }
        
        $resetIds = '';
        foreach ($widget->getResetWidgetIds(false) as $resetId) {
            $resetIds .= '"' . $resetId . '", ';
        }
        
        $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;
        $eventParamsJs = <<<JS

                    [{
                        trigger_widget_id: "{$widget->getId()}",
                        page_alias: "{$widget->getPage()->getAliasWithNamespace()}",
                        action_alias: "{$action->getAliasWithNamespace()}",
                        effects: [ $effectsJs ],
                        refresh_widgets: [ $refreshIds ],
                        refresh_not_widgets: [ $refreshNotIds ],
                        reset_widgets: [ $resetIds ],
                    }]
JS;
        if ($returnEventParamsOnly === true) {
            return $eventParamsJs;
        }
        
        return <<<JS

                {$this->buildJsResetWidgets()}
                
                $(document).trigger("$actionperformed", $eventParamsJs);
                
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
        
        $output = "
						if ({$this->getInputElement()->buildJsValidator()}) {
                            {$this->buildJsRequestDataCheckSize($jsRequestData, $this->getAjaxPostSizeMax())}
							{$this->buildJsBusyIconShow()}
							$.ajax({
								type: 'POST',
								url: '{$this->getAjaxUrl()}',
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
							{$this->getInputElement()->buildJsValidationError()}
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
        $output = <<<JS

					var {$action->getAlias()}Url='{$action->getUrl()}';
					{$this->buildJsPlaceholderReplacer($action->getAlias() . "Url", "{$jsRequestData}.rows[0]", $action->getUrl(), ($action->getUrlencodePlaceholders() ? 'encodeURIComponent' : null))}
JS;
        
        switch (true) {
            case $action->getOpenInNewWindow() === true:
                $output .= $input_element->buildJsBusyIconShow() . "window.open(" . $action->getAlias() . "Url);" . $input_element->buildJsBusyIconHide();
                break;
            case null !== $browserId = $action->getOpenInBrowserWidget():
                $output .= $this->getFacade()->getElementByWidgetId($browserId, $this->getWidget()->getPage())->buildJsValueSetter("{$action->getAlias()}Url");
                break;
            default:
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
        $phVals = $this->getCommonPlaceholderValues($phs);
        // Backwards compatibility with older scripts
        if (in_array('widget_id', $phs) === true) {
            $phVals['widget_id'] = $this->getInputElement()->getId();
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
    
    /**
     * Returns the JS code to reset the input widget of the action
     *
     * @param ActionInterface $action
     * @return string
     */
    protected function buildJsClickResetWidget(ActionInterface $action) : string
    {
        return <<<JS
        
                {$this->getInputElement()->buildJsResetter()};
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
    protected function buildJsClickCallWidgetFunction(iCallWidgetFunction $action, string $jsRequestData) : string
    {
        // TODO how to replace column-placeholders in function arguments with JS-data? E.g. for functions like
        // `set_value` or `run_command`.
        $targetEl = $this->getFacade()->getElement($action->getWidget($this->getWidget()->getPage()));
        $beforeJs = '';
        $afterJs = '';
        $thisButtonScriptJs = <<<JS

                {$this->buildJsTriggerActionEffects($action)}
                {$this->buildJsCloseDialog()}
JS;
        
        // If the widget function is pressing another button, make sure the success/error 
        // scripts of this button run AFTER the action of that other button succeeds/fails. 
        // This is particularly important for dialog buttons, that press other types of
        // buttons (e.g. data buttons). The dialog button should only close its dialog
        // AFTER the other button succeeded. It should not close the dialog if the
        // other button failed!
        if ($action->getFunctionName() === Button::FUNCTION_PRESS && ($targetEl->getWidget() instanceof iTriggerAction)) {
            // add the onErrorScripts of the calling Button to the error scripts of the Button to be pressed
            // FIXME this does not work if the other button is already rendered...
            if (method_exists($targetEl, 'addOnErrorScript')) {
                $targetEl->addOnErrorScript($this->buildJsOnErrorScript());
            }
            // Add an event listener to on-action-performed to trigger postprocessing for 
            // this button.
            $afterJs = '';
            $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;
            $beforeJs = <<<JS

            $( document ).off( "{$actionperformed}.{$this->getId()}" );
            $( document ).on( "{$actionperformed}.{$this->getId()}", function( oEvent, oParams ) {
                if (oParams.page_alias !== '{$targetEl->getWidget()->getPage()->getAliasWithNamespace()}' || oParams.trigger_widget_id !== '{$targetEl->getWidget()->getId()}') {
                    return;
                }
                // Avoid errors if widget was removed already
                if ({$this->buildJsCheckInitialized()} === false || {$targetEl->buildJsCheckInitialized()} === false) {
                    return;
                }
                {$thisButtonScriptJs}
            });
JS;
        } else {
            $afterJs = $thisButtonScriptJs;
        }
        
        // Make sure to trigger the widget function only once the 
        return <<<JS

            {$beforeJs}
            {$targetEl->buildJsCallFunction($action->getFunctionName(), $action->getFunctionArguments(), $jsRequestData)}
            {$afterJs}
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
    public function buildJsCallFunction(string $functionName = null, array $parameters = [], ?string $jsRequestData = null) : string
    {
        switch (true) {
            case $functionName === null:
            case $functionName === Button::FUNCTION_PRESS:
                return $this->buildJsClickFunctionName() . '()';
            case $functionName === Button::FUNCTION_FOCUS:
                return "$('#{$this->getId()}').focus()";
        }
        return parent::buildJsCallFunction($functionName, $parameters, $jsRequestData);
    }
    
    protected function buildJsRequestDataCheckSize(string $jsRequestData, int $bytes = null) : string
    {
        if ($bytes === null) {
            return '';
        }
        
        if (null === $msgs = self::$sizeErrors[$bytes]) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            $messageJs = $this->escapeString("{$translator->translate('WIDGET.BUTTON.ERROR_DATA_TO_LARGE')}\n\n{$translator->translate('WIDGET.BUTTON.ERROR_DATA_TO_LARGE_DESCRIPTION')}\n\n{$translator->translate('WIDGET.BUTTON.ERROR_DATA_MAX_SIZE', ['%size_formatted%' => ByteSizeDataType::formatWithScale($bytes)])}");
            $titleJs = $this->escapeString($translator->translate('ERROR.CAPTION') . ' 7V9OSYM');
            self::$sizeErrors[$bytes] = [$titleJs, $messageJs];
        } else {
            list($titleJs, $messageJs) = $msgs;
        }
        return <<<JS
        
                            if ($.param($jsRequestData).length > ($bytes - 1000)) {
                                {$this->buildJsShowMessageError($messageJs, $titleJs)}
                                return;
                            }
JS;
    }
}