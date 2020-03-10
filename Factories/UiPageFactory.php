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
     * 
     * @return UiPageInterface
     */
    public static function create(UiPageSelectorInterface $selector) : UiPageInterface
    {
        $page = null;
        if (! $selector->isEmpty()) {
            try {
                $page = self::createFromModel($selector->getWorkbench(), $selector);
            } catch (UiPageNotFoundError $e) {
                // do nothing
            }
        }
        return ! is_null($page) ? $page : new UiPage($selector);
    }
    
    /**
     * Creates an empty page (even without a root container) for the passed selector.
     * 
     * @param WorkbenchInterface $workbench
     * @param UiPageSelectorInterface|string $selectorOrString
     * 
     * @return UiPageInterface
     */
    public static function createBlank(WorkbenchInterface $workbench, $selectorOrString) : UiPageInterface
    {
        $selector = $selectorOrString instanceof UiPageSelectorInterface ? $selectorOrString : SelectorFactory::createPageSelector($workbench, $selectorOrString);
        return new UiPage($selector);
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
     * @return UiPageInterface
     */
    public static function createFromString(UiPageSelectorInterface $selector, string $contents) : UiPageInterface
    {
        $page = static::createBlank($selector->getWorkbench(), $selector);
        $page->setContents($contents);
        return $page;
    }
    
    public static function createFromModel(WorkbenchInterface $workbench, $selectorOrString, bool $ignoreReplacement = false) : UiPageInterface
    {
        if ($selectorOrString instanceof UiPageSelectorInterface) {
            $selector = $selectorOrString;
        } else {
            $selector = SelectorFactory::createPageSelector($workbench, $selectorOrString);
        }
        
        return $workbench->model()->getModelLoader()->loadPage($selector, $ignoreReplacement);
    }

    /**
     * Creates a page from a uxon description.
     * 
     * @param WorkbenchInterface $workbench
     * @param UxonObject $uxon
     * @param array $skip_property_names
     * 
     * @return UiPageInterface
     */
    public static function createFromUxon(WorkbenchInterface $workbench, UxonObject $uxon, array $skip_property_names = array())
    {
        $page = static::createBlank($workbench, '');
        $page->importUxonObject($uxon, $skip_property_names);
        return $page;
    }
}

?>