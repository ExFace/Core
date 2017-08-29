<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\TemplateInterface;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Exceptions\UiPageNotFoundError;

class UiPageFactory extends AbstractFactory
{

    /**
     *
     * @param TemplateInterface $template            
     * @throws UiPageNotFoundError if the page id is invalid (i.e. not a number or a string)
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public static function create(UiManagerInterface $ui, $page_id)
    {
        if (is_null($page_id)) {
            throw new UiPageNotFoundError('Cannot fetch UI page: page id not specified!');
        }
        $page = new UiPage($ui);
        $page->setId($page_id);
        return $page;
    }

    /**
     * Creates an empty page with a simple root container without any meta object
     *
     * @param UiManagerInterface $ui            
     * @param number $page_id            
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public static function createEmpty(UiManagerInterface $ui, $page_id = 0)
    {
        $page = static::create($ui, $page_id);
        $root_container = WidgetFactory::create($page, 'Container');
        $page->addWidget($root_container);
        return $page;
    }

    /**
     *
     * @param TemplateInterface $template            
     * @param string $page_id            
     * @param string $page_text            
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public static function createFromString(UiManagerInterface $ui, $page_id, $page_text)
    {
        $page = static::create($ui, $page_id);
        WidgetFactory::createFromUxon($page, UxonObject::fromAnything($page_text));
        return $page;
    }

    /**
     * TODO This method is still unfinished!
     *
     * @param TemplateInterface $template            
     * @param string $page_id            
     * @throws UiPageNotFoundError if no CMS page can be found by the given id
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public static function createFromCmsPage(UiManagerInterface $ui, $page_id)
    {
        $page_text = $ui->getWorkbench()->getCMS()->getPageContents($page_id);
        if (is_null($page_text)) {
            throw new UiPageNotFoundError('UI page with id "' . $page_id . '" not found!');
        } elseif (substr(trim($page_text), 0, 1) !== '{') {
            return static::create($ui, $page_id);
        }
        return static::createFromString($ui, $page_id, $page_text);
    }
}

?>