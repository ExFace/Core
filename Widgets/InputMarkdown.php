<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\TextStencil;

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
    private bool $allowImages = false;
    private array $stencils = [];
    private ?UxonObject $stencilsUxon = null;
    
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

    /**
     * Toggle whether users are allowed to paste images into the editor.
     * WARNING: For now this property merely disables the image upload button,
     * users can still paste images directly into the text.
     * 
     * @uxon-property allow_images
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool $value
     * @return $this
     */
    public function setAllowImages(bool $value) : static
    {
        $this->allowImages = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowImages() : bool
    {
        return  $this->allowImages;
    }

    /**
     * @return TextStencil[]
     */
    public function getStencils() : array
    {
        if (empty($this->stencils) && $this->stencilsUxon !== null) {
            foreach ($this->stencilsUxon->getPropertiesAll() as $uxon) {
                $type = $uxon->getProperty('type');
                if (! empty($type)) {
                    $class = '\\exface\\Core\\Widgets\\Parts\\' . $type . 'Stencil';
                    if (! class_exists($class)) {
                        $class = null;
                    }
                }
                $class = $class ?? '\\exface\\Core\\Widgets\\Parts\\TextStencil';
                $this->stencils[] = new $class($this, $uxon);
            }
        }
        return $this->stencils;
    }

    /**
     * Array of stencils (templates), that will be available through the toolbar of the editor
     * 
     * @uxon-property stencils
     * @uxon-type \exface\Core\Widgets\Parts\TextStencil[]
     * @uxon-template [{"type": "HtmlTag", "caption": "", "hint": ""}]
     * 
     * @param UxonObject $arrayOfUxons
     * @return $this
     */
    protected function setStencils(UxonObject $arrayOfUxons) : InputMarkdown
    {
        $this->stencilsUxon = $arrayOfUxons;
        return $this;
    }
}