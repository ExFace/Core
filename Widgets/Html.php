<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
/**
 * The HTML widget simply shows some HTML.
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
 * @author Andrej Kabachnik
 *        
 */
class Html extends Display
{
    private $css = null;

    private $javascript = null;
    
    private $headTags = null;
    
    private $margins = false;
    
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
     * Defines the HTML to show.
     * 
     * NOTE: Any <script> tags will be automatically extracted and placed in the "javascript" property!
     * 
     * @uxon-property html
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Text
     */
    public function setHtml($value)
    {
        return $this->setValue($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::setValue()
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
     * Defines custom CSS for this widget: accepts any CSS style definitions.
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
     */
    public function setMargins($true_or_false)
    {
        $this->margins = BooleanDataType::cast($true_or_false);
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
                $base = HttpFileServerFacade::buildUrlForDownload($this->getWorkbench(), $base);
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
}
?>