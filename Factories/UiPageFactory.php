<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Exceptions\UiPageNotFoundError;

class UiPageFactory extends AbstractFactory
{

    /**
     * 
     * @param UiManagerInterface $ui
     * @param string $page_alias
     * @throws UiPageNotFoundError if the page id is invalid (i.e. not a number or a string)
     * @return UiPage
     */
    public static function create(UiManagerInterface $ui, $page_alias)
    {
        if (is_null($page_alias)) {
            throw new UiPageNotFoundError('Cannot fetch UI page: page alias not specified!');
        }
        $page = new UiPage($ui);
        $page->setAliasWithNamespace($page_alias);
        return $page;
    }

    /**
     * Creates an empty page with a simple root container without any meta object
     * 
     * @param UiManagerInterface $ui
     * @param string $page_alias
     * @return UiPage
     */
    public static function createEmpty(UiManagerInterface $ui, $page_alias = '')
    {
        $page = static::create($ui, $page_alias);
        $root_container = WidgetFactory::create($page, 'Container');
        $page->addWidget($root_container);
        return $page;
    }

    /**
     * 
     * @param UiManagerInterface $ui
     * @param string $page_alias
     * @param string $page_text
     * @return UiPage
     */
    public static function createFromString(UiManagerInterface $ui, $page_alias, $page_text)
    {
        $page = static::create($ui, $page_alias);
        $page->setContents($page_text);
        return $page;
    }

    /**
     * 
     * @param UiManagerInterface $ui
     * @param string $page_id_or_alias
     * @return UiPage
     */
    public static function createFromCmsPage(UiManagerInterface $ui, $page_id_or_alias)
    {
        return $ui->getWorkbench()->getCMS()->loadPage($page_id_or_alias);
    }
}

?>