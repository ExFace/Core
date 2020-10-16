<?php
namespace exface\Core\CommonLogic\Tasks;

use Psr\Http\Message\UriInterface;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\UrlDataType;

/**
 * Task result redirecting to a UI page or URI.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultRedirect extends ResultUri
{  
    private $pageSelector = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultUriInterface::hasUri()
     */
    public function hasUri(): bool
    {
        return parent::hasUri() || $this->hasTargetPage();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultUri::getUri()
     */
    public function getUri(FacadeInterface $facade = null) : UriInterface
    {
        $facade = $facade ?? $this->getTask()->getFacade();
        if ($facade instanceof HtmlPageFacadeInterface) {
            if ($targetSelector = $this->getTargetPageSelector()) {
                $url = $facade->buildUrlToPage($targetSelector);
            } else {
                $url = parent::getUri();
            }
        } else {
            throw new LogicException('Cannot transform a page selector into a URL for task result: need to pass a facade instance explicitly for results of tasks not issued by HTML page templates!');
        }
        return $this->getUriFromString($url);
    }
    
    /**
     * 
     * @return bool
     */
    public function hasTargetPage() : bool
    {
        return $this->pageSelector !== null;
    }
    
    /**
     *
     * @return UiPageSelectorInterface|null
     */
    public function getTargetPageSelector() : ?UiPageSelectorInterface
    {
        return $this->pageSelector;
    }
    
    /**
     * 
     * @param UiPageSelectorInterface|string $selectorOrString
     * @return ResultRedirect
     */
    public function setTargetPageSelector($selectorOrString) : ResultRedirect
    {
        if ($selectorOrString instanceof UiPageSelectorInterface) {
            $this->pageSelector = $selectorOrString;
        } else {
            $this->pageSelector = new UiPageSelector($this->getWorkbench(), $selectorOrString);
        }
        return $this;
    }
    
    /**
     * 
     * @param UiPageInterface $page
     * @return ResultRedirect
     */
    public function setTargetPage(UiPageInterface $page) : ResultRedirect
    {
        return $this->setTargetPageSelector($page->getSelector());
    }
}