<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Widgets\WidgetLinkCoordinateInterface;

use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;

/**
 *
 * @method \exface\Core\Widgets\InputCustom getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JsInputCustomTrait {

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value)
    {
        return $this->getWidget()->getScriptToSetValue($value) ?? $this->buildJsFallbackForEmptyScript('script_to_set_value');
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueGetter()
     */
    public function buildJsValueGetter(string|WidgetLinkCoordinateInterface|null $coord = null) : string
    {
        $coord = WidgetLinkFactory::createCoordinateFromAnything($coord);
        $this->validateValueGetterCoordinate($coord);
        return $this->getWidget()->getScriptToGetValue() ?? $this->buildJsFallbackForEmptyScript('script_to_get_value');
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        return $this->getWidget()->getScriptToValidateInput() ?? parent::buildJsValidator($valJs);
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        // TODO call on-true/false widget functions here. But currently they cannot be defined for InputCustom...
        if ($trueOrFalse === true) {
            return $this->getWidget()->getScriptToDisable() ?? parent::buildJsSetDisabled($trueOrFalse);
        } else {
            return $this->getWidget()->getScriptToEnable() ?? parent::buildJsSetDisabled($trueOrFalse);
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        return $this->getWidget()->getScriptToGetData($action) ?? parent::buildJsDataGetter($action);
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataSetter()
     */
    public function buildJsDataSetter(string $jsData) : string
    {
        return $this->getWidget()->getScriptToSetData($jsData) ?? parent::buildJsDataSetter($jsData);
    }

    /**
     *
     * @param string $widgetProperty
     * @param string $returnValueJs
     * @return string
     */
    protected function buildJsFallbackForEmptyScript(string $widgetProperty, string $returnValueJs = "''") : string
    {
        return "(function(){console.warn('Property {$widgetProperty} not set for widget InputCustom. Falling back to empty string'); return {$returnValueJs};})()";
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildJsOnChangeHandler()
     */
    protected function buildJsOnChangeHandler()
    {
        return $this->getWidget()->getScriptToAttachOnChange($this->getOnChangeScript()) ?? $this->buildJsFallbackForEmptyScript('script_to_attach_on_change');
    }

    protected function registerLiveReferencesAtCustomLinks()
    {
        $widget = $this->getWidget();
        if (! $widget->hasLiveReferences()) {
            return;
        }
        $tplRenderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
        $tplRenderer->addPlaceholder(
            new ArrayPlaceholders([
                '~id' => $this->getId(),
                '~value' => 'mValue'
            ])
        );
        foreach ($widget->getLiveReferences() as $ref) {
            $linkStr = $ref['widget_link'];
            $js = $ref['on_change_script'];
            $link = WidgetLinkFactory::createFromWidget($widget, $linkStr);
            if ($linked_element = $this->getFacade()->getElement($link->getTargetWidget())) {
                $js = $tplRenderer->render($js);

                $callbackJs = <<<JS
                    (function(mValue){
                        {$js}
                    })({$linked_element->buildJsValueGetter($link->getTargetCoordinate())})
JS;

                $linked_element->addOnChangeAtCoordinateScript($callbackJs, $link->getTargetCoordinate());
            }
        }
    }

    public function getOnResizeScript()
    {
        return $this->getWidget()->getScriptToResize() ?? '';
    }
}