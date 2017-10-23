<?php
namespace exface\Core\Widgets;

use cebe\markdown\GithubMarkdown;

/**
 * Shows markdown contents rendered as HTML.
 *
 * @author Andrej Kabachnik
 *        
 */
class Markdown extends Html
{
    private $markdown = null;
    
    /**
     * @return string $markdown
     */
    public function getMarkdown()
    {
        return $this->getText();
    }

    /**
     * @param string $markdown
     * @return Markdown
     */
    public function setMarkdown($string)
    {
        return $this->setText($string);
    }
    
    public function getHtml(){
        return self::convertMarkdownToHtml($this->getMarkdown());
    }

    public static function convertMarkdownToHtml($markdown)
    {
        $parser = new GithubMarkdown();
        return $parser->parse($markdown);
    }
    
    
}
?>