<?php
namespace exface\Core\Widgets;

/**
 * The TextHeading widget can be used for headings.
 * 
 * In most HTML-based templates it will get mapped to <h1></h1> or similar.
 *
 * @author Andrej Kabachnik
 *        
 */
class TextHeading extends Text
{

    private $heading_level = null;

    /**
     *
     * @return integer
     */
    public function getHeadingLevel()
    {
        return is_null($this->heading_level) ? 1 : $this->heading_level;
    }

    /**
     * Sets the level of the heading (e.g. 1 for top-level heading, 2 for subheading, etc.)
     *
     * @uxon-property heading_level
     * @uxon-type integer
     * @uxon-default 1
     * 
     * @param integer $value            
     * @return \exface\Core\Widgets\TextHeading
     */
    public function setHeadingLevel($value)
    {
        $this->heading_level = $value;
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
        if (! is_null($this->heading_level)) {
            $uxon->setProperty('heading_level', $this->getHeadingLevel());
        }
        return $uxon;
    }
}
?>