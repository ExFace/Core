<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultUriInterface;
use Psr\Http\Message\UriInterface;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Task result containing a downloadable file: i.e. text, code, etc..
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultUri extends ResultMessage implements ResultUriInterface
{
    private $uri = null;
    
    private $autoRedirect = true;
    
    private $openInNewWindow = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::setUri()
     */
    public function setUri(UriInterface $uri): ResultUriInterface
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::getUri()
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::hasUri()
     */
    public function hasUri(): bool
    {
        return is_null($this->uri) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::getAutoRedirect()
     */
    public function getAutoRedirect() : bool
    {
        return $this->autoRedirect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::setAutoRedirect()
     */
    public function setAutoRedirect($trueOrFalse) : ResultUriInterface
    {
        $this->autoRedirect = BooleanDataType::cast($trueOrFalse);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::setOpenInNewWindow()
     */
    public function setOpenInNewWindow($trueOrFalse): ResultUriInterface
    {
        $this->openInNewWindow = BooleanDataType::cast($trueOrFalse);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::getOpenInNewWindow()
     */
    public function getOpenInNewWindow(): bool
    {
        return $this->openInNewWindow;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::isEmpty()
     */
    public function isEmpty() : bool
    {
        return parent::isEmpty() && ! $this->hasUri();
    }
    
}