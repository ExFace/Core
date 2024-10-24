<?php
namespace exface\Core\Widgets;

/**
 * Markdown editor
 * 
 * How exactly the editor will look and function depends on the facade used. Facades are encouraged
 * to offer WYSIWYG-editors here or at least those with proper syntax highlighting.
 * 
 * @author Andrej Kabachnik
 *
 */
class InputMarkdown extends InputText
{
    const MODE_WYSIWYG = 'wysiwyg';
    const MODE_MARKDOWN = 'markdown';
    
    private string $mode = self::MODE_MARKDOWN;
    
    /**
     * Set the editor to a "Word-like" WYSIWYG mode or to raw markdown mode.
     * 
     * WYSIWYG means "what-you-see-is-what-you-get" and will result in the Markdown syntax
     * being hidden and the user seeing the document fully rendered with the option of editing
     * it in a similar way as in Microsoft Word or like editors.
     * 
     * @uxon-property editor_mode
     * @uxon-type [markdown,wysiwyg]
     * @uxon-default markdown
     * 
     * @return string
     */
    public function getEditorMode() : string
    {
        return $this->mode;
    }
    
    /**
     * 
     * @param string $value
     * @return InputMarkdown
     */
    public function setEditorMode(string $value) : InputMarkdown
    {
        
        $this->mode = $value;
        return $this;
    }
}