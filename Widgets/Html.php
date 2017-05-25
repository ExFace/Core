<?php
namespace exface\Core\Widgets;

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

    public function getHtml()
    {
        return $this->getText();
    }

    public function setHtml($value)
    {
        return $this->setText($value);
    }

    public function getCss()
    {
        return $this->css;
    }

    public function setCss($value)
    {
        $this->css = $value;
        return $this;
    }

    public function getJavascript()
    {
        return $this->javascript;
    }

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
}
?>