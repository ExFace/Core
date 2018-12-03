<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;

/**
 * Lists messages within other widgets (e.g. Forms).
 *
 * @author Andrej Kabachnik
 *        
 */
class MessageList extends Container
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
     * Adds a message widget to the list.
     * 
     * @param Message $widget
     * @return MessageList
     */
    public function addMesage(Message $widget) : MessageList
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
     * @param string $type
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    protected function addMessageFromString(MessageTypeDataType $type, string $text, string $title = null, string $subtitle = null) : MessageList
    {
        $message = WidgetFactory::createFromUxon($this->getPage(), new UxonObject([
            'widget_type' => 'Message',
            'type' => $type->__toString(),
            'text' => ($subtitle ?? $text)
        ]), $this);
        if ($title !== null) {
            $message->setCaption($title);
        }
        $this->addMesage($message);
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
            $ds->addFilterInFromString('CODE', array_keys($this->messageCodesToLoad), ComparatorDataType::IN);
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
}
?>