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

    const FOLDER_INBOX = 'INBOX';
    
    private $widgetUxon = null;
    
    private $buttonsUxon = null;
    
    private $text = null;
    
    private $title = null;

    private $folder = null;

    private $senderName = null;

    private $sendingTime = null;

    private $reference = null;
    
    /**
     * 
     * @throws RuntimeException
     * @return UxonObject|NULL
     */
    public function getBodyWidgetUxon() : ?UxonObject
    {
        if ($this->widgetUxon === null) {
            $textUxon = new UxonObject([
                'widget_type' => 'Markdown',
                'hide_caption' => true,
                'value' =>  $this->getText()
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
     * The title of the notification (shown in the menu when the context icon is clicked).
     * alternative you can use `title`.
     *
     * @uxon-property subject
     * @uxon-type string
     *
     * @param string $value
     * @return NotificationMessage
     */
    public function setSubject(string $value) : NotificationMessage
    {
        return $this->setTitle($value);
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

    public function getFolder() : ?string
    {
        return $this->folder;
    }

    /**
     * Place this message into a folder
     * 
     * @uxon-property folder
     * @uxon-type string
     * 
     * @param string $name
     * @return \exface\Core\Communication\Messages\NotificationMessage
     */
    protected function setFolder(string $name) : NotificationMessage
    {
        $this->folder = $name;
        return $this;
    }

    public function getSenderName() : ?string
    {
        return $this->senderName;
    }

    /**
     * What to be displayed as sender of the message.
     * 
     * Examples:
     * 
     * - `Administration`
     * - `Background validation`
     * - `[#=User('FULL_NAME')#]`
     * 
     * @uxon-property sender
     * @uxon-type string
     * 
     * @param string $name
     * @return \exface\Core\Communication\Messages\NotificationMessage
     */
    protected function setSender(string $name) : NotificationMessage
    {
        $this->senderName = $name;
        return $this;
    }

    public function getReference() : ?string
    {
        return $this->reference;
    }

    public function setReference(string $value) : NotificationMessage
    {
        $this->reference = $value;
        return $this;
    }

    /**
     * 
     * @return string|null
     */
    public function getSendingTime() : ?string
    {
        return $this->sendingTime;
    }

    /**
     * 
     * @param string $dateTime
     * @return \exface\Core\Communication\Messages\NotificationMessage
     */
    protected function setSendingTime(string $dateTime) : NotificationMessage
    {
        $this->sendingTime = $dateTime;
        return $this;
    }
}