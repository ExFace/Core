<?php
namespace exface\Core\Communication\Messages;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\Widgets\Traits\iHaveIconTrait;
use exface\Core\CommonLogic\Communication\AbstractMessage;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;

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
    public function getBodyWidgetUxon() : ?UxonObject
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
     * @uxon-property body_widget
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     * @uxon-template {"widget_type":""}
     * 
     * @param UxonObject $uxon
     * @return NotificationMessage
     */
    protected function setBodyWidget(UxonObject $uxon) : NotificationMessage
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::setText()
     */
    public function setText(string $value) : CommunicationMessageInterface
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Communication\AbstractMessage::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if ($this->title !== null) {
            $uxon->setProperty('title', $this->title);
        }
        if ($this->getIcon() !== null) {
            $uxon->setProperty('icon', $this->getIcon());
        }
        if ($this->getIconSet() !== null) {
            $uxon->setProperty('icon_set', $this->getIconSet());
        }
        if ($this->text !== null) {
            $uxon->setProperty('text', $this->text);
        }
        if ($this->widgetUxon !== null) {
            $uxon->setProperty('body_widget', $this->widgetUxon);
        }
        if ($this->buttonsUxon !== null) {
            $uxon->setProperty('buttons', $this->buttonsUxon);
        }
        return $uxon;
    }
}