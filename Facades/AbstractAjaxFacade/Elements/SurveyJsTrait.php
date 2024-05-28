<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputFormDesigner;
use exface\Core\DataTypes\LocaleDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\DataSheets\DataColumn;

/**
 * Helps implement InputForm and InputFormDesigner widgets with Survey JS and jQuery.
 *
 * See
 * 
 * - https://surveyjs.io/
 * - UI5InputForm (UI5 facade) for an example of a survey element
 * - UI5InputFormDesigner (UI5 facade) for the survey creator element
 *
 * To use this trait, you will need the following JS dependency:
 *
 * ```
 * "npm-asset/survey-core": "^1",
 * "npm-asset/survey-knockout": "^1",
 * "npm-asset/survey-creator": "^1"
 *
 * ```
 *
 * If your facade is based on the `AbstractAjaxFacade`, add these configuration options
 * to the facade config file. Make sure, each config option points to an existing
 * inlcude file!
 *
 * ```
 * 	"LIBS.SURVEY.KNOCKOUT_JS": "npm-asset/knockout/build/output/knockout-latest.js",
 * 	"LIBS.SURVEY.SURVEY_JS": "npm-asset/survey-knockout/survey.ko.min.js",
 * 	"LIBS.SURVEY.SURVEY_JS_I18N": "npm-asset/survey-core/survey.i18n.min.js",
 * 	"LIBS.SURVEY.SURVEY_CSS": "npm-asset/survey-knockout/survey.min.css",
 * 	"LIBS.SURVEY.THEME_CSS": "<path_to_your_theme>",
 * 	"LIBS.SURVEY.CREATOR_JS": "npm-asset/survey-creator/survey-creator.min.js",
 * 	"LIBS.SURVEY.CREATOR_CSS": "npm-asset/survey-creator/survey-creator.min.css",
 *	"LIBS.SURVEY.CREATOR_ACE_JS": [
 *		"npm-asset/ace-builds/src-min/ace.js",
 *		"npm-asset/ace-builds/src-min/ext-language_tools.js"
 *	],
 *
 * ```
 *
 * @link https://surveyjs.io/
 *
 * @author Andrej Kabachnik
 *
 * @method \exface\Core\Widgets\InputForm getWidget()
 *
 */
trait SurveyJsTrait
{
    // use JsUploaderTrait;
    
    /**
     *
     * @param string $oParamsJs
     * @param string $onUploadCompleteJs
     * @return string
     */
    //protected abstract function buildJsUploadSend(string $oParamsJs, string $onUploadCompleteJs) : string;
    
    /**
     * Returns the HTML code of the carousel.
     *
     * @return string
     */
    protected function buildHtmlSurveyDiv() : string
    {
        return <<<HTML
        
<div id="{$this->getIdOfSurveyDiv()}"></div>

HTML;
    }
    
    protected function buildHtmlCreatorDiv() : string
    {
        return <<<HTML
        
<div id="{$this->getIdOfCreatorDiv()}"></div>

HTML;
    }
    
    protected function getIdOfSurveyDiv() : string
    {
        return $this->getId() . '_survey';
    }
    
    protected function getIdOfCreatorDiv() : string
    {
        return $this->getId() . '_survey';
    }
    
    protected function buildJsSurveyVar() : string
    {
        return "$('#{$this->getId()}')[0].exf_survey";
    }
    
    protected function buildJsCreatorVar() : string
    {
        return "$('#{$this->getId()}')[0].exf_survey_creator";
    }
    
    /**
     * 
     * @param string $oSurveyJs
     * @return string
     */
    protected function buildJsSurveyInit(string $oSurveyJs = 'oSurvey') : string
    {
        $disableJs = $this->getWidget()->isDisabled() ? "{$oSurveyJs}.mode = 'display';" : '';
        return <<<JS
        
    $oSurveyJs.locale = '{$this->getSurveyLocale()}';  
    $oSurveyJs.focusFirstQuestionAutomatic = false;  
    $disableJs
JS;
    }
    
    protected function buildJsSurveySetup() : string
    {
        return <<<JS

Survey.StylesManager.applyTheme({$this->buildJsSurveyTheme()});

JS;
    }
    
    protected function buildJsSurveyTheme() : string
    {
        return '"default"';
    }
    
    public function buildJsSetDisabled(bool $trueOrFalse) : string
    {
        $mode = $trueOrFalse ? 'display' : 'edit';
        return "{$this->buildJsSurveyVar()}.mode = '{$mode}'";
    }
    
    /**
     * Returns an array of <head> tags required for this trait to work
     *
     * @return string[]
     */
    protected function buildHtmlHeadTagsForSurvey()
    {
        $includes = [];
        $facade = $this->getFacade();
        
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.SURVEY.KNOCKOUT_JS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.SURVEY.SURVEY_JS') . '"></script>';
        if ($facade->getConfig()->hasOption('LIBS.SURVEY.THEME_SCRIPT')) {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.SURVEY.THEME_SCRIPT') . '"></script>';
        }
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SURVEY.SURVEY_CSS') . '">';
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SURVEY.THEME_CSS') . '">';
        
        if ($this->getSurveyLocale() !== 'en') {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.SURVEY.SURVEY_JS_I18N') . '"></script>';
        }
        
        if ($this->getWidget() instanceof InputFormDesigner) {
            foreach ($facade->getConfig()->getOption('LIBS.SURVEY.CREATOR_ACE_JS') as $url) {
                $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToVendorFile($url) . '"></script>';
            }
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.SURVEY.CREATOR_JS') . '"></script>';
            $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SURVEY.CREATOR_CSS') . '">';
        }
        
        return $includes;
    }
    
    protected function getSurveyLocale() : string
    {
        return LocaleDataType::findLanguage($this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale());
    }
    
    /**
     *
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value)
    {
        // Make sure not to re-render the survey if its model is still the same! Otherwise
        // any unsaved input will be lost every time the value setter is called - which
        // in most cases does NOT change the survery model, but merely its data (= answers)
        return <<<JS
(function(jqContainer){
    var oConfig = {$this->buildJsSurveyModelGetter()};
    var oConfigCurrent = jqContainer.data('survey-config');
    var oSurvey = {$this->buildJsSurveyVar()} || null;

    if (oSurvey === null || oConfigCurrent !== oConfig) {
        oSurvey = new Survey.Model(oConfig);
        {$this->buildJsSurveyInit('oSurvey')};
        oSurvey.render(jqContainer[0]);
        jqContainer.data('survey-config', oConfig);

        oSurvey.onValueChanged.add(function(oEvent){
            {$this->getOnChangeScript()}
        });

        {$this->buildJsSurveyVar()} = oSurvey;
    }

    oSurvey.data = (JSON.parse({$value} || '{}'));
})($('#{$this->getIdOfSurveyDiv()}'))

JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return <<<JS
        function() {
            if ({$this->buildJsSurveyVar()} != null) {
                return JSON.stringify({$this->buildJsSurveyVar()}.data);
            }
            return '';
        }()

JS;
    }
    
    /**
     * 
     * @param string $value
     * @return string
     */
    public function buildJsCreatorValueSetter(string $value) : string
    {
        return "{$this->buildJsCreatorVar()}.text = ($value || '')";
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsCreatorValueGetter() : string
    {
        return "{$this->buildJsCreatorVar()}.text";
    }
    
    /**
     * 
     * @param string $oOptionsJs
     * @return string
     */
    protected function buildJsCreatorOptions(string $oOptionsJs = 'oOptions') : string
    {
        return <<<JS
        
                $oOptionsJs.showLogicTab = true;
JS;
    }
    
    /**
     * Returns the JS code to setup the SurveyCreator global
     * 
     * @return string
     */
    protected function buildJsCreatorSetup() : string
    {
        return <<<JS
        
                SurveyCreator.StylesManager.applyTheme({$this->buildJsSurveyTheme()});
                SurveyCreator.localization.currentLocale = "{$this->getSurveyLocale()}";

JS;
    }
    
    protected function buildJsCreatorInit(string $oCreatorJs = 'oCreator') : string
    {
        return <<<JS
        
                //Show toolbox in the right container. It is shown on the left by default
                $oCreatorJs.showToolbox = "left";
                //Show property grid in the right container, combined with toolbox
                $oCreatorJs.showPropertyGrid = "right";
                
                $oCreatorJs.onQuestionAdded.add(function(_, options) {
                    options.question.hideNumber = true
                    switch (options.question.jsonObj.type) {
                        case 'text':
                            options.question.titleLocation = 'left';
                            break;
                        case 'dropdown':
                            options.question.titleLocation = 'left';
                            break;
                        case 'boolean':
                            options.question.titleLocation = 'left';
                            break;
                        case 'file':
                            options.question.titleLocation = 'left';
                            break;
                    }
                });
                
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected abstract function buildJsSurveyModelGetter() : string;
    
    /**
     * 
     * @param string $valueJs
     * @return string
     */
    protected abstract function buildJsSurveyModelSetter(string $valueJs) : string;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryInputValidationTrait::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        // Always validate the form - even if the widget is not required explicitly. Otherwise required
        // fields inside the form will not produce validation errors if the InputForm is not explicitly
        // marked as required
        $bRequiredJs = $this->getWidget()->isRequired() === true ? 'false' : 'true';
        return <<<JS
(function(oSurvey){
    if (oSurvey === undefined) {
        return $bRequiredJs;
    }
    return oSurvey.validate()
})({$this->buildJsSurveyVar()})
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValidationError()
     */
    public function buildJsValidationError()
    {
        // No need to do anything here - the .validate() method of Survey.js already shows the errors
        return '';
    }
    
    /**
     * @return void
     */
    protected function registerSurveyLiveConfigAtLinkedElement()
    {
        $widget = $this->getWidget();
        if ($widget->isFormConfigBoundByReference() === true) {
            $link = $widget->getFormConfigExpression()->getWidgetLink($widget);
            $linkedEl = $this->getFacade()->getElement($link->getTargetWidget());
            
            $col = $link->getTargetColumnId();
            if (! StringDataType::startsWith($col, '~')) {
                $col = DataColumn::sanitizeColumnName($col);
            }
            if ($link->isOnlyIfNotEmpty()) {
                $getAndSetValueJs = <<<JS
                
(function() {
    var mVal = {$linkedEl->buildJsValueGetter($col, $link->getTargetRowNumber())};
    if (mVal !== undefined && mVal !== '' && mVal != null) {
        {$this->buildJsSurveyModelSetter('mVal')};
    }
})();
JS;
        
            } else {
                $getAndSetValueJs = '
					   ' . $this->buildJsSurveyModelSetter($linkedEl->buildJsValueGetter($col, $link->getTargetRowNumber())) . ';';
            }
            
            // If the link targets a specific row, activate it with every refresh,
            // otherwise it targets the current value, so only activate it if the value changes
            if (null !== $link->getTargetRowNumber()) {
                $linkedEl->addOnRefreshScript($getAndSetValueJs);
            } else {
                $linkedEl->addOnChangeScript($getAndSetValueJs);
            }
        }
        return;
    }
}