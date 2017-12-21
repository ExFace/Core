<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;

/**
 * The HTML widget simply shows some HTML.
 * In contrast to a Text widget it will be seamlessly embedded in an HTML-based template
 * and not put into a paragraph as plain text.
 *
 * @author Andrej Kabachnik
 *        
 */
class Html extends Text
{

    private $css = null;

    private $javascript = null;
    
    private $headTags = null;
    
    private $margins = false;

    /**
     * 
     * @return string|NULL
     */
    public function getHtml()
    {
        return $this->getText();
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
        return $this->setText($value);
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
     * NOTE: only HTML-templates will actually place these tags in the head of the page, 
     * while other templates may use a different location with a similar result.
     * 
     * @uxon-property head_tags
     * @uxon-type string
     * 
     * @param string $html
     */
    public function setHeadTags($html)
    {
        $this->headTags = $html;
        return $this;
    }


}
?>