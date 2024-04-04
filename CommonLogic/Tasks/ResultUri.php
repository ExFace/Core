<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultUriInterface;
use Psr\Http\Message\UriInterface;
use function GuzzleHttp\Psr7\uri_for;

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
    
    private $download = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::setUri()
     */
    public function setUri($uri): ResultUriInterface
    {
        if ($uri instanceof UriInterface) {
            $this->uri = $uri;
        } else {
            $this->uri = $this->getUriFromString($uri);
        }
        return $this;
    }
    
    /**
     * 
     * @param string $url
     * @return UriInterface
     */
    protected function getUriFromString(string $url) : UriInterface
    {
        return uri_for($url);
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
        return $this->uri !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::isAutoRedirect()
     */
    public function isAutoRedirect() : bool
    {
        return $this->autoRedirect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::setAutoRedirect()
     */
    public function setAutoRedirect(bool $trueOrFalse) : ResultUriInterface
    {
        $this->autoRedirect = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::setOpenInNewWindow()
     */
    public function setOpenInNewWindow(bool $trueOrFalse): ResultUriInterface
    {
        $this->openInNewWindow = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::isOpenInNewWindow()
     */
    public function isOpenInNewWindow(): bool
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::isDownload()
     */
    public function isDownload() : bool
    {
        return $this->download;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::setDownload()
     */
    public function setDownload(bool $value) : ResultUriInterface
    {
        $this->download = $value;
        return $this;
    }
}