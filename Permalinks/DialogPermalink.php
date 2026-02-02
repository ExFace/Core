<?php

namespace exface\Core\Permalinks;

use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\Permalink\AbstractPermalink;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\WidgetInterface;
use http\Exception\BadUrlException;

/**
 * Use this prototype to configure a persistent link to any dialog in the application. If a valid UID is passed
 * as an argument, the dialog will be prefilled with data from that entry.
 * 
 * **Link Syntax:** 
 * 
 * - `api/permalink/<config_alias>/[target_uid]`
 */
class DialogPermalink extends AbstractPermalink
{
    private ?string $pageAlias = null;
    private ?string $widgetId = null;
    private ?string $uid = null;
    private ?UiPageInterface $page = null;
    private ?WidgetInterface $widget = null;

    /**
     * @inheritdoc 
     * @see AbstractPermalink::parse()
     */
    protected function parse(string $innerUrl) : PermalinkInterface
    {
        $this->uid = $innerUrl;
        return $this;
    }

    /**
     * @inheritdoc 
     * @see PermalinkInterface::buildRelativeRedirectUrl()
     */
    public function buildRelativeRedirectUrl() : string
    {
        $widget = $this->getWidget();
        if(!$this->destinationMatchesProfile($widget->exportUxonObjectOriginal())) {
            throw new BadUrlException('Cannot resolve permalink (' . $this->getAliasWithNamespace() . '): Destination does not match profile!');
        }
        
        $facade = $this->getFacade();
        $object = $widget->getMetaObject();

        $prefillSheet = null;
        if(!empty($this->uid) && $object->hasUidAttribute()) {
            $prefillSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $object);
            $uidCol = $prefillSheet->getColumns()->addFromUidAttribute();
            $uidCol->setValue(0, $this->uid);
        }
        
        return $facade->buildUrlToWidget($this->getWidget(), $prefillSheet);
    }

    /**
     * Returns original pathURL (`config_alias/target_uid`) without the facade routing, 
     * for example `exface.Core.show_object/1260-TB`
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->getAliasWithNamespace() . '/' . $this->uid;
    }

    /**
     * @return UiPageInterface
     */
    public function getPage() : UiPageInterface
    {
        if($this->page === null) {
            $this->page = UiPageFactory::createFromModel($this->getWorkbench(), $this->pageAlias);
        }
        
        return $this->page;
    }

    /**
     * The page where the dialogue is located.
     * 
     * @uxon-property page_alias
     * @uxon-type metamodel:page
     * @uxon-required true
     * 
     * @param string $selector
     * @return $this
     */
    protected function setPageAlias(string $selector) : DialogPermalink
    {
        $this->pageAlias = $selector;
        $this->page = null;
        return $this;
    }

    /**
     * @return HtmlPageFacadeInterface
     */
    public function getFacade() : HtmlPageFacadeInterface
    {
        $facade = $this->getPage()->getFacade();
        if (! $facade instanceof HtmlPageFacadeInterface) {
            throw new InvalidArgumentException('Invalid facade "' . $facade->getAlias() . '": Facade must be of type "' . HtmlPageFacadeInterface::class . '"!');
        }
        
        return $facade;
    }

    /**
     * @return WidgetInterface
     */
    protected function getWidget() : WidgetInterface
    {
        if($this->widget === null) {
            $this->widget = $this->getPage()->getWidget($this->widgetId);
        }
        
        return $this->widget; 
    }

    /**
     * The ID of the widget targeted by this link.
     * 
     * The id MUST belong to a widget in the root id space of the page or have an explicit id space. You cannot address 
     * ids inside lazy dialogs without specifying the correct id space. Any widget id, that does not have an id
     * space will be resolved in the root id space of the page.
     * 
     * Examples:
     * 
     * - `DataTable_DataToolbar_ButtongGroup_Button03` will be resolved in the root id space
     * - `.DataTable_DataToolbar_ButtongGroup_Button03` is the same as above, but with the id space explicitly specified
     * 
     * @uxon-property widget_id
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $id
     * @return $this
     */
    protected function setWidgetId(string $id) : DialogPermalink
    {
        // Make sure the id has an id space
        if (mb_strpos($id, UiPage::WIDGET_ID_SPACE_SEPARATOR) === false) {
            $id = UiPage::WIDGET_ID_SPACE_SEPARATOR . $id;
        }
        $this->widgetId = $id;
        $this->widget = null;
        return $this;
    }
}