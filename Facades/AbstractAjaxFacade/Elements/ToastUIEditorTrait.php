<?php

namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\InputMarkdown;
use exface\Core\Widgets\Parts\HtmlTagStencil;
use exface\Core\Widgets\Parts\TextStencil;

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
                    customHTMLRenderer: {
                        {$this->buildJsCustomHtmlRenderers()}
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
                    customHTMLRenderer: {
                        {$this->buildJsCustomHtmlRenderers()}
                    }
                });
                
                return ed;
            }();
JS;
    }

    protected function buildJsCustomHtmlRenderers() : string
    {
        if (! $this->getWidget() instanceof InputMarkdown) {
            return '';
        }
        $inlineTagRenderersJs = '';
        foreach ($this->getWidget()->getStencils() as $stencil) {
            if ($stencil->isHtmlTag()) {
                $inlineTagRenderersJs .= $this->buildJsCustomHtmlInlineRenderer($stencil) . ',';
            }
        }
        return <<<JS

                        htmlInline: {
                            {$inlineTagRenderersJs}
                        },
                        /* html: {}*/
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
                  ['table', {$image} 'link', {$this->buildJsToolbarItemsForStencils()}],
                  ['code', 'codeblock',]],
JS;

    }
    
    protected function buildJsToolbarItemsForStencils() : string
    {
        $js = '';
        if ($this->getWidget() instanceof InputMarkdown) {
            foreach ($this->getWidget()->getStencils() as $stencil) {
                switch (true) {
                    case $stencil instanceof HtmlTagStencil:
                        $js .= $this->buildJsToolbarItemForHtmlTagStencil($stencil);
                        break;
                    default:
                        // TODO add support for regular stencils - just insert them at cursor position
                        throw new WidgetConfigurationError($this->getWidget(), 'Only HtmlTag stencils currently supported');
                        /*$js .= $this->buildJsToolbarItemForTextStencil($stencil);*/
                        break;
                }
            }
        }
        return $js;
    }
    
    protected function buildJsToolbarItemForHtmlTagStencil(TextStencil $stencil) : string
    {
        if ($stencil->getIcon() === null && null !== $iconText = $stencil->getIconText()) {
            $icon = $iconText;
        } else {
            $icon = $stencil->getIcon();
        }
        $insertKbdButtonHTML = implode(' ', [
            '<button type="button"',
            //'id="' . $this->getId() . '_stencil_' . spl_object_id($stencil) . '"',
            'style="margin: -7px -5px; background: transparent;">',
            $icon,
            '</button>',
        ]);
        
        $insertKbdButtonJs = <<<JS
                (function (){
                    let button = \$('$insertKbdButtonHTML')[0];
                    button.addEventListener('click', () => {
                        let  oEditor = {$this->buildJsMarkdownVar()};

                        const [start, end] = oEditor.getSelection();
                        const selectedText = oEditor.getSelectedText();
        
                        // If no Text is selected.
                        if (!selectedText.trim()) {
                            return;
                        }
        
                        if (oEditor.isMarkdownMode()) {
                          // Writes the keyboard tags directly into the Markdown.
                          const wrapped = `<{$stencil->getHtmlTag()}>\${selectedText}</{$stencil->getHtmlTag()}>`;
                          oEditor.replaceSelection(wrapped, start, end);
                        } else {
                          // In WYSIWYG mode, the KBD tags must be inserted directly 
                          // into the HTML of the editor so that the customHTMLParser can process them, 
                          // as in the Markdown section above. 
                          //
                          // Note: The parser will delete all non-supported attributes 
                          // from this element if given.
                          const kbdElement = document.createElement("{$stencil->getHtmlTag()}");
                          const userSelection = window.getSelection();
                          const selectedTextRange = userSelection.getRangeAt(0);
                          selectedTextRange.surroundContents(kbdElement);
                        }
                    });
                    
                    return button;
                })()
JS;
        return <<<JS
                {
                    name: {$this->escapeString($stencil->getCaption())},
                    tooltip: {$this->escapeString($stencil->getHint())},
                    el: {$insertKbdButtonJs}
                }
JS;

    }
    
    protected function buildJsCustomHtmlInlineRenderer(TextStencil $stencil) : string
    {
        return <<<JS
          {$stencil->getHtmlTag()}(entering) {
              return entering
                  ? { type: 'openTag', tagName: '{$stencil->getHtmlTag()}', attributes: { style: "{$stencil->buildCssStyle()}"} }
                  : { type: 'closeTag', tagName: '{$stencil->getHtmlTag()}' };
              }
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