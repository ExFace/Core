<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\DataTypes\ColorDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Shared value and lifecycle contract for independently adopted Markian editors.
 * 
 * Facades own asset loading, framework bindings and layout. This trait never
 * changes the legacy Toast UI integration or constructs a display viewer.
 * 
 * ## How to use
 * 
 * 1. Add `axenox/markian` to the facade's Composer dependencies. The package supplies
 *    prebuilt browser assets; consumers do not need Node.js or a build step.
 * 2. Add the following asset settings to the facade configuration. Paths are relative
 *    to the installation's vendor directory and resolved through `buildUrlToSource()`:
 * 
 * ```
 * 
 * {
 *     "LIBS.MARKIAN.EDITOR.JS": "axenox/markian/dist/markian.min.js",
 *     "LIBS.MARKIAN.EDITOR.CSS": "axenox/markian/dist/markian.css",
 *     "LIBS.MARKIAN.MARKDOWN.CSS": "npm-asset/github-markdown-css/github-markdown-light.css",
 *     "LIBS.MARKIAN.MARKDOWN.CSS_CLASSES": ["markdown-body"],
 *     "LIBS.MARKIAN.CODE.JS": "axenox/markian/dist/extensions/code.min.js",
 *     "LIBS.MARKIAN.CODE.CSS": "axenox/markian/dist/extensions/code.css",
 *     "LIBS.MARKIAN.CODE.LANGUAGES": "axenox/markian/dist/extensions/languages/",
 *     "LIBS.MARKIAN.BRIDGE.JS": "exface/core/Facades/AbstractAjaxFacade/js/markian/MarkianAdapter.js"
 * }
 * 
 * ```
 * 
 * 3. Use this trait in the facade's `InputMarkdown` element. The element must provide
 *    `getId()`, `getWidget()` and `getFacade()`, as an `AbstractJqueryElement` does.
 *    See `\exface\JEasyUIFacade\Facades\Elements\EuiInputMarkdown` for a complete example.
 * 4. Render a stable host element with that ID and an explicit height or minimum
 *    height. Return `buildHtmlMarkianHeadTags()` from the element's head-tag builder
 *    (or merge its result with other required assets). Once the host exists and the
 *    scripts have loaded, execute `buildJsMarkdownInitEditor()`.
 * 5. Retain the trait's `buildJsValueGetter()`, `buildJsValueSetter()` and
 *    `buildJsResetter()` for Markdown source access. Wire the host's bubbling `change`
 *    event to the facade's change handler, filtering for the host as `event.target`.
 *    Required-state handling and validation remain the facade element's responsibility.
 * 6. Use the handle expression from `buildJsMarkdownVar()` to call `setDisabled()`,
 *    `focus()` or `resize()` from facade lifecycle hooks. The handle can be absent
 *    before mounting, so guard those calls. Call `buildJsMarkdownRemove()` during
 *    element teardown; automatic DOM-removal cleanup is an additional safeguard.
 * 
 * ## Optional Mermaid rendering
 * 
 * If the widget enables Mermaid, configure `LIBS.MARKIAN.MERMAID.JS` and
 * `LIBS.MARKIAN.MERMAID.CSS` as `axenox/markian/dist/extensions/mermaid.min.js` and
 * `axenox/markian/dist/extensions/mermaid.css`. Configure `LIBS.MERMAID.JS` with the
 * host's Mermaid runtime URL. The adapter passes that URL as a `source` string;
 * no runtime version setting or former `asset` object is required.
 * 
 * ## Adapter ownership and display widgets
 * 
 * Keep foreign-library lifecycle logic in the pure JavaScript `MarkianAdapter`;
 * PHP should generate configuration, asset declarations and adapter calls. The
 * adapter owns the inner mount, readiness, queued values and disabled state,
 * resize observation and disposal. Do not initialize Markian separately on the
 * same host. A different host value intentionally discards pending drafts and
 * resets editor history; identical projected values are left unchanged.
 * 
 * `buildJsMarkdownInitEditor()` always creates an editor, even if its compatibility
 * argument is true. Display elements must initialize `MarkianViewer` through
 * `MarkianAdapter.mount()` with viewer mode enabled and use
 * `buildHtmlMarkianHeadTags(true)`. They additionally need `LIBS.MARKIAN.VIEWER.JS`
 * and `.CSS`, and, for Mermaid, `LIBS.MARKIAN.VIEWER_MERMAID.JS` and `.CSS`.
 * See `\exface\JEasyUIFacade\Facades\Elements\EuiMarkdown` for that integration.
 */
trait MarkianTrait
{
    /**
     * Keeps instance ownership on the actual mount rather than a reusable global ID.
     *
     * @return string
     */
    protected function buildJsMarkdownVar() : string
    {
        $idJs = "'" . substr(json_encode($this->getId(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), 1, -1) . "'";
        return '(document.getElementById(' . $idJs . ') || {})._exfMarkian';
    }

    /**
     * Encodes source for inline scripts without interpreting document HTML.
     *
     * @param mixed $markdown
     * @return string
     */
    protected function escapeMarkdownForInlineJavaScript($markdown) : string
    {
        return json_encode((string) ($markdown ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * Reads active drafts without switching a hidden editor's mode.
     *
     * @return string
     */
    public function buildJsValueGetter()
    {
        return '(function(){var e = ' . $this->buildJsMarkdownVar() . '; return e ? e.get() : "";})()';
    }

    /**
     * Loads a new host value explicitly while leaving identical model echoes alone.
     *
     * @param string $value JavaScript expression.
     * @param bool $disable_formatting Retained for display-element compatibility.
     * @return string
     */
    public function buildJsValueSetter($value, $disable_formatting = false) : string
    {
        return <<<JS
(function(value){
    var editor = {$this->buildJsMarkdownVar()};
    if (editor) editor.set(String(value == null ? '' : value));
})({$value});
JS;
    }

    /**
     * Releases the owned instance without affecting other facades or editors.
     *
     * @return string
     */
    protected function buildJsMarkdownRemove() : string
    {
        return '(function(){var e = ' . $this->buildJsMarkdownVar() . '; if(e) e.destroy();})();';
    }

    /**
     * Creates an editor through the host bridge, including pre-readiness value updates.
     *
     * @param bool $isViewer Retained for inherited element compatibility; inputs always edit.
     * @return string
     */
    protected function buildJsMarkdownInitEditor(bool $isViewer = false) : string
    {
        $widget = $this->getWidget();
        $type = $widget->getValueDataType();
        $options = [
            'markdown' => (string) ($widget->getValueWithDefaults() ?? ''),
            'markdownCssUrls' => [$this->getFacade()->buildUrlToSource('LIBS.MARKIAN.MARKDOWN.CSS')],
            'markdownCssClasses' => $this->getFacade()->getConfig()->getOption('LIBS.MARKIAN.MARKDOWN.CSS_CLASSES')->toArray(),
            'initialMode' => 'visual',
            'disabled' => $widget->isDisabled(),
            'readOnly' => $widget->isReadonly(),
            'label' => $widget->getCaption() ?: 'Markdown editor',
            'softbreaks' => $type instanceof MarkdownDataType ? $type->getEnableNewlines() : true,
            'sidebarVisible' => false
        ];
        $optionsJs = json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $optionsJs = 'Object.assign(' . $optionsJs . ', {tags: MarkianAdapter.buildTags(' . $this->buildJsMarkdownMentions() . '), extensions: {code: {enabled: true, config: MarkianAdapter.buildCodeConfig(' . $this->buildJsMarkdownCodeSettings() . ')}}})';
        $mermaidJs = $widget->hasRenderMermaidDiagrams()
            ? $this->escapeMarkdownForInlineJavaScript($this->getFacade()->buildUrlToSource('LIBS.MERMAID.JS'))
            : 'null';
        $allowImagesJs = $widget->getAllowImages() ? 'true' : 'false';
        return 'MarkianAdapter.mount(document.getElementById(' . json_encode($this->getId()) . '), ' . $optionsJs . ', false, ' . $mermaidJs . ', ' . $allowImagesJs . ')';
    }

    /**
     * Passes abstract highlighting settings and resolved asset URLs to the library adapter.
     *
     * @return string
     */
    protected function buildJsMarkdownCodeSettings() : string
    {
        $formatter = $this->getWidget()->getCodeFormatter();
        return json_encode([
            'language' => $formatter->getLanguage(),
            'colorize' => $formatter->getColorize(),
            'languageBase' => $this->getFacade()->buildUrlToSource('LIBS.MARKIAN.CODE.LANGUAGES')
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * Translates abstract mention settings into explicit host-side AJAX metadata.
     * 
     * @return string
     */
    protected function buildJsMarkdownMentions() : string
    {
        $models = [];
        foreach ($this->getWidget()->getMentions() as $index => $mention) {
            $autosuggest = $mention->getAutosuggest();
            $object = $autosuggest->getObject();
            $filter = $object->getAttribute($autosuggest->getFilterAttribute() ?? ($object->hasLabelAttribute() ? $object->getLabelAttributeAlias() : $object->getUidAttributeAlias()))->getAliasWithRelationPath();
            $tag = $object->getAttribute($autosuggest->getTagAttribute() ?? $filter)->getAliasWithRelationPath();
            $suggestion = $object->getAttribute($autosuggest->getSuggestionAttribute() ?? $filter)->getAliasWithRelationPath();
            $limit = $autosuggest->getMaxNumberOfRows() ?? 10;
            if ($limit < 1 || $limit > 100) {
                throw new WidgetConfigurationError($this->getWidget(), 'Mention autosuggest max_number_of_rows must be between 1 and 100.');
            }
            $color = $mention->getTagColor();
            if ($color !== null) {
                $rgba = ColorDataType::parseToRgba($color);
                if ($rgba === null || $rgba[3] !== 1.0) {
                    throw new WidgetConfigurationError($this->getWidget(), 'Mention tag_color must be an opaque CSS color.');
                }
                $color = sprintf('#%02x%02x%02x', $rgba[0], $rgba[1], $rgba[2]);
            }
            $button = $mention->getAutosuggestButton();
            $action = $button->getAction();
            $models[] = [
                'id' => 'mention-' . $index,
                'prefix' => $mention->getTagPrefix(),
                'textRegex' => $mention->getTagTextRegex(),
                'color' => $color,
                'caption' => $mention->getCaption(),
                'filterAttribute' => $filter,
                'filterAttributeIsNumeric' => $object->getAttribute($filter)->getDataType() instanceof NumberDataType,
                'tagAttribute' => $tag,
                'tagAttributeIsNumeric' => $object->getAttribute($tag)->getDataType() instanceof NumberDataType,
                'suggestionAttribute' => $suggestion,
                'limit' => $limit,
                'url' => $this->getAjaxUrl(),
                'headers' => $this->getAjaxHeaders(),
                'request' => [
                    'action' => $action->getAliasWithNamespace(),
                    'object' => $action->getMetaObject()->getId(),
                    'resource' => $button->getPage()->getAliasWithNamespace(),
                    'element' => $button->getId()
                ]
            ];
        }
        return json_encode($models, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * Loads only the assets needed by this editor or rendering-only viewer.
     *
     * @param bool $viewer
     * @return string[]
     */
    protected function buildHtmlMarkianHeadTags(bool $viewer = false) : array
    {
        $facade = $this->getFacade();
        $prefix = $viewer ? 'LIBS.MARKIAN.VIEWER' : 'LIBS.MARKIAN.EDITOR';
        $includes = [
            '<link rel="stylesheet" href="' . $facade->buildUrlToSource($prefix . '.CSS') . '" />',
            '<script src="' . $facade->buildUrlToSource($prefix . '.JS') . '"></script>',
            '<script src="' . $facade->buildUrlToSource('LIBS.MARKIAN.BRIDGE.JS') . '"></script>'
        ];
        $includes[] = '<link rel="stylesheet" href="' . $facade->buildUrlToSource('LIBS.MARKIAN.CODE.CSS') . '" />';
        $includes[] = '<script src="' . $facade->buildUrlToSource('LIBS.MARKIAN.CODE.JS') . '"></script>';
        if ($this->getWidget()->hasRenderMermaidDiagrams()) {
            $prefix = $viewer ? 'LIBS.MARKIAN.VIEWER_MERMAID' : 'LIBS.MARKIAN.MERMAID';
            $includes[] = '<link rel="stylesheet" href="' . $facade->buildUrlToSource($prefix . '.CSS') . '" />';
            $includes[] = '<script src="' . $facade->buildUrlToSource($prefix . '.JS') . '"></script>';
        }
        return $includes;
    }

    /**
     * Resets source through safe script encoding rather than interpolated quotes.
     *
     * {@inheritDoc}
     *
     * @see AbstractJqueryElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        if ($this->getWidget()->getValueWidgetLink() !== null) {
            return '';
        }
        return $this->buildJsValueSetter($this->escapeMarkdownForInlineJavaScript($this->getWidget()->getValueWithDefaults()));
    }
}