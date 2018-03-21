<?php
namespace exface\Core\CommonLogic;

use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;

class UiManager implements UiManagerInterface
{
    private $exface = null;

    private $page_current = null;

    public function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns the UI page with the given $page_alias.
     * If the $page_alias is ommitted or ='', the default (initially empty) page is returned.
     * 
     * @param UiPageSelector|string $selectorOrString
     * @return UiPageInterface
     */
    public function getPage($selectorOrString = null)
    {
        // FIXME use UiPageSelector in the factory and in the CMS interfaces
        $string = $selectorOrString instanceof UiPageSelector ? $selectorOrString->toString() : $selectorOrString;
        return UiPageFactory::createFromCmsPage($this, $string);
    }

    /**
     * 
     * @return UiPageInterface
     */
    public function getPageCurrent()
    {
        if (is_null($this->page_current)) {
            $this->page_current = UiPageFactory::createFromCmsPageCurrent($this);
        }
        return $this->page_current;
    }

    /**
     * 
     * @param UiPageInterface $pageCurrent
     * @return UiManager
     */
    public function setPageCurrent(UiPageInterface $pageCurrent)
    {
        $this->page_current = $pageCurrent;
        return $this;
    }
}

?>