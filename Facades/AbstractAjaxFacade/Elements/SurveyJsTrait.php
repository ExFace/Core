<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputFormDesigner;

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
        return "$('#{$this->getId()}').data('exf_survey')";
    }
    
    protected function buildJsCreatorVar() : string
    {
        return "$('#{$this->getId()}').data('exf_survey_creator')";
    }
    
    /**
     * 
     * @param string $oSurveyJs
     * @return string
     */
    protected function buildJsSurveyInitOptions(string $oSurveyJs = 'oSurvey') : string
    {
        $disableJs = $this->getWidget()->isDisabled() ? "{$oSurveyJs}.mode = 'display';" : '';
        return <<<JS
        
    $oSurveyJs.locale = '{$this->getSurveyLocale()}';    
    $disableJs
JS;
    }
    
    protected function buildJsSurveySetup() : string
    {
        return <<<JS

Survey.StylesManager.applyTheme("default");

JS;
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
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SURVEY.SURVEY_CSS') . '">';
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SURVEY.THEME_CSS') . '">';
        
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
        return $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
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
        {$this->buildJsSurveyInitOptions('oSurvey')};
        oSurvey.render(jqContainer[0]);
        jqContainer.data('survey-config', oConfig);
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
        return "JSON.stringify({$this->buildJsSurveyVar()}.data)";
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
     * @return string
     */
    protected abstract function buildJsSurveyModelGetter() : string;
}