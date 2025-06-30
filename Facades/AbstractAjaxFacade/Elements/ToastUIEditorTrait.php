<?php

namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InputMarkdown;

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
        
        return <<<JS

            function(){
                var ed = toastui.Editor.factory({
                    el: document.querySelector('#{$this->getId()}'),
                    height: '100%',
                    initialValue: ($contentJs || ''),
                    extendedAutolinks: true,
                    language: 'en',
                    autofocus: false,
                    viewer: false,
                    $editorOptions,
                    {$this->buildJsToolbarItems($widget)}
                    events: {
                        change: function(){
                            {$this->getOnChangeScript()} 
                        }    
                    },
                    customHTMLRenderer: {{$this->buildJsKbdCustomHTMLRenderer()}}
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
                    extendedAutolinks: true,
                    language: 'en',
                    autofocus: false,
                    viewer: true,
                    events: {
                        change: function(){
                            {$this->getOnChangeScript()} 
                        }    
                    },
                    customHTMLRenderer: {{$this->buildJsKbdCustomHTMLRenderer()}}
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

        $insertKbdButtonHTML = implode(' ', [
            '<button type="button"',
            'id="' . $this->getInsertKbdButton() . '"',
            'style="margin: -7px -5px; background: transparent;">',
            'BT',
            '</button>',
        ]);

        $insertKbdButtonJs = <<<JS
                (function (){
                    let button = \$('$insertKbdButtonHTML')[0];
                    button.addEventListener('click', () => {
                        {$this->buildJsInsertKbdButtonClickHandler()}
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
                  ['table', {$image} 'link',
                  {
                    name: 'Insert Button',
                    tooltip: 'Insert Button',
                    el: {$insertKbdButtonJs}
                  }],
                  ['code', 'codeblock',]],
JS;

    }

    /**
     * Returns the custom html renderer for the "<kbd> ... </kbd>" tags
     *
     * It parses the kbd tags, making the text inside look like a button.
     *
     * @return string
     */
    protected function buildJsKbdCustomHTMLRenderer(): string
    {
        $buttonStyle = implode(' ', [
            'background-color: #f1f5f9;',
            'border: 1px solid #cbd5e1;',
            'border-radius: 4px;',
            'padding: 4px 10px;',
            'font-size: 14px;',
            'font-family: inherit;',
            'color: #1e293b;',
            'cursor: default;',
            'transition: background-color 0.2s ease, color 0.2s ease;',
        ]);

        return <<<JS
        htmlInline: {
          kbd(entering) {
          return entering
              ? { type: 'openTag', tagName: 'kbd', attributes: { style: "{$buttonStyle}"} }
              : { type: 'closeTag', tagName: 'kbd' };
          },
        },
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
     * Returns a click handler for the kbd insert button.
     *
     * The handler wraps the selected text into the "<kbs> ... </kbd>" tags so it can be parsed.
     *
     * @return string
     */
    protected function buildJsInsertKbdButtonClickHandler() : string
    {
        $markdownVarJs = $this->buildJsMarkdownVar();

        return <<<JS
                let  oEditor = {$markdownVarJs};

                const [start, end] = oEditor.getSelection();
                const selectedText = oEditor.getSelectedText();

                // If no Text is selected.
                if (!selectedText.trim()) {
                    return;
                }

                if (oEditor.isMarkdownMode()) {
                  // Writes the keyboard tags directly into the Markdown.
                  const wrapped = `<kbd>\${selectedText}</kbd>`;
                  oEditor.replaceSelection(wrapped, start, end);
                } else {
                  // In WYSIWYG mode, the KBD tags must be inserted directly 
                  // into the HTML of the editor so that the customHTMLParser can process them, 
                  // as in the Markdown section above. 
                  //
                  // Note: The parser will delete all non-supported attributes 
                  // from this element if given.
                  const kbdElement = document.createElement("kbd");
                  const userSelection = window.getSelection();
                  const selectedTextRange = userSelection.getRangeAt(0);
                  selectedTextRange.surroundContents(kbdElement);
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
     * @return string
     */
    protected function getInsertKbdButton() : string
    {
        return $this->getId().'_tuiInsertKbdButton';
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
    public function buildJsValueSetter($value) : string
    {
        return <<<JS
        
        var oEditor = {$this->buildJsMarkdownVar()};
        if({$value} === undefined || {$value} === null) {
            {$value} = "";
        }
        
        {$this->buildJsImageDataSanitizer($value)}

        if ("getMarkdown" in oEditor && {$value} === oEditor.getMarkdown()) {
            return;
        } else {
            if (!("_lastSetValue" in oEditor)) {
                oEditor._lastSetValue = null;
            }
            
            if (oEditor._lastSetValue === {$value}) {
                return;
            }
        }
        
        oEditor.setMarkdown({$value});
        oEditor._lastSetValue = {$value};
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
        // Make sure, the value getter does not crash if the editor was not initialized yet!
        return <<<JS
        (function () {
            var value = '';
            var oEditor = {$this->buildJsMarkdownVar()};
            if (oEditor) {
                if (oEditor.getMarkdown !== undefined) {
                    value = oEditor.getMarkdown();
                } else if (oEditor._lastSetValue !== undefined) {
                    value = oEditor._lastSetValue;
                }
            }
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