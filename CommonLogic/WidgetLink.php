<?php
namespace exface\Core\CommonLogic;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\Exceptions\UnexpectedValueException;

class WidgetLink implements WidgetLinkInterface
{

    private $exface;

    private $page_alias;

    private $widget_id;

    private $widget_id_space = null;

    private $column_id;

    private $row_number;

    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::parseLink()
     */
    public function parseLink($string_or_object)
    {
        if ($string_or_object instanceof UxonObject) {
            return $this->parseLinkUxon($string_or_object);
        } else {
            return $this->parseLinkString($string_or_object);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::parseLinkString()
     */
    public function parseLinkString($string)
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

    public function parseLinkUxon(UxonObject $object)
    {
        $this->setPageAlias($object->getProperty('page_alias'));
        $this->setWidgetId($object->getProperty('widget_id'));
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        return $this->parseLinkUxon($uxon);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getWidgetId()
     */
    public function getWidgetId()
    {
        return ($this->getWidgetIdSpace() ? $this->getWidgetIdSpace() . $this->getPage()->getWidgetIdSpaceSeparator() : '') . $this->widget_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::setWidgetId()
     */
    public function setWidgetId($value)
    {
        $this->widget_id = $value;
    }

    /**
     * Returns the widget instance referenced by this link
     *
     * @throws uiWidgetNotFoundException if no widget with a matching id can be found in the specified resource
     * @return AbstractWidget
     */
    public function getWidget()
    {
        $widget = $this->getPage()->getWidget($this->getWidgetId());
        if (! $widget) {
            throw new WidgetNotFoundError('Cannot find widget "' . $this->getWidgetId() . '" in resource "' . $this->getPage()->getAliasWithNamespace() . '"!');
        }
        return $widget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getPageAlias()
     */
    public function getPageAlias()
    {
        return $this->page_alias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::setPageAlias()
     */
    public function setPageAlias($pageAlias)
    {
        $this->page_alias = $pageAlias;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getPage()
     */
    public function getPage()
    {
        if (is_null($this->getPageAlias())) {
            return $this->getWorkbench()->ui()->getPageCurrent();
        }
        return $this->getWorkbench()->ui()->getPage($this->page_alias);
    }

    /**
     *
     * @return UxonObject
     */
    public function getWidgetUxon()
    {
        $uxon = $this->getPage()->getContentsUxon();
        if ($this->getWidgetId() && $uxon->getProperty('widget_id') != $this->getWidgetId()) {
            $uxon = $this->findWidgetIdInUxon($uxon, $this->getWidgetId());
            if ($uxon === false) {
                $uxon = $this->exface->createUxonObject();
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
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = $this->exface->createUxonObject();
        $uxon->setProperty('widget_id', $this->widget_id);
        $uxon->setProperty('page_alias', $this->getPage()->getAliasWithNamespace());
        $uxon->setProperty('widget_id_space', $this->widget_id_space);
        $uxon->setProperty('column_id', $this->column_id);
        $uxon->setProperty('row_number', $this->row_number);
        return $uxon;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getColumnId()
     */
    public function getColumnId()
    {
        return $this->column_id;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::setColumnId()
     */
    public function setColumnId($value)
    {
        $this->column_id = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getRowNumber()
     */
    public function getRowNumber()
    {
        return $this->row_number;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::setRowNumber()
     */
    public function setRowNumber($value)
    {
        $this->row_number = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::getWidgetIdSpace()
     */
    public function getWidgetIdSpace()
    {
        return $this->widget_id_space;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\WidgetLinkInterface::setWidgetIdSpace()
     */
    public function setWidgetIdSpace($value)
    {
        $this->widget_id_space = $value;
        return $this;
    }
}
?>