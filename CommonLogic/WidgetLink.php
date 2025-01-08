<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\SelectorFactory;
use exface\Core\Events\Widget\OnWidgetLinkedEvent;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\DataTypes\StringDataType;

/**
 * A reference to another widget or its data.
 * 
 * Widget links are similar to Excel references. Think of a UI page as an Excel workbook, a widget as
 * a worksheet and the widgets data as that worksheets contents.
 * 
 * You can address
 * 
 * - UI pages using their namespaced alias in square braces
 * - Widgets using their ids. **NOTE:** This means, to explicitly linke a widget, you MUST give it an `id`.
 * - Data columns using their `data_column_name` following the widgets id separated by an `!` 
 * 
 * There are also a couple of "shortcut" references available instead of explicit page/widget ids:
 * 
 * - `~self` - references the widget the link is defined in
 * - `~parent` - references the immediate parent of `~self`
 * - `~input` - references the `input_widget` of a `Button` or anything else that supports input widgets. 
 * 
 * You can make a widget link "optional" by adding a trailing `?`. This will make sure, the linked value
 * us only used if it is not empty. Thus, the widget, that contains the link will not be emptied if the
 * linked widget has no value.
 * 
 * A few examples:
 * 
 * - `some_widget` - references the entire widget with id `some_widget`
 * - `some_widget!mycol` - references the column `mycol` in the data of the widget with id `some_widget`
 * - `some_widget!mycol?` - references the column `mycol` only if it has a non-empty value. Otherwise the reference is
 * not applied!
 * - `[my.App.page1]` - references the root widget of the page with alias `my.App.page1`
 * - `[my.App.page1]some_widget` - references the widget with id `some_widget` on page `my.App.page1`
 * - `~self!mycol` - references the column `mycol` in the data of the current widget
 * - `~parent!mycol` - references the column `mycol` of the current widgets parent
 * - `~input!mycol` - references the column `mycol` of the input widget (if the current widget is a `Button`)
 * 
 * @author andrej.kabachnik
 *
 */
class WidgetLink implements WidgetLinkInterface
{
    
    private $sourcePage = null;
    
    private $sourceWidget = null;

    private $targetPageAlias = null;
    
    private $targetPage = null;

    private $targetWidgetId = null;

    private $widget_id_space = null;

    private $targetColumnId = null;

    private $targetRowNumber = null;
    
    private $ifNotEmpty = false;

    /**
     * Serves as a global storage for all established widget links, across all pages.
     * 
     * The idea is that it should always be possible to find out what links exist or point to a particular
     * widget, regardless of event timings or local context.
     * 
     * @see self::getLinksAll(), self::getLinksOnPage(), self::getLinksToWidget()
     * @var array 
     */
    private static array $linkCache = [];

    /**
     * 
     * @param UiPageInterface $sourcePage
     * @param WidgetInterface $sourceWidget
     * @param string|UxonObject $stringOrUxon
     * 
     * @triggers \exface\Core\Events\Widget\OnWidgetLinkedEvent
     */
    public function __construct(UiPageInterface $sourcePage, WidgetInterface $sourceWidget = null, $stringOrUxon)
    {
        $this->sourcePage = $sourcePage;
        $this->sourceWidget = $sourceWidget;
        $this->parseLink($stringOrUxon);
        
        self::addLinkToCache($this);
        $this->getWorkbench()->eventManager()->dispatch(new OnWidgetLinkedEvent($this));
    }

    public function __destruct()
    {
        self::removeLinkFromCache($this);
    }

    /**
     * Remove a WidgetLinkInterface instance from the link cache.
     * 
     * Returns TRUE only if the specified instance was actually found and removed from the cache.
     * 
     * @param WidgetLinkInterface $link
     * @return bool
     */
    private static function removeLinkFromCache(WidgetLinkInterface $link) : bool
    {
        $targetPageAlias = $link->getOrLoadTargetPageAlias();
        if(!key_exists($targetPageAlias, self::$linkCache)) {
            return false;
        }
        
        $targetWidgetId = $link->getTargetWidgetId() ?? '';
        if(!key_exists($targetWidgetId, self::$linkCache[$targetPageAlias])) {
            return  false;
        }
        
        foreach (self::$linkCache[$targetPageAlias][$targetWidgetId] as $index => $cachedLink) {
            if($cachedLink === $link) {
                unset(self::$linkCache[$targetPageAlias][$targetWidgetId][$index]);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add a WidgetLinkInterface instance to the cache.
     * 
     * Overwrites any instance with the same target page alias and target widget ID already
     * stored in the cache. 
     * 
     * @param WidgetLinkInterface $link
     * @return void
     */
    private static function addLinkToCache(WidgetLinkInterface $link) : void
    {
        $targetPageAlias = $link->getOrLoadTargetPageAlias();
        $targetWidgetId = $link->getTargetWidgetId() ?? '';
        
        self::$linkCache[$targetPageAlias][$targetWidgetId][] = $link;
    }

    /**
     * Returns ALL WidgetLinkInterface instances for all pages and widgets as a flat
     * array with numeric indices.
     * 
     * @see self::$linkCache
     * @return WidgetLinkInterface[]
     */
    public static function getLinksAll() : array
    {
        $result = [];
        
        foreach (self::$linkCache as $widgetsPerPage) {
            foreach ($widgetsPerPage as $linksPerWidget) {
                $result = array_merge($result, $linksPerWidget);
            }
        }
        
        return $result;
    }

    /**
     * Returns any WidgetLinkInterface instances on a given page as a flat array with
     * numeric indices.
     * 
     * @see self::$linkCache
     * @param UiPageInterface $page
     * @return WidgetLinkInterface[]
     */
    public static function getLinksOnPage(UiPageInterface $page) : array
    {
        $result = [];
        $pageAlias = $page->getAlias() ?? '';
        
        foreach (self::$linkCache[$pageAlias] as $linksPerWidget) {
            $result = array_merge($result, $linksPerWidget);
        }
        
        return $result;
    }

    /**
     * Returns any WidgetLinkInterface instances pointing to the specified widget.
     * 
     * @see self::$linkCache
     * @param WidgetInterface $widget
     * @return WidgetLinkInterface[]
     */
    public static function getLinksToWidget(WidgetInterface $widget) : array
    {
        $page = $widget->getPage();
        $pageAlias = $page !== null ? ($page->getAlias() ?? '') : '';
        $widgetId = $widget->getId() ?? '';
        
        if( key_exists($pageAlias, self::$linkCache) && 
            key_exists($widgetId, self::$linkCache[$pageAlias])) {
            return self::$linkCache[$pageAlias][$widgetId];
        }
        
        return [];
    }

    /**
     * 
     * @param string|UxonObject $string_or_object
     * @return WidgetLinkInterface
     */
    protected function parseLink($string_or_object) : WidgetLinkInterface
    {
        if ($string_or_object instanceof UxonObject) {
            $this->parseLinkUxon($string_or_object);
        } else {
            $this->parseLinkString($string_or_object);
        }
        
        return $this;
    }

    /**
     * 
     * @param string $string
     * @throws UnexpectedValueException
     * @return WidgetLinkInterface
     */
    protected function parseLinkString(string $string) : WidgetLinkInterface
    {
        $string = trim($string);
        
        if (StringDataType::endsWith($string, '?')) {
            $this->setOnlyIfNotEmpty(true);
            $string = substr($string, 0, -1);
        } else {
            $this->setOnlyIfNotEmpty(false);
        }
        
        // Check for reference to specific page_alias
        if (strpos($string, '[') === 0) {
            $page_alias = substr($string, 1, strpos($string, ']') - 1);
            if ($page_alias) {
                $this->setPageAlias($page_alias);
                $string = substr($string, strpos($string, ']') + 1);
            } else {
                throw new UnexpectedValueException('Cannot parse widget reference "' . $string . '"! Expected format: "[page_alias]widget_id".', '6T91IGZ');
            }
        }
        
        // Determine the widget id
        // Now the string definitely does not contain a resource id any more
        if ($pos = strpos($string, '!')) {
            // If there is a "!", there is at least a column id following it
            $widget_id = substr($string, 0, $pos);
            $string = substr($string, ($pos + 1));
            
            // Determine the column id
            if ($pos = strpos($string, '$')) {
                // If there is a "$", there is a row number following it
                $column_id = substr($string, 0, $pos);
                $string = substr($string, ($pos + 1));
                $this->setRowNumber($string);
            } else {
                // Otherwise, everything that is left, is the column id
                $column_id = $string;
            }
            $this->setColumnId($column_id);
        } else {
            // Otherwise, everything that is left, is the widget id
            $widget_id = $string;
        }
        
        $this->setWidgetId($widget_id);
        
        return $this;
    }

    /**
     * 
     * @param UxonObject $object
     * @return WidgetLinkInterface
     */
    protected function parseLinkUxon(UxonObject $object) : WidgetLinkInterface
    {
        $this->setPageAlias($object->getProperty('page_alias'));
        $this->setWidgetId($object->getProperty('widget_id'));
        if ($object->hasProperty('only_if_not_empty')) {
            $this->setOnlyIfNotEmpty($object->getProperty('only_if_not_empty'));
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        return $this->parseLinkUxon($uxon);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetWidgetId()
     */
    public function getTargetWidgetId() : string
    {
        return ($this->getTargetWidgetIdSpace() ? $this->getTargetWidgetIdSpace() . $this->getTargetPage()->getWidgetIdSpaceSeparator() : '') . $this->targetWidgetId;
    }

    /**
     * The id of the target widget.
     * 
     * @uxon-property widget_id
     * @uxon-type uxon:..id
     * 
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::setWidgetId()
     */
    protected function setWidgetId($value)
    {
        // Handle magir refs
        switch ($value) {
            case WidgetLinkInterface::REF_SELF:
                if ($this->hasSourceWidget()) {
                    $value = $this->getSourceWidget()->getId();
                    break;
                }
                throw new RuntimeException('Cannot parse widget link: reference "' . WidgetLinkInterface::REF_SELF . '" only available if the links source widget is known!');
            case WidgetLinkInterface::REF_PARENT:
                if ($this->hasSourceWidget() && $this->getSourceWidget()->hasParent()) {
                    $value = $this->getSourceWidget()->getParent()->getId();
                    break;
                }
                throw new RuntimeException('Cannot parse widget link: reference "' . WidgetLinkInterface::REF_INPUT . '" only available if the links source widget is known and it has a parent!');
            case WidgetLinkInterface::REF_INPUT:
                if ($this->hasSourceWidget()) {
                    $src = $this->getSourceWidget();
                    if ($src instanceof iUseInputWidget && $input = $src->getInputWidget()) {
                        $value = $input->getId();
                        break;
                    }
                }
                throw new RuntimeException('Cannot parse widget link: reference "' . WidgetLinkInterface::REF_INPUT . '" only available if the links source widget is a button (or any other widget with an input widget) and the input widget is known!');
        }
        
        $this->targetWidgetId = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetWidget()
     */
    public function getTargetWidget() : WidgetInterface
    {
        $widget = $this->getTargetPage()->getWidget($this->getTargetWidgetId());
        if (! $widget) {
            throw new WidgetNotFoundError('Cannot find widget "' . $this->getTargetWidgetId() . '" in resource "' . $this->getTargetPage()->getAliasWithNamespace() . '"!');
        }
        return $widget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetPageAlias()
     */
    public function getTargetPageAlias() : ?string
    {
        return $this->targetPageAlias;
    }

    /**
     * Shortcut to get the alias of the target page. If the alias is `null`, the return
     * value will be loaded from the target page. 
     * 
     * @return string
     */
    // TODO 2024-12-09 geb: This could be merged with getTargetPageAlias(), but was kept as a separate method to avoid refactoring. 
    public function getOrLoadTargetPageAlias() : string
    {
        if($this->targetPageAlias === null) {
            return $this->getTargetPage()->getAlias() ?? '';
        }
        
        return $this->targetPageAlias;
    }

    /**
     * Selector of the page of the target widget
     * 
     * @uxon-property page_alias
     * @uxon-type metamodel:page
     * 
     * @param string $pageAlias
     * @return \exface\Core\CommonLogic\WidgetLink
     */
    protected function setPageAlias($pageAlias)
    {
        $this->targetPageAlias = $pageAlias;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetPage()
     */
    public function getTargetPage() : UiPageInterface
    {
        if ($this->targetPage === null) {
            if ($this->targetPageAlias === null) {
                $this->targetPage = $this->sourcePage;
            } else {
                $this->targetPage = UiPageFactory::create(SelectorFactory::createPageSelector($this->getWorkbench(), $this->targetPageAlias));
            }
        }
        return $this->targetPage;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetWidgetUxon()
     */
    public function getTargetWidgetUxon() : UxonObject
    {
        $uxon = $this->getTargetPage()->getContentsUxon();
        if ($this->getTargetWidgetId() && $uxon->getProperty('widget_id') != $this->getTargetWidgetId()) {
            $uxon = $this->findWidgetIdInUxon($uxon, $this->getTargetWidgetId());
            if ($uxon === false) {
                $uxon = new UxonObject();
            }
        }
        return $uxon;
    }

    /**
     * 
     * @param UxonObject $uxon
     * @param string $widget_id            
     * @return UxonObject|boolean
     */
    private function findWidgetIdInUxon(UxonObject $uxon, $widget_id)
    {
        $result = false;
        
        if ($uxon->hasProperty('id')){
            if ($uxon->getProperty('id') == $widget_id) {
                return $uxon;
            }
        }
        
        foreach ($uxon as $prop) {
            if (! ($prop instanceof UxonObject)){
                continue;
            }
            if ($result = $this->findWidgetIdInUxon($prop, $widget_id)) {
                return $result;
            }
        }
        
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('widget_id', $this->targetWidgetId);
        $uxon->setProperty('page_alias', $this->getTargetPage()->getAliasWithNamespace());
        $uxon->setProperty('widget_id_space', $this->widget_id_space);
        if ($this->targetColumnId !== null) {
        $uxon->setProperty('column_id', $this->targetColumnId);
        }
        if ($this->targetRowNumber !== null) {
            $uxon->setProperty('row_number', $this->targetRowNumber);
        }
        return $uxon;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetColumnId()
     */
    public function getTargetColumnId() : ?string
    {
        return $this->targetColumnId;
    }

    /**
     * 
     * @param string $value
     * @return WidgetLinkInterface
     */
    protected function setColumnId(string $value) : WidgetLinkInterface
    {
        $this->targetColumnId = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetRowNumber()
     */
    public function getTargetRowNumber() : ?int
    {
        if ($this->targetRowNumber !== null && $this->targetColumnId === null) {
            throw new UxonParserError($this->exportUxonObject(), 'Cannot user row numbers in widget links without a column reference!');
        }
        return $this->targetRowNumber;
    }

    /**
     * 
     * @param int $value
     * @return WidgetLinkInterface
     */
    protected function setRowNumber(int $value) : WidgetLinkInterface
    {
        $this->targetRowNumber = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->sourcePage->getWorkbench();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetWidgetIdSpace()
     */
    protected function getTargetWidgetIdSpace()
    {
        return $this->hasSourceWidget() ? $this->getSourceWidget()->getIdSpace() : '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getSourceWidget()
     */
    public function getSourceWidget(): WidgetInterface
    {
        return $this->sourceWidget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getSourcePage()
     */
    public function getSourcePage(): UiPageInterface
    {
        return $this->sourcePage;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::hasSourceWidget()
     */
    public function hasSourceWidget() : bool
    {
        return $this->sourceWidget !== null;
    }
    
    /**
     * 
     * @return bool
     */
    public function isOnlyIfNotEmpty() : bool
    {
        return $this->ifNotEmpty;
    }
    
    /**
     * Set to TRUE to ignore the link completely if the linked value is empty
     * 
     * @uxon-property only_if_not_empty
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $value
     * @return WidgetLink
     */
    public function setOnlyIfNotEmpty(bool $value) : WidgetLink
    {
        $this->ifNotEmpty = $value;
        return $this;
    }
}
?>