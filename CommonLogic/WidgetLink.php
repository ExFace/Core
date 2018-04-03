<?php
namespace exface\Core\CommonLogic;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\SelectorFactory;

class WidgetLink implements WidgetLinkInterface
{

    private $sourcePage = null;
    
    private $sourceWidget = null;

    private $targetPageAlias = null;
    
    private $targetPage = null;

    private $targetWidgetId = null;

    private $widget_id_space = null;

    private $targetColumnId;

    private $targetRowNumber;

    public function __construct(UiPageInterface $sourcePage, WidgetInterface $sourceWidget = null, $stringOrUxon)
    {
        $this->sourcePage = $sourcePage;
        $this->sourceWidget = $sourceWidget;
        $this->parseLink($stringOrUxon);
    }

    /**
     * 
     * @param string|UxonObject $string_or_object
     * @return WidgetLinkInterface
     */
    protected function parseLink($string_or_object) : WidgetLinkInterface
    {
        if ($string_or_object instanceof UxonObject) {
            return $this->parseLinkUxon($string_or_object);
        } else {
            return $this->parseLinkString($string_or_object);
        }
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
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::setWidgetId()
     */
    protected function setWidgetId($value)
    {
        $this->targetWidgetId = $value;
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
    public function getTargetPageAlias() : string
    {
        return $this->targetPageAlias;
    }

    /**
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
        $uxon->setProperty('column_id', $this->targetColumnId);
        $uxon->setProperty('row_number', $this->targetRowNumber);
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getTargetColumnId()
     */
    public function getTargetColumnId()
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
    public function getTargetRowNumber()
    {
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
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
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

}
?>