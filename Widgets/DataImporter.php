<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\Widgets\Traits\iHaveColumnsAndColumnGroupsTrait;
use exface\Core\Interfaces\Widgets\iHaveColumnGroups;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * The DataImporter allows users to quickly create data by copy-pasting tabels from Excel-compatible editors.
 * 
 * This is particularly usefull to import data from Excel or Excel-compatible formats. Instead
 * of uploading a file with a predefined format and praying for a successfull import, the user 
 * can see his data and actively work with it _before_ attempting to actually save it.
 * 
 * It is recommended to render the `DataImporter` as a spreadsheet, where the user can past the
 * the data. Each editable column of the spreadsheet should represent an writable attribute.
 * 
 * The `DataImporter` has columns similarly to a `DataTable` or `DataSpreadSheet`, but it does
 * not have filters, sorters, etc. as it is a write-only widget. You can specify the available 
 * columns directly by adding them to `columns` or by creating non-empty `column_groups`. 
 * The `DataImporter` will initially show columns with `visibility` set to `promoted` or `normal`. 
 * Adding `optional` columns will allow the user to include these if needed (e.g. by selecting
 * form an "Add columns" menu or similar).
 * 
 * If no `columns` specified, the widget will automatically produce a column for every required editable attribute 
 * of the object of the widget with it's default editor as `cell_widget`.
 * 
 * ## Examples
 * 
 * ### A quick-create dialog for attributes
 * 
 * ```
 * {
 *  "action": {
 *      "alias": "exface.Core.ShowDialog",
 *      "object_alias": "exface.Core.ATTRIBUTE",
 *        "widget": {
 *        "widget_type": "Dialog",
 *        "widgets": [
 *          {
 *            "widget_type": "DataImporter",
 *            "columns": [
 *               {
 *                "attribute_alias": "OBJECT"
 *              },
 *              {
 *                "attribute_alias": "NAME"
 *              },
 *              {
 *                "attribute_alias": "ALIAS"
 *              },
 *              {
 *                "attribute_alias": "DATA_ADDRESS"
 *              },
 *              {
 *                "attribute_alias": "DATATYPE",
 *                "cell_widget": {
 *                  "widget_type": "InputCombo",
 *                  "lazy_loading": false
 *                }
 *              }
 *            ]
 *          }
 *        ],
 *        "buttons": [
 *          {
 *            "action_alias": "exface.Core.CreateData"
 *          }
 *        ]
 *      }
 *    }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataImporter extends AbstractWidget implements iHaveColumns, iHaveColumnGroups, iFillEntireContainer, iTakeInput
{
    use iHaveColumnsAndColumnGroupsTrait;
    
    private $empty_text = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueDataType()
     */
    public function getValueDataType()
    {
        return $this->getUidColumn()->getDataType();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::hasValue()
     */
    public function hasValue()
    {
        return is_null($this->getValue()) ? false : true;
    }
    
    /**
     * Set to TRUE to force the user to fill all required fields of at least one row.
     *
     * @uxon-property required
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iCanBeRequired::setRequired()
     */
    public function setRequired($value)
    {
        $this->required = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iCanBeRequired::isRequired()
     */
    public function isRequired()
    {
        return $this->required;
    }
    
    /**
     * If set to TRUE, the table remains fully interactive, but it's data will be ignored by actions.
     *
     * @uxon-property display_only
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setDisplayOnly()
     */
    public function setDisplayOnly($true_or_false) : iTakeInput
    {
        $this->displayOnly = BooleanDataType::cast($true_or_false);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isDisplayOnly()
     */
    public function isDisplayOnly() : bool
    {
        if ($this->isReadonly() === true) {
            return true;
        }
        return $this->displayOnly;
    }
    
    /**
     * In a DataTable readonly is the opposite of editable, so there is no point in an
     * extra uxon-property here.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::setReadonly()
     */
    public function setReadonly($true_or_false) : WidgetInterface
    {
        $this->setEditable(! BooleanDataType::cast($true_or_false));
        return $this;
    }
    
    /**
     * A DataTable is readonly as long as it is not editable.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iTakeInput::isReadonly()
     */
    public function isReadonly() : bool
    {
        return $this->isEditable() === false;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveValue::getValueWithDefaults()
     */
    public function getValueWithDefaults()
    {
        // TODO return the UID of programmatically selected row
        return null;
    }
    
    public function getEmptyText()
    {
        if (! $this->empty_text) {
            $this->empty_text = $this->translate('WIDGET.DATA.NO_DATA_FOUND');
        }
        return $this->empty_text;
    }
    
    /**
     * Sets a custom text to be displayed in the Data widget, if not data is found.
     *
     * The text may contain any facade-specific formatting: e.g. HTML for HTML-facades.
     *
     * @uxon-property empty_text
     * @uxon-type string|metamodel:formula
     *
     * @param string $value
     * @return Data
     */
    public function setEmptyText($value)
    {
        $this->empty_text = $this->evaluatePropertyExpression($value);
        return $this;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see iHaveColumnsAndColumnGroupsTrait::isEditable()
     */
    public function isEditable() : bool
    {
        return true;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iHaveColumnsAndColumnGroupsTrait::createDefaultColumns()
     */
    public function createDefaultColumns() : array
    {
        $attrs = $this->getMetaObject()->getAttributes()->filter(function(MetaAttributeInterface $attr){
            return $attr->isWritable() === true && $attr->isRequired() === true && $attr->isHidden() === false;
        });
        $cols = [];
        foreach ($attrs as $attr) {
            $cols[] = $this->createColumnFromAttribute($attr);
        }
        return $cols;
    }
}