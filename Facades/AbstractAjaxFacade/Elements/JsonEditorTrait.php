<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputUxon;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Facades\DocsFacade;

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
 *	"npm-asset/picomodal" : "^3.0.0"
 * }
 * 
 * ```
 * 
 * Add pathes to the dependencies to the configuration of the facade:
 * 
 * ```
 *  "LIBS.JSONEDITOR.JS": "npm-asset/jsoneditor/dist/jsoneditor.min.js",
 *  "LIBS.JSONEDITOR.CSS": "npm-asset/jsoneditor/dist/jsoneditor.min.css",
 *  "LIBS.JSONEDITOR.PICOMODAL": "npm-asset/picomodal/src/picoModal.js",
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
    
    protected function buildJsJsonEditor()
    {
       
        return <<<JS
            var {$this->getId()}_JSONeditor = new JSONEditor(
                document.getElementById("{$this->getId()}"), 
                {
                    {$this->buildJsEditorOptions()}
			    },
                {$this->getWidget()->getValue()}
            );
            {$this->getId()}_JSONeditor.expandAll();
            $('#{$this->getId()}').parents('.exf-input').children('label').css('vertical-align', 'top');
            {$this->buildJsEditorAddHelpButton()}
            
JS;
    }
    
    protected function buildJsEditorOptions() : string
    {
        $widget = $this->getWidget();
        if (($widget instanceof InputUxon) && $widget->getAutosuggest() === true) {
            $uxonEditorOptions = $this::buildJsUxonEditorOptions($this->getWidget()->getSchema(), $this->buildJsFunctionPrefix());
        } else {
            $uxonEditorOptions = '';
        }
        
        return <<<JS

                    onError: function (err) {
                        try{
    				        {$this->buildJsShowMessageError('err.toString()')};
                        }
                        catch{
                            console.error('Failed to show JSON Editor error: ', err);
                        }
    				},
				    mode: {$this->buildJsEditorModeDefault()},
    				modes: {$this->buildJsEditorModes()},
                    {$uxonEditorOptions}
    

JS;
    }
    
    /**
     * Building the options for UXON editor including filter function and error handler
     * 
     * @param string $uxonSchema
     * @param string $fetchAutosuggestFunctionName
     * @return string
     */
    public static function buildJsUxonEditorOptions(string $uxonSchema, string $funcPrefix) : string
    {
        return <<<JS
        
                    name: "{$uxonSchema}",
                    enableTransform: false,
                	enableSort: false,
                    autocomplete: {
                        applyTo: ['value'],
                        filter: function (token, match, config) {
					     	// remove leading space in token if not the only character
						    if (  token.length > 1
						     	&& ( token.search(/^\s[^\s]/i) > -1 )
						    ) {
					    		token = token.substr(1, token.length - 1);
					    	}
					    	
					    	// remove spaces in token if preceeded by double underscores
				            if (  token.length > 3  && token.search(/\_\_\s/i) ) {
                                token = token.substr(0, token.length - 1);
                            } else if (!token.replace(/\s/g, '').length) {
					            // return true if token consists of whitespace characters only
								return true;
					        }
					        return match.indexOf(token) > -1;
					    },
                        getOptions: function (text, path, input, editor) {
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
                                        if (json.values !== undefined ) {
                                            resolve({$funcPrefix}_filterAutosuggest(json.values, text));
                                        }
                                        
                                        // return response data for further processing
                                        return json;
                                    })
                                   .catch((err) => {
                                        editor._autosuggestPending = false;
                                        console.warn('Autosuggest failed. ', err);
                                   });
               		           }
                            })
                            .catch((err) => {
                                editor._autosuggestPending = false;
                                console.warn("Autosuggest failed while getting options - ignored.", err);
                                return Promise.resolve([]);
                            });
                        }
                    }
                    
JS;
    }
                    
    protected function buildJsAutosuggestFunction() : string
    {
        $widget = $this->getWidget();
        if (($widget instanceof InputUxon) === false || $widget->getAutosuggest() === false) {
            return '';
        }
        
        return $this::buildJsUxonAutosuggestFunctions(
            $this->buildJsFunctionPrefix(),
            $widget->getSchema(),
            $this->buildJsRootPrototypeGetter(),
            $this->buildJsRootObjectGetter(),
            $this->getAjaxUrl()
        ) . <<<JS
        
    $(function() {
    	$(document).on('blur', '#{$this->getId()} div.jsoneditor-field[contenteditable="true"]', {jsonEditor: {$this->getId()}_JSONeditor}, {$this->buildJsFunctionPrefix()}_onBlur);
    });

JS;
    }
    
    /**
     * 
     * @param string $funcPrefix
     * @param string $uxonSchema
     * @param string $rootPrototype
     * @param string $rootObject
     * @param string $ajaxUrl
     * @return string
     */
    public static function buildJsUxonAutosuggestFunctions(string $funcPrefix, string $uxonSchema, string $rootPrototype, string $rootObject, string $ajaxUrl) : string
    {
        $addHelpButtonFunction = static::buildJsFunctionNameAddHelpButton($funcPrefix);
        $onBlurFunctionName = static::buildJsFunctionNameOnBlur($funcPrefix);
        
        return <<<JS
        
    function {$funcPrefix}_fetchAutosuggest(text, path, input, uxon) {
        var formData = new URLSearchParams({
    		action: 'exface.Core.UxonAutosuggest',
    		text: text,
    		path: JSON.stringify(path),
    		input: input,
    		schema: '{$uxonSchema}',
            prototype: {$rootPrototype},
            object: {$rootObject},
    		uxon: uxon
    	});
    	return fetch('{$ajaxUrl}', {
    		method: "POST",
    		mode: "cors",
    		cache: "no-cache",
    		headers: {
    			"Content-Type": "application/x-www-form-urlencoded",
    		},
    		body: formData, // body data type must match "Content-Type" header
    	})
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
                return Promise.reject({message: "Failed read JSON from fetch: Malformed response!", response: response});
            }
        });
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
    
    function {$funcPrefix}_openModal(title, contentHTML, focus = false, cssClass = '', onAfterCreate ) {
    
          if (! onAfterCreate) {
              onAfterCreate = function(modal) {
                  
              };
          }
        var contentStr = '<label class="pico-modal-contents">' + 
                         '<div class="pico-modal-header">' + title + '</div>' + contentHTML;
       
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

    function {$funcPrefix}_getNodeFromTarget(target) {
	   while (target) {
    	    if (target.node) {
    	       return target.node;
    	    }
    	    target = target.parentNode;
       }
       
	   return undefined;
    }
    
    function {$funcPrefix}_focusFirstChildValue(node) {
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

    function {$onBlurFunctionName}(event) {
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
        var helpBtn = $('<button type="button" title="' + title+ '" style="background: transparent;"><i class="fa fa-question-circle-o" style="font-size: 22px"></i></button>');
        $('#' + editorId + ' .jsoneditor-menu .jsoneditor-search').before(helpBtn);
        var helpBtnContent = '<iframe src="' + url + '"></iframe>';
        helpBtn.click(function() {
            return {$funcPrefix}_openModal(title , helpBtnContent, false, 'jsoneditor-modal-nopadding jsoneditor-modal-maximized' );
        });

    }
    
JS;
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsEditorModes() : string
    {
        if ($this->getWidget()->isDisabled()) {
            return "['view']";
        }
        return "['code', 'tree']";
    }
    
    /**
     *
     * @return string
     */
    protected function buildJsEditorModeDefault() : string
    {
        if ($this->getWidget()->isDisabled()) {
            return "'view'";
        }
        return "'tree'";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return 'function(){var text = ' . $this->getId() . '_JSONeditor.getText(); if (text === "{}" || text === "[]") { return ""; } else { return text;}}';
    }
    
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $facade = $this->getFacade();        
        $includes[] = '<link href="' . $facade->buildUrlToSource('LIBS.JSONEDITOR.CSS') . '" rel="stylesheet">';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JSONEDITOR.JS') . '"></script>';
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.JSONEDITOR.PICOMODAL') . '"></script>';
        
        $includes[] = '<style type="text/css">' . $this::buildCssModalStyles() . '</style>';
        
        return $includes;
    }
    
    public static function buildCssModalStyles() : string
    {
        return <<<CSS
  
    .jsoneditor-modal.jsoneditor-modal-maximized {
        width: 100% !important;
        height: 95%;
    }
  
    .jsoneditor-modal.jsoneditor-modal-nopadding {
        padding: 30px 0 0 0 !important;
    }

    .jsoneditor-modal.jsoneditor-modal-nopadding iframe {
        width: calc(100% - 16px) !important;
        height: calc(100% - 12px)!important;
        border: 0;
        padding: 5px 0 0 15px;
    }
CSS;
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
    
    /**
     * Returns javascript code for adding UXON editor help on top toolbar.
     * Both the function call and help content URL are constructed from given 
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
        return <<<JS

            {$addHelpButtonFunction}(
                $,
                "{$this->getId()}",
                "{$url}",
                "Help" 
            );

JS;
    }
    
    /**
     * Returns the name of the JS function to add a help button to the top toolbar
     * 
     * The function is defined in buildJsUxonAutosuggestFunctions()
     * 
     * @see buildJsUxonAutosuggestFunctions()
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
     * The function is defined in buildJsUxonAutosuggestFunctions()
     *
     * @see buildJsUxonAutosuggestFunctions()
     *
     * @param string $funcPrefix
     * @return string
     */
    public static function buildJsFunctionNameOnBlur(string $funcPrefix) : string
    {
        return $funcPrefix . '_onBlur';
    }
}