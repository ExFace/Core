<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputUxon;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\DocsFacade;
use exface\Core\CommonLogic\Workbench;

/**
 * This trait helps use the JsonEditor library to create InputJson and InputUxon widgets.
 * 
 * ## How to use
 * 
 * Include the following dependencies in composer.json of the app, where the trait is used:
 * 
 * ```
 * require: {
 *	"npm-asset/jsoneditor" : "^6.1||^7.0",
 *	"npm-asset/picomodal" : "^3.0.0",
 *	"npm-asset/mobius1-selectr" : "^2.4.12"
 * }
 * 
 * ```
 * 
 * Add paths to the dependencies to the configuration of the facade:
 * 
 * ```
 *  "LIBS.JSONEDITOR.JS": "npm-asset/jsoneditor/dist/jsoneditor.min.js",
 *  "LIBS.JSONEDITOR.CSS": "npm-asset/jsoneditor/dist/jsoneditor.min.css",
 *  "LIBS.JSONEDITOR.PICOMODAL": "npm-asset/picomodal/src/picoModal.js",
 *  "LIBS.JSONEDITOR.SELECTR.JS": "npm-asset/mobius1-selectr/src/selectr.js",
 *  "LIBS.JSONEDITOR.SELECTR.CSS": "npm-asset/mobius1-selectr/src/selectr.css",
 *  
 * ```
 * 
 * @method InputJson getWidget()
 * @method WorkbenchInterface getWorkbench()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JsonEditorTrait
{
    use JqueryInputValidationTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssHeightDefaultValue()
     */
    

    protected function buildCssHeightDefaultValue()
    {
        return ($this->getHeightRelativeUnit() * 5) . 'px';
    }
    
    /**
     * Returns javascript code for adding UXON editor preset hint on top toolbar.
     * Both the function call and help content URL are constructed using a given markdown file as target
     *
     * @return string
     */
    protected function buildJsPresetHint(string $editorIdJs) : string
    {
        $funcPrefix = $this->buildJsFunctionPrefix();
        $addPresetHint = static::buildJsFunctionNameAddPresetHint($funcPrefix);
     
        return <<<JS
        
                 {$addPresetHint}({$editorIdJs});   
JS;
        
    }
                 
    public static function buildJsPresetHintTrigger(string $uxonEditorIdJs, string $jsonJs) : string
    {
        $showJs = static::buildJsPresetHintShow($uxonEditorIdJs);
        $hideJs = static::buildJsPresetHintHide($uxonEditorIdJs);
        return <<<JS
        if ($jsonJs && $jsonJs.constructor === Object && Object.keys($jsonJs).length === 0) {
            {$showJs}
        } else {
            {$hideJs}
        }
        
JS;
    }
    
    protected static function buildJsPresetHintHide(string $uxonEditorIdJs) : string
    {
        return "$('#' + {$uxonEditorIdJs} + ' .uxoneditor-preset-hint').hide()";
    }
    
    protected static function buildJsPresetHintShow(string $uxonEditorId) : string
    {
        // TODO this only works if the preset hint is present. When switching editor modes, it gets removed,
        // so it would be better to remove/add the hint instead of hiding/showing it.
        return "$('#' + {$uxonEditorId} + ' .uxoneditor-preset-hint').show()";
    }
    
    /**
     * Returns javascript code for adding UXON editor help on top toolbar.
     * Both the function call and help content URL are constructed using a given markdown file as target
     *
     * @return string
     */
    protected function buildJsEditorAddHelpButton() : string
    {
        if (! $this->getWidget() instanceof InputUxon) {
            return '';
        }
        
        $addHelpButtonFunction = $this::buildJsFunctionNameAddHelpButton($this->buildJsFunctionPrefix());
        /* @var \exface\Core\Facades\DocsFacade $docsFacade */
        $docsFacade = FacadeFactory::createFromAnything(DocsFacade::class, $this->getWorkbench());
        $url = $docsFacade->buildUrlToFacade() . '/exface/Core/Docs/Creating_UIs/UXON/Introduction_to_the_UXON_editor.md';
        $workbench = $this->getWorkbench();
        $buttonTitleHelp = static::translateJsUxonEditorTerm($workbench, 'HELP');
        
        return <<<JS
                
        			{$addHelpButtonFunction}(
        				$,
        				"{$this->getId()}",
        				"{$url}",
        				"{$buttonTitleHelp}"
        			);
JS;
    }
    
    
    protected function buildJsJsonEditor() : string
    {
        $widget = $this->getWidget();
        $uxonEditorId = $this->getId();
        
        if ($widget instanceof InputUxon) {
            $uxonInitScripts = <<<JS

                    {$this->buildJsEditorAddHelpButton()}
        			{$this->buildJsPresetHint("'{$uxonEditorId}'")}
                    setTimeout(function(){
                        var json = {$uxonEditorId}_JSONeditor.get();
                        {$this::buildJsPresetHintTrigger("'{$uxonEditorId}'", 'json')}
                    }, 0);

JS;
        } else {
            $uxonInitScripts = '';
        }
       
        return <<<JS
                   var {$uxonEditorId}_JSONeditor = new JSONEditor(
                        document.getElementById("{$uxonEditorId}"),
                        { 
                            {$this->buildJsEditorOptions()}
                        },
        
                        {$this->getValueWithDefaults()}
                    );
        
                    {$uxonEditorId}_JSONeditor.expandAll();
                    $('#{$uxonEditorId}').parents('.exf-input').children('label').css('vertical-align', 'top');
                    {$uxonInitScripts}
        			
        
JS;
    }
    

    /**
     *
     * @return string
     */
    protected function buildJsEditorModeDefault($isWidgetDisabled) : string
    {
        if ($isWidgetDisabled) {
            return "'view'";
        }
        return "'tree'";
    }
    
    
    /**
     *
     * @return string
     */
    protected function buildJsEditorModes($isWidgetDisabled) : string
    {
        if ($isWidgetDisabled) {
            return "['view']";
        }
        return "['code', 'tree']";
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsOnModeChangeFunction($uxonEditorId, $funcPrefix) : string
    {
        if (! $this->getWidget() instanceof InputUxon) {
            $script = '';
        } else {
            $script = <<<JS

            if (newMode === 'tree') {
                var json = {$uxonEditorId}_JSONeditor.get();
                {$this::buildJsPresetHintTrigger($uxonEditorId, 'json')}
            }
            {$this::buildJsPresetHintHide($uxonEditorId)}

JS;
        }
        
        return <<<JS
        
        function(newMode, oldMode){
            $script
        }    

JS;
    }
    
   
    protected function buildJsEditorOptions() : string
    {
        $widget = $this->getWidget();
        $isWidgetDisabled = $widget->isDisabled();
        
        $funcPrefix = $this->buildJsFunctionPrefix();
        $uxonEditorId = $this->getId();
        $uxonSchemaJs = $this->buildJsSchemaGetter();
        $workbench = $this->getWorkbench();
        
        if (($widget instanceof InputUxon) && $widget->getAutosuggest() === true) {
            $uxonEditorOptions = $this::buildJsUxonEditorOptions("'{$uxonEditorId}'", $uxonSchemaJs, $funcPrefix, $workbench);
        } else {
            $uxonEditorOptions = '';
        }
        
	return <<<JS

    onError: {$this->buildJsOnErrorFunction()},
    mode: {$this->buildJsEditorModeDefault($isWidgetDisabled)},
    modes: {$this->buildJsEditorModes($isWidgetDisabled)},
    onModeChange: {$this->buildJsOnModeChangeFunction($uxonEditorId, $funcPrefix)},
    onChangeJSON: {$this->buildJsOnChangeFunction($uxonEditorId, $funcPrefix)},
    {$uxonEditorOptions}

JS;
    }
    
    protected function buildJsOnChangeFunction() : string
    {
        // TODO add getOnChangeScript() somewhere here. 
        $fn = '';
        if ($this->getWidget() instanceof InputUxon) {
            $fn .= $this->buildJsPresetHintTrigger("'{$this->getId()}'", 'json');
        }
        return "function(json) { $fn }";
    }
    
    protected function buildJsOnErrorFunction() : string
    {
    
        $workbench = $this->getWorkbench();
        $errorShowingError = static::translateJsUxonEditorTerm($workbench, 'ERROR.SHOW_ERROR');
        
    return <<<JS
                        function (err) {
                            try{
                                {$this->buildJsShowMessageError('err.toString()')};
                            }
                            catch{
                                console.error("{$errorShowingError}", err);
                            }
                        }
JS;
    }
    
    /**
     * Building the options for UXON editor including filter function and error handler
     * 
     * @param string $uxonSchema
     * @param string $funcPrefix
     * @return string
     */
    public static function buildJsUxonEditorOptions(string $editorIdJs, string $uxonSchema, string $funcPrefix, Workbench $workbench) : string
    {   
        $trans = static::getTranslations($workbench);
        
        return <<<JS
                    name: ({$uxonSchema} === 'generic' ? 'UXON' : {$uxonSchema}),
                    enableTransform: false,
                    enableSort: false,
                    history: true,
                    autocomplete: 
                    {
                        applyTo: ['value'],
                        filter: function (token, match, config) {
                            // remove leading space in token if not the only character
                            if (  token.length > 1 && ( token.search(/^\s[^\s]/i) > -1 ) ) {
                         		token = token.substr(1, token.length - 1);
                            }
                            
                            //remove spaces in token if preceeded by double underscores
                            if (  token.length > 3  && token.search(/\_\_\s/i) ) {
                                token = token.substr(0, token.length - 1);
                            } else if (!token.replace(/\s/g, '').length) {
                                // return true if token consists of whitespace characters only
                                return true;
                            }
                           return match.indexOf(token) > -1;
                        },
                
                        getOptions:  function (text, path, input, editor) {
                            return new Promise(function (resolve, reject) {
                               var pathBase = path.length <= 1 ? '' : JSON.stringify(path.slice(-1));
                               if (editor._autosuggestPending === true) {
                                        if (editor._autosuggestLastResult && editor._autosuggestLastPath == pathBase) {
                                            resolve({$funcPrefix}_filterAutosuggest(editor._autosuggestLastResult.values, text));
                                        } else {
                                            reject();
                                        }
                                } else {
                                        editor._autosuggestPending = true;
                                        var uxon = JSON.stringify(editor.get());
                                        return {$funcPrefix}_fetchAutosuggest(text, path, input, uxon)
                                        .then(json => {                                          
                                            editor._autosuggestPending = false;
                                            if (json === undefined) {
                                                reject();
                                            }
                                            
                                            // Cache response data
                                            editor._autosuggestLastPath = pathBase;
                                            editor._autosuggestLastResult = json;
                                            
                                            // If there are values for the autosuggest, call resolve()
                                            if (json.values !== undefined ){
                                                resolve({$funcPrefix}_filterAutosuggest(json.values, text));
                                            }
                                            
                                            // return response data for further processing
                                            return json;
                                        })
                                       .catch((err) => { 
                                            editor._autosuggestPending = false;
                                            console.warn("{$trans['ERROR.AUTOSUGGEST_FAILED']}", err);
                                       });
                    	           }
                                })
                                .catch((err) => {
                                    editor._autosuggestPending = false;
                                    console.warn("{$trans['ERROR.GETTING_OPTIONS']}", err);
                                    return Promise.resolve([]);
                                });
                            }
                        },
                        onCreateMenu : function (items, node){
                            var path = node.path;
                            var rootNode = {$funcPrefix}_getNodeFromTarget( $('#' + {$editorIdJs} + ' .jsoneditor-tree tr:first-of-type td:last-of-type .jsoneditor-readonly').get()[0]);
                            var menuNode = path.length > 0 ? rootNode.findNodeByPath(node.path) : rootNode;
                            
                            var val = menuNode.getValue();
                            var menuNodeType = {$funcPrefix}_getNodeType(menuNode);
                            
                            // Add preset button if applicable
                            // ist objekt oder wert === leer                            
                            if(menuNodeType === "object" || menuNodeType === "root") {
                                items.unshift(
                                {
                                    text : "<i class=\"fa fa-magic\"></i>{$trans['PRESETS.TITLE']}",   // the text for the menu item
                                    title : "{$trans['PRESETS.TITLE']}",  // the HTML title attribute
                                    className : "jsoneditor-no-menuicon jsoneditor-type-object active-button", // the css class name(s) for the menu item
                                    click: function(){ 
                                        return {$funcPrefix}_openPresetsModal(menuNode); 
                                    }
                                });
                            }

                            // Add details button if applicable
                            if(menuNodeType === "object" || menuNodeType === "root") {
                                items.unshift(
                                {
                                    text : "<i class=\"fa fa-th-list\"></i>{$trans['DETAILS.TITLE']}",   // the text for the menu item
                                    title : "{$trans['DETAILS.TITLE']}",  // the HTML title attribute
                                    className : "jsoneditor-no-menuicon jsoneditor-type-object active-button", // the css class name(s) for the menu item
                                    click: function(){ 
                                        return {$funcPrefix}_openDetailsModal(menuNode); 
                                    }
                                });
                            }
                            
                            // Add menu item for copying current JSON path always
                        	items.push({
                                text: "{$trans['JSON_PATH']}",   // the text for the menu item
                                title: "{$trans['JSON_PATH']}",  // the HTML title attribute
                                className: "jsoneditor-type-object active-button" ,     // the css class name(s) for the menu item
                                click : function() { 
                                    return {$funcPrefix}_openJsonPathViewModal(menuNode); 
                                }
                            });
                            
                            return items;
                        } // onCreateMenu
JS;
    }
            
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter() : string
    {
        return 'function(){var text = ' . $this->getId() . '_JSONeditor.getText(); if (text === "{}" || text === "[]") { return ""; } else { return text;}}';
    }
    
    
    public static function buildCssModalStyles() : string
    {
        return <<<CSS
        
                    .jsoneditor-modal .jsoneditor table td {
                        padding: 0;
                        margin-top: 5px !important;
                    }
                    .active-button{ /
                        opacity: 1.0 !important;
                    }
                    .deactivate-button{
                        opacity: 0.3 !important;
                    }
                    .jsoneditor-modal.jsoneditor-modal-maximized { 
                        width: 100% !important;
                        height: 95%;
                    }
                    .jsoneditor-modal.jsoneditor-modal-nopadding {
                        padding: 30px 0 0 0 !important;
                                    }
                    .jsoneditor-modal.jsoneditor-modal-nopadding iframe{
                        width: calc(100% - 16px) !important;
                        height: calc(100% - 11px);
                        border: 0;
                        padding: 5px 0 0 15px;
                        text-shadow: 2px;
                    
                    }

                    .jsoneditor-modal 
                    .pico-modal-contents {height: 100%; width: 100%;}
                                        
                    .jsoneditor-modal .uxoneditor-input, 
                    .spinner-wrapper { 
                        font-family: "dejavu sans mono", "droid sans mono", consolas,
							monaco, "lucida console", "courier new", courier, monospace,
							sans-serif;
                        font-size: 10pt;
                        width: 100%;
                        box-sizing: border-box;
                    }
                    .jsoneditor-modal .uxoneditor-input,
                    .jsoneditor-modal .selectr-selected,
                    .spinner-wrapper {
                        height: 35px;
                        margin-bottom: 4px;
                    }
                    .selectr-selected {
                        line-height: 25px;
                    }
                    .jsoneditor-modal .jsoneditor input {
                        padding: inherit;
                    }
                    .jsoneditor-modal.jsonPathView textarea {
                        width: 300px;
                        height: 100px;
                    }
                    .jsoneditor-modal input:read-only,
					.jsoneditor-modal textarea:read-only {
                        background-color:  #f5f5f5;
                    }
                    .jsoneditor-modal input[type="submit"],
                    .jsoneditor-modal input[type="button"] {
                        width: auto;
                        font-family: inherit;
                    }
                    .jsoneditor-modal .action-buttons {
                        float: right;
                    }

                    .jsoneditor-modal table.jsoneditor-values {width: initial;}

                    .jsoneditor-no-menuicon .jsoneditor-icon {display: none;}
                    .jsoneditor-no-menuicon .jsoneditor-text {padding-left: 4px !important;}
                    .jsoneditor-no-menuicon i {padding: 2px; background-color: #4C4C4C; margin-right: 4px; color: white;}

                    .spinner {
                        display: inline-block;
                        width: 16px;
                        height: 16px;
                        border: 3px solid lightgray;
                        border-radius: 50%;
                        border-top-color: gray;
                        animation: spin 1s ease-in-out infinite;
                        -webkit-animation: spin 1s ease-in-out infinite;
                    }
                    .spinner-wrapper {
                        position: absolute;
                        width: 100%;
                        background-color: #f5f5f5;
                        border: 1px solid #d3d3d3;
                        border-radius: 3px;
                        z-index: 100;
                        text-align: center;
                        padding: 4px 0 0 0;
                    }
                    @keyframes spin {
                        to { -webkit-transform: rotate(360deg); }
                    }
                    @-webkit-keyframes spin {
                        to { -webkit-transform: rotate(360deg); }
                    }

                    .uxoneditor-preset-hint {
                        font-size: initial;
                        padding: 4px;
                        margin: 10px;
                        width: calc(100% - 20px);
                        text-align: center;
                        position: absolute;
                        top: calc(40% - 50px);
                    }
                    .uxoneditor-preset-hint a {color: #ccc; text-decoration: none;}
                    .uxoneditor-preset-hint a:hover {color: #1a1a1a;}
                    .uxoneditor-preset-hint i {display: block; font-size: 400%; margin-bottom: 15px;}

                    .uxoneditor-checkbox {-webkit-appearance: checkbox; -moz-appearance: checkbox;}
                    .uxoneditor-details-table {margin-bottom: 20px}
                    .uxoneditor-details-table th {font-weight: bold !important; padding: 3px !important; border-bottom: 1px solid #3883fa}
                    .uxoneditor-details-table td {padding: 3px !important;}
                    .uxoneditor-details-table p {margin: 0.3em 0 0.7em 0;}
                    .uxoneditor-details-table code {
                        padding: 0.2em 0.4em;
                        margin: 0;
                        font-size: 85%;
                        background-color: rgba(27,31,35,0.05);
                        border-radius: 3px;
                    }
                    .uxoneditor-details-table pre {
                        padding: 16px;
                        overflow: auto;
                        font-size: 85%;
                        line-height: 1.45;
                        background-color: #f6f8fa;
                        border-radius: 3px;
                    }
                    .uxoneditor-details-table pre code {
                        display: inline;
                        max-width: auto;
                        padding: 0;
                        margin: 0;
                        overflow: visible;
                        line-height: inherit;
                        word-wrap: normal;
                        background-color: transparent;
                        border: 0;
                    }

    
                    .uxoneditor-object-details-title {margin: 0.3em 0 0.7em 0;}
                    .uxoneditor-object-details-description {margin: 0.3em 0 0.7em 0;}
                    
                    
CSS;
    }
    
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $facade = $this->getFacade();
        $includes[] = '<link href="exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.css" rel="stylesheet">';
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.js"></script>';
        $includes[] = '<link href="' . $facade->buildUrlToSource('LIBS.JSONEDITOR.CSS') . '" rel="stylesheet">';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource("LIBS.JSONEDITOR.JS") . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource("LIBS.JSONEDITOR.PICOMODAL") . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource("LIBS.JSONEDITOR.SELECTR.JS")  . '"></script>';
        $includes[] = '<link href="' . $facade->buildUrlToSource('LIBS.JSONEDITOR.SELECTR.CSS') . '" rel="stylesheet"/>';
        $includes[] = '<style type=' . '"text/css"' . '>' . $this::buildCssModalStyles() . '</style>';
        
        return $includes; 
	}
    
   /**
    * Returns translation for a specific UXON editor term using core app's translator.
    * Add new terms by maintaining "exface.Core.(en|de|ru).json" using prefix 'WIDGET.UXONEDITOR.'.
    * 
    * @param Workbench $workbench,
    * @param string $message_id
    * @return string
    * 
    * For usage of core app's translator:
    * @see \exface\Core\Interfaces\TranslationInterface::translate()
    */
    protected static function translateJsUxonEditorTerm($workbench, string $message_id) : string
    {   
        $translator = $workbench->getCoreApp()->getTranslator();
        
        return $translator->translate(trim('WIDGET.UXONEDITOR.' . $message_id), null, null);
    }
    
    protected static function getTranslations(WorkbenchInterface $workbench, string $prefix = 'WIDGET.UXONEDITOR') : array
    {
        $translator = $workbench->getCoreApp()->getTranslator();
        $trans = [];
        $prefixLen = strlen($prefix);
        foreach ($translator->getDictionary() as $key => $text) {
            if (substr($key, 0, $prefixLen) === $prefix) {
                $trans[substr($key, $prefixLen+1)] = $text;
            }
        }
        return $trans;
    }
    
    /**
     *
     * @param string $funcPrefix
     * @param string $uxonSchema
     * @param string $rootPrototype
     * @param string $rootObject
     * @param string $ajaxUrl
     * @param Workbench $workbench
     * @param string uxonEditorId
     * @return string
     */
    public static function buildJsUxonEditorFunctions(
        string $funcPrefix,
        string $uxonSchema,
        string $rootPrototype,
        string $rootObject,
        string $ajaxUrl,
        Workbench $workbench,
        string $uxonEditorIdJs
        ) : string
        {
            $addHelpButtonFunction = static::buildJsFunctionNameAddHelpButton($funcPrefix);
            $addPresetHint = static::buildJsFunctionNameAddPresetHint($funcPrefix);
            $onBlurFunctionName = static::buildJsFunctionNameOnBlur($funcPrefix);
            $presetHintHide = static::buildJsPresetHintHide($uxonEditorIdJs);
            $trans = static::getTranslations($workbench);
            
            return <<<JS
                
        var wrapData = {};
        var nodeIsWrappingTarget = false;
        var hasArrayContext = false;
        
        function {$funcPrefix}_fetchAutosuggest(text, path, input, uxon){
            var formData = new URLSearchParams({
        		action: 'exface.Core.UxonAutosuggest',
        		text: text,
        		path: JSON.stringify(path),
        		input: input,
        		schema: {$uxonSchema},
                prototype: {$rootPrototype},
                object: {$rootObject},
        		uxon: uxon
        	});
        	return fetch('{$ajaxUrl}',
                {
                    method: "POST",
                    mode: "cors",
                    cache: "no-cache",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
        	      body: formData, // body data type must match "Content-Type" header
        	   }
            )
          	.then(response => {
                if (
                    response
                    && response.ok
                    && response.status === 200
                    && response.headers
                    && ((response.headers.get('content-type') || '') === "application/json")
                ) {
                    return response.json();
                } else {
                    return Promise.reject({message: "{$trans['ERROR.MALFORMED_RESPONSE']}", response: response});
                }
            });
        }
        
        function {$funcPrefix}_openModal(
            title,
            contentHTML,
            focus = false,
            cssClass = '', 
            onAfterCreate
        ){
            if (!onAfterCreate) {
                onAfterCreate = function(modal) {
                };
            }
            var contentStr = 
                '<div class="pico-modal-contents">' +
                '   <div class="pico-modal-header">' + title + '</div>' + 
                    contentHTML +
                '</div>';
            
            picoModal({
                parent: window.document.body,
                title: title,
                content: contentStr,
                overlayClass: 'jsoneditor-modal-overlay',
                modalClass: 'jsoneditor-modal uxoneditor-modal ' + cssClass,
                focus: focus
            })
            .afterCreate(onAfterCreate)
            .afterClose(function (modal) {
                modal.destroy();
            })
            .show();
            
        }
        
        function {$funcPrefix}_getNodeFromTarget(target){
           while (target) {
        	    if (target.node) {
        	       return target.node;
        	    }
        	    target = target.parentNode;
           }
           
           return undefined;
        }
        
        function {$funcPrefix}_focusFirstChildValue(node, onlyEmptyValues){
            if (onlyEmptyValues === undefined) {
                onlyEmptyValues = false;
            }
        	var child, found;
        	for (var i in node.childs) {
        		child = node.childs[i];
        		if ((child.type === 'string' || child.type === 'auto') && (onlyEmptyValues === false || child.getValue() === undefined || child.getValue() === '')) {
        			child.focus(child.getField() ? 'value' : 'field');
                    return child;
        		} else {
        			found = {$funcPrefix}_focusFirstChildValue(child, onlyEmptyValues);
                    if (found) {
                        return found;
                    }
        		}
        	}
        	return false;
        }
        
        function {$onBlurFunctionName}(event){
            var editor = event.data.jsonEditor;
        	var node = {$funcPrefix}_getNodeFromTarget(this);
        	if (node.getValue() !== '') {
        		return;
        	}
        	var path = node.getPath();
        	var prop = path[path.length-1];
        	if (editor._autosuggestLastResult && editor._autosuggestLastResult.templates) {
        		var tpl = editor._autosuggestLastResult.templates[prop];
        		if (tpl) {
        			var val = JSON.parse(tpl);
        			node.setValue(val, (Array.isArray(val) ? 'array' : 'object'));
        			node.expand(true);
        			{$funcPrefix}_focusFirstChildValue(node);
        		}
        	}
        }
          function {$addHelpButtonFunction}($, editorId, url, title) {
            var helpBtn = $(
                '<button type="button" title="{$trans['HELP']}" style="background: transparent;"><i ' +
                'class="fa fa-question-circle-o" style="font-size: 22px"></i></button>'
            );
           
            $('#' + editorId + ' .jsoneditor-menu .jsoneditor-search').before(helpBtn);
        
            var helpBtnContent = '<iframe src="' + url + '"></iframe>';
            helpBtn.click(function() {
                return {$funcPrefix}_openModal(
                    title,
                    helpBtnContent,
                    false,
                     "jsoneditor-modal-nopadding jsoneditor-modal-maximized"
                );
            });
        }

        function {$addPresetHint}(editorId) {
            var presetHint = $(
                '<div class="uxoneditor-preset-hint">' +
                '   <a href="javascript:;">' + 
                '       <i class="fa fa-magic preset-hint-pulse" title="{$trans['PRESETS.HINT']}"></i>' + 
                '       {$trans['PRESETS.HINT']}' + 
                '   </a>' + 
                '</div>'
            );
            
            $("#" + editorId + " .jsoneditor-tree-inner").after(presetHint);
            
            $("#" + editorId + " .uxoneditor-preset-hint a").click( function(){
                var rootNode = {$funcPrefix}_getNodeFromTarget(
                    $(".jsoneditor-tree tr:first-of-type td:last-of-type .jsoneditor-readonly").get()[0]
                );
                return {$funcPrefix}_openPresetsModal(rootNode);
            });
        }
        
        function {$funcPrefix}_getJsonPathViewContent(){
            var jsonPathViewContent =
                '<div>' +
                '   <textarea id="jsonPathView" ' +
                '      class="preset-path uxoneditor-input"' +
                '      readonly> ' +
                '   </textarea>' +
                '</div>';
            return jsonPathViewContent;
        }
        
        function {$funcPrefix}_filterAutosuggest(aSuggestions, sSearch) {
            if (Array.isArray(aSuggestions) === false) {
                return aSuggestions;
            }
           sSearch = sSearch.toLowerCase();
            var aFiltered = [[],[],[]];
            var aResult = [];
            var iLen = aSuggestions.length;
            aSuggestions.forEach(sVal => {
                sValLower = sVal.toLowerCase();
                switch (true) {
                    case sValLower.startsWith(sSearch):
                        aFiltered[0].push(sVal);
                        break;
                    case sValLower.indexOf('.' + sSearch) > -1:
                        aFiltered[1].push(sVal);
                        break;
                    default:
                        aFiltered[2].push(sVal);
                }
            });
            
            aFiltered.forEach(aPart => {
                aResult = aResult.concat(aPart);
            });
            
            return aResult;
        }

        function {$funcPrefix}_convertToJsonPath(aPath){
            let str = '$';
            aPath.forEach(function(crumb) {
               if (crumb.toString().match(/\d+/)) {
                   str += '[' + crumb + ']';
               } else {
                   str += '.' + crumb;
               }
            });
            return str;
        }
        function {$funcPrefix}_convertToArrayPath(jsonPathString){
            let aSplit = jsonPathString.split('.');
            let aPath = [];
            aSplit.forEach(function(sPart){
                if (sPart === '$') return;
                if (sPart.endsWith(']')) {
                    let sPartSplit = sPart.split('[');
                    aPath.push(sPartSplit[0]);
                    aPath.push(sPartSplit[1].slice(0, -1));
                } else {
                    aPath.push(sPart);
                }
            });
            return aPath;
        }
        
        function {$funcPrefix}_openJsonPathViewModal(node) {
            return {$funcPrefix}_openModal(
                "{$trans['JSON_PATH']}",
                {$funcPrefix}_getJsonPathViewContent(),
                false,
                "jsoneditor-modal jsonPathView",
                function(modal) {
                    {$funcPrefix}_loadJsonPathView(modal, node);
                }
            );
        }
        
        function {$funcPrefix}_openPresetsModal(node){
            return {$funcPrefix}_openModal(
                "{$trans['PRESETS.TITLE']}",
                {$funcPrefix}_getPresetsBtnContent(),
                false,
                'jsoneditor-modal jsoneditor-modal-maximized',
                function(modal) {
                    return {$funcPrefix}_loadPresets(modal, node);
                }
            );
        }

        function {$funcPrefix}_openDetailsModal(node){
            return {$funcPrefix}_openModal(
                "{$trans['WIDGET_DETAILS']}",
                {$funcPrefix}_getDetailsBtnContent(),
                false,
                'jsoneditor-modal jsoneditor-modal-maximized',
                function(modal) {
                    return {$funcPrefix}_loadDetails(modal, node);
                }
            );
        }
        
        function {$funcPrefix}_loadJsonPathView(oModal, oNode){
            // get node path tree
            oNode.editor.expandAll(false);
            var oShowPathElem = document.getElementById('jsonPathView');
            var aPath = oNode.getPath();
            oShowPathElem.value = {$funcPrefix}_convertToJsonPath(aPath);
            oShowPathElem.title = oNode.editor.options.name + (aPath.length > 0 ? ' > ' : '') + aPath.join(' > ');
        }
        
        function {$funcPrefix}_insertIntoArray(aTargetArray, iPosition, oObject){
            return aTargetArray.splice(iPosition, 0, oObject);
        }
        
        function {$funcPrefix}_getRootNodeValue(){
            let rootNode = {$funcPrefix}_getNodeFromTarget($('.jsoneditor-tree tr:first-of-type td:last-of-type .jsoneditor-readonly').get()[0]);
            return ( rootNode === undefined ) ? null  : rootNode.getValue();
        }
        
        function {$funcPrefix}_getNodeType(node){
           if ( node === null || node.type === null ) {
               return 'root';
           }
           
           switch(node.type.toLowerCase()) {
               case 'auto'  : return 'auto';
               case 'string': return 'string';
               case 'array' : return 'array';
               case 'object': return 'object';
               default      : return 'undefined';
           }
        }

        function {$funcPrefix}_autoWidth(element){
            var jqEl = $(element);
            setTimeout(function(){
                var fSiblingsWidth = 0;
                var fParentWidth = jqEl.parent().width();
                jqEl.siblings().each(function(){
                    fSiblingsWidth += $(this).outerWidth(true);     
                });
                jqEl.css('width', 'calc(100% - ' + (fSiblingsWidth + 10) + 'px)');
            }, 0);
        }
        
        function {$funcPrefix}_getPresetsBtnContent(node){
            return  '   <div class="jsoneditor-jmespath-block uxoneditor-preset-selector" style="position: relative">' +
                    '      <div class="spinner-wrapper">' +
                    '          <div class="spinner"></div>' +
                    '      </div>' +
                    '      <select class="jsoneditor-jmespath-select-fields"></select>' +
                    '   </div>' +
                    '   <div class="jsoneditor-jmespath-block">' +
                    '      <textarea id="uxonPresetDescription" class="uxoneditor-input" style="height: 90px;" readonly></textarea>' +
                    '   </div>' +
                    '   <div class="jsoneditor-jmespath-label">{$trans['PRESETS.PREVIEW']} </div>' +
                    '   <div class="jsoneditor-jmespath-block" style="height: calc(100% - 35px - 10px - 90px - 10px - 18px - 25px - 18px - 25px - 35px - 4px)">' +
                    '     <div class="uxoneditor-preset-preview" style="height: 100%"> </div>' +
                    '   </div>' +
                    '   <div class="jsoneditor-jmespath-label">{$trans['PRESETS.USE_PRESET_AT']} </div>' +
                    '   <div class="jsoneditor-jmespath-block jsoneditor-modal-actions">' +
                    '       <input class="uxoneditor-input" id="uxonPresetPath" style="margin-right: 4px;" readonly></input>' +
                    '       <div class="action-buttons">' +
                    '         <input class="uxoneditor-input uxoneditor-preset-replace" autofocus disabled type="submit" value="{$trans['PRESETS.BUTTON_REPLACE']}"/>' +
                    '         <input class="uxoneditor-input uxoneditor-preset-prepend" disabled type="submit" value="{$trans['PRESETS.BUTTON_PREPEND']}"/>' +
                    '         <input class="uxoneditor-input uxoneditor-preset-append" disabled type="submit" value="{$trans['PRESETS.BUTTON_APPEND']}" />' +
                    '         <input class="uxoneditor-input uxoneditor-preset-wrap" disabled type="submit" value="{$trans['PRESETS.BUTTON_WRAP']}"   />' +
                    '         <input class="uxoneditor-input uxoneditor-preset-cancel" type="submit" value="{$trans['BUTTON_CANCEL']}" />' +
                    '       </div>' +
                    '   </div>';
        }
        
        function {$funcPrefix}_loadPresets(modal, node){
        
            var oPreviewEditor = new JSONEditor(
                modal.modalElem().querySelector(".uxoneditor-preset-preview"),
                {
                    mode: 'view',
                    mainMenuBar: false,
                    navigationBar: false
                }
            );
            
            // get node path tree
            node.editor.expandAll(false);
            
            var path = node.getPath();
            var nodeType = {$funcPrefix}_getNodeType(node);
            var parentNodeType = {$funcPrefix}_getNodeType(node.parent);
            
            // Wrap button enabled if node type is object
            if ( nodeType === 'object' || nodeType === 'root' ) {
                nodeIsWrappingTarget = true;
            }
            else {
                nodeIsWrappingTarget = false;
            }
            
            hasArrayContext = (node.parent !== null && node.parent.childs)? true : false;
           
            var oPresetPathElem = document.getElementById('uxonPresetPath');
            {$funcPrefix}_autoWidth(oPresetPathElem);
            oPresetPathElem.value = {$funcPrefix}_convertToJsonPath(path);
            oPresetPathElem.title = node.editor.options.name + (path.length > 0 ? ' > ' : '') + path.join(' > ');
            
            $.ajax( {
                type: 'POST',
                url: '{$ajaxUrl}',
                dataType: 'json',
                data: {
                    action: 'exface.Core.UxonAutosuggest',
                    path: JSON.stringify(path),
                    input: 'preset',
                    schema: {$uxonSchema},
                    prototype: {$rootPrototype},
                    uxon: node.editor.getText()
                }, // data
                
            }) // ajax POST request
            .done(function(data, textStatus, jqXHR) {
                // Fill path textarea
                var elem = modal.modalElem();
                
                var aPresetData = data;
                var aPresetOptions = [];
                var lastOption = {};
                var row;
                var length = aPresetData.length;
                for(var i = 0; i < length; i++){
                    row = aPresetData[i];
                    if (! row['PROTOTYPE__LABEL']) {
                       row['PROTOTYPE__LABEL'] = "{$trans['PRESET_GROUP_GENERAL']}";
                    }
                    if (lastOption['text'] === row['PROTOTYPE__LABEL']) {
                         lastOption['children'].push({
                             text: row['NAME'],
                             value: row['UID']
                         });
                     } else {
                         if (lastOption['children'] && lastOption['children'].length > 0) {
                            aPresetOptions.push(lastOption);
                         }
                         lastOption = {
                             text: row['PROTOTYPE__LABEL'],
                             children: [
                                 {
                                     text: row['NAME'],
                                     value: row['UID']
                                 }
                             ]
                         };
                     }
                }
                aPresetOptions.push(lastOption);
                var oPresetSelector = modal.modalElem().querySelector('.uxoneditor-preset-selector select');
                var oSelectrPresets = new Selectr(
                    oPresetSelector,
                    {   clearable: true,
                        defaultSelected: false,
                        placeholder: 'Select a preset...',
                        data: aPresetOptions
                    }
                ); // new Selectr()
                oSelectrPresets.open();
                
                modal.modalElem().querySelector('.uxoneditor-preset-selector .spinner-wrapper').remove();
                oSelectrPresets.on('selectr.select', function(option) {
                    var uid = option.value;
                    var oPresetWrapBtn = document.getElementById("presetWrap");
                    
                    
                    for (var i in aPresetData) {
                        var oRow = aPresetData[i];
                        if (oRow['UID'] === uid) {
                            oPreviewEditor.setText(oRow['UXON']);
                            
                            document.getElementById('uxonPresetDescription').value = oRow['DESCRIPTION'];
                            oPreviewEditor.expandAll(true);
                            modal.modalElem().querySelector(".uxoneditor-preset-replace").disabled = false;
                            
                            // Check if clicked editor node is object and preset is a wrapper
                            if ( oRow.WRAP_FLAG === "1" && nodeIsWrappingTarget ) {
                                modal.modalElem().querySelector(".uxoneditor-preset-wrap").disabled = false;
                                wrapData = oRow;
                            } else {
                                modal.modalElem().querySelector(".uxoneditor-preset-wrap").disabled = true;
                            }
                            modal.modalElem().querySelector(".uxoneditor-preset-replace").disabled = false;
                            
                            if(hasArrayContext) {
                                modal.modalElem().querySelector(".uxoneditor-preset-prepend").disabled = false;
                                modal.modalElem().querySelector(".uxoneditor-preset-append").disabled = false;
                            } else{
                                modal.modalElem().querySelector(".uxoneditor-preset-prepend").disabled = true;
                                modal.modalElem().querySelector(".uxoneditor-preset-append").disabled = true;
                            }
                            return;
                        }
                    }
                }); // on selectr.select
            }) // done
            .fail( function (jqXHR, textStatus, errorThrown) {
                console.warn("{$trans['ERROR.SERVER_ERROR']}", jqXHR);
                return [];
            } ); // fail
            
            var {$funcPrefix}_replaceNodeValue = function(oEditor, oNode, sJson, oModal){  
               oNode.update(sJson);
               oNode.expand(true);
               {$funcPrefix}_focusFirstChildValue(oNode, true);
               {$presetHintHide}
               oModal.close();
               
            }; // var
            
            var {$funcPrefix}_insertAtPosition = function(oParentNode, iTargetPosition, sJsonPreset,  oModal)
            {
                var aJsonParentNode = oParentNode.getValue();
                aJsonParentNode.splice(iTargetPosition, 0, sJsonPreset);
                {$funcPrefix}_replaceNodeValue(node.editor, oParentNode, aJsonParentNode, oModal);
            };
            
            var presetReplace = modal.modalElem().querySelector(".uxoneditor-preset-replace");
            presetReplace.onclick = function() {
                {$funcPrefix}_replaceNodeValue(node.editor, node, oPreviewEditor.get(), modal);
            };
            
            var presetPrepend = modal.modalElem().querySelector(".uxoneditor-preset-prepend");
            presetPrepend.onclick = function(){
                {$funcPrefix}_insertAtPosition(node.parent, node.getIndex(), oPreviewEditor.get(), modal);
            };
            
            var presetAppend = modal.modalElem().querySelector(".uxoneditor-preset-append");
            presetAppend.onclick = function(){
                {$funcPrefix}_insertAtPosition(node.parent, node.getIndex()+1, oPreviewEditor.get(), modal);
            };
            
            var presetWrap = modal.modalElem().querySelector(".uxoneditor-preset-wrap");
            presetWrap.onclick = function(){
               var jsonPath = wrapData['WRAP_PATH'];
               var oWrapTargetNode = oPreviewEditor.node.findNodeByPath({$funcPrefix}_convertToArrayPath(jsonPath));
               if (oWrapTargetNode === undefined){
                   alert("Wrapping target node within preset not found - please check preset configuration.");
                   return;
               }
               
              var val = node.getValue();
              oWrapTargetNode.setValue(val, (Array.isArray(val) ? 'array' : 'object'));
              {$funcPrefix}_replaceNodeValue(node.editor, node, oPreviewEditor.get(), modal);
            };
            
            var presetCancel = modal.modalElem().querySelector(".uxoneditor-preset-cancel");
            presetCancel.onclick = function() {
                modal.close();
            };
        }        

        function {$funcPrefix}_getDetailsBtnContent(node){
// here

            return  '   <p class="uxoneditor-object-details-title" style="display:none"></p>' + 
                    '   <div class="uxoneditor-object-details-description" style="display:none"></div>' +
                    '   <table class="uxoneditor-details-table">' +
                    '       <thead>' +
                    '           <tr>' +
                    '               <th style="text-align: center"><i class="fa fa-eye"></i></th>' +
                    '               <th>{$trans['DETAILS.PROPERTY']}</th>' +
                    '               <th>{$trans['DETAILS.VALUE']}</th>' +
                    '               <th>{$trans['DETAILS.DEFAULT']}</th>' +
                    '               <th>{$trans['DETAILS.DESCRIPTION']}</th>' +
                    '               <th>{$trans['DETAILS.REQUIRED']}</th>' +
                    '               <th> </th>' +
                    '           </tr>' +
                    '       </thead>' +
                    '       <tbody>' +
                    '       </tbody>' +
                    '   </table>' +
                    '   <div style="width: calc(100% - 20px); padding: 0 0 20px 0; text-align: center;">' +
                    '       <div class="spinner" style="width: 32px; height: 32px"></div>' +
                    '   </div>' +
                    '   <div class="jsoneditor-jmespath-block jsoneditor-modal-actions">' +
                    '      <input class="uxoneditor-input" id="uxonPresetPath" style="margin-right: 4px;" readonly></input>' +
                    '      <div class="action-buttons">' +
                    '          <input class="uxoneditor-input uxoneditor-btn-ok" autofocus type="submit" value="{$trans['BUTTON_OK']}"/>' +
                    '          <input class="uxoneditor-input uxoneditor-btn-cancel" type="submit" value="{$trans['BUTTON_CANCEL']}" />' +
                    '      </div>' +
                    '   </div>';
        }

        function {$funcPrefix}_loadDetails(modal, node){
            
            var path = node.getPath();
            
            var oPresetPathElem = document.getElementById('uxonPresetPath');
            {$funcPrefix}_autoWidth(oPresetPathElem);
            oPresetPathElem.value = {$funcPrefix}_convertToJsonPath(path);
            oPresetPathElem.title = node.editor.options.name + (path.length > 0 ? ' > ' : '') + path.join(' > ');
            
            var jqTableBody = $(modal.modalElem().querySelector('.uxoneditor-details-table > tbody'));
            $.ajax( {
                type: 'POST',
                url: '{$ajaxUrl}',
                dataType: 'json',
                data: {
                    action: 'exface.Core.UxonAutosuggest',
                    path: JSON.stringify(path),
                    input: 'details',
                    schema: {$uxonSchema},
                    prototype: {$rootPrototype},
                    uxon: node.editor.getText()
                }, // data
                
            }) // ajax POST request
            .done(function(oResponse, sTextStatus, jqXHR) {
                var aData = oResponse.properties || [];
                var sObjectTitle = oResponse.title;
                var sObjectDescription = oResponse.description;
                var oCurrentValues = [];
                var val, sVal, oFieldData, iPos = 0;

                modal.modalElem().querySelector('.pico-modal-header').innerHTML = oResponse.prototype_schema.charAt(0).toUpperCase() + oResponse.prototype_schema.slice(1) + ' "' + oResponse.alias + '"';
                modal.modalElem().querySelector('.spinner').parentNode.style.display = 'none';
                
                node.childs.forEach(function(oChildNode){
                    if (oCurrentValues[oChildNode.getField()] !== undefined) return;

                    val = oChildNode.getValue();
                    if (Array.isArray(val) === true) {
                        sVal = '[' + val.length + ' {$trans['DETAILS.VALUE_ARRAY_ITEMS']}]';
                    } else if (typeof val === 'object') {
                        sVal = '{{$trans['DETAILS.VALUE_OBJECT']}}'
                    } else {
                        sVal = val;
                    }
                    oCurrentValues[oChildNode.getField()] = sVal;
                    
                    // Make explicitly set properties appear at the top of the list
                    // in the same order as in the editor.
                    aData.forEach(function(oRow, i){
                        if (oRow['PROPERTY'] == oChildNode.getField()) {
                            aData.splice(iPos, 0, aData.splice(i, 1)[0]);
                            return;
                        }
                    });
                    iPos++;             
                });
                
                
                var sMoreLink;
                if (sObjectTitle){        
                    $('.uxoneditor-object-details-title').append(sObjectTitle);
                    $('.uxoneditor-object-details-title').toggle();
                }

                if (sObjectDescription){
                    $('.uxoneditor-object-details-title').append(' <a class="uxoneditor-button-show-more" href="javascript:;">[{$trans['DETAILS.SHOW_MORE']}]</a>');
                    $('.uxoneditor-object-details-description').append(sObjectDescription);
                    $('.uxoneditor-button-show-more').on('click', function(oEvent){
                        $('.uxoneditor-object-details-description').toggle();
        			});
                }



                var sBtnRowDetails = "";
                var iLength = aData.length;
                for(var i = 0; i < iLength; i++){
                    oRow = aData[i];
            
                    if(oRow['DESCRIPTION'] == ""){
                        sBtnRowDetails = '   <td></td>';
                    } else {
                        sBtnRowDetails =        '   <td><a href="javascript:;" class="btn-row-description"><i class="fa fa-info-circle" aria-hidden="true"></i></a></td>';
                    }             



                    jqTableBody.append($(
                        '<tr>' + 
                        '   <td style="text-align: center"><input class="uxoneditor-checkbox" type="checkbox" name="' + oRow['PROPERTY'] + '" ' + (oCurrentValues[oRow['PROPERTY']] !== undefined ? 'checked ' : '') + '></input></td>' + 
                        '   <td>' + oRow['PROPERTY'] + '</td>' + 
                        '   <td style="font-style: italic;">' + (oCurrentValues[oRow['PROPERTY']] || '') + '</td>' + 
                        '   <td>' + (oRow['DEFAULT'] || '') + '</td>' + 
                        '   <td>' + (oRow['TITLE'] || '') + '</td>' + 
                        '   <td style="text-align: center;">' + (oRow['REQUIRED'] ? '<i class="fa fa-check" aria-hidden="true"></i>' : '') + '</td>' +
                        sBtnRowDetails + 
                        '</tr>' + 
                        '<tr style="display: none;">' + 
                        '   <td></td>' +
                        '   <td colspan="5" class="row-description">' + oRow['DESCRIPTION'] + ' </td>' +
                        '   <td></td>' +
                        '</tr>'
                    ));
                }

              
            }) // done
            .fail( function (jqXHR, textStatus, errorThrown) {
                console.warn("{$trans['ERROR.SERVER_ERROR']}", jqXHR);
                return [];
            } ); // fail
            
            modal.modalElem().querySelector(".uxoneditor-btn-ok").onclick = function() {
                var oContentOld = node.getValue(), oContentNew = {};
                jqTableBody.find('.uxoneditor-checkbox:checked').each(function(){
                    if (oContentOld[this.name] === undefined) {
                        oContentNew[this.name] = '';
                    } else {
                        oContentNew[this.name] = oContentOld[this.name];
                    }
                });
                node.setValue(oContentNew);
                node.expand(true);
                {$funcPrefix}_focusFirstChildValue(node, true);
                modal.close();
            };

            modal.modalElem().querySelector(".uxoneditor-btn-cancel").onclick = function() {
                modal.close();
            };

            jqTableBody.on('click', '.btn-row-description', function(oEvent){
                $(oEvent.target).closest('tr').next("tr").toggle();
			});

        }


	
    
JS;
    }
    
    protected function buildJsRootPrototypeGetter() : string
    {
        $widget = $this->getWidget();
        if ($widget instanceof InputUxon) {
            $expr = $widget->getRootPrototype();
            if ($expr !== null) {
                if ($expr->isString() === true) {
                    return '"' . $expr->toString() . '"';
                } elseif ($expr->isReference() === true) {
                    $link = $expr->getWidgetLink($widget);
                    return $this->getFacade()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetColumnId());
                }
            }
        }
        return '""';
    }
    
    protected function buildJsSchemaGetter() : string
    {
        $widget = $this->getWidget();
        if ($widget instanceof InputUxon) {
            $expr = $widget->getSchemaExpression();
            if ($expr->isString() === true) {
                return '"' . trim($expr->toString(), "'\"") . '"';
            } elseif ($expr->isReference() === true) {
                $link = $expr->getWidgetLink($widget);
                return $this->getFacade()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetColumnId());
            }
        }
        return '""';
    }
    
    protected function buildJsRootObjectGetter() : string
    {
        $widget = $this->getWidget();
        if ($widget instanceof InputUxon) {
            $expr = $widget->getRootObject();
            if ($expr !== null) {
                if ($expr->isString() === true) {
                    return '"' . $expr->toString() . '"';
                } elseif ($expr->isReference() === true) {
                    $link = $expr->getWidgetLink($widget);
                    return $this->getFacade()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetColumnId());
                }
            }
        }
        return '""';
    }
    
    protected function buildJsAutosuggestFunction() : string
    {
        $widget = $this->getWidget();
        $uxonEditorId = $this->getId();
        $funcPrefix = $this->buildJsFunctionPrefix();
        if (($widget instanceof InputUxon) === false || $widget->getAutosuggest() === false) {
            return '';
        }
        
        return $this::buildJsUxonEditorFunctions(
            $funcPrefix,
            $this->buildJsSchemaGetter(),
            $this->buildJsRootPrototypeGetter(),
            $this->buildJsRootObjectGetter(),
            $this->getAjaxUrl(),
            $this->getWorkbench(),
            "'{$uxonEditorId}'"
            )
            .
            <<<JS
                
                $(function() {
            		$(document).on('blur', '#{$uxonEditorId} div.jsoneditor-field[contenteditable="true"]', {jsonEditor: {$uxonEditorId}_JSONeditor }, {$funcPrefix}_onBlur);
            	});
JS;
    }
    
    /**
     * Returns the name of the JS function to add a preset hint after the Uxon editor tree
     *
     * The function is defined in buildJsUxonEditorFunctions()
     *
     * @see buildJsUxonEditorFunctions()
     *
     * @param string $funcPrefix
     * @return string
     */
    public static function buildJsFunctionNameAddPresetHint(string $funcPrefix) : string {
        return $funcPrefix . '_addPresetHint';
    }
    
    /**
     * Returns the name of the JS function to add a help button to the top toolbar
     * 
     * The function is defined in buildJsUxonEditorFunctions()
     * 
     * @see buildJsUxonEditorFunctions()
     * 
     * @param string $funcPrefix
     * @return string
     */
    public static function buildJsFunctionNameAddHelpButton(string $funcPrefix) : string
    {
        return $funcPrefix . '_addHelpButton';
    }
    
    /**
     * Returns the name of the JS function to call when an editor field is left (triggers inserting the UXON template)
     *
     * The function is defined in buildJsUxonEditorFunctions()
     *
     * @see buildJsUxonEditorFunctions()
     *
     * @param string $funcPrefix
     * @return string
     */
    public static function buildJsFunctionNameOnBlur(string $funcPrefix) : string
    {
        return $funcPrefix . '_onBlur';
    }
}