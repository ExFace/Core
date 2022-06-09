<?php
namespace exface\Core\Exceptions\Communication;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * Exception thrown if a communication message could not be sent.
 *
 * @author Andrej Kabachnik
 *        
 */
class CommunicationNotSentError extends RuntimeException implements CommunicationExceptionInterface
{
    private $communicationMsg = null;
    
    private $connection = null;
    
    private $debugMarkdown = nulL;
    
    /**
     * 
     * @param CommunicationMessageInterface $communicationMessage
     * @param string $errorMessage
     * @param string $alias
     * @param \Throwable $previous
     * @param CommunicationConnectionInterface $connection
     * @param string $debugMarkdown
     */
    public function __construct(CommunicationMessageInterface $communicationMessage, $errorMessage, $alias = null, $previous = null, CommunicationConnectionInterface $connection = null, string $debugMarkdown = null)
    {
        parent::__construct($errorMessage, null, $previous);
        $this->setAlias($alias);
        $this->communicationMsg = $communicationMessage;
        $this->connection = $connection;
        $this->debugMarkdown = $debugMarkdown;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface::getCommunicationMessage()
     */
    public function getCommunicationMessage() : CommunicationMessageInterface
    {
        return $this->communicationMsg;
    }
    
    /**
     * 
     * @return CommunicationConnectionInterface|NULL
     */
    public function getConnection() : ?CommunicationConnectionInterface
    {
        return $this->connection;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getDebugMarkdown() : ?string
    {
        return $this->debugMarkdown;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);
        // Add a tab for the message
        $debugWidget = $this->getCommunicationMessage()->createDebugWidget($debugWidget);
        // Add a debug tab if there is debug information available
        if ($debug = $this->getDebugMarkdown()) {
            $debugWidget->addTab(WidgetFactory::createFromUxonInParent($debugWidget, new UxonObject([
                'widget_type' => 'Tab',
                'caption' => 'Communication debug',
                'widgets' => [
                    [
                        'widget_type' => 'Markdown',
                        'width' => '100%',
                        'height' => '100%',
                        'value' => $debug
                    ]
                ]
            ])));
        }
        return $debugWidget;
    }
}
