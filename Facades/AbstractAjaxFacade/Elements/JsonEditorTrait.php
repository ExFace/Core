<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputUxon;

/**
 *
 * @method InputJson getWidget()
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
JS;
    }
    
    protected function buildJsEditorOptions() : string
    {
        $widget = $this->getWidget();
        if (($widget instanceof InputUxon) && $widget->getAutosuggest() === true) {
            $uxonEditorOptions = self::buildJsUxonEditorOptions($this->getWidget()->getSchema(), $this->buildJsFunctionPrefix() . '_fetchAutosuggest');
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
    
    public static function buildJsUxonEditorOptions(string $uxonSchema, string $fetchAutosuggestFunctionName) : string
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
                                        resolve(editor._autosuggestLastResult.values);
                                    } else {
                                        reject();
                                    }
                       		   } else {
                                    editor._autosuggestPending = true;
                                    var uxon = JSON.stringify(editor.get());
                                    return {$fetchAutosuggestFunctionName}(text, path, input, uxon)
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
                                            resolve(json.values);
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
        
        return self::buildJsUxonAutosuggestFunctions(
            $this->buildJsFunctionPrefix() . '_fetchAutosuggest',
            $widget->getSchema(),
            $this->buildJsRootPrototypeGetter(),
            $this->buildJsRootObjectGetter(),
            $this->getAjaxUrl()
        ) . <<<JS
        
    $(function() {
    	$(document).on('blur', '#{$this->getId()} div.jsoneditor-field[contenteditable="true"]', {jsonEditor: {$this->getId()}_JSONeditor}, {$this->buildJsFunctionPrefix()}_fetchAutosuggest_onBlur);
    });

JS;
    }
    
    public static function buildJsUxonAutosuggestFunctions(string $funcName, string $uxonSchema, string $rootPrototype, string $rootObject, string $ajaxUrl) : string
    {
        return <<<JS
        
    function {$funcName}(text, path, input, uxon) {
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
    
    function {$funcName}_getNodeFromTarget(target) {
	   while (target) {
    	    if (target.node) {
    	       return target.node;
    	    }
    	    target = target.parentNode;
       }
       
	   return undefined;
    }
    
    function {$funcName}_focusFirstChildValue(node) {
    	var child, found;
    	for (var i in node.childs) {
    		child = node.childs[i];
    		if (child.type === 'string' || child.type === 'auto') {
    			child.focus(child.getField() ? 'value' : 'field');
                return child;
    		} else {
    			found = {$funcName}_focusFirstChildValue(child);
                if (found) {
                    return found;
                }
    		}
    	}
    	return false;
    }

    function {$funcName}_onBlur(event) {
        var editor = event.data.jsonEditor;
		var node = {$funcName}_getNodeFromTarget(this);
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
				{$funcName}_focusFirstChildValue(node);
			}
		}
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
        $includes[] = '<link href="exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.css" rel="stylesheet">';
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/jsoneditor/dist/jsoneditor.min.js"></script>';
        return $includes;
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
}