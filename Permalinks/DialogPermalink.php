<?php

namespace exface\Core\Permalinks;

use exface\Core\CommonLogic\Permalink\AbstractPermalink;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\FacadeFactory;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\WidgetInterface;

class DialogPermalink extends AbstractPermalink
{
    private $pageAlias = null;
    private $facadeAlias = null;
    private $widgetId = null;
    private $uid = null;

    protected function parse(string $urlPath) : PermalinkInterface
    {
        $this->uid = $urlPath;
        return $this;
    }

    public function getRedirect(): string
    {
        $facade = $this->getFacade();
        $prefillSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getWidget()->getMetaObject());
        if ($prefillSheet->getMetaObject()->hasUidAttribute()) {
            $uidCol = $prefillSheet->getColumns()->addFromUidAttribute();
            $uidCol->setValue(0, $this->uid);
        }
        return $facade->buildUrlToWidget($this->getPage(), $prefillSheet);
    }

    public function getLink(): string
    {
        return $this->getAliasWithNamespace() . '/' . $this->uid;
    }

    public function getPage() : UiPageInterface
    {
        return UiPageFactory::createFromModel($this->getWorkbench(), $this->pageAlias);
    }

    /**
     * Page to be openeded
     *
     * @uxon-proeprty page_alias
     * @uxon-type metamodel:page
     * @uxon-required true
     *
     * @param string $selector
     * @return $this
     */
    protected function setPageAlias(string $selector) : DialogPermalink
    {
        $this->pageAlias = $selector;
        return $this;
    }

    public function getFacade() : HtmlPageFacadeInterface
    {
        $facade = FacadeFactory::createFromString($this->facadeAlias, $this->getWorkbench());
        if (! $facade instanceof HtmlPageFacadeInterface) {
            // TODO
        }
        return $facade;
    }

    /**
     * Facade to be openeded
     *
     * @uxon-proeprty facade_alias
     * @uxon-type metamodel:facade
     * @uxon-required true
     *
     * @param string $selector
     * @return $this
     */
    protected function setFacadeAlias(string $selector) : DialogPermalink
    {
        $this->facadeAlias = $selector;
        return $this;
    }

    protected function getWidget() : WidgetInterface
    {
        return $this->getPage()->getWidget($this->widgetId);
    }

    /**
     * @uxon-property widget_id
     * @uxon-type string
     * @uxon-required true
     *
     * @param string $id
     * @return $this
     */
    protected function setWidgetId(string $id) : DialogPermalink
    {
        $this->widgetId = $id;
        return $this;
    }
}