<?php
namespace exface\Core\Communication\Messages;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Widgets\Traits\iHaveIconTrait;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class NotificationMessage extends GenericMessage implements iHaveIcon
{
    use iHaveIconTrait;
    
    private $widgetUxon = null;
    
    private $buttonsUxon = null;
    
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
}