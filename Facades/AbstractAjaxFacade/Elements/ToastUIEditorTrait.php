<?php

namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\InputMarkdown;
use exface\Core\Widgets\Parts\HtmlTagStencil;
use exface\Core\Widgets\Parts\TextMention;
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
                    },
                    widgetRules: [
                        {$this->buildJsWidgetRules()}
                        ],
                });
                
                {$this->buildJsAdditionalWidgetsCode()}
                
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
                    },
                    widgetRules: [
                        {$this->buildJsWidgetRules()}
                    ],
                });
                
                {$this->buildJsAdditionalWidgetsCode()}
                
                return ed;
            }();
JS;
    }

    /**
     * All new ToastUI Widget extension code can be included below:
     * 
     * @return string
     */
    protected function buildJsAdditionalWidgetsCode(): string {
        $additionalWidgetsCode = '';

        if ($this->getWidget() instanceof InputMarkdown 
            && !empty($this->getWidget()->getMentions())
        ) {
            $additionalWidgetsCode.= <<<JS
              
            {$this->buildJsMentionsWidgetComponents()}
JS;
        }

        return $additionalWidgetsCode;
    }

    /**
     * This function builds all mention widget code.
     * 
     * @return string
     */
    protected function buildJsMentionsWidgetComponents() : string
    {
        return <<<JS
        
                ed.exfWidget = {
  
                  mentionMenuCss: 'position: absolute; padding: 4px 0; background: white; border: 1px solid #eee; box-shadow: 0 2px 8px rgba(0,0,0,.15); border-radius: 6px; font-size: 14px; z-index: 100; min-width: 150px;',
                  mentionItemEmptyCss: 'color: gray; pointer-events: none;',

                  lastCharacterWasRelevantSpace: false,
                  
                  mentionModels: [
                      {$this->buildJsMentionsDataModel()}
                  ],

                  createFilteredMentionDropdown: {$this->buildJsCreateFilteredMentionDropdown()} ,
                  
                  mentionOnKeyupListener: {$this->buildJsMentionOnKeyupListener()},
                  
                  selectMentionElement:  {$this->buildJsSelectMentionElement()},
                  
                  addMentionTag: {$this->buildJsAddMentionTag()},
                  
                  mentionOnSpaceKeydownListener: {$this->buildJsMentionOnSpaceKeydownListener()},
                  
                  mentionActivationListener: {$this->buildJsMentionActivationListener()},
                  
                  setMentionPotentialMatch: {$this->buildJsSetMentionPotentialMatch()},
                  
                  createCollisionWarning: {$this->buildJsMentionCreateCollisionWarning()},
                  
                  resetMentionModel: {$this->buildJsMentionResetMentionModel()}
                }
                
                ed.on("keyup", (editorType, ev) => {
                    ed.exfWidget.mentionOnKeyupListener(ev);
                });
                
                document.addEventListener("keydown", function handleSpace(e) {
                  ed.exfWidget.mentionModels.forEach(function(oMentionModel){
                    ed.exfWidget.mentionOnSpaceKeydownListener(oMentionModel, e);
                  });
                });
JS;

    }
    
    protected function buildJsWidgetRules(): string
    {
        $widgetRulesJs = '';
        // You can add more widget rules here.
        if ($this->getWidget() instanceof InputMarkdown) {
            foreach ($this->getWidget()->getMentions() as $mention) {
                switch (true) {
                    case $mention instanceof TextMention:
                        $widgetRulesJs .= '{' . $this->buildJsMentionWidgetRule($mention) . '},';
                        break;
                    default:
                        throw new WidgetConfigurationError($this->getWidget(), 'Only TextMention currently supported');
                }
            }
        }
        
        return $widgetRulesJs;
    }

    protected function buildJsMentionWidgetRule(TextMention $mention): string
    {
        $tagColor = ($mention->getTagColor() ?? $this->getFacade()->getConfig()->getOption('WIDGET.INPUTMARKDOWN.TAG_COLOR_DEFAULT')) ?? '#001580';
        $reMentionWidgetRule =
            '/\[('
                . $mention->getTagPrefix()
                . $mention->getTagTextRegex()
            . ')\]'
            .'\((.*?)\)/';
        $mentionWidgetCss = 'display: inline-block; padding: 4px 10px; background-color: ' . $tagColor .'; color: white; text-decoration: none; border-radius: 9999px; font-size: 14px; font-family: sans-serif; font-weight: 600; white-space: nowrap;';
        return <<<JS
      /**
       * For example, if the tagPrefix = "@", this mention widget will react to:
       * "[@text](url)",
       * "[@text]()",
       *
       * and will convert it into a <span> element with a hyperlink
       * that looks like a mention tag.
       */
      rule: {$reMentionWidgetRule},
      toDOM(text) {
        const matched = text.match({$reMentionWidgetRule});
        
        if(!matched) {
          return;
        } 
        
        const value = matched[1];
        const url = matched[2];

        const span = document.createElement("span");

        if (url) {
          span.innerHTML = `<a style="{$mentionWidgetCss}" href="\${url}">\${value}</a>`;
        } else {
          span.style.cssText = "{$mentionWidgetCss}";
          span.innerHTML = `\${value}`;
        }

        return span;
      }
JS;
    }

    /**
     * It builds mentionData for one mention widget.
     * 
     * @return string
     */
    protected function buildJsMentionsDataModel() : string
    {
        $widget = $this->getWidget();
        $mentionsDataJs = '';
        if ($widget instanceof InputMarkdown) {
            foreach ($widget->getMentions() as $mention) {
                
                $btn = $mention->getAutosuggestButton();
                $tagTextRegex = json_encode($mention->getTagTextRegex());
                $pageSize = ($mention->getAutosuggestMaxNumberOfRows() ?? $this->getFacade()->getConfig()->getOption('WIDGET.INPUTMARKDOWN.TAG_AUTOSUGGEST_PAGE_SIZE')) ?? 10;
                $mentionsDataJs .= <<<JS
                 {
                   tagPrefix: {$this->escapeString($mention->getTagPrefix())},
                   filterAttributeAlias: {$this->escapeString($mention->getAutosuggestFilterAttributeAlias())},
                   maxNumberOfRows:  {$pageSize},
                   tagTextRegex: {$tagTextRegex},
                   tagTextAttribute: {$this->escapeString($mention->getTagTextAttribute())},
                   
                   ajaxCallActionAlias: {$this->escapeString($btn->getAction()->getAliasWithNamespace())},
                   ajaxCallObjectId: {$this->escapeString($btn->getMetaObject()->getId())},
                   ajaxCallResourceAlias: {$this->escapeString($btn->getPage()->getAliasWithNamespace())},
                   ajaxCallElementId: {$this->escapeString($btn->getId())},
                   ajaxCallActionObjectId: {$this->escapeString($btn->getAction()->getMetaObject()->getId())},
                   
                   currentMentionWidget: null,
                   lastLine: 0,
                   lastCharPos: 0,
                   lastFilter: "",
                   
                   isPotentialMatch: true
                 },
JS;

            }
        }

        return $mentionsDataJs;
    }
    

    protected function buildJsMentionOnKeyupListener() : string
    {
        return <<<JS
        
          function mentionOnKeyupListener(ev) {
              if( ev.code !== "Space") {
                ed.exfWidget.lastCharacterWasRelevantSpace = false;
              }
          
              let line = null;
              let charPos = null;
              let textBeforeCursor = null;
              
              if (ed.isMarkdownMode()) {
                [[line, charPos]] = ed.getSelection(); // line and char position
                // It takes the text from the start of the line to the cursor.
                textBeforeCursor = ed.getSelectedText([line, 0], [line, charPos]);
              } else {
                [,charPos] = ed.getSelection();
                // It takes the text from the start of the editor to the cursor.
                textBeforeCursor = ed.getSelectedText(0, charPos);
              }
              
              const potentialMatchCount = ed.exfWidget.setMentionPotentialMatch(line, charPos, textBeforeCursor);
              
              //In case of collision of multiple mention widgets:
              if (potentialMatchCount > 1) {
                ed.exfWidget.createCollisionWarning();
                return;
              }
               
              ed.exfWidget.mentionModels.forEach(function(oMentionModel){
                if (oMentionModel.isPotentialMatch) {
                  ed.exfWidget.mentionActivationListener(oMentionModel, line, charPos, textBeforeCursor);
                }
              });
        }
JS;

    }

    /**
     * This function checks if the current input matches with one of the mention widgets 
     * and prepares the values for the mention dropdown call.
     * 
     * It also returns the number of matches to detect collisions.
     * 
     * @return string
     */
    protected function buildJsSetMentionPotentialMatch() :string
    {
        return <<<JS

        function setMentionPotentialMatch(line, charPos, textBeforeCursor) {
          let potentialMatchCount = 0;
          
          ed.exfWidget.mentionModels.forEach(function(oMentionModel) {
            
            // Mention widget input activation:
            const activationRegEx = new RegExp(oMentionModel.tagPrefix + "\\\w*$");
            const activationMatch = textBeforeCursor.match(activationRegEx);
            
            if (!activationMatch) return ed.exfWidget.resetMentionModel(oMentionModel);
              
            // Mention widget input verification
            const lastFilter = activationMatch[0];
            const regEx = new RegExp(oMentionModel.tagPrefix + oMentionModel.tagTextRegex + "$");
            const mentionMatch = lastFilter.match(regEx);
              
            if (!mentionMatch) return ed.exfWidget.resetMentionModel(oMentionModel);
            
            potentialMatchCount++;
            oMentionModel.isPotentialMatch = true;
            oMentionModel.lastFilter = mentionMatch[0];
            oMentionModel.lastLine = line;
            oMentionModel.lastCharPos = charPos;
          })
          
          return potentialMatchCount;
        }
JS;

    }

    /**
     * It calls the mention dropdown widget and adds an eventListener to all items of it
     * 
     * @return string
     */
    protected function buildJsMentionActivationListener() :string
    {
        return <<<JS
      
        function mentionActivationListener(oMentionModel) {
            const filter = oMentionModel.lastFilter;
            
            ed.exfWidget.createFilteredMentionDropdown(oMentionModel, filter).then(widget => {
              
              oMentionModel.currentMentionWidget = null;
              
              //Aborts the dropdown if the Ajax response comes after 
              // the user has already pressed the space bar.
              if (ed.exfWidget.lastCharacterWasRelevantSpace) {
                return;
              }
  
              oMentionModel.currentMentionWidget = widget;
              ed.addWidget(oMentionModel.currentMentionWidget, "bottom");
            
              const ul = oMentionModel.currentMentionWidget.querySelector("ul");
              ul.addEventListener("mousedown", (e) => {
                const target = e.target.closest(".mention-item");
                ed.exfWidget.selectMentionElement(oMentionModel, target);
              });
            });
        }
JS;

    }
    
    
    protected function buildJsMentionOnSpaceKeydownListener(): string
    {
        return <<<JS

          function mentionOnSpaceKeydownListener(oMentionModel, e) {
  
            if (!oMentionModel.currentMentionWidget || e.code !== "Space") return;
              
            ed.exfWidget.lastCharacterWasRelevantSpace = true; 
            const items = oMentionModel.currentMentionWidget.querySelectorAll(".mention-item:not(.empty)");
            
            if (items.length === 1) {
              ed.exfWidget.selectMentionElement(oMentionModel,items[0]);
            } else if (oMentionModel.lastFilter !== oMentionModel.tagPrefix) {
              
              //TODO: in case of "#6" if "6" and "16" got fetched and the user press "space", the "#6" here will be saved, 
              // but without the items (url). This may get an error in the future, if url support will be introduced.
               ed.exfWidget.addMentionTag(oMentionModel,null);
            }
          }
JS;
    }
    
    protected function buildJsSelectMentionElement(): string
    {
        return <<<JS

          /**
          * It takes the value and URL from the item that was clicked on in the mention widget.
          * @param oMentionModel
          * @param item
          */
          function selectMentionElement(oMentionModel ,item) {
            if (!item) return;
            const value = item.dataset.value;
            const url = item.dataset.url;
          
            ed.exfWidget.addMentionTag(oMentionModel, value, url);
          }
JS;
    }
    
    protected function buildJsAddMentionTag(): string
    {
        return <<<JS

        /**
         *  It takes the value and URL
         *  and converts the "#text" on saved position to "[#text]()"
         *
         * @param oMentionModel
         * @param value
         * @param url
         */
          function addMentionTag(oMentionModel, value, url = "") {
            
            if(!value && !oMentionModel.lastFilter) {
              return;
            }
          
            let from = null;
            let to = null;
          
            if (ed.isMarkdownMode()) {
              from = [oMentionModel.lastLine, oMentionModel.lastCharPos - oMentionModel.lastFilter.length];
              to = [oMentionModel.lastLine, oMentionModel.lastCharPos];
            } else if (ed.isWysiwygMode()) {
              from = oMentionModel.lastCharPos - oMentionModel.lastFilter.length;
              to = oMentionModel.lastCharPos;
            }
            
            ed.replaceSelection(`[\${value ? value : oMentionModel.lastFilter}](\${url})`, from, to);
            
            if (oMentionModel.currentMentionWidget) {
              oMentionModel.lastFilter = null;
              oMentionModel.currentMentionWidget = null;
            }
          }
JS;
    }
    
    protected function buildJsCreateFilteredMentionDropdown(): string
    {
        $mentionItemCss = 'padding: 4px 12px; cursor: pointer; border-bottom: 1px solid #eee;';
        $mentionMenuUl = 'list-style: none; margin: 0; padding: 0;';
        
        $headers = ! empty($this->getAjaxHeaders()) ? 'headers: ' . json_encode($this->getAjaxHeaders()) . ',' : '';
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        
        return <<<JS

            /**
            * builds the dropdown list of suggested mentions.
            *
            * @param oMentionModel
            * @param filter
            * @returns {Promise<HTMLDivElement>}
            */
            function createFilteredMentionDropdown(oMentionModel ,filter = "") {
                return new Promise((resolve) => {
                    const wrapper = document.createElement("div");
                    wrapper.style.cssText = ed.exfWidget.mentionMenuCss;
                    
                    const regEx = new RegExp(`^\${oMentionModel.tagPrefix}`);
                    const searchQuery = filter.replace(regEx, "");
                    
                    const tagTextAttribute = oMentionModel.tagTextAttribute;
                    
                    $.ajax({
                        type: 'POST',
                        url: '{$this->getAjaxUrl()}',
                        {$headers}
                        data: {
                          action: oMentionModel.ajaxCallActionAlias,
                          object: oMentionModel.ajaxCallObjectId,
                          resource: oMentionModel.ajaxCallResourceAlias,
                          element: oMentionModel.ajaxCallElementId,
                          page: 1,
                          rows: oMentionModel.maxNumberOfRows,
                          data: {
                            oId: oMentionModel.ajaxCallActionObjectId,
                            columns: [oMentionModel.filterAttributeAlias, ...(tagTextAttribute ? [tagTextAttribute] : [])],       
                            filters: {
                              operator: 'OR',
                              conditions: [
                                  {
                                    expression: oMentionModel.filterAttributeAlias,
                                    comparator: '=',
                                    value: searchQuery
                                  },
                                  ...(oMentionModel.tagTextAttribute
                                      ? [{
                                          expression: oMentionModel.tagTextAttribute,
                                          comparator: '=',
                                          value: searchQuery
                                        }]
                                      : [])
                              ]
                            }
                          }
                        },
                        success: function (response) {
                          
                            if (response.rows.length === 0) {
                                wrapper.innerHTML = `<ul><li class="mention-item empty" style="\${ed.exfWidget.mentionItemEmptyCss}">
                                    {$translator->translate('ERROR.No_RESULTS')}
                                  </li></ul>`;
                                return resolve(wrapper);
                            }
                          
                            const mentionList = (response.rows || []).map(item => ({
                                value:  item[oMentionModel.filterAttributeAlias],
                                tagTextValue: item[oMentionModel.tagTextAttribute],
                                url: "" //TODO: Add here an clickAction from uxon.
                            }));
            
                            wrapper.innerHTML = `
                                <ul style="{$mentionMenuUl}">
                                    \${mentionList
                                        .map(item => 
                                        `<li class="mention-item" style="{$mentionItemCss}" 
                                         data-url="\${item.url}" 
                                         data-value="\${oMentionModel.tagPrefix}\${item.tagTextValue !== undefined ? `\${item.tagTextValue}` : `\${item.value}` }">
                                            \${item.value}
                                        </li>`)
                                        .join("")}
                                </ul>
                            `;
            
                            resolve(wrapper);
                        },
                        error: function () {
                            wrapper.innerHTML = `<ul><li class="mention-item empty" style="\${ed.exfWidget.mentionItemEmptyCss}">
                                {$translator->translate('WIDGET.TEXTMENTION.AJAX_CALL_ERROR')}
                              </li></ul>`;
                            
                            resolve(wrapper);
                        }
                    });
                });
            }
JS;
    }

    /**
     * It shows the collision warning with the corresponding tag prefixes
     * inside the mention widget dropdown.
     * 
     * @return string
     */
    protected function buildJsMentionCreateCollisionWarning(): string {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        
        return <<<JS
          
         function createCollisionWarning() {
            const potentialMatches = ed.exfWidget.mentionModels.filter(model => model.isPotentialMatch);
            const tagPrefixes = potentialMatches.map(model => model.tagPrefix);
  
            const widget = document.createElement("div");
            widget.style.cssText = ed.exfWidget.mentionMenuCss;
                      
            widget.innerHTML = `<ul><li class="mention-item empty" 
              style="\${ed.exfWidget.mentionItemEmptyCss}">
                {$translator->translate('WIDGET.TEXTMENTION.COLLISION')} \${tagPrefixes}
              </li></ul>`;
            
            ed.addWidget(widget, "bottom");
         }
JS;
    }

    /**
     * It resets the current mention widget.
     * 
     * @return string
     */
    protected function buildJsMentionResetMentionModel(): string {
        
        return <<<JS

        function resetMentionModel(oMentionModel) {
            oMentionModel.isPotentialMatch = false;
            oMentionModel.lastFilter = null;
            
            if (oMentionModel.currentMentionWidget) {
              oMentionModel.currentMentionWidget = null;
            }
        }
JS;
    }

    protected function buildJsCustomHtmlRenderers(): string
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
                        // TODO add support for regular stencils - just insert them at cursor position!
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
        $insertHtmlTagButtonHTML = implode(' ', [
            '<button type="button"',
            //'id="' . $this->getId() . '_stencil_' . spl_object_id($stencil) . '"',
            'style="margin: -7px -5px; background: transparent;">',
            $icon,
            '</button>',
        ]);
        
        $insertHtmlTagButtonJs = <<<JS
                (function (){
                    let button = \$('$insertHtmlTagButtonHTML')[0];
                    button.addEventListener('click', () => {
                        let  oEditor = {$this->buildJsMarkdownVar()};

                        const [start, end] = oEditor.getSelection();
                        const selectedText = oEditor.getSelectedText();
        
                        // If no Text is selected.
                        if (!selectedText.trim()) {
                            return;
                        }
                        
                        if (oEditor.isMarkdownMode()) {
                          // Writes the HTML tags directly into the Markdown.
                          const wrapped = `<{$stencil->getHtmlTag()}>\${selectedText}</{$stencil->getHtmlTag()}>`;
                          oEditor.replaceSelection(wrapped, start, end);
                        } else {
                          // In WYSIWYG mode, the HTML tags must be inserted directly 
                          // into the HTML of the editor so that the customHTMLParser can process them, 
                          // as in the Markdown section above. 
                          //
                          // Note: The parser will delete all non-supported attributes 
                          // from this element if given.
                          const htmlElement = document.createElement("{$stencil->getHtmlTag()}");
                          const userSelection = window.getSelection();
                          const selectedTextRange = userSelection.getRangeAt(0);
                          selectedTextRange.surroundContents(htmlElement);
                        }
                    });
                    
                    return button;
                })()
JS;
        return <<<JS
                {
                    name: {$this->escapeString($stencil->getCaption())},
                    tooltip: {$this->escapeString($stencil->getHint())},
                    el: {$insertHtmlTagButtonJs}
                }
JS;

    }
    
    protected function buildJsCustomHtmlInlineRenderer(TextStencil $stencil) : string
    {
        return <<<JS
          {$stencil->getHtmlTag()}(node, { entering }) {
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
                    if(oEditor.isMarkdownMode()) {
                      value = oEditor.getMarkdown();
                    } else {
                      // ToastUi widgets in WYSIWYG mode like "[@Andrej]()" are saved as "\$\$widget0 [@Andrej])$$"
                      // Bevor save, we have to get rid of the "\$\$widget0 .. $$" wrapper. 
                      // To do so, we switch here to "Markdown" editor mode, write the Markdown to the value, 
                      // and then switch back to the last-used mode.
                      const currentMode = oEditor.mode;
                      oEditor.changeMode("markdown",true);
                  
                      value = oEditor.getMarkdown();
                      
                      oEditor.changeMode(currentMode,true);
                    }
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