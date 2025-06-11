<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Dialog;
use exface\Core\Widgets\Message;

/**
 * Shows a dalog with a data importer to quickly fill the input widget of the button from Excel.
 *
 * @author Andrej Kabachnik
 *
 */
class ShowDataImportDialog extends ShowDialog
{
    private $targetWidgetId = null;
    private $targetWidget = null;
    private $excludedAliases = [];
    private $updateIfMatchingAttributeAliases = [];
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::UPLOAD);
    } 

    protected function createDialogWidget(UiPageInterface $page, WidgetInterface $contained_widget = NULL)
    {
        $dialog = parent::createDialogWidget($page);
        
        $actionObj = $this->getMetaObject();
        if (! $actionObj->hasDataSource() || ! $actionObj->isWritable()) {
            $this->addMessage($dialog, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWOBJECTEDITDIALOG.DATA_SOURCE_NOT_WRITABLE'));
            return $dialog;
        }

        if ($dialog->isEmpty()) {
            $colAttrs = [];
            $dataWidget = $this->getWidgetToImportInto();
            $uxon = new UxonObject([
                'columns' => []
            ]);
            $excludeAliases = $this->getExcludedAttributeAliases();
            foreach($dataWidget->getColumns() as $colWidget) {
                if (! $colWidget->isBoundToAttribute()) {
                    continue;
                }
                if ($colWidget->hasAggregator()) {
                    continue;
                }
                $attr = $colWidget->getAttribute();
                if ($attr->getRelationPath()->countRelations() > 1) {
                    continue;
                }
                if ($attr->getRelationPath()->countRelations() === 1) {
                    $firstRel = $attr->getRelationPath()->getRelationFirst();
                    if ($firstRel->isForwardRelation()) {
                        $attr = $firstRel->getLeftKeyAttribute();
                    } else {
                        continue;
                    }
                }
                if (! $attr->isEditable()) {
                    continue;
                }
                if (array_key_exists($attr->getAlias(), $colAttrs)) {
                    continue;
                }
                $colAttrs[$attr->getAlias()] = $attr;
            }
        } else {
            foreach ($this->getMetaObject()->getAttributes()->getEditable() as $attr) {
                $colAttrs[$attr->getAlias()] = $attr;
            }
        }

        if (empty($colAttrs)) {
            $this->addMessage($dialog, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.SHOWDATAIMPORTDIALOG.MESSAGE_EMPTY'));
            return $dialog;
        }

        foreach ($colAttrs as $i => $attr) {
            if (in_array($attr->getAliasWithRelationPath(), $excludeAliases)) {
                unset($colAttrs[$i]);
                continue;
            }
            $colUxon = new UxonObject([
                'attribute_alias' => $attr->getAliasWithRelationPath(),
                'editable' => true
            ]);
            if ($attr->isRelation()) {
                $colUxon->setProperty('cell_widget', new UxonObject([
                    'widget_type' => 'InputComboTable',
                    'lazy_loading' => false
                ]));
            }
            $uxon->appendToProperty('columns', $colUxon);
        }
        
        /** @var \exface\Core\Widgets\DataImporter $importer*/
        $importer = WidgetFactory::createFromUxonInParent($dialog, $uxon, 'DataImporter');
        foreach ($dialog->getMetaObject()->getAttributes()->getRequired()->getEditable() as $attr) {
            if (! array_key_exists($attr->getAlias(), $colAttrs)) {
                $colAttrs[$attr->getAlias()] = $attr;
                $col = $importer->createColumnFromAttribute($attr);
                $col->setEditable(true);
                $importer->addColumn($col);
            }
        }
        $dialog->addWidget($importer);
        if (count($colAttrs) === 1) {
            $width = 1;
        } else {
            $widthCalc = floor(count($colAttrs) / 2);
            $width = max(min($widthCalc, 4), 2);
        }
        $importer->setWidth($width);
        $importer->setHeight('100%');
        $dialog->setWidth($width);

        if ($dialog->getHeight()->isUndefined()) {
            $dialog->setHeight('80%');
        }

        $dialog->addButton($dialog->createButton(new UxonObject([
            'caption' => $this->isDefinedInWidget() ? $this->getWidgetDefinedIn()->getCaption() : $this->getName(),
            'action' => $this->getMergeActionUxon(),
            'visibility' => WidgetVisibilityDataType::PROMOTED,
            'align' => EXF_ALIGN_OPPOSITE
        ])));
        
        return $dialog;
    }
    
    /**
     * Returns the widget for which the data is to be imported.
     * 
     * @param TaskInterface $task
     * 
     * @return WidgetInterface|NULL
     */
    public function getWidgetToImportInto() : ?WidgetInterface
    {
        $trigger = $this->getWidgetDefinedIn();
        if ($this->targetWidgetId !== null) {
            $widget = $trigger->getPage()->getWidget($this->targetWidgetId);
        } else {
            if ($trigger instanceof iUseInputWidget) {
                $widget = $trigger->getInputWidget();
            } else {
                $widget = $trigger;
            }
        }

        switch (true) {
            case $widget instanceof iShowData:
                return $widget;
            case $widget instanceof iUseData:
                return $widget->getData();
        }
        
        return null;
    }

    /**
     * List of attribute aliases to be excluded from the import although they might be shown in the input widget
     * 
     * @uxon-property exclude_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $arrayOfExpressions
     * @return ShowDataImportDialog
     */
    protected function setExcludeAttributes(UxonObject $arrayOfExpressions) : ShowDataImportDialog
    {
        $this->excludedAliases = $arrayOfExpressions->toArray();
        return $this;
    }

    /**
     * 
     * @return string[]
     */
    protected function getExcludedAttributeAliases() : array
    {
        return $this->excludedAliases;
    }
    protected function addMessage(Dialog $dialog, string $message, string $type = MessageTypeDataType::WARNING) : Message
    {
        $message = WidgetFactory::createFromUxonInParent($dialog, new UxonObject([
            'widget_type' => 'Message',
            'type' => $type,
            'width' => '100%',
            'text' => $message
        ]));
        $dialog->addWidget($message);
        return $message;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getUpdateIfMatchingAttributeAliases() : array
    {
        return $this->updateIfMatchingAttributeAliases;
    }
    
    /**
     * If values in these attibutes are found in the data source, the corresponding rows will be updated instead of a create.
     * 
     * **NOTE:** in case of an update this will overwrite data in all the attributes included in the import.
     *
     * @uxon-property update_if_matching_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return ShowDataImportDialog
     */
    protected function setUpdateIfMatchingAttributes(UxonObject $uxon) : ShowDataImportDialog
    {
        $this->updateIfMatchingAttributeAliases = $uxon->toArray();
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function isUpdateIfMatchingAttributes() : bool
    {
        return empty($this->updateIfMatchingAttributeAliases) === false;
    }

    /**
     * 
     * @return UxonObject
     */
    protected function getMergeActionUxon() : UxonObject
    {
        $actionUxon = new UxonObject([
            'alias' => 'exface.Core.MergeData'
        ]);
        if ($this->isUpdateIfMatchingAttributes()) {
            $actionUxon->setProperty('update_if_matching_attributes', new UxonObject(
                $this->getUpdateIfMatchingAttributeAliases()
            ));
        } elseif ($this->getMetaObject()->hasLabelAttribute()) {
            $actionUxon->setProperty('update_if_matching_attributes', new UxonObject([
                $this->getMetaObject()->getLabelAttribute()->getAlias()
            ]));
        }
        return $actionUxon;
    }
}