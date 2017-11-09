<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Exceptions\UiPageNotFoundError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;

class UiPageFactory extends AbstractFactory
{

    /**
     * Creates an empty page (even without a root container) with the passed UID and alias.
     * 
     * @param UiManagerInterface $ui
     * @param string $pageAlias
     * @param string $pageUid
     * @param string $appUidOrAlias The app the page belongs to.
     * @throws UiPageNotFoundError if the page id is invalid (i.e. not a number or a string)
     * @return UiPageInterface
     */
    public static function create(UiManagerInterface $ui, $pageAlias, $pageUid = null, $appUidOrAlias = null)
    {
        if (is_null($pageAlias)) {
            throw new UiPageNotFoundError('Cannot fetch UI page: page alias not specified!');
        }
        $page = new UiPage($ui, $pageAlias, $pageUid, $appUidOrAlias);
        return $page;
    }

    /**
     * Creates an empty page with a simple root container without any meta object.
     * 
     * @param UiManagerInterface $ui
     * @param string $page_alias
     * @return UiPageInterface
     */
    public static function createEmpty(UiManagerInterface $ui, $page_alias = '')
    {
        $page = static::create($ui, $page_alias);
        $root_container = WidgetFactory::create($page, 'Container');
        $page->addWidget($root_container);
        return $page;
    }

    /**
     * Creates a page with the passed alias and the passed content.
     * 
     * @param UiManagerInterface $ui
     * @param string $page_alias
     * @param string $page_text
     * @return UiPageInterface
     */
    public static function createFromString(UiManagerInterface $ui, $page_alias, $page_text)
    {
        $page = static::create($ui, $page_alias);
        $page->setContents($page_text);
        return $page;
    }

    /**
     * Creates a page which is obtained from the CMS by the passed alias.
     * 
     * @param UiManagerInterface $ui
     * @param string $page_alias
     * @return UiPageInterface
     */
    public static function createFromCmsPage(UiManagerInterface $ui, $page_alias)
    {
        return $ui->getWorkbench()->getCMS()->loadPage($page_alias);
    }

    /**
     * Creates a page which is obtained from the current CMS page.
     * 
     * @param UiManagerInterface $ui
     * @return UiPageInterface
     */
    public static function createFromCmsPageCurrent(UiManagerInterface $ui)
    {
        return $ui->getWorkbench()->getCMS()->loadPageCurrent();
    }

    /**
     * Creates a page from a uxon description.
     * 
     * @param UiManagerInterface $ui
     * @param UxonObject $uxon
     * @param array $skip_property_names
     * @return UiPageInterface
     */
    public static function createFromUxon(UiManagerInterface $ui, UxonObject $uxon, array $skip_property_names = array())
    {
        $page = static::create($ui, '');
        $page->importUxonObject($uxon, $skip_property_names);
        return $page;
    }
}

?>