<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
/**
 * The HTML widget simply shows some HTML - either static or from an attribute.
 * 
 * In contrast to a Text widget it will be seamlessly embedded in an HTML-based facade
 * and not put into a paragraph as plain text.
 * 
 * The HTML can be 
 * 
 * - loaded from a data source (by specifying an `attribute_alias` for the widget)
 * - loaded from a file (by specifying the path in `file`)
 * - directly specified in the `html` property of the widget
 * 
 * To make the HTML better fit the facade, you can add `html_template` to wrap the HTML in a template, add `css` to 
 * style the HTML and add `javascript` to add custom JS code.
 * 
 * The `Html` widget embeds the HTML code into the page optionally added custom CSS and Javascript too. So the HTML
 * becomes and integral part of the page rendered by the selected facade and will be affected by the facades scripts
 * and styling. The CSS and Javascript defined in the widget are merely supplements. If you need "clean" HTML rendering,
 * consider the `Browser` widget.
 * 
 * **NOTE:** HTML pages saved somewhere on the web server may be accessible directly (bypassing the workbench security
 * system) depending on the server configuration. Take care to secure them properly!
 *
 * @author Andrej Kabachnik
 *        
 */
class Html extends Display
{
    private $css = null;
    private $cssInlineStyle = null;

    private $javascript = null;
    
    private $headTags = null;
    
    private $htmlTemplate = null;
    
    private $margins = false;
    
    private $inline = false;
    
    private $baseUrl = '';
    
    private $baseUrlAttributeAlias = null;
    
    private $filePathRelativeToBase = null;

    /**
     * 
     * @return string|NULL
     */
    public function getHtml()
    {
        return $this->getValue();
    }

    /**
     * @depracated use value instead
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Html
     */
    protected function setHtml($value)
    {
        return $this->setValue($value);
    }

    /**
     * @return string|null
     */
    public function getHtmlTemplate() : ?string
    {
        return $this->htmlTemplate;
    }

    /**
     * A template to wrap around the HTML value
     * 
     * E.g. `<div class="myclass">[#~value#]</div>`
     * 
     * @uxon-property html_template
     * @uxon-type string
     * @uxon-template <div class="myclass">[#~value#]</div>
     * 
     * @param string $value
     * @return $this
     */
    public function setHtmlTemplate(string $value) : Html
    {
        $this->htmlTemplate = $value;
        return $this;
    }
    
    /**
     * Explicitly sets the value of the widget: static value, widget link or formula.
     * 
     * *WARNING:** If a calculated expression (link of formula) is used as `value`, the widget
     * will not be able to read the value of its attribute or prefill data. In fact, it will not
     * get prefilled at all: the formula replaces the own value. However, if the widget is interactive,
     * the value will still change on user input as long as the widget is not disabled.
     * 
     * If you want the widget to have its own value in addition to a calculation use the `calculation`
     * option explicitly. In this case, the calculation will only be performed if the widget has no
     * explicit value.
     *
     * @uxon-property value
     * @uxon-type metamodel:expression
     * @uxon-translatable true
     * 
     * @see \exface\Core\Widgets\Value::setValue()
     */
    public function setValue($value, bool $parseStringAsExpression = true)
    {
        if ($value !== null) {
            $value = $this->rebaseRelativeLinks($value);
        }
        return parent::setValue($value, $parseStringAsExpression);
    }

    /**
     * 
     * @return string
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * Custom CSS for styling DOM elements defined in the `html` property.
     * 
     * @uxon-property css
     * @uxon-type string
     * 
     * @return Html
     */
    public function setCss($value)
    {
        $this->css = $value;
        return $this;
    }

    /**
     *
     * @return string|null
     */
    public function getCssInlineStyle() : ?string
    {
        return $this->cssInlineStyle;
    }

    /**
     * Defines the value for the `style` DOM element property for this particular widget.
     * 
     * Most facades will generate a `<div>` element for an HTML widget. Using `css_inline_style` you can inject
     * styles into `<div style="">`.
     *
     * @uxon-property css_inline_style
     * @uxon-type string
     *
     * @return Html
     */
    public function setCssInlineStyle($value) : Html
    {
        $this->cssInlineStyle = $value;
        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getJavascript()
    {
        return $this->javascript;
    }

    /**
     * Specifies custom javascript code for this widget. 
     * 
     * @uxon-property javascript
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Html
     */
    public function setJavascript($value)
    {
        $this->javascript = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Text::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if (! is_null($this->getCss())) {
            $uxon->setProperty('css', $this->getCss());
        }
        if (! is_null($this->getJavascript())) {
            $uxon->setProperty('javascript', $this->getJavascript());
        }
        return $uxon;
    }
    
    /**
     * @return boolean
     */
    public function getMargins()
    {
        return $this->margins;
    }

    /**
     * Set to TRUE to enable margins around the HTML block - FALSE by default.
     * 
     * @uxon-property margins
     * @uxon-type boolean
     * @uxon-default
     * 
     * @param boolean $margins
     * @return Html
     */
    public function setMargins(bool $true_or_false)
    {
        $this->margins = $true_or_false;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getHeadTags()
    {
        return $this->headTags;
    }

    /**
     * Allows to specify HTML tags for the <head> section of the resulting page.
     * 
     * NOTE: only HTML-facades will actually place these tags in the head of the page, 
     * while other facades may use a different location with a similar result.
     * 
     * @uxon-property head_tags
     * @uxon-type string
     * 
     * @param string $html
     */
    public function setHeadTags($html)
    {
        $this->headTags = $this->rebaseRelativeLinks($html);
        return $this;
    }

    /**
     * Sets a static base URL: all relative links will be resolved relative to this URL.
     * 
     * @uxon-property base_url
     * @uxon-type uri
     * 
     * @param string $url
     * @return Html
     */
    public function setBaseUrl(string $url) : Html
    {
        $this->baseUrl = $url;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getBaseUrl() : string
    {
        return $this->baseUrl;
    }
    
    /**
     * 
     * @return string|null
     */
    public function getBaseUrlAttributeAlias()
    {
        return $this->baseUrlAttributeAlias;
    }

    /**
     * Sets a dynamic base URL to be fetched from prefill data.
     * 
     * @uxon-property base_url
     * @uxon-type metamodel:attribute
     * 
     * @param string $string
     * @return Html
     */
    public function setBaseUrlAttributeAlias(string $string) : Html
    {
        $this->baseUrlAttributeAlias = $string;
        return $this;
    }
    
    protected function doPrefill(DataSheetInterface $dataSheet)
    {
        if ($baseAlias = $this->getBaseUrlAttributeAlias()) {
            if ($dataSheet->getMetaObject()->is($this->getMetaObject())) {
                if ($column = $dataSheet->getColumns()->getByExpression($baseAlias)) {
                    $pointer = DataPointerFactory::createFromColumn($column, 0);
                    $this->setBaseUrl($pointer->getValue());
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'base_url', $pointer));
                }
            } else {
                // TODO
            }
        }
        parent::doPrefill($dataSheet);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        if ($baseAlias = $this->getBaseUrlAttributeAlias()) {
            if ($data_sheet->getMetaObject()->is($this->getMetaObject())) {
                $data_sheet->getColumns()->addFromAttribute($this->getMetaObject()->getAttribute($baseAlias));
            } else {
                // TODO
            }
        }
        return $data_sheet;
    }
    
    /**
     * 
     * @param string $html
     * @return mixed
     */
    protected function rebaseRelativeLinks(string $html) : string
    {
        if ($base = $this->getBaseUrl()) {
            $fm = $this->getWorkbench()->filemanager();
            if ($fm::pathGetCommonBase([$base, $fm->getPathToBaseFolder()])) {
                $base = HttpFileServerFacade::buildUrlToDownloadFile($this->getWorkbench(), $base);
            }
            $base = rtrim($base, "/\\") . '/';
            $html = preg_replace('#(href|src)="([^:"]*)("|(?:(?:%20|\s|\+)[^"]*"))#','$1="' . $base . '$2$3', $html);
        }
        return $html;
    }
    
    /**
     * If required to show contents of a file, place the file path relative to base folder here.
     * 
     * NOTE: the widget cannot show file contents and be bound to a meta attribute at the same
     * time! Either user `attribute_alias` or `file`, but not both!
     * 
     * @uxon-property file
     * @uxon-type string
     * 
     * @param string $pathRelativeToBaseFolder
     * @return Html
     */
    public function setFile(string $pathRelativeToBaseFolder) : Html
    {
        $this->filePathRelativeToBase = $pathRelativeToBaseFolder;
        return $this;
    }
    
    public function isBoundToFile() : bool
    {
        return $this->filePathRelativeToBase !== null;
    }
    
    public function getFilePathRelative() : ?string
    {
        if ($this->isBoundToAttribute() === true) {
            throw new WidgetConfigurationError($this, 'Cannot bind an ' . $this->getWidgetType() . ' widget to meta attribute "' . $this->getAttributeAlias() . '" and file "' . $this->filePathRelativeToBase . '" at the same time!');
        }
        return $this->filePathRelativeToBase;
    }
    
    protected function getFilePathAbsolute() : ?string
    {
        if ($relPath = $this->getFilePathRelative()) {
            $filemanager = $this->getWorkbench()->filemanager();
            return $filemanager::pathJoin([$filemanager->getPathToBaseFolder(), $relPath]);
        }
        return null;
    }
    
    public function getFileContents() : ?string
    {
        if ($absPath = $this->getFilePathAbsolute()) {
            return file_get_contents($absPath);
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getValue()
     */
    public function getValue()
    {
        if ($this->isBoundToFile() === true) {
            return $this->getFileContents();
        }
        return parent::getValue();
    }

    /**
     * @return bool
     */
    public function getInline() : bool
    {
        return $this->inline;
    }
    
    /**
     * Set to TRUE to make the widget appear in-line with its caption.
     * 
     * @uxon-property inline
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return Html
     */
    public function setInline(bool $value) : Html
    {
        $this->inline = $value;
        return $this;
    }
}