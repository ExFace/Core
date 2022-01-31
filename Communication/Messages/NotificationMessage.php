<?php
namespace exface\Core\Communication\Messages;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\CommonLogic\Communication\AbstractMessage;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class NotificationMessage extends AbstractMessage implements iHaveIcon
{
    use iHaveIconTrait;
    
    private $widgetUxon = null;
    
    private $buttonsUxon = null;
    
    private $text = null;
    
    private $title = null;
    
    /**
     * 
     * @throws RuntimeException
     * @return UxonObject|NULL
     */
    public function getContentWidgetUxon() : ?UxonObject
    {
        if ($this->widgetUxon === null) {
            $textUxon = new UxonObject([
                'widget_type' => 'Text',
                'hide_caption' => true,
                'text' =>  $this->getText()
            ]);
            $this->widgetUxon = $textUxon;
        }
        return $this->widgetUxon;
    }
    
    /**
     * The widget to show in the expanded view of the notification
     * 
     * @uxon-property content_widget
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     * @uxon-template {"widget_type":""}
     * 
     * @param UxonObject $uxon
     * @return NotificationMessage
     */
    protected function setContentWidget(UxonObject $uxon) : NotificationMessage
    {
        $this->widgetUxon = $uxon;
        return $this;
    }
    
    public function getButtonsUxon() : ?UxonObject
    {
        return $this->buttonsUxon;
    }
    
    /**
     * Buttons to show for the notification
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template [{"caption": "", "action": {"alias": "", "object_alias": ""}}]
     * 
     * @param UxonObject $value
     * @return NotificationMessage
     */
    protected function setButtons(UxonObject $value) : NotificationMessage
    {
        $this->buttonsUxon = $value;
        return $this;
    }
    
    public function getText() : string
    {
        return $this->text ?? '';
    }
    
    /**
     * A simple text for the notification (instead of complex `content_widget`)
     * 
     * @uxon-property text
     * @uxon-type string
     * 
     * @param string $value
     * @return NotificationMessage
     */
    protected function setText(string $value) : NotificationMessage
    {
        $this->text = $value;
        return $this;
    }
    
    public function getTitle() : ?string
    {
        return $this->title;
    }
    
    /**
     * The title of the notification (shown in the menu when the context icon is clicked)
     *
     * @uxon-property title
     * @uxon-type string
     *
     * @param string $value
     * @return NotificationMessage
     */
    public function setTitle(string $value) : NotificationMessage
    {
        $this->title = $value;
        return $this;
    }
}