<?php

namespace exface\Core\Widgets;

use exface\Core\Widgets\Traits\iRenderMermaidTrait;

/**
 * This widget can render text with Markdown syntax.
 */
class DisplayMarkdown extends Value 
{
    use iRenderMermaidTrait;
    
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
        return InputMarkdown::MODE_WYSIWYG;
    }

    /**
     * @return bool
     */
    public function getAllowImages() : bool
    {
        return 'true';
    }
}