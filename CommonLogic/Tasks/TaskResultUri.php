<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultFileInterface;
use exface\Core\Interfaces\Tasks\TaskResultUriInterface;
use Psr\Http\Message\UriInterface;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Task result containing a downloadable file: i.e. text, code, etc..
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultUri extends TaskResultMessage implements TaskResultUriInterface
{
    private $uri = null;
    
    private $autoRedirect = true;
    
    private $openInNewWindow = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultUriInterface::setUri()
     */
    public function setUri(UriInterface $uri): TaskResultUriInterface
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultUriInterface::getUri()
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultUriInterface::hasUri()
     */
    public function hasUri(): bool
    {
        return is_null($this->uri) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultUriInterface::getAutoRedirect()
     */
    public function getAutoRedirect() : bool
    {
        return $this->autoRedirect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultUriInterface::setAutoRedirect()
     */
    public function setAutoRedirect($trueOrFalse) : TaskResultUriInterface
    {
        $this->autoRedirect = BooleanDataType::cast($trueOrFalse);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultUriInterface::setOpenInNewWindow()
     */
    public function setOpenInNewWindow($trueOrFalse): TaskResultUriInterface
    {
        $this->openInNewWindow = BooleanDataType::cast($trueOrFalse);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultUriInterface::getOpenInNewWindow()
     */
    public function getOpenInNewWindow(): bool
    {
        return $this->openInNewWindow;
    }


    
}