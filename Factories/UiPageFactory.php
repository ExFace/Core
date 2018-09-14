<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Interfaces\CmsConnectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class UiPageFactory extends AbstractStaticFactory
{

    /**
     * Creates a page for the passed selector automatically loading it from 
     * the CMS if it exists there.
     * 
     * @param UiPageSelectorInterface $selector
     * @param CmsConnectorInterface $cms
     * 
     * @return UiPageInterface
     */
    public static function create(UiPageSelectorInterface $selector, CmsConnectorInterface $cms = null) : UiPageInterface
    {
        $cms = is_null($cms) ? $selector->getWorkbench()->getCMS() : $cms;
        $page = null;
        if (! $selector->isEmpty()) {
            try {
                $page = $cms->getPage($selector);
            } catch (UiPageNotFoundError $e) {
                // do nothing
            }
        }
        return ! is_null($page) ? $page : new UiPage($selector, $cms);
    }
    
    /**
     * Creates an empty page (even without a root container) for the passed selector.
     * 
     * @param WorkbenchInterface $workbench
     * @param UiPageSelectorInterface|string $selectorOrString
     * @param CmsConnectorInterface $cms
     * 
     * @return UiPageInterface
     */
    public static function createBlank(WorkbenchInterface $workbench, $selectorOrString, CmsConnectorInterface $cms = null) : UiPageInterface
    {
        $selector = $selectorOrString instanceof UiPageSelectorInterface ? $selectorOrString : SelectorFactory::createPageSelector($workbench, $selectorOrString);
        return new UiPage($selector, $cms);
    }

    /**
     * Creates a page with a simple root container widget without any meta object.
     * 
     * @param WorkbenchInterface $workbench
     * @param UiPageSelectorInterface|string $page_alias
     * 
     * @return UiPageInterface
     */
    public static function createEmpty(WorkbenchInterface $workbench, $selectorOrString = '') : UiPageInterface
    {
        $page = static::createBlank($workbench, $selectorOrString);
        $page->addWidget(WidgetFactory::create($page, 'Container'));
        return $page;
    }

    /**
     * Creates a page from with the specified selector and fills it with the given contents.
     * 
     * @param UiPageSelectorInterface $selector
     * @param string $contents
     * @param CmsConnectorInterface $cms
     * @return UiPageInterface
     */
    public static function createFromString(UiPageSelectorInterface $selector, string $contents) : UiPageInterface
    {
        $page = static::createBlank($selector);
        $page->setContents($contents);
        return $page;
    }

    /**
     * Creates a page which is obtained from the CMS by the passed alias.
     * 
     * @param CmsConnectorFactory $cms
     * @param UiPageSelectorInterface|string $selectorOrString
     * 
     * @throws UiPageNotFoundError
     * 
     * @return UiPageInterface
     */
    public static function createFromCmsPage(CmsConnectorInterface $cms, $selectorOrString) : UiPageInterface
    {
        if ($selectorOrString instanceof UiPageSelectorInterface) {
            $selector = $selectorOrString;
        } else {
            $selector = SelectorFactory::createPageSelector($cms->getWorkbench(), $selectorOrString);
        }
        
        return $cms->getPage($selector);
    }

    /**
     * Creates a page which is obtained from the current CMS page.
     * 
     * @param CmsConnectorInterface $cms
     * @return UiPageInterface
     */
    public static function createFromCmsPageCurrent(CmsConnectorInterface $cms) : UiPageInterface
    {
        return $cms->getPageCurrent();
    }

    /**
     * Creates a page from a uxon description.
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     * @param CmsConnectorInterface $cms
     * @param array $skip_property_names
     * 
     * @return UiPageInterface
     */
    public static function createFromUxon(WorkbenchInterface $workbench, UxonObject $uxon, CmsConnectorInterface $cms = null, array $skip_property_names = array())
    {
        $page = static::createBlank($workbench, '', $cms);
        $page->importUxonObject($uxon, $skip_property_names);
        return $page;
    }
}

?>