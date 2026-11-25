<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Factories\WidgetLinkFactory;

use exface\Core\Interfaces\Widgets\WidgetLinkCoordinateInterface;

use exface\Core\Facades\DocsFacade;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\InputJson;
use exface\Core\Widgets\InputUxon;

/**
 * Configures exfJsonEditor for InputJson and InputUxon in AJAX facades.
 *
 * The JS adapter owns editor state, UXON tools, validation and cleanup. Facades
 * only provide a host, value/change integration and library loading.
 *
 * Requires npm-asset/jsoneditor (^7.0||^8.0||^9.0||^10) and npm-asset/picomodal (^3.0.0).
 * Configure LIBS.JSONEDITOR.JS, LIBS.JSONEDITOR.CSS and LIBS.JSONEDITOR.PICOMODAL
 * in the facade. UI5 elements should register getJsonEditorJsIncludes() and
 * getJsonEditorCssIncludes() as controller dependencies instead of HTML tags.
 *
 * @method InputJson getWidget()
 * @method WorkbenchInterface getWorkbench()
 */
trait JsonEditorTrait
{
    use JqueryInputValidationTrait;

    /**
     * Returns the editor host ID, independently of a facade's outer control ID.
     */
    protected function getJsonEditorId() : string
    {
        return $this->getId();
    }

    /**
     * Builds the editor host. The adapter creates its own preset hint.
     */
    protected function buildHtmlJsonEditor() : string
    {
        return '<div id="' . $this->getJsonEditorId() . '_wrapper" style="height:100%;width:100%;position:relative">'
            . '<div id="' . $this->getJsonEditorId() . '" style="height:100%;width:100%"></div></div>';
    }

    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return ($this->getHeightRelativeUnit() * 5) . 'px';
    }

    /**
     * Builds an expression retrieving the host-owned adapter, without global variables.
     */
    protected function buildJsEditorGetter() : string
    {
        return 'document.getElementById(' . $this->escapeString($this->getJsonEditorId(), true, false) . ').exfJsonEditor';
    }

    /**
     * Builds editor initialization for HTML-based facades.
     */
    protected function buildJsJsonEditor() : string
    {
        return 'exfJsonEditor.create(document.getElementById(' . $this->escapeString($this->getJsonEditorId(), true, false)
            . '), ' . $this->buildJsJsonEditorConfig() . ', '
            . $this->escapeString($this->getWidget()->getValueWithDefaults(), true, false) . ');';
    }

    /**
     * Builds the shared configuration. Context getters stay live for widget links.
     */
    protected function buildJsJsonEditorConfig() : string
    {
        $widget = $this->getWidget();
        $isUxon = $widget instanceof InputUxon;
        $helpUrl = '';
        if ($isUxon) {
            $docsFacade = FacadeFactory::createFromAnything(DocsFacade::class, $this->getWorkbench());
            $helpUrl = $docsFacade->buildUrlToFacade() . '/exface/Core/Docs/UXON/Introduction_to_the_UXON_editor.md';
        }
        return <<<JS
{
    uxon: {$this->escapeBool($isUxon)},
    autosuggest: {$this->escapeBool($isUxon && $widget->getAutosuggest())},
    validate: {$this->escapeBool(! $widget->getDisableValidation())},
    disabled: {$this->escapeBool($widget->isDisabled())},
    required: {$this->escapeBool($widget->isRequired())},
    name: {$this->escapeString($widget->getCaption(), true, false)},
    ajaxUrl: {$this->escapeString($this->getAjaxUrl(), true, false)},
    serverAvailable: {$this->buildJsJsonEditorServerAvailable()},
    helpUrl: {$this->escapeString($helpUrl, true, false)},
    translations: {$this->escapeString(static::getTranslations($this->getWorkbench()), true, false)},
    schema: function() { return {$this->buildJsSchemaGetter()}; },
    rootPrototype: function() { return {$this->buildJsRootPrototypeGetter()}; },
    rootObject: function() { return {$this->buildJsRootObjectGetter()}; },
    scrollKey: {$this->escapeString($this->getJsonEditorScrollKey(), true, false)},
    onError: function(oError) { {$this->buildJsShowMessageError('oError.message || oError.toString()')}; },
    onAjaxError: {$this->buildJsJsonEditorOnAjaxError()},
    onSuccess: function(sMessage) { {$this->buildJsShowMessageSuccess('sMessage')}; },
    onChange: {$this->buildJsJsonEditorOnChange()},
    options: {
        mainMenuBar: {$this->escapeBool(! $widget->isMinimalistic())},
        navigationBar: {$this->escapeBool(! $widget->isMinimalistic())}
    }
}
JS;
    }

    /**
     * Facades may restrict the custom editor endpoints to a compatible backend.
     * Connectivity is checked separately by the shared adapter on every request.
     */
    protected function buildJsJsonEditorServerAvailable() : string
    {
        return 'function() { return true; }';
    }

    /**
     * Lets the facade render server-provided HTML error widgets, not just their text.
     */
    protected function buildJsJsonEditorOnAjaxError() : string
    {
        return 'function(oRequest, domModal) { '
            . $this->buildJsShowError('oRequest.responseText', 'oRequest.status + " " + oRequest.statusText')
            . ' }';
    }

    /**
     * Forwards edits to the facade's regular change handlers.
     */
    protected function buildJsJsonEditorOnChange() : string
    {
        return 'function() { $("#' . $this->getId() . '").trigger("change"); }';
    }

    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter(string|WidgetLinkCoordinateInterface|null $coord = null) : string
    {
        $coord = WidgetLinkFactory::createCoordinateFromAnything($coord);
        $this->validateValueGetterCoordinate($coord);
        return $this->buildJsEditorGetter() . '.getValue()';
    }

    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value) : string
    {
        return $this->buildJsEditorGetter() . '.setValue(' . $value . ')';
    }

    /**
     * Returns the JS dependencies in load order.
     *
     * @return string[]
     */
    protected function getJsonEditorJsIncludes() : array
    {
        $facade = $this->getFacade();
        return [
            $facade->buildUrlToSource('LIBS.JSONEDITOR.JS'),
            $facade->buildUrlToSource('LIBS.JSONEDITOR.PICOMODAL'),
            $facade->buildUrlToVendorFile('exface/core/Facades/AbstractAjaxFacade/js/exfJsonEditor.js')
        ];
    }

    /**
     * Returns shared styles for the editor and its popups.
     *
     * @return string[]
     */
    protected function getJsonEditorCssIncludes() : array
    {
        $facade = $this->getFacade();
        return [
            $facade->buildUrlToSource('LIBS.JSONEDITOR.CSS'),
            $facade->buildUrlToSource('LIBS.MARKDOWN.CSS'),
            $facade->buildUrlToVendorFile('exface/core/Facades/AbstractAjaxFacade/css/exfJsonEditor.css')
        ];
    }

    /**
     * Builds dependency tags for HTML-based facades.
     */
    protected function buildHtmlHeadTagsForJsonEditor() : array
    {
        $includes = [];
        foreach ($this->getJsonEditorJsIncludes() as $url) {
            $includes[] = '<script src="' . $url . '"></script>';
        }
        foreach ($this->getJsonEditorCssIncludes() as $url) {
            $includes[] = '<link href="' . $url . '" rel="stylesheet">';
        }
        return $includes;
    }

    /**
     * Collects UXON editor translations as data, not interpolated JS source.
     */
    protected static function getTranslations(WorkbenchInterface $workbench, string $prefix = 'WIDGET.UXONEDITOR') : array
    {
        $trans = [];
        foreach ($workbench->getCoreApp()->getTranslator()->getDictionary() as $key => $text) {
            if (strpos($key, $prefix . '.') === 0) {
                $trans[substr($key, strlen($prefix) + 1)] = $text;
            }
        }
        return $trans;
    }

    /**
     * Builds the live root prototype getter for UXON autosuggest.
     */
    protected function buildJsRootPrototypeGetter() : string
    {
        $widget = $this->getWidget();
        if ($widget instanceof InputUxon && ($expr = $widget->getRootPrototype()) !== null) {
            if ($expr->isReference()) {
                $link = $expr->getWidgetLink($widget);
                return $this->getFacade()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetCoordinate());
            }
            return $this->escapeString(trim($expr->toString(), "'\""), true, false);
        }
        return '""';
    }

    /**
     * Builds the live UXON schema getter.
     */
    protected function buildJsSchemaGetter() : string
    {
        $widget = $this->getWidget();
        if ($widget instanceof InputUxon) {
            $expr = $widget->getSchemaExpression();
            if ($expr->isReference()) {
                $link = $expr->getWidgetLink($widget);
                return $this->getFacade()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetCoordinate());
            }
            return $this->escapeString(trim($expr->toString(), "'\""), true, false);
        }
        return '""';
    }

    /**
     * Builds the live root object getter for UXON autosuggest.
     */
    protected function buildJsRootObjectGetter() : string
    {
        $widget = $this->getWidget();
        if ($widget instanceof InputUxon && ($expr = $widget->getRootObject()) !== null) {
            if ($expr->isReference()) {
                $link = $expr->getWidgetLink($widget);
                return $this->getFacade()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetCoordinate());
            }
            return $this->escapeString(trim($expr->toString(), "'\""), true, false);
        }
        return '""';
    }

    /**
     * Stores runtime required state in the adapter.
     */
    protected function buildJsSetRequired(bool $required) : string
    {
        return $this->buildJsEditorGetter() . '.setRequired(' . $this->escapeBool($required) . ')';
    }

    /**
     * Reads runtime required state in the adapter.
     */
    protected function buildJsRequiredGetter() : string
    {
        return $this->buildJsEditorGetter() . '.getRequired()';
    }

    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsSetDisabled()
     */
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        return $this->buildJsEditorGetter() . '.setDisabled(' . $this->escapeBool($trueOrFalse) . ')';
    }

    /**
     * Restores scroll after a lazy-rendered tab becomes visible.
     */
    protected function buildJsRememberScrollPos() : string
    {
        return '(function(oHost) { if (oHost && oHost.exfJsonEditor) { oHost.exfJsonEditor.restoreScroll(); } })'
            . '(document.getElementById(' . $this->escapeString($this->getJsonEditorId(), true, false) . '));';
    }

    /**
     * Returns the prefilled row identity for scroll memory.
     */
    protected function getJsonEditorScrollKey() : ?string
    {
        $sheet = $this->getWidget()->getPrefillData();
        if ($sheet === null || $sheet->isEmpty() || ! ($uidCol = $sheet->getUidColumn())) {
            return null;
        }
        $uid = $uidCol->getValue(0);
        return $uid === null || $uid === '' ? null : (string) $uid;
    }
}
