<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * Shows a dalog with a data importer to quickly fill the input widget of the button from Excel
 *
 * @author Andrej Kabachnik
 *
 */
class ShowDataImportDialog extends ShowDialog
{
    private $targetWidgetId = null;

    private $targetWidget = null;
    
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

        if ($dialog->isEmpty()) {
            $colAttrs = [];
            $dataWidget = $this->getWidgetToImportInto();
            $uxon = new UxonObject([
                'columns' => []
            ]);
            foreach($dataWidget->getColumns() as $colWidget) {
                if (! $colWidget->isBoundToAttribute()) {
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
                $uxon->appendToProperty('columns', new UxonObject([
                    'attribute_alias' => $colWidget->getAttributeAlias(),
                    'editable' => true
                ]));
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
            $widthCalc = floor(count($colAttrs) / 3);
            $width = max(min($widthCalc, 4), 1);
            $importer->setWidth($width);
            $importer->setHeight('100%');
            $dialog->setWidth($width);

            $actionUxon = new UxonObject([
                'alias' => 'exface.Core.MergeData'
            ]);
            if (false) {
                // TODO
            } elseif ($this->getMetaObject()->hasLabelAttribute()) {
                $actionUxon->setProperty('update_if_matching_attributes', new UxonObject([
                    $this->getMetaObject()->getLabelAttribute()->getAlias()
                ]));
            }

            if ($dialog->getHeight()->isUndefined()) {
                $dialog->setHeight('80%');
            }

            $dialog->addButton($dialog->createButton(new UxonObject([
                'caption' => $this->isDefinedInWidget() ? $this->getWidgetDefinedIn()->getCaption() : $this->getName(),
                'action' => $actionUxon,
                'visibility' => WidgetVisibilityDataType::PROMOTED,
                'align' => EXF_ALIGN_OPPOSITE
            ])));
        }
        
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
        
        return $widget;
    }
}