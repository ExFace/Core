<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\DataSheets\DataSheetMapper;
use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\OfflineStrategyDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Factories\WidgetFactory;

/**
 * DataTable-configurators contain tabs for filters, sorters and column controls.
 *
 * In addition to the basic DataConfigurator which can be applied to any Data
 * widget, the DataTableConfigurator has a tab to control the order and visibility
 * of table columns.
 * 
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
    private $setupsDisabled = false;
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
    
    public function getOptionalColumnsUxon() : ?UxonObject
    {
        return $this->columnsUxon;
    }

    public function hasOptionalColumns() : bool
    {
        return $this->columnsUxon !== null && $this->columnsUxon->isEmpty() === false;
    }

    public function addOptionalColumn(DataColumn $column) : DataTableConfigurator
    {
        $alias = $column->getAttributeAlias();
        $columnExists = false;

        $tab = $this->tabColumns; // Do not use getOptionalColumnsTab() here to avoid infinite loop
        foreach ($tab->getWidgets() as $existingColumn) {
            if($alias === $existingColumn->getAttributeAlias()) {
                $columnExists = true;
                if($existingColumn->getVisibility() === WidgetVisibilityDataType::HIDDEN) {
                    $existingColumn->importUxonObject($column->exportUxonObject());
                }
                break;
            }
        }

        $configuredWidget = $this->getWidgetConfigured();
        foreach($configuredWidget->getColumns() as $existingColumn) {
            if($alias === $existingColumn->getAttributeAlias()) {
                $columnExists = true;
                if($existingColumn->getVisibility() === WidgetVisibilityDataType::HIDDEN) {
                    $existingColumn->importUxonObject($column->exportUxonObject());
                }
                break;
            }
        }
        
        if($columnExists) {
            return $this;
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
        // Using a column group makes it automatically possible to use attribute_group_alias
        // with the list of optional columns for the configurator!
        $colGrp = WidgetFactory::createFromUxonInParent(
            $table, 
            new UxonObject([
                'visibility' => WidgetVisibilityDataType::OPTIONAL
            ]), 
            'DataColumnGroup'
        );

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
        // TODO re-enable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
    }

    /**
     * @return Tab|null
     */
    public function getSetupsTab() : ?Tab
    {
        if (! $this->hasSetups()) {
            $this->tabSetups->setHidden(true);
            return $this->tabSetups;
        }
        if ($this->tabSetups->isEmpty()) {
            try {
                $this->initSetupsTable($this->tabSetups);
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException(new WidgetLogicError($this->getDataWidget(), 'Error instantiating setups table. ' . $e->getMessage(), null, $e));
            }
        }
        return $this->tabSetups;
    }

    /**
     *
     * @return Tab
     */
    protected function initSetupsTable(Tab $tab) : Tab
    {
        /* @var $table \exface\Core\Widgets\DataTableResponsive */
        $table = WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'DataTableResponsive',
            'object_alias' => 'exface.Core.WIDGET_SETUP',
            'paginate' => false,
            'configurator_setups_enabled' => false,
            'hide_caption' => true,
            'lazy_loading_action' => [
                'alias' => 'exface.Core.ReadData',
                'offline_strategy' => OfflineStrategyDataType::IGNORE
            ],
            'filters' => [
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
                ], [
                    'hidden' => true,
                    'condition_group' => [
                        'operator' => EXF_LOGICAL_OR,
                        'conditions' => [
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
                ]
            ],
            'columns' => [
                [
                    'data_column_name' => 'SETUP_APPLIED',
                    'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_ACTIVE'),
                    'value' => '',
                    'align' => 'Center',
                    'readonly' => true,
                    "cell_widget" => [
                      "widget_type" => "Icon"
                    ]
                ], [
                    'attribute_alias' => 'NAME',
                ], [
                    'attribute_alias' => 'WIDGET_SETUP_USER__FAVORITE_FLAG',
                    'readonly' => true
                ], [
                    'attribute_alias' => 'VISIBILITY',
                    'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_VISIBILITY'),
                ], [
                    'attribute_alias' => 'DESCRIPTION',
                    'nowrap' => false
                ], [
                    'attribute_alias' => 'PAGE',
                    'hidden' => true
                ], [
                    'attribute_alias' => 'WIDGET_ID',
                    'hidden' => true
                ], [
                    'attribute_alias' => 'SETUP_UXON',
                    'hidden' => true
                ], [
                    'attribute_alias' => 'UID',
                    'hidden' => true
                ],[
                    'attribute_alias' => 'WIDGET_SETUP_USER__UID',
                    'readonly' => true,
                    'hidden' => true
                ], [
                    'attribute_alias' => 'WIDGET_SETUP_USER__MODIFIED_ON',
                    'readonly' => true,
                    'hidden' => true
                ]
            ]
        ]));
        $table->setHideHelpButton(true);
        $table->getToolbarMain()->setIncludeNoExtraActions(true);
        $table->setButtons(new UxonObject([
            [
                'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_APPLY'),
                'hint' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_APPLY_HINT'),
                'icon' => 'check-circle-o',
                'visibility' => WidgetVisibilityDataType::PROMOTED,
                'bind_to_double_click' => true,
                'action' => $this->buildUxonForActionApplySetup()
            ],
            [
                'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_SAVE'),
                'hint' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_SAVE_HINT'),
                'icon' => 'bookmark-o',
                'action' => $this->buildUxonForActionSaveSetup(),
                'id' => $this->getIdAutogenerated() . '_saveSetupBtn'
            ],
            [
                'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_UPDATE'),
                'hint' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_UPDATE_HINT'),
                'icon' => 'refresh',
                'action' => $this->buildUxonForActionUpdateSetup(),
                "disabled_if" => [
                    "operator" => EXF_LOGICAL_OR,
                    "conditions" => [
                        [
                            "value_left" => "=~input!VISIBILITY",
                            "comparator" => "!==",
                            "value_right" => "PRIVATE"
                        ],
                        [
                            "value_left" => "=~input!UID",
                            "comparator" => "==",
                            "value_right" => ""
                        ]
                    ]
                ]
            ],
            [
                'caption' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_FAVORITE'),
                'hint' => $this->translate('WIDGET.DATACONFIGURATOR.SETUPS_TAB_FAVORITE_HINT'),
                'icon' => 'star',
                'hide_caption' => true,
                'action' => $this->buildUxonForActionFavoriteSetup()
            ], [
                'hide_caption' => true,
                'icon' => 'share',
                'action' => $this->buildUxonForActionShareSetup()
            ],
            [
                'hide_caption' => true,
                'action' => $this->buildUxonForActionEditSetup()
            ], 
            [
                'hide_caption' => true,
                'caption' => $this->translate('ACTION.DELETEOBJECT.NAME'),
                'icon' => 'trash-o',
                'disabled_if' => [
                    'operator' => 'AND',
                    'conditions' => [
                        [
                            'value_left' => '=~input!VISIBILITY',
                            'comparator' => ComparatorDataType::EQUALS_NOT,
                            'value_right' => 'PRIVATE'
                        ]
                    ]
                ],
                'action' => $this->buildUxonForActionDeleteSetup()
            ]
        ]));
        $tab->addWidget($table);
        return $tab;
    }

    public function setSetups(UxonObject $arrayOfSetups) : DataTableConfigurator
    {
        $this->setupsUxon = $arrayOfSetups;
        return $this;
    }

    /**
     * Set to FALSE to disable saving/loading widget setups entirely
     *
     * @uxon-property setups_enabled
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool $trueOrFalse
     * @return $this
     */
    public function setSetupsEnabled(bool $trueOrFalse) : DataTableConfigurator
    {
        $this->setupsDisabled = ! $trueOrFalse;
        return $this;
    }

    public function hasSetups() : bool
    {
        return $this->setupsDisabled === false && ! $this->isDisabled() && $this->getDataWidget()->getConfiguratorSetupsEnabled() === true;
    }

    /**
     * Returns the ID of the setups table, or null if setups are disabled
     *
     * @return string|null
     */
    public function getSetupsTableId() : ?string
    {
        if (!$this->hasSetups()){
            return null;
        }
        else{
            return $this->getSetupsTab()->getWidgetFirst()->getId();
        }
    }
    
    protected function buildUxonForActionSaveSetup() : array
    {
        return [
            'alias' => "exface.core.ShowObjectCreateDialog",
            'offline_strategy' => OfflineStrategyDataType::IGNORE,
            "input_rows_min" => 0,
            "input_rows_max" => 1,
            "input_object_alias" => "exface.Core.WIDGET_SETUP",
            "dialog" => [
                "width" => 1,
                "height" => "auto",
                "columns_in_grid" => 1,
                "widgets" => [
                    [
                        "attribute_alias" => "NAME"
                    ],
                    [
                        "attribute_alias" => "DESCRIPTION",
                        "caption" => $this->translate("WIDGET.DATACONFIGURATOR.SETUPS_TAB_SETUP_DESC_PLACEHOLDER")
                    ]
                ],
                "buttons" => [
                    [
                        "caption" => $this->translate("WIDGET.DATACONFIGURATOR.SETUPS_TAB_SETUP_NAME_PROMPT_TITLE"),
                        "visibility" => "promoted",
                        "align" => "opposite",
                        'icon' => 'bookmark-o',
                        "action" => [
                            "alias" => "exface.Core.ActionChain",
                            "offline_strategy" => OfflineStrategyDataType::IGNORE,
                            'use_result_of_action' => 1,
                            "actions" => [
                                [
                                    "alias" => "exface.Core.CallWidgetFunction",
                                    "widget_id" => $this->getDataWidget()->getIdAutogenerated(),
                                    "widget_id_space" => UiPage::WIDGET_ID_SEPARATOR, // Root id space because we are using the autogenerated id!
                                    "function" => DataTable::FUNCTION_DUMP_SETUP . "(SETUP_UXON, PAGE, WIDGET_ID, PROTOTYPE_FILE, OBJECT, PRIVATE_FOR_USER)"
                                ],
                                [
                                    "alias" => "exface.Core.ActionChain",
                                    'use_result_of_action' => 0,
                                    "actions" => [
                                        [
                                            "alias" => "exface.Core.CreateData"
                                        ],
                                        [
                                            "alias" => "exface.Core.CreateData",
                                            "object_alias" => "exface.Core.WIDGET_SETUP_USER",
                                            "input_mapper" => [
                                                "column_to_column_mappings" => [
                                                    [
                                                        "from" => "UID",
                                                        "to" => "WIDGET_SETUP"
                                                    ],
                                                    [
                                                        "from" => "CREATED_BY_USER",
                                                        "to" => "USER"
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    'alias' => "exface.Core.CallWidgetFunction",
                                    'widget_id' => $this->getDataWidget()->getIdAutogenerated(),
                                    "widget_id_space" => UiPage::WIDGET_ID_SEPARATOR,
                                    'function' => "apply_setup([#SETUP_UXON#])"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
    
    protected function buildUxonForActionUpdateSetup() : array
    {
        return [
            'offline_strategy' => OfflineStrategyDataType::IGNORE,
            "input_rows_min" => 1,
            "input_rows_max" => 1,
            "input_object_alias" => "exface.Core.WIDGET_SETUP",
            "alias" => "exface.Core.ActionChain",
            "actions" => [
                [
                    "alias" => "exface.Core.CallWidgetFunction",
                    "widget_id" => $this->getDataWidget()->getIdAutogenerated(),
                    "function" => "dump_setup(SETUP_UXON, PAGE, WIDGET_ID, PROTOTYPE_FILE, OBJECT, PRIVATE_FOR_USER, true)"
                ],
                [
                    "alias" => "exface.Core.UpdateData",
                    "object_alias" => "exface.Core.WIDGET_SETUP"
                ]
            ]
        ];
    }

    protected function buildUxonForActionDeleteSetup() : array
    {
        return [
            'offline_strategy' => OfflineStrategyDataType::IGNORE,
            "input_rows_min" => 1,
            "input_rows_max" => 1,
            "input_object_alias" => "exface.Core.WIDGET_SETUP",
            "alias" => "exface.Core.ActionChain",
            "actions" => [
                [
                    "alias" => "exface.Core.CallWidgetFunction",
                    "widget_id" => $this->getDataWidget()->getIdAutogenerated(),
                    "function" => "clear_applied_setup()"
                ],
                [
                    "alias" => "exface.Core.DeleteObject",
                    "object_alias" => "exface.Core.WIDGET_SETUP"
                ]
            ]
        ];
    }
    
    protected function buildUxonForActionApplySetup() : array
    {
        return [
            'alias' => "exface.Core.CallWidgetFunction",
            "input_rows_min" => 1,
            "input_rows_max" => 1,
            'offline_strategy' => OfflineStrategyDataType::IGNORE,
            'widget_id' => $this->getDataWidget()->getIdAutogenerated(),
            'function' => "apply_setup([#SETUP_UXON#])"
        ];
    }
    
    protected function buildUxonForActionShareSetup() : array
    {
        return [
            'alias' => "exface.Core.WidgetSetupShareForUsers"
        ];
    }

    protected function buildUxonForActionEditSetup() : array
    {
        return [
            'alias' => 'exface.Core.WidgetSetupEditForUsers',
            // Make sure to use system columns for prefill only. DO NOT use WIDGET_SETUP_USER__FAVORITE_FLAG
            // and similar because they will produce errors when trying to read missing data. These columns
            // only work here because of the user filter!
            'input_mapper' => [
                'inherit_columns' => DataSheetMapper::INHERIT_COLUMNS_OWN_SYSTEM_ATTRIBUTES
            ]
        ];
    }
    
    protected function buildUxonForActionFavoriteSetup() : array
    {
        return [
            "alias" => "exface.Core.SaveData",
            'offline_strategy' => OfflineStrategyDataType::IGNORE,
            "object_alias" => "exface.Core.WIDGET_SETUP_USER",
            "input_rows_min" => 1,
            "input_rows_max" => 1,
            "input_mapper" => [
                "from_object_alias" => "exface.Core.WIDGET_SETUP",
                "to_object_alias" => "exface.Core.WIDGET_SETUP_USER",
                "column_to_column_mappings" => [
                    [
                        "from" => "WIDGET_SETUP_USER__UID",
                        "to" => "UID"
                    ],[
                        "from" => "WIDGET_SETUP_USER__MODIFIED_ON",
                        "to" => "MODIFIED_ON"
                    ],[
                        "from" => "=Not(WIDGET_SETUP_USER__FAVORITE_FLAG)",
                        "to" => "FAVORITE_FLAG"
                    ]
                ]
            ]
        ];
    }
}