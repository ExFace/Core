<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iContainTypedWidgets;

/**
 * Lists `Message` widgets - usefull within `Form`s, `Dialog`s, etc.
 * 
 * ## Example
 * 
 * ```
 * {
 *  "widget_type": "MessageList",
 *  "object_alias": "my.App.SOME_OBJECT",
 *  "messages": [
 *      {
 *          "type": "info",
 *          "text": "Hi! I am an info-message"
 *      }
 *  ]
 * }
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class MessageList extends Container implements iContainTypedWidgets
{
    private $messageCodesToLoad = [];
    
    /**
     * 
     * @return array
     */
    public function getMessages() : array
    {
        return $this->getWidgets();
    }
    
    /**
     * Array of `Message` widgets to display in the message list.
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\Message[]
     * @uxon-template [{"type": "info", "text": ""}]
     * 
     * @param UxonObject $uxon
     * @return MessageList
     */
    public function setMessages(UxonObject $uxon) : MessageList
    {
        return $this->setWidgets($uxon);
    }
    
    /**
     * Array of `Message` widgets to display in the message list.
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\Message[]
     * @uxon-template [{"type": "info", "text": ""}]
     * 
     * @see \exface\Core\Widgets\Container::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = [];
        foreach ($widget_or_uxon_array as $widgetOrUxon) {
            if ($widgetOrUxon instanceof UxonObject) {
                $widget = WidgetFactory::createFromUxonInParent($this, $widgetOrUxon, 'Message');
            } else {
                $widget = $widgetOrUxon;
            }
            
            if (! $this->isWidgetAllowed($widget)) {
                throw new WidgetConfigurationError($this, 'Cannot include "' . ($widget instanceof WidgetInterface ? $widget->getWidgetType() : gettype($widget)) . '" in a ' . $this->getWidgetType() . ': only Message widgets or derivatives allowed!');
            }
            
            $widgets[] = $widget;
        }
        
        return parent::setWidgets($widgets);
    }
    
    /**
     * Adds a message widget to the list.
     * 
     * @param Message $widget
     * @return MessageList
     */
    public function addMessage(Message $widget) : MessageList
    {
        $this->addWidget($widget);
        return $this;
    }
    
    /**
     * Creates an info message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addInfo(string $text, string $title = null, string $subtitle = null) : MessageList
    {
        return $this->addMessageFromString(MessageTypeDataType::INFO($this->getWorkbench()), $text, $title, $subtitle);
    }
    
    /**
     * Creates an error message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addError(string $text, string $title = null, string $subtitle = null) : MessageList
    {
        return $this->addMessageFromString(MessageTypeDataType::ERROR($this->getWorkbench()), $text, $title, $subtitle);
    }
    
    /**
     * Creates a warning message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addWarning(string $text, string $title = null, string $subtitle = null) : MessageList
    {
        return $this->addMessageFromString(MessageTypeDataType::WARNING($this->getWorkbench()), $text, $title, $subtitle);
    }
    
    /**
     * Creates a success message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addSuccess(string $text, string $title = null, string $subtitle = null) : MessageList
    {
        return $this->addMessageFromString(MessageTypeDataType::SUCCESS($this->getWorkbench()), $text, $title, $subtitle);
    }
    
    /**
     * Creates a hint message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addHint(string $text, string $title = null, string $subtitle = null) : MessageList
    {
        return $this->addMessageFromString(MessageTypeDataType::HINT($this->getWorkbench()), $text, $title, $subtitle);
    }
    
    /**
     * Creates a message of the given type and appends it to the list.
     * 
     * @param string|MessageTypeDataType $type
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addMessageFromString($type, string $text, string $title = null, string $subtitle = null) : MessageList
    {
        $message = WidgetFactory::createFromUxon($this->getPage(), new UxonObject([
            'widget_type' => 'Message',
            'type' => ($type instanceof MessageTypeDataType) ? $type->__toString() : MessageTypeDataType::cast($type),
            'text' => ($subtitle ?? $text)
        ]), $this);
        if ($title !== null) {
            $message->setCaption($title);
        }
        $this->addMessage($message);
        return $this;
    }
    
    /**
     * 
     * @param string $messageCode
     * @param string $fallbackMessage
     * @return MessageList
     */
    public function addMessageByCode(string $messageCode, string $fallbackMessage = null) : MessageList
    {
        $this->messageCodesToLoad[$messageCode] = $fallbackMessage;
        return $this;
    }
    
    /**
     * 
     * @param Message $messageModel
     * @return \exface\Core\CommonLogic\Model\Message
     */
    public function addMessageFromModel(\exface\Core\CommonLogic\Model\Message $messageModel) : MessageList
    {
        return $this->addMessageFromString($messageModel->getType(), $messageModel->getDescription() ?? '', $messageModel->getTitle() . ' (' . $messageModel->getCode() . ')', $messageModel->getHint());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter = null)
    {
        if (empty($this->messageCodesToLoad) === false) {
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.MESSAGE');
            $ds->getColumns()->addFromExpression('CODE');
            $ds->getColumns()->addFromExpression('TYPE');
            $ds->getColumns()->addFromExpression('TITLE');
            $ds->getColumns()->addFromExpression('HINT');
            $ds->getColumns()->addFromExpression('DESCRIPTION');
            $ds->getColumns()->addFromExpression('DOCS');
            $ds->getFilters()->addConditionFromValueArray('CODE', array_keys($this->messageCodesToLoad), ComparatorDataType::IN);
            $ds->dataRead();
            
            foreach ($ds->getRows() as $row) {
                $this->addMessageFromString(MessageTypeDataType::fromValue($this->getWorkbench(), $row['TYPE']), ($row['DESCRIPTION'] ?? ''), $row['TITLE'] . ' (' . $row['CODE'] . ')', $row['HINT']);
                unset($this->messageCodesToLoad[$row['CODE']]);
            }
            
            // If there are messages, that were not found in the model, just dump them
            foreach ($this->messageCodesToLoad as $code => $msg) {
                if ($msg) {
                    $this->addWarning($msg, 'Unexpected message ' . $code);
                } else {
                    $this->addError('Contact the support.', 'Invalid message code ' . $code . '!');
                }
                unset ($this->messageCodesToLoad[$code]);
            }
        }
        
        return parent::getWidgets($filter);
    }

    /**
     * {@inheritDoc}
     * @see iContainTypedWidgets::isWidgetAllowed()
     */
    public function isWidgetAllowed(WidgetInterface $widget) : bool
    {
        return $widget instanceof Message;
    }

    /**
     * {@inheritDoc}
     * @see iContainTypedWidgets::isWidgetTypeAllowed()
     */
    public function isWidgetTypeAllowed(string $typeOrClassOrInterface) : bool
    {
        if (mb_strpos($typeOrClassOrInterface, '\\') !== false) {
            $class = $typeOrClassOrInterface;
        } else {
            $class = WidgetFactory::getWidgetClassFromType($typeOrClassOrInterface);
        }
        return is_a($class, Message::class, true);
    }
}