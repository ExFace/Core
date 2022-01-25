<?php
namespace exface\Core\Communication\Messages;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\RuntimeException;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class NotificationMessage extends GenericMessage
{
    private $icon = null;
    
    private $widgetUxon = null;
    
    private $buttonsUxon = null;
    
    /**
     * @deprecated use setSubject()
     * 
     * @param string $value
     * @return NotificationMessage
     */
    protected function setTitle(string $value) : NotificationMessage
    {
        return $this->setSubject($value);
    }
    
    /**
     * 
     * @throws RuntimeException
     * @return UxonObject|NULL
     */
    public function getContentWidgetUxon() : ?UxonObject
    {
        if ($this->widgetUxon === null) {
            $text = $this->getText();
            $textUxon = $this->getOptionsUxon()->getProperty('content_widget');
            if ($text && $textUxon) {
                throw new RuntimeException('Cannot set notification `text` and `content_widget` at the same time!');
            }
            if (! $textUxon) {
                $textUxon = new UxonObject([
                    'widget_type' => 'Text',
                    'hide_caption' => true,
                    'text' => $text
                ]);
            }
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
    
    /**
     * @deprecated use setText()
     * 
     * @param string $value
     * @return NotificationMessage
     */
    protected function setBody(string $value) : NotificationMessage
    {
        return $this->setText($value);
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
    
    public function getIcon() : ?string
    {
        return $this->icon;
    }
    
    /**
     * An icon to show for this notification
     * 
     * @uxon-property icon
     * @uxon-type icon
     * 
     * @param string $value
     * @return NotificationMessage
     */
    public function setIcon(string $value) : NotificationMessage
    {
        $this->icon = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getSubject()
     */
    public function getSubject(): ?string
    {
        return $this->getTitle();
    }

    
    public function getText(): string
    {
        return '';
    }
}