<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Factories\WidgetFactory;

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
    private $tabColumns = null;
    private UxonObject|null $columnsUxon = null;
    private int $columnsDefaultVisibility = WidgetVisibilityDataType::OPTIONAL;

    private $aggregation_tab = null;

    private $tabSetups = null;
    private $setupsUxon = null;

    /**
     * {@inheritDoc}
     * @see DataConfigurator::initTabs()
     */
    protected function initTabs() : void
    {
        parent::initTabs();

        // Setups tab
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_CAPTION'));
        $tab->setIcon(Icons::STAR);
        $this->tabSetups = $tab;
        $this->addTab($tab, 0);

        // Columns tab
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.COLUMN_TAB_CAPTION'));
        $tab->setIcon(Icons::TABLE);
        $this->tabColumns = $tab;
        $this->addTab($tab);
    }

    public function getWidgets(callable $filter_callback = null): array
    {
        if (! $this->isDisabled()) {
            // Make sure to initialize setups and columns tabs if we might need them
            $this->getSetupsTab();
            $this->getOptionalColumnsTab();
        }
        return parent::getWidgets($filter_callback);
    }

    /**
     * @return Tab|null
     */
    public function getOptionalColumnsTab() : ?Tab
    {
        if ($this->isDisabled()) {
            $this->tabColumns->setHidden(true);
            return $this->tabColumns;
        }
        if ($this->tabColumns->isEmpty()){
            $this->initColumns($this->tabColumns);
        }
        return $this->tabColumns;
    }

    public function getOptionalColumns() : array
    {
        return $this->getOptionalColumnsTab()->getWidgets();
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

        $tab = $this->tabColumns; // Do not use getOptionalColumnsTab() here to avoid infinite loop
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
    protected function initColumns(Tab $tab) : DataTableConfigurator
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
        return $this->tabColumns;
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
        if ($this->isDisabled()) {
            $this->tabSetups->setHidden(true);
            return $this->tabSetups;
        }
        if ($this->tabSetups->isEmpty()) {
            $this->initSetupsTable($this->tabSetups);
        }
        return $this->tabSetups;
    }

    /**
     *
     * @return Tab
     */
    protected function initSetupsTable(Tab $tab) : Tab
    {
        /* TODO/FIXME:  -> the table doesnt update after changes (new, delete, updates); 
                        -> this happens for both the JS functions, and the normal ones (like DeleteObject)
                        -> chaining the action with a widgetRefresh breaks everything
                        -> right now, the table must be refreshed via the refreh button (?)

                        ->  Default button removes all defaults for the user (across all pages, page filter not working?)

        */
        /* @var $table \exface\Core\Widgets\DataTableResponsive */
        $table = WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'DataTableResponsive',
            'object_alias' => 'exface.Core.WIDGET_SETUP',
            'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_CAPTION'),
            'filters' => [
                [
                    'attribute_alias' => 'WIDGET_SETUP_USER__USER__UID',
                    'comparator' => ComparatorDataType::EQUALS,
                    'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                    'apply_to_aggregates' => true
                ], 
                [
                    'attribute_alias' => 'PAGE',
                    'comparator' => ComparatorDataType::EQUALS,
                    'value' => $this->getPage()->getUid(),
                    'hidden' => true
                ], 
                [
                    'attribute_alias' => 'WIDGET_ID',
                    'comparator' => ComparatorDataType::EQUALS,
                    'value' => $this->getDataWidget()->getId(),
                    'hidden' => true
                ], /*[
                    'hidden' => true,
                    'condition_group' => [
                        'operator' => EXF_LOGICAL_OR,
                        'conditons' => [
                            [
                                'expression' => 'PRIVATE_FOR_USER',
                                'comparator' => ComparatorDataType::EQUALS,
                                'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid()
                            ], 
                            [
                                'expression' => 'PRIVATE_FOR_USER',
                                'comparator' => ComparatorDataType::EQUALS,
                                'value' => EXF_LOGICAL_NULL
                            ], 
                            [
                                'expression' => 'WIDGET_SETUP_USER__USER__UID',
                                'comparator' => ComparatorDataType::EQUALS,
                                'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                                'apply_to_aggregates' => true
                            ]
                        ]
                    ]
                ]*/
            ],
            'columns' => [
                [
                    'attribute_alias' => 'NAME',
                ],
                [
                    'attribute_alias' => 'WIDGET_SETUP_USER__FAVORITE_FLAG'
                ], 
                [
                    'attribute_alias' => 'WIDGET_SETUP_USER__DEFAULT_SETUP_FLAG'
                ], 
                [
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
                [
                    'caption' => 'Favorit',
                    'icon' => 'star',
                    'action' => [
                        "alias" => "exface.Core.ActionChain",
                        "input_rows_min" => 1,
                        "input_rows_max" => 1,
                        "input_object_alias" => "exface.Core.WIDGET_SETUP",
                        "actions" => [
                            [
                                "alias" => "exface.core.ReadData",
                                "object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                "input_mapper" => [
                                    "from_object_alias" => "exface.Core.WIDGET_SETUP",
                                    "to_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                    "column_to_column_mappings" => [
                                        [
                                        "from" => EXF_LOGICAL_NULL,
                                        "to" => "WIDGET_SETUP"
                                        ]
                                    ],
                                    "column_to_filter_mappings" => [
                                        [
                                            "from" => "UID",
                                            "to" => "WIDGET_SETUP"
                                        ],
                                        [
                                            "from" => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                                            "to" => "USER",
                                            "comparator" => ComparatorDataType::EQUALS
                                        ]
                                    ]
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
                                            "from" => "=Not(FAVORITE_FLAG)",
                                            "to" => "FAVORITE_FLAG"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'caption' => 'Share',
                    'icon' => 'share',
                    'action' => [
                        "alias" => "exface.Core.ShowDialog",
                        "input_rows_min" => 1,
                        "input_rows_max" => 1,
                        "input_object_alias" => "exface.Core.WIDGET_SETUP",
                        "dialog" => [
                            "height" => "auto",
                            "width" => 1,
                            "columns_in_grid" => 1,
                            "maximized" => false,
                            "caption" => "Ansicht teilen",
                            "widgets" => [
                                [
                                    "widget_type" => "InputComboTable",
                                    "caption" => "Nutzer",
                                    "table_object_alias" => "exface.Core.USER",
                                    "text_attribute_alias" => "FULL_NAME",
                                    "value_attribute_alias" => "UID",
                                    "data_column_name" => "_SharedUser"
                                ]
                            ],
                            "buttons" => [
                            [
                                "caption" => "Share",
                                "visibility" => "promoted",
                                "align" => "opposite",
                                "action" => [
                                "result_message_text" => "Ansicht erfolgreich geteilt!",
                                "alias" => "exface.Core.ActionChain",
                                "actions" => [
                                    // [
                                    //     // -> should sharing set private_for_user to null?
                                    //     "alias" => "exface.Core.UpdateData",
                                    //     "object_alias" => "exface.Core.WIDGET_SETUP",
                                    //     "input_mapper" => [
                                    //         "column_to_column_mappings" => [
                                    //             [
                                    //                 "from" => EXF_LOGICAL_NULL,
                                    //                 "to" => "PRIVATE_FOR_USER"
                                    //             ]
                                    //         ]
                                    //     ],
                                    // ],
                                    [
                                    "alias" => "exface.Core.CreateData",
                                    "object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                    "input_mapper" => [
                                        "from_object_alias" => "exface.Core.WIDGET_SETUP",
                                        "to_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                        "column_to_column_mappings" => [
                                            [
                                                "from" => "UID",
                                                "to" => "WIDGET_SETUP"
                                            ],
                                            [
                                                "from" => "_SharedUser",
                                                "to" => "USER"
                                            ]
                                        ]
                                    ]
                                    ]
                                ]
                                ]
                            ]
                            ]
                        ]
                    ]
                ],
                /*[
                    'caption' => 'Default',
                    'icon' => 'table',
                    'action' => [
                        "input_rows_min" => 1,
                        "input_rows_max" => 1,
                        "input_object_alias" => "exface.Core.WIDGET_SETUP",
                        "alias" => "exface.Core.ActionChain",
                        "use_input_data_of_action" => 0,
                        "actions" => [
                            [
                                "alias" => "exface.core.ActionChain",
                                "input_object_alias" => "exface.Core.WIDGET_SETUP",
                                "actions" => [
                                    [
                                        "alias" => "exface.core.ReadData",
                                        "object_alias" => "exface.Core.WIDGET_SETUP",
                                        "input_mapper" => [
                                            "from_object_alias" => "exface.Core.WIDGET_SETUP",
                                            "to_object_alias" => "exface.Core.WIDGET_SETUP",
                                            "column_to_filter_mappings" => [
                                                [
                                                    "from" => "UID",
                                                    "to" => "WIDGET_ID",
                                                    "comparator" => ComparatorDataType::EQUALS
                                                ],
                                                [
                                                    "from" => "PAGE",
                                                    "to" => "PAGE",
                                                    "comparator" => ComparatorDataType::EQUALS
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
                                            "from" => EXF_LOGICAL_NULL,
                                            "to" => "WIDGET_SETUP"
                                            ]
                                        ],
                                        "column_to_filter_mappings" => [
                                            [
                                            "from" => "UID",
                                            "to" => "WIDGET_SETUP",
                                            "comparator" => ComparatorDataType::EQUALS
                                            ],
                                            [
                                            "from" => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                                            "to" => "USER",
                                            "comparator" => ComparatorDataType::EQUALS
                                            ]
                                        ]
                                        ]
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
                                ],
                                [
                                "alias" => "exface.core.ActionChain",
                                "input_object_alias" => "exface.Core.WIDGET_SETUP",
                                "actions" => [
                                    [
                                    "alias" => "exface.core.ReadData",
                                    "input_mappers" => [
                                        [
                                        "from_object_alias" => "exface.Core.WIDGET_SETUP",
                                        "to_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                        "column_to_column_mappings" => [
                                            [
                                            "from" => EXF_LOGICAL_NULL,
                                            "to" => "WIDGET_SETUP"
                                            ]
                                        ],
                                        "column_to_filter_mappings" => [
                                            [
                                            "from" => "UID",
                                            "to" => "WIDGET_SETUP"
                                            ],
                                            [
                                            "from" => $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid(),
                                            "to" => "USER",
                                            "comparator" => ComparatorDataType::EQUALS
                                            ]
                                        ]
                                        ]
                                    ],
                                    "object_alias" => "exface.Core.WIDGET_SETUP_USER"
                                    ],
                                    [
                                    "alias" => "exface.core.UpdateData",
                                    "object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                    "input_mapper" => [
                                        "from_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                        "to_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                        "column_to_column_mappings" => [
                                            [
                                                "from" => true,
                                                "to" => "DEFAULT_SETUP_FLAG"
                                            ]
                                        ]
                                    ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],*/
                /*[
                    'caption' => 'Manage',
                    'icon' => 'pencil-square-o'
                ]*/
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
                                'comparator' => ComparatorDataType::EQUALS_NOT,
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