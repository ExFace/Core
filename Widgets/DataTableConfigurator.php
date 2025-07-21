<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iHaveColumns;

/**
 * DataTable-configurators contain tabs for filters, sorters and column controls.
 *
 * In addition to the basic DataConfigurator which can be applied to any Data
 * widget, the DataTableConfigurator has a tab to control the order and visibility
 * of table columns.
 *
 * TODO the table column control tab is not available yet
 * TODO the aggregations control tab is not available yet
 *
 * @author Andrej Kabachnik, Georg Bieger
 *
 * @method \exface\Core\Widgets\DataTable getWidgetConfigured()
 *
 */
class DataTableConfigurator extends DataConfigurator
{
    private $column_tab = null;
    private UxonObject|null $columnsUxon = null;
    private int $columnsDefaultVisibility = WidgetVisibilityDataType::OPTIONAL;

    private $aggregation_tab = null;

    private $setupsTab = null;
    private $setupsUxon = null;

    public function getWidgets(callable $filter_callback = null): array
    {
        if (! $this->isDisabled()) {
            // Make sure to initialize the columns tab. This will automatically add
            // it to the default widget array inside the container.
            if (null === $this->setupsTab) {
                $this->getSetupsTab();
            }
            if (null === $this->column_tab) {
                $this->getOptionalColumnsTab();
            }
        }
        // TODO add aggregation tab once it is functional
        return parent::getWidgets($filter_callback);
    }

    /**
     * @return Tab|null
     */
    public function getOptionalColumnsTab() : ?Tab
    {
        if (null === $this->column_tab){
            $this->column_tab = $this->createColumnsTab();
            $this->addTab($this->column_tab, 3);
            $this->initColumns();
        }
        return $this->column_tab;
    }

    public function getOptionalColumns() : array
    {
        return $this->getOptionalColumnsTab()->getWidgets();
    }

    /**
     *
     * @return Tab
     */
    protected function createColumnsTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.COLUMN_TAB_CAPTION'));
        $tab->setIcon(Icons::TABLE);
        return $tab;
    }

    public function setOptionalColumns(UxonObject $arrayOfColumns) : DataTableConfigurator
    {
        $this->columnsUxon = $arrayOfColumns;
        return $this;
    }

    public function hasOptionalColumns() : bool
    {
        return $this->columnsUxon !== null && $this->columnsUxon->isEmpty() === false;
    }

    public function addOptionalColumn(DataColumn $column) : DataTableConfigurator
    {
        $alias = $column->getAttributeAlias();

        $tab = $this->getOptionalColumnsTab();
        foreach ($tab->getWidgets() as $existingColumn) {
            if($alias === $existingColumn->getAttributeAlias()) {
                return $this;
            }
        }

        $configuredWidget = $this->getWidgetConfigured();
        foreach($configuredWidget->getColumns() as $existingColumn) {
            if($alias === $existingColumn->getAttributeAlias()) {
                return $this;
            }
        }

        $tab->addWidget($column);
        $column->setParent($configuredWidget);
        return $this;
    }

    /**
     *
     * @return \exface\Core\Widgets\DataTableConfigurator
     */
    protected function initColumns() : DataTableConfigurator
    {
        if (! $this->hasOptionalColumns()) {
            return $this;
        }
        $table = $this->getWidgetConfigured();
        // Do not create the columns for the table itself because that would reserve column
        // ids in the main column group, eventually resulting in shifting ids when optional
        // columns are added. Instead, create a detached column group and use that.
        // IDEA maybe we don't even need a column group? Couldn't we just create a detached
        // column with the columns-tab as parent?
        $colGrp = WidgetFactory::createFromUxonInParent($table, new UxonObject([
            'visibility' => WidgetVisibilityDataType::OPTIONAL
        ]), 'DataColumnGroup');

        foreach($arr = $this->columnsUxon->toArray() as $key => $value) {
            if($value['visibility'] === null) {
                $arr[$key]['visibility'] = $this->columnsDefaultVisibility;
            }
        }

        $colGrp->importUxonObject(new UxonObject(['columns' => $arr]));
        foreach ($colGrp->getColumns() as $column) {
            $this->addOptionalColumn($column);
        }

        return $this;
    }

    /**
     *
     * @return Tab
     */
    public function getAggregationTab()
    {
        if (is_null($this->aggregation_tab)){
            $this->aggregation_tab = $this->createAggregationTab();
            $this->addTab($this->aggregation_tab, 4);
        }
        return $this->column_tab;
    }

    /**
     *
     * @return Tab
     */
    protected function createAggregationTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.AGGREGATION_TAB_CAPTION'));
        $tab->setIcon(Icons::OBJECT_GROUP);
        // TODO reenable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
    }

    /**
     * @return Tab|null
     */
    public function getSetupsTab() : ?Tab
    {
        if (null === $this->setupsTab){
            $this->setupsTab = $this->createSetupsTab();
            $this->addTab($this->setupsTab/*, 0*/);
        }
        return $this->setupsTab;
    }

    /**
     *
     * @return Tab
     */
    protected function createSetupsTab()
    {
        /* TODO/FIXME:  -> the table doesnt update after changes (new, delete, updates); 
                        -> this happens for both the JS functions, and the normal ones (like DeleteObject)
                        -> chaining the action with a widgetRefresh breaks everything

                        rn, the table must be refreshed via the refreh button (?)

        */
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_CAPTION'));
        $tab->setIcon(Icons::STAR);
        /* @var $table \exface\Core\Widgets\DataTableResponsive */
        $table = WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'DataTableResponsive',
            'object_alias' => 'exface.Core.WIDGET_SETUP',
            'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_CAPTION'),
            'filters' => [
                [
                    'attribute_alias' => 'PAGE',
                    'comparator' => ComparatorDataType::EQUALS,
                    'value' => $this->getPage()->getUid(),
                    'hidden' => true
                ], [
                    'attribute_alias' => 'WIDGET_ID',
                    'comparator' => ComparatorDataType::EQUALS,
                    'value' => $this->getDataWidget()->getId(),
                    'hidden' => true
                ], [
                    'hidden' => true,
                    'condition_group' => [
                        'operator' => EXF_LOGICAL_OR,
                        'conditons' => [
                            [
                                'expression' => 'PRIVATE_FOR_USER',
                                'comparator' => ComparatorDataType::EQUALS,
                                'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid()
                            ], [
                                'expression' => 'PRIVATE_FOR_USER',
                                'comparator' => ComparatorDataType::EQUALS,
                                'value' => EXF_LOGICAL_NULL
                            ], [
                                'expression' => 'WIDGET_SETUP_USER__USER',
                                'comparator' => ComparatorDataType::EQUALS,
                                'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                                'apply_to_aggregates' => true
                            ]
                        ]
                    ]
                ]
            ],
            'columns' => [
                [
                    'attribute_alias' => 'NAME',
                ], [
                    'attribute_alias' => 'WIDGET_SETUP_USER__FAVORITE_FLAG'
                ], [
                    'attribute_alias' => 'WIDGET_SETUP_USER__DEFAULT_SETUP_FLAG',
                    'hidden' => true
                ], [
                    'attribute_alias' => 'VISIBILITY',
                    'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_VISIBILITY'),
                ], [
                    'attribute_alias' => 'SETUP_UXON',
                    'hidden' => true
                ]
            ],
            'buttons' => [
                [
                    'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_APPLY'),
                    'icon' => 'check-circle-o',
                    'visibility' => WidgetVisibilityDataType::PROMOTED,
                    'action' => [
                        "input_rows_min" => 1,
                        "input_rows_max" => 1,
                        'alias' => "exface.Core.CallWidgetFunction",
                        'widget_id' => $this->getDataWidget()->getId(),
                        'function' => "apply_setup([#SETUP_UXON#])"
                    ]

                ],
                [
                    'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_SAVE'),
                    'icon' => 'bookmark-o',
                    'visibility' => WidgetVisibilityDataType::PROMOTED,
                    'action' => [
                        'alias' => "exface.Core.CallWidgetFunction",
                        'widget_id' => $this->getDataWidget()->getId(),
                        'function' => "save_setup"
                    ]
                ],
                /*[
                    'caption' => 'Save Refresh Test',
                    'action' => [
                        "alias" => "exface.Core.ActionChain",
                        "actions" => [
                            [
                                'alias' => "exface.Core.CallWidgetFunction",
                                'widget_id' => $this->getDataWidget()->getId(),
                                'function' => "save_setup"
                            ],
                            [
                                'alias' => "exface.Core.CallWidgetFunction",
                                'widget_id' => $this->getDataWidget()->getId(),
                                'function' => "refresh"
                            ]
                        ]
                    ]
                ],*/
                /*[
                    'caption' => 'Default',
                    'icon' => 'table',
                    'action' => [
                        "input_rows_min" => 1,
                        "input_rows_max" => 1,
                        "input_object_alias" => "exface.Core.WIDGET_SETUP",
                        "alias" => "exface.Core.ActionChain",
                        "actions" => [
                                [
                                "alias" => "exface.core.ReadData",
                                "object_alias" => "exface.Core.WIDGET_SETUP",
                                "input_mapper" => [
                                    "from_object_alias" => "exface.Core.WIDGET_SETUP",
                                    "to_object_alias" => "exface.Core.WIDGET_SETUP",
                                    "column_to_filter_mappings" => [
                                        [
                                            "from" => "WIDGET_ID",
                                            "to" => "WIDGET_ID",
                                            "comparator" => "=="
                                        ]
                                    ]
                                ]
                            ],
                            [
                                "alias" => "exface.core.ReadData",
                                "object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                "input_mappers" => [
                                    [
                                        "from_object_alias" => "exface.Core.WIDGET_SETUP",
                                        "to_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                        "column_to_column_mappings" => [
                                            [
                                            "from" => "=NullValue()",
                                            "to" => "WIDGET_SETUP"
                                            ]
                                        ],
                                        "column_to_filter_mappings" => [
                                            [
                                            "from" => "UID",
                                            "to" => "WIDGET_SETUP",
                                            "comparator" => "=="
                                            ]
                                        ]
                                    ],
                                ]
                            ],
                            [
                                "alias" => "exface.core.UpdateData",
                                "object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                "input_mapper" => [
                                    "from_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                    "to_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                    "column_to_column_mappings" => [
                                    [
                                        "from" => false,
                                        "to" => "DEFAULT_SETUP_FLAG"
                                    ]
                                    ]
                                ]
                            ]
                        ]

                    ]
                ],
                [
                    'caption' => 'Manage',
                    'icon' => 'pencil-square-o'
                ],
                [
                    'caption' => 'Share',
                    'icon' => 'share-alt'
                ],*/
                [
                    'icon' => 'undo',
                    'action_alias' => 'exface.Core.RefreshWidget'
                ],
                [
                    'action_alias' => 'exface.Core.DeleteObject',
                    'disabled_if' => [
                        'operator' => 'AND',
                        'conditions' => [
                            [
                                'value_left' => '=~input!VISIBILITY',
                                'comparator' => ComparatorDataType::EQUALS,
                                'value_right' => 'PRIVATE'
                            ]
                        ]
                    ]
                ]
            ]
        ]));
        $table->setHideHelpButton(true);
        $table->getToolbarMain()->setIncludeNoExtraActions(true);
        $table->setPaginate(false);
        $table->getConfiguratorWidget()->setDisabled(true);
        $tab->addWidget($table);
        return $tab;
    }

    public function setSetups(UxonObject $arrayOfSetups) : DataTableConfigurator
    {
        $this->setupsUxon = $arrayOfSetups;
        return $this;
    }
}