<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

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
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::buildCssHeightDefaultValue()
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
                    onError: function (err) {
    				    {$this->buildJsShowMessageError('err.toString()')};
    				},
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
            $uxonEditorOptions = <<<JS

                    name: "{$this->getWidget()->getSchema()}",
                    enableTransform: false,
                	enableSort: false,
                    autocomplete: {
                        applyTo: ['value'],
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
                                    return {$this->buildJsFunctionPrefix()}_fetchAutosuggest(text, path, input, uxon, resolve, reject)
                           			.then(json => {
                   				         if (json !== undefined) {
                           					editor._autosuggestPending = false;
                           					editor._autosuggestLastPath = pathBase;
                           					editor._autosuggestLastResult = json;
                           				}
                       			    });
               		           }
                            });
                        }
                    }
                	

JS;
        } else {
            $uxonEditorOptions = '';
        }
        
        return <<<JS

                    mode: {$this->buildJsEditorModeDefault()},
    				modes: {$this->buildJsEditorModes()},
                    {$uxonEditorOptions}
    

JS;
    }
                    
    protected function buildJsAutosuggestFunction() : string
    {
        $widget = $this->getWidget();
        if (($widget instanceof InputUxon) === false || $widget->getAutosuggest() === false) {
            return '';
        }
        
        return <<<JS

    function {$this->buildJsFunctionPrefix()}_fetchAutosuggest(text, path, input, uxon, resolve, reject) {
        var formData = new URLSearchParams({
    		action: 'exface.Core.UxonAutosuggest',
    		text: text,
    		path: JSON.stringify(path),
    		input: input,
    		schema: '{$widget->getSchema()}',
            prototype: {$this->buildJsRootPrototypeGetter()},
            object: {$this->buildJsRootObjectGetter()},
    		uxon: uxon
    	});
    	return fetch('{$this->getAjaxUrl()}', {
    		method: "POST",
    		mode: "cors",
    		cache: "no-cache",
    		headers: {
    			"Content-Type": "application/x-www-form-urlencoded",
    		},
    		body: formData, // body data type must match "Content-Type" header
    	})
    	.then(response => response.json())
    	.then(json => {resolve(json.values); return json;})
    	.catch(response => {reject();});
    }

    function {$this->buildJsFunctionPrefix()}_getNodeFromTarget(target) {
	   while (target) {
    	    if (target.node) {
    	       return target.node;
    	    }
    	    target = target.parentNode;
       }
    
	   return undefined;
    }
  
    function {$this->buildJsFunctionPrefix()}_focusFirstChildValue(node) {
    	var child, found;
    	for (var i in node.childs) {
    		child = node.childs[i];
    		if (child.type === 'string' || child.type === 'auto') {
    			child.focus(child.getField() ? 'value' : 'field');
                return child;
    		} else {
    			found = {$this->buildJsFunctionPrefix()}_focusFirstChildValue(child);
                if (found) {
                    return found;
                }
    		}
    	}
    	return false;
    }

    $(function() {
    	$(document).on('blur', '#{$this->getId()} div.jsoneditor-field[contenteditable="true"]', function() {
            var editor = {$this->getId()}_JSONeditor;
    		var node = {$this->buildJsFunctionPrefix()}_getNodeFromTarget(this);
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
    				{$this->buildJsFunctionPrefix()}_focusFirstChildValue(node);
    			}
    		} 
    	});
    });

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
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::buildJsValueGetter()
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
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::buildJsValidator()
     */
    public function buildJsValidator()
    {
        return 'true';
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
                    return $this->getTemplate()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetColumnId());
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
                    return $this->getTemplate()->getElement($link->getTargetWidget())->buildJsValueGetter($link->getTargetColumnId());
                }
            }
        }
        return '""';
    }
}