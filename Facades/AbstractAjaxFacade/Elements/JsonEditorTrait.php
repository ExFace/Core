<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputUxon;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\CommonLogic\Workbench;
use Symfony\Component\Translation\Translator;

/**
 * This trait helps use the JsonEditor library to create InputJson and InputUxon widgets.
 * 
 * ## How to use
 * 
 * Include the following dependencies in composer.json of the app, where the trait is used:
 * 
 * ```
 * require: {
 *	"npm-asset/jsoneditor" : "^6.1",
 *	"npm-asset/picomodal" : "^3.0.0",
 *	"npm-asset/mobius1-selectr" : "^2.4.12"
 * }
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
    protected function buildJsPresetHint() : string
    {
        $funcPrefix = $this->buildJsFunctionPrefix();
        $addPresetHint = static::buildJsFunctionNameAddPresetHint($funcPrefix);
     
        return <<<JS
        
                 {$addPresetHint}();   
JS;
        
    }
                 
    protected static function buildJsPresetHintHide(string $uxonEditorId) : string
    {
        return "$('#{$uxonEditorId} .uxoneditor-preset-hint').hide()";
    }
    
    protected static function buildJsPresetHintShow(string $uxonEditorId) : string
    {
        return "$('#{$uxonEditorId} .uxoneditor-preset-hint').show()";
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
        $uxonEditorId = $this->getId();
       
        return <<<JS
                   var {$uxonEditorId}_JSONeditor = new JSONEditor(
                        document.getElementById("{$uxonEditorId}"),
                        { 
                            {$this->buildJsEditorOptions()}
                        },
        
                        {$this->getWidget()->getValue()}
                    );
        
                    {$uxonEditorId}_JSONeditor.expandAll();
        
                    {$this->buildJsEditorAddHelpButton()}
        			$('#{$uxonEditorId}').parents('.exf-input').children('label').css('vertical-align', 'top');
        			{$this->buildJsPresetHint()}
        
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
        return <<<JS
        
        function(oldMode,newMode){
/*             let rootNodeJSON =  {$funcPrefix}_getRootNodeValue();
            var presetHint = $('#presetHint');
            if (newMode !== oldMode && newMode === "tree" && rootNodeJSON !== null && JSON.stringify(rootNodeJSON) === "{}"){
                $("#{$uxonEditorId} .jsoneditor-tree-inner").after(presetHint);
                presetHint.hide();
                presetHint.fadeIn(700)
            } */
            presetHintActive = false;
        }    
JS;
    }
    
   
    protected function buildJsEditorOptions() : string
    {
        $widget = $this->getWidget();
        $isWidgetDisabled = $widget->isDisabled();
        
        $funcPrefix = $this->buildJsFunctionPrefix();
        $uxonEditorId = $this->getId();
        $uxonSchema = $this->buildJsSchemaGetter();
        $workbench = $this->getWorkbench();
        
        if (($widget instanceof InputUxon) && $widget->getAutosuggest() === true) {
            $uxonEditorOptions = $this::buildJsUxonEditorOptions($uxonSchema, $funcPrefix, $workbench);
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
            $fn .= <<<JS
        if (json && json.constructor === Object && Object.keys(json).length === 0) {
            {$this::buildJsPresetHintShow($this->getId())}
        } else {
            {$this::buildJsPresetHintHide($this->getId())}
        }

JS;
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
    public static function buildJsUxonEditorOptions(string $uxonSchema, string $funcPrefix, Workbench $workbench) : string
    {   
        $uxonEditorTerms = [
            'JSON_PATH' => static::translateJsUxonEditorTerm($workbench, "CONTEXT_MENU.JSON_PATH.TITLE"),
            'PRESETS'   => static::translateJsUxonEditorTerm($workbench, "CONTEXT_MENU.PRESETS"),
            'ERROR.AUTOSUGGEST_FAILED' => static::translateJsUxonEditorTerm($workbench, "ERROR.AUTOSUGGEST_FAILED.GENERAL"),
            'ERROR.GETTING_OPTIONS' => static::translateJsUxonEditorTerm($workbench, "ERROR.AUTOSUGGEST_FAILED.GETTING_OPTIONS"),
            'ERROR.PRESETS_NOT_AVAILABLE' => static::translateJsUxonEditorTerm($workbench, "ERROR.PRESETS_NOT_AVAILABLE")
        ];
        
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
                                            console.warn("{$uxonEditorTerms['ERROR.AUTOSUGGEST_FAILED']}", err);
                                       });
                    	           }
                                })
                                .catch((err) => {
                                    editor._autosuggestPending = false;
                                    console.warn("{$uxonEditorTerms['ERROR.GETTING_OPTIONS']}", err);
                                    return Promise.resolve([]);
                                });
                            }
                        },
                        onCreateMenu : function (items, node){
                            var path = node.path;
                            var rootNode = {$funcPrefix}_getNodeFromTarget( $('.jsoneditor-tree tr:first-of-type td:last-of-type .jsoneditor-readonly').get()[0]);
                            var menuNode = path.length > 0 ? rootNode.findNodeByPath(node.path) : rootNode;
                            var val = menuNode.getValue();
                            var presetsMenuBtnActive = false;
                            
                            /* ist objekt oder wert === leer */ 
                            var menuNodeType = {$funcPrefix}_getNodeType(menuNode);
                            if ( menuNodeType === "object" || menuNode.getValue( ) === "" || menuNodeType === "root" ) {
                                presetsMenuBtnActive = true;
                            };
                                  // Menü aktiv
                            if(!presetsMenuBtnActive) {
                                items.unshift(
                                    {
                                        text : "{$uxonEditorTerms['PRESETS']}",   // the text for the menu item
                                        title : "{$uxonEditorTerms['PRESETS']}",  // the HTML title attribute
                                        className : "jsoneditor-default-icon deactivate-button" ,     // the css class name(s) for the menu item
                                        click : function(){ console.warn("{$uxonEditorTerms['ERROR.PRESETS_NOT_AVAILABLE']}"); }
                                    }
                                );
                            } else{
                                items.unshift(
                                {
                                    text : "{$uxonEditorTerms['PRESETS']}",   // the text for the menu item
                                    title : "{$uxonEditorTerms['PRESETS']}",  // the HTML title attribute
                                    className : "jsoneditor-default-icon active-button", // the css class name(s) for the menu item
                                    click: function(){ 
                                        return {$funcPrefix}_openPresetsModal(menuNode); 
                                    }
                                });
                            }
                            
                            // Always add menu item for copying current path
                        	items.push({
                                text: "{$uxonEditorTerms['JSON_PATH']}",   // the text for the menu item
                                title: "{$uxonEditorTerms['JSON_PATH']}",  // the HTML title attribute
                                className: "jsoneditor-default-icon active-button" ,     // the css class name(s) for the menu item
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
    
    
    public static function buildCssModalStyles(string $uxonEditorId) : string
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

                    .jsoneditor-modal .pico-modal-contents {height: 100%; width: 100%;}
                                        
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
                        top: 50%;
                    }
                    .uxoneditor-preset-hint a {color: #ccc; text-decoration: none;}
                    .uxoneditor-preset-hint a:hover {color: #000;}
                    .uxoneditor-preset-hint i {display: block; font-size: 400%; margin-bottom: 15px;}
                    
CSS;
    }
    
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $facade = $this->getFacade();
        $uxonEditorId = $this->getId();
        $includes[] = '<link href="exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.css" rel="stylesheet">';
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.js"></script>';
        $includes[] = '<link href="' . $facade->buildUrlToSource('LIBS.JSONEDITOR.CSS') . '" rel="stylesheet">';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource("LIBS.JSONEDITOR.JS") . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource("LIBS.JSONEDITOR.PICOMODAL") . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource("LIBS.JSONEDITOR.SELECTR.JS")  . '"></script>';
        $includes[] = '<link href="' . $facade->buildUrlToSource('LIBS.JSONEDITOR.SELECTR.CSS') . '" rel="stylesheet"/>';
        $includes[] = '<style type=' . '"text/css"' . '>' . $this::buildCssModalStyles($uxonEditorId) . '</style>';
        
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
        string $uxonEditorId
        ) : string
        {
            $addHelpButtonFunction = static::buildJsFunctionNameAddHelpButton($funcPrefix);
            $addPresetHint = static::buildJsFunctionNameAddPresetHint($funcPrefix);
            $onBlurFunctionName = static::buildJsFunctionNameOnBlur($funcPrefix);
            $presetHintHide = static::buildJsPresetHintHide($uxonEditorId);
            $uxonEditorTerms = [
                'HELP' => static::translateJsUxonEditorTerm($workbench, 'HELP'),
                'JSON_PATH' => static::translateJsUxonEditorTerm($workbench, 'JSON_PATH'),
                'USE_PRESET_AT' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.LABEL.USE_PRESET_AT'),
                'PRESET_PREVIEW' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.PRESET_PREVIEW'),
                'WIDGET_PRESETS' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.TITLE'),
                'REPLACE' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.ACTION.REPLACE'),
                'PREPEND' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.ACTION.PREPEND'),
                'APPEND' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.ACTION.APPEND'),
                'WRAP' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.ACTION.WRAP'),
                'CANCEL' => static::translateJsUxonEditorTerm($workbench, 'WIDGET_PRESETS.ACTION.CANCEL'),
                'PRESET_HINT' => static::translateJsUxonEditorTerm($workbench, 'PRESET_HINT'),
                'ERROR.SERVER_ERROR' => static::translateJsUxonEditorTerm($workbench, 'ERROR.SERVER_ERROR'),
                'ERROR.MALFORMED_RESPONSE' => static::translateJsUxonEditorTerm($workbench, 'ERROR.MALFORMED_RESPONSE')
            ];
            
            return <<<JS
                
        var wrapData = {};
        var nodeIsWrappingTarget = false;
        var hasArrayContext = false;
        var presetHintActive = true;
        
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
                    return Promise.reject({message: "{$uxonEditorTerms['ERROR.MALFORMED_RESPONSE']}", response: response});
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
                '<label class="pico-modal-contents">' +
                '<div class="pico-modal-header">' + 
                title +
                '</div>' + 
                contentHTML;
            
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
        
        function {$funcPrefix}_focusFirstChildValue(node){
        	var child, found;
        	for (var i in node.childs) {
        		child = node.childs[i];
        		if (child.type === 'string' || child.type === 'auto') {
        			child.focus(child.getField() ? 'value' : 'field');
                    return child;
        		} else {
        			found = {$funcPrefix}_focusFirstChildValue(child);
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
                '<button type="button" title="{$uxonEditorTerms['HELP']}" style="background: transparent;"><i ' +
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

        function {$addPresetHint}() {
            var presetHint = $(
                '<div class="uxoneditor-preset-hint">' +
                '   <a href="javascript:;">' + 
                '       <i class="fa fa-magic preset-hint-pulse" title="{$uxonEditorTerms['PRESET_HINT']}"></i>' + 
                '       {$uxonEditorTerms['PRESET_HINT']}' + 
                '   </a>' + 
                '</div>'
            );
        
            $("#{$uxonEditorId} .jsoneditor-tree-inner").after(presetHint);
            
            $("#{$uxonEditorId} .uxoneditor-preset-hint a").click( function(){
                var rootNode = {$funcPrefix}_getNodeFromTarget(
                    $(".jsoneditor-tree tr:first-of-type td:last-of-type .jsoneditor-readonly").get()[0]
                );
                return {$funcPrefix}_openPresetsModal(rootNode);
            });
        }
        
        function {$funcPrefix}_getJsonPathViewContent(){
            var jsonPathViewContent =
                '<label class="pico-modal-contents">' +
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
        
        function {$funcPrefix}_getPresetsBtnContent(node){
            var presetsContent =
                '<label class="pico-modal-contents">' +
                '<div class="jsoneditor-jmespath-label">{$uxonEditorTerms['WIDGET_PRESETS']}</div>' +
                '<div class="jsoneditor-jmespath-block" style="position: relative">' +
                '   <div id="presetSpinnerWrapper" class="spinner-wrapper">' +
                '       <div id="presetSpinner" class="spinner"></div>' +
                '   </div>' +
                '  <select class="jsoneditor-jmespath-select-fields" id="uxonPresets"></select>' +
                '</div>' +
                '<div class="jsoneditor-jmespath-block">' +
                '   <textarea id="uxonPresetDescription" ' +
                '      class="uxoneditor-input" style="height: 60px;"' +
                '      readonly> ' +
                '   </textarea>' +
                '</div>' +
                '<div class="jsoneditor-jmespath-label">{$uxonEditorTerms['PRESET_PREVIEW']} </div>' +
                '<div class="jsoneditor-jmespath-block" style="height: calc(100% - 18px - 104px - 28px - 18px - 26px - 45px - 35px - 42px)">' +
                '  <div id="uxonPresetPreview" style="height: 100%"> </div>' +
                '</div>' +
                '<div class="jsoneditor-jmespath-label">{$uxonEditorTerms['USE_PRESET_AT']} </div>' +
                '<div class="jsoneditor-jmespath-block jsoneditor-modal-actions">' +
                '   <input class="uxoneditor-input" id="uxonPresetPath" ' +
                '      style="width: calc(100% - 455px); margin-right: 4px;"' +
                '      readonly> ' +
                '   </input>' +
                '   <div class="action-buttons">' +
                '       <input class="uxoneditor-input" type="submit" id="presetReplace" value="{$uxonEditorTerms['REPLACE']}" autofocus class="action-button" />' +
                '       <input class="uxoneditor-input" type="submit" id="presetPrepend" value="{$uxonEditorTerms['PREPEND']}" class="action-button"/>' +
                '       <input class="uxoneditor-input" type="submit" id="presetAppend"  value="{$uxonEditorTerms['APPEND']}"  class="action-button"/>' +
                '       <input class="uxoneditor-input" type="submit" id="presetWrap"    value="{$uxonEditorTerms['WRAP']}"    class="action-button"/>' +
                '       <input class="uxoneditor-input" type="submit" id="presetCancel"  value="{$uxonEditorTerms['CANCEL']}"  class="action-button"/>' +
                '   </div>' +
                '</div>';
            return presetsContent;
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
                "{$uxonEditorTerms['JSON_PATH']}",
                {$funcPrefix}_getJsonPathViewContent(),
                false,
                "jsoneditor-modal jsonPathView",
                function(modal) {
                    {$funcPrefix}_loadJsonPathView(modal, node);
                }
            );
        }
        
        function {$funcPrefix}_openPresetsModal(node){
            // show preset window as modal
            return {$funcPrefix}_openModal(
                "{$uxonEditorTerms['WIDGET_PRESETS']}",
                {$funcPrefix}_getPresetsBtnContent(),
                false,
                'jsoneditor-modal jsoneditor-modal-maximized',
                function(modal) {
                    return {$funcPrefix}_loadPresets(modal, node);
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
        
        function {$funcPrefix}_setDisabledFlag(id,disabledFlag){
            document.getElementById(id).disabled = disabledFlag;
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
        
        function {$funcPrefix}_loadPresets(modal, node){
        
            var oPreviewEditor = new JSONEditor(
                modal.modalElem().querySelector("#uxonPresetPreview"),
                {
                    mode: 'view',
                    mainMenuBar: false,
                    navigationBar: false
                }
            );
            
            // get node path tree
            node.editor.expandAll(false);
            
            var oPresetPathElem = document.getElementById('uxonPresetPath');
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
                
            $( document ).ready(function() {
                // disable Buttons until preset was selected;
                {$funcPrefix}_setDisabledFlag("presetReplace", true);
                {$funcPrefix}_setDisabledFlag("presetAppend", true);
                {$funcPrefix}_setDisabledFlag("presetPrepend", true);
                {$funcPrefix}_setDisabledFlag("presetWrap", true);
            });
            
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
                    object: $("#DataTable_DataToolbar_ButtonGroup_DataButton02_object_uid").val(),
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
                var oPresetSelector = elem.querySelector('#uxonPresets');
                var oSelectrPresets = new Selectr(
                    oPresetSelector,
                    {   clearable: true,
                        defaultSelected: false,
                        placeholder: 'Select a preset...',
                        data: aPresetOptions
                    }
                ); // new Selectr()
                
                $('#presetSpinnerWrapper').remove();
                oSelectrPresets.on('selectr.select', function(option) {
                    var uid = option.value;
                    var oPresetWrapBtn = document.getElementById("presetWrap");
                    
                    
                    for (var i in aPresetData) {
                        var oRow = aPresetData[i];
                        if (oRow['UID'] === uid) {
                            oPreviewEditor.setText(oRow['UXON']);
                            
                            document.getElementById('uxonPresetDescription').value = oRow['DESCRIPTION'];
                            oPreviewEditor.expandAll(true);
                            {$funcPrefix}_setDisabledFlag("presetReplace", false);
                            
                            // Check if clicked editor node is object and preset is a wrapper
                            if ( oRow.WRAP_FLAG === "1" && nodeIsWrappingTarget ) {
                                {$funcPrefix}_setDisabledFlag("presetWrap", false);
                                wrapData = oRow;
                            }
                            else {
                                {$funcPrefix}_setDisabledFlag("presetWrap", true);
                            }
                            {$funcPrefix}_setDisabledFlag("presetReplace", false);
                            
                            if(hasArrayContext) {
                               {$funcPrefix}_setDisabledFlag("presetPrepend", false);
                               {$funcPrefix}_setDisabledFlag("presetAppend", false);
                            } else{
                               {$funcPrefix}_setDisabledFlag("presetPrepend", true);
                               {$funcPrefix}_setDisabledFlag("presetAppend", true);
                            }
                            return;
                        }
                    }
                }); // on selectr.select
            }) // done
            .fail( function (jqXHR, textStatus, errorThrown) {
                console.warn("{$uxonEditorTerms['ERROR.SERVER_ERROR']}", jqXHR);
                return [];
            } ); // fail
            
            var {$funcPrefix}_replaceNodeValue = function(oEditor, oNode, sJson, oModal){  
               oNode.update(sJson);
               oNode.expand(true);
               {$funcPrefix}_focusFirstChildValue(oNode);
               {$presetHintHide}
               presetHintActive = false;
               oModal.close();
               
            }; // var
            
            var {$funcPrefix}_insertAtPosition = function(oParentNode, iTargetPosition, sJsonPreset,  oModal)
            {
                var aJsonParentNode = oParentNode.getValue();
                aJsonParentNode.splice(iTargetPosition, 0, sJsonPreset);
                {$funcPrefix}_replaceNodeValue(node.editor, oParentNode, aJsonParentNode, oModal);
            };
            
            var presetReplace = $("#presetReplace");
            presetReplace.click( function() {
                {$funcPrefix}_replaceNodeValue(node.editor, node, oPreviewEditor.get(), modal);
            });
            
            var presetPrepend = $("#presetPrepend");
            presetPrepend.click( function(){
                {$funcPrefix}_insertAtPosition(node.parent, node.getIndex(), oPreviewEditor.get(), modal);
            });
            
            var presetAppend = $("#presetAppend");
            presetAppend.click(function(){
                {$funcPrefix}_insertAtPosition(node.parent, node.getIndex()+1, oPreviewEditor.get(), modal);
            });
            
            var presetWrap = $("#presetWrap");
            presetWrap.click( function(){
               var jsonPath = wrapData['WRAP_PATH'];
               var oWrapTargetNode = oPreviewEditor.node.findNodeByPath({$funcPrefix}_convertToArrayPath(jsonPath));
               if (oWrapTargetNode === undefined){
                   alert("Wrapping target node within preset not found - please check preset configuration.");
                   return;
               }
               
              var val = node.getValue();
              oWrapTargetNode.setValue(val, (Array.isArray(val) ? 'array' : 'object'));
              {$funcPrefix}_replaceNodeValue(node.editor, node, oPreviewEditor.get(), modal);
            });
            
            var presetCancel = $("#presetCancel");
            presetCancel.click( function() {
                node.expand(true);
                {$funcPrefix}_focusFirstChildValue(node);
                modal.close();
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
            $uxonEditorId
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