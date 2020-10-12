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
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Widgets\Traits\DataTableTrait;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Widgets\Traits\iHaveConfiguratorTrait;

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
 * If no `columns` specified, the widget will automatically produce a column for every required 
 * editable attribute of the object of the widget with it's default editor as `cell_widget`.
 * 
 * The `DataImporter` also can have buttons - just like a `DataTable`. However, instead of a
 * search-button, that is added automatically to other `Data` widgets, the `DataImporter`
 * has a specie button for the `preview_action`. 
 * 
 * The `preview_action` of the `DataImporter` can be used to preform a dry-run before importing.
 * This is particularly usefull if data is enriched when being imported. The preview should do the
 * enrichment and sent the enriched data back instead of writing it as the regular import action
 * would do. The enriched data than appears in the `DataImporter` making it easy for the user
 * to take care of enrichment errors or simply to verify the result before it is actually saved
 * to the data source. The `preview_action` must return the same columns as it receives from the
 * `DataImporter`. 
 * 
 * A `preview_action` is recommended for custom-built import actions with extra logic in addtion
 * to pure saving data. In this case, it is a good Idea to extract this logic into a separate
 * action prototype and use it as the `preview_action`, while the actual import action would
 * simply call the preview logic and save the result to the destination data source.
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
class DataImporter extends AbstractWidget implements iHaveColumns, iHaveColumnGroups, iFillEntireContainer, iTakeInput, iHaveToolbars, iHaveButtons
{
    use iHaveColumnsAndColumnGroupsTrait;
    
    use iHaveButtonsAndToolbarsTrait;
    
    use iHaveConfiguratorTrait;
    
    use DataTableTrait;
    
    private $empty_text = null;
    
    private $previewButton = null;
    
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
    public function hasValue() : bool
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
    public function getAlternativeContainerForOrphanedSiblings() : ?iContainOtherWidgets
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
     * @uxon-translatable true
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
    
    /**
     * Customize the preview button.
     * 
     * @uxon-property button_preview
     * @uxon-type \exface\Core\Widgets\Button
     * @uxon-template {"action": {"alias"}}
     * 
     * @param Button $value
     * @return DataImporter
     */
    public function setButtonPreview(UxonObject $uxon) : DataImporter
    {
        $button = WidgetFactory::createFromUxonInParent($this, $uxon, 'Button');
        $this->addButton($button);
        $this->previewButton = $button;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach ($this->getToolbars() as $tb) {
            yield $tb;
        }
        
        // IDEA yield column groups? They are actually the direct children...
        foreach ($this->getColumns() as $col) {
            yield $col;
        }
    }
    
    /**
     * Returns the preview action.
     * 
     * @see hasPreview() for a simple check, if the preview feature is enabled.
     * 
     * @return ActionInterface
     */
    public function getPreviewAction() : ActionInterface
    {
        return $this->previewButton->getAction();
    }
    
    /**
     * Returns the button for the preview action.
     * 
     * @return Button
     */
    public function getPreviewButton() : Button
    {
        return $this->previewButton;
    }
    
    /**
     * Set an action to perform a dry-run a preview/edit the resulting data.
     * 
     * The `preview_action` is particularly usefull if data is enriched when being imported. The 
     * preview should do the enrichment and sent the enriched data back instead of writing it as 
     * the regular import action would do. The enriched data than appears in the `DataImporter` 
     * making it easy for the user to take care of enrichment errors or simply to verify the result 
     * before it is actually saved to the data source. The `preview_action` must return the same 
     * columns as it receives from the `DataImporter`. 
     * 
     * The preview button is automatically generated in the main toolbar, if a `preview_action` 
     * is configured.
     * 
     * @uxon-property preview_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * 
     * @param ActionInterface $value
     * @return DataImporter
     */
    public function setPreviewAction(UxonObject $uxon) : DataImporter
    {
        if ($uxon->hasProperty('icon') === false) {
            $uxon->setProperty('icon', Icons::EYE);
        }
        
        $button = WidgetFactory::createFromUxonInParent($this, new UxonObject([
            'align' => EXF_ALIGN_OPPOSITE, 
            'visibility' => EXF_WIDGET_VISIBILITY_PROMOTED,
            'action' => $uxon->toArray()]), 'Button');
        
        $this->addButton($button);
        $this->previewButton = $button;
        
        return $this;
    }
    
    /**
     * Returns TRUE if the widget has a preview action and a corresponding button.
     * 
     * @return bool
     */
    public function hasPreview() : bool
    {
        return $this->previewButton !== null;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType() : string
    {
        return 'DataConfigurator';
    } 
}