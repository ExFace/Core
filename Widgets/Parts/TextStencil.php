<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveCaption;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Traits\iHaveCaptionTrait;
use exface\Core\Widgets\Traits\iHaveIconTrait;

/**
 * Configuration for a stencil (insertable template) for all sorts of text editors
 * 
 * Text editing widgets like InputMarkdown will typically have toolbars and stencils will be accessible
 * as items in these toolbars. When a user presses the toolbar button, the stencil will be inserted at
 * the cursor position or around the selection.
 * 
 * Each stencils will need:
 * 
 * - `caption` - will be shown in the toolbar
 * - `hint` - will typically appear as tooltip
 * - `icon` or `icon-text` will be displayed on the tooblar button 
 * 
 * @author Andrej Kabachnik
 *
 */
class TextStencil implements WidgetPartInterface, iHaveCaption, iHaveIcon
{    
    use ImportUxonObjectTrait;
    use iHaveCaptionTrait;
    use iHaveIconTrait;
    
    private WidgetInterface $widget;
    private UxonObject $uxon;
    private ?string $hint = null;
    private ?string $iconText = null;
    
    public function __construct(WidgetInterface $widget, UxonObject $uxon)
    {
        $this->widget = $widget;
        $this->uxon = $uxon;
        $this->importUxonObject($uxon, ['type']);
    }

    /**
     * @return bool
     */
    public function isHtmlTag(): bool
    {
        return false;
    }

    /**
     * @return string|null
     */
    public function getHint(): ?string
    {
        return $this->hint;
    }

    /**
     * Tooltip
     * 
     * @uxon-property hint
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @param string|null $hint
     * @return WidgetPartInterface
     */
    protected function setHint(?string $hint): WidgetPartInterface
    {
        $this->hint = $hint;
        return $this;
    }
    
    public function getIconText(): ?string
    {
        return $this->iconText;
    }

    /**
     * Short text to use in the toolbar instead of an icon
     * 
     * @uxon-property icon_text
     * @uxon-type string
     * @uxon-template BT
     * 
     * @param string|null $iconText
     * @return WidgetPartInterface
     */
    protected function setIconText(?string $iconText): WidgetPartInterface
    {
        $this->iconText = $iconText;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->uxon;
    }

    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
}