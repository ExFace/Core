<?php

namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputMarkdown;
use exface\JEasyUIFacade\Facades\Elements\EuiInputMarkdown;
use exface\UI5Facade\Facades\Elements\UI5DisplayMarkdown;
use exface\UI5Facade\Facades\Elements\UI5InputMarkdown;

/**
 * Aides Facade specific implementation of the ToastUI markdown editor.
 * 
 * This trait supports both an editor and a viewer version:
 * - Editor: `buildJsMarkdownInitEditor()`
 * - Viewer: `buildJsMarkdownInitViewer()`
 * 
 * Only ever initialize one version per widget.
 * 
 * @see UI5InputMarkdown 
 * @see EuiInputMarkdown
 */
trait ToastUIEditorTrait
{
    /**
     * Creates a JS snippet that initializes a ToastUI markdown editor
     * instance and returns it, complete with toolbar and live reference hooks.
     * 
     * @see UI5InputMarkdown
     * @see EuiInputMarkdown
     * 
     * @param bool $isViewer
     * @return string
     */
    protected function buildJsMarkdownInitEditor(bool $isViewer = false) : string
    {
        $widget = $this->getWidget();
        $contentJs = $this->escapeString($widget->getValueWithDefaults(), true, false);
        $editorOptions = "initialEditType: '" . ($widget->getEditorMode() === InputMarkdown::MODE_WYSIWYG ? 'wysiwyg' : 'markdown') . "'";
        $markdownVarJs = str_replace("'", '"', $this->buildJsMarkdownVar());
        
        return <<<JS

            function(){
                var ed = toastui.Editor.factory({
                    el: document.querySelector('#{$this->getId()}'),
                    height: '100%',
                    initialValue: ($contentJs || ''),
                    language: 'en',
                    autofocus: false,
                    viewer: false,
                    $editorOptions,
                    {$this->buildJsToolbarItems($widget)}
                    events: {
                        beforePreviewRender: function(sHtml){
                            setTimeout(function(){
                                var oEditor = {$markdownVarJs};
                            }, 0);
                        },
                        change: function(){
                            {$this->getOnChangeScript()} 
                        }    
                    }
                });
                
                return ed;
            }();
JS;
    }

    /**
     * Creates a JS snippet that initializes a ToastUI markdown viewer instance
     * and returns it. The viewer is much more light weight than the editor,
     * has no toolbar and does not support editing.
     * 
     * @see UI5DisplayMarkdown
     * 
     * @return string
     */
    protected function buildJsMarkdownInitViewer() : string
    {
        $widget = $this->getWidget();
        $contentJs = $this->escapeString($widget->getValueWithDefaults(), true, false);
        $markdownVarJs = str_replace("'", '"', $this->buildJsMarkdownVar());

        return <<<JS

            function(){
                var ed = toastui.Editor.factory({
                    el: document.querySelector('#{$this->getId()}'),
                    height: '100%',
                    initialValue: ($contentJs || ''),
                    language: 'en',
                    autofocus: false,
                    viewer: true,
                    events: {
                        beforePreviewRender: function(sHtml){
                            setTimeout(function(){
                                var oEditor = {$markdownVarJs};
                            }, 0);
                        },
                        change: function(){
                            {$this->getOnChangeScript()} 
                        }    
                    }
                });
                
                return ed;
            }();
JS;
    }

    /**
     * Assembles the markdown editor toolbar.
     * 
     * @param $widget
     * @return string
     */
    protected function buildJsToolbarItems($widget) : string
    {
        $image = $widget->getAllowImages() ? "'image', " : "";
        $fullScreenToggleJs = <<<JS
                (function (){
                    var button = $('<button type="button" id="{$this->getFullScreenToggleId()}" style="margin: -7px -5px; background: transparent;"><i class="fa fa-expand" style="padding: 4px;border: 1px solid black;margin-top: 1px"></i></button>')[0];
                    button.addEventListener('click', () => {
                        {$this->buildJsFullScreenToggleClickHandler()}
                    });
                    
                    return button;
                })()
JS;

        
        return <<<JS

        toolbarItems: [
                  [{
                    name: 'Full Screen',
                    tooltip: 'Full Screen',
                    el: {$fullScreenToggleJs}
                  },'heading', 'bold', 'italic', 'strike'],
                  ['hr', 'quote'],
                  ['ul', 'ol', 'task', 'indent', 'outdent'],
                  ['table', {$image} 'link'],
                  ['code', 'codeblock']],
JS;

    }

    /**
     * Returns a click handler for the full screen toggle button.
     * 
     * The handler will be used in this context:
     * 
     * ```
     * 
     * button.addEventListener('click', () => {
     *      {$this->buildJsFullScreenToggleClickHandler()}
     * });
     * 
     * ```
     *
     * @return string
     */
    protected function buildJsFullScreenToggleClickHandler() : string
    {
        $markdownVarJs = $this->buildJsMarkdownVar();

        return <<<JS

                        var jqWrapper = $('#{$this->getId()}');
                        var oEditor = {$markdownVarJs};
                        var jqBtn = $('#{$this->getFullScreenToggleId()}');
                        var bExpanding = ! jqWrapper.hasClass('fullscreen');
                    
                        jqWrapper.toggleClass('fullscreen'); 
                        jqBtn.find('i')
                            .removeClass('fa-expand')
                            .removeClass('fa-compress')
                            .addClass(bExpanding ? 'fa-compress' : 'fa-expand');
                        if (bExpanding && jqWrapper.innerWidth() > 800) {
                            oEditor.changePreviewStyle('vertical');
                        } else {
                            oEditor.changePreviewStyle('tab');
                        }
JS;
    }

    /**
     * @return string
     */
    protected function getFullScreenToggleId() : string
    {
        return $this->getId().'_tuiFullScreenToggle';
    }

    /**
     *
     * @return string
     */
    protected function buildJsMarkdownVar() : string
    {
        return "{$this->buildJsFunctionPrefix()}_editor";
    }

    /**
     *
     * @return string
     */
    protected function buildJsMarkdownRemove() : string
    {
        return "{$this->buildJsMarkdownVar()}.remove();";
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\JEasyUIFacade\Facades\Elements\EuiInput::buildJsValueSetterMethod()
     */
    public function buildJsValueSetter($value)
    {
        return <<<JS
        
        var oEditor = {$this->buildJsMarkdownVar()};
        if({$value} === undefined || {$value} === null) {
            {$value} = "";
        }

        if ({$value} === oEditor.getMarkdown()) {
            return;
        }
        
        {$this->buildJsImageDataSanitizer($value)}
        oEditor.setMarkdown({$value});
JS;
    }

    /**
     * Builds an inline JS snippet that removes any raw image data from a string
     * variable called `$value`.
     * 
     * ```
     * 
     *  if ({$value} !== undefined) {
     *      {$value} = {$value}.replace(/!\[[^\]]+\]\((data:[^\s\"]+)[\"|\s|\)]/, '');
     *  }
     * 
     * ```
     * 
     * @param string $value
     * @return string
     */
    protected function buildJsImageDataSanitizer(string $value) : string
    {
        if($this->getWidget()->getAllowImages()) {
            return '';
        }
        
        return <<<JS

        {$value} = {$value}.replace(/!\[[^\]]+\]\((data:[^\s\"]+)[\"|\s|\)]/, '');
JS;

    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return <<<JS

        (function () {
            var value = {$this->buildJsMarkdownVar()}.getMarkdown();
            if(value === undefined || value === null) {
                return "";
            }
            
            {$this->buildJsImageDataSanitizer('value')}
            return value;
        })()
JS;
    }

    /**
     *
     * @return string
     */
    protected function buildHtmlMarkdownEditor() : string
    {
        $html = '<div id="'.$this->getId().'" class="markdown-editor"></div>';
        return $html;
    }
}