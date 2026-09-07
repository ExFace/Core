<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Widgets\Parts\Pivot\PivotLayout;

/**
 * Common methods for facade elements that render a PivotTable widget with Perspective.
 * 
 * See [Prespective integratino architecture](../../../Docs/developer_docs/Facades/Common_JS_libraries/Perspective_for_PivotTable.md) 
 * in the docs for more details
 *
 * Perspective performs filtering, sorting, grouping, pivoting and aggregation in a Web Worker.
 * This implementation loads the complete DataSheet into a browser-side Perspective table and
 * initializes its layout from the widget's PivotLayout. It supports multiple value columns,
 * calculated DataSheet columns, the interactive Perspective configuration panel and the
 * Datagrid and chart plugins.
 *
 * ## Dependencies
 *
 * Add the Perspective packages to the facade's Composer dependencies:
 *
 * ```json
 * {
 *     "require": {
 *         "npm-asset/perspective-dev--client": "^5.3",
 *         "npm-asset/perspective-dev--viewer": "^5.3",
 *         "npm-asset/perspective-dev--viewer-datagrid": "^5.3",
 *         "npm-asset/perspective-dev--viewer-charts": "^5.3"
 *     }
 * }
 * ```
 *
 * Add the following source paths to the facade configuration. The inline client bundle is
 * required because Asset Packagist changes the directory name of scoped NPM packages, which
 * prevents the regular bundle from resolving its external WASM files correctly.
 *
 * ```json
 * "LIBS.PERSPECTIVE.LOADER.JS": "exface/core/Facades/AbstractAjaxFacade/js/perspective/perspective-loader.js",
 * "LIBS.PERSPECTIVE.CLIENT.JS": "npm-asset/perspective-dev--client/dist/esm/perspective.inline.js",
 * "LIBS.PERSPECTIVE.VIEWER.JS": "npm-asset/perspective-dev--viewer/dist/cdn/perspective-viewer.js",
 * "LIBS.PERSPECTIVE.DATAGRID.JS": "npm-asset/perspective-dev--viewer-datagrid/dist/cdn/perspective-viewer-datagrid.js",
 * "LIBS.PERSPECTIVE.CHARTS.JS": "npm-asset/perspective-dev--viewer-charts/dist/cdn/perspective-viewer-charts.js",
 * "LIBS.PERSPECTIVE.THEME.CSS": "npm-asset/perspective-dev--viewer/dist/css/pro.css",
 * "LIBS.PERSPECTIVE.FACADE.CSS": "exface/core/Facades/AbstractAjaxFacade/js/perspective/perspective.css"
 * ```
 *
 * Perspective creates a Blob-based Web Worker. If the facade sends a Content Security Policy,
 * it must therefore allow workers from the current origin and Blob URLs, for example:
 *
 * ```json
 * "FACADE.HEADERS.CONTENT_SECURITY_POLICY.WORKER_SRC": "'self' blob:"
 * ```
 *
 * ## Renderer selection
 *
 * Keep the PivotTable.js and Perspective integrations in separate facade element classes because
 * PHP traits are composed statically and cannot be selected from configuration at runtime. The
 * existing PivotTable.js element should remain the conventional class for the widget, such as
 * `EuiPivotTable` or `UI5PivotTable`. Put this trait and all Perspective-specific HTML, data
 * conversion, assets and lifecycle code in a sibling class, such as `EuiPerspectivePivotTable`
 * or `UI5PerspectivePivotTable`.
 *
 * Select the sibling class in the facade's `getElementClassForWidget()` override with this
 * facade-wide option:
 *
 * ```json
 * "WIDGET.PIVOTTABLE.RENDERER": "PivotTable.js"
 * ```
 *
 * `PivotTable.js` is the compatibility default. Setting the value to `Perspective`, compared
 * case-insensitively, opts all PivotTable widgets rendered by that facade into Perspective. Any
 * other value should fall back to the normal PivotTable.js element through the parent element
 * class resolver. Only the selected element class should register its renderer's JavaScript and
 * CSS assets; this prevents work on Perspective from changing or loading dependencies into the
 * legacy renderer. Both classes continue to share only the `PivotTable` and `PivotLayout` widget
 * model.
 *
 * This switch is intentionally facade-wide. `AbstractAjaxFacade` caches the resolved element
 * class by widget type, so selecting different renderers for individual PivotTable instances
 * would require a widget property and corresponding changes to the element-class cache or
 * construction path.
 *
 * ## Facade integration
 *
 * The consuming element must provide `getFacade()`, `getWidget()` and `getId()`. Render the
 * custom element returned by buildHtmlPerspective(), include the assets returned by
 * buildHtmlHeadTagsForPerspective(), and pass the loaded rows to buildJsPerspectiveRender().
 * Facades with their own module or stylesheet registry, such as UI5, should register the loader,
 * theme and facade CSS through that registry instead of buildHtmlHeadTagsForPerspective().
 *
 * Input rows passed to buildJsPerspectiveRender() must be plain objects keyed by widget column
 * captions, not DataSheet column names. The trait uses the same captions for Perspective's
 * schema, dimensions, values and aggregators. See EuiPerspectivePivotTable and
 * UI5PerspectivePivotTable for response conversion examples.
 *
 * The loader imports all Perspective ES modules once per page and the renderer reuses one shared
 * worker. Every widget owns its Perspective table; rendering new data deletes and replaces that
 * table. Enabled widgets open Perspective's configuration panel, while disabled widgets keep the
 * predefined layout and prevent the panel from opening. Configuration changes emit the existing
 * `pivotrendered` jQuery event with the element ID, object alias and current Perspective config.
 *
 * This is currently a client-side implementation: pagination is disabled and all filtered
 * DataSheet rows are transferred to the browser before Perspective can query them.
 *
 * @author Andrej Kabachnik
 */
trait PerspectiveTrait
{
    /**
     * Returns the JavaScript and CSS needed by Perspective.
     *
     * @return string[]
     */
    protected function buildHtmlHeadTagsForPerspective() : array
    {
        $facade = $this->getFacade();

        return [
            '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.PERSPECTIVE.LOADER.JS') . '"></script>',
            '<link href="' . $facade->buildUrlToSource('LIBS.PERSPECTIVE.THEME.CSS') . '" rel="stylesheet" media="screen">',
            '<link href="' . $facade->buildUrlToSource('LIBS.PERSPECTIVE.FACADE.CSS') . '" rel="stylesheet" media="screen">'
        ];
    }

    /**
     * Returns the Perspective custom element.
     *
     * @return string
     */
    protected function buildHtmlPerspective() : string
    {
        return <<<HTML

<perspective-viewer id="{$this->getId()}" class="exf-perspective-viewer" style="width:100%; height:100%; min-height:100px;"></perspective-viewer>
HTML;
    }

    /**
     * Builds the asynchronous Perspective render call.
     *
     * @param string $dataJs
     * @return string
     */
    protected function buildJsPerspectiveRender(string $dataJs) : string
    {
        $facade = $this->getFacade();
        $widget = $this->getWidget();
        $configJs = json_encode($this->buildPerspectiveConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $columnsJs = json_encode($this->buildPerspectiveColumnMetadata(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $urlsJs = json_encode([
            'client' => $facade->buildUrlToSource('LIBS.PERSPECTIVE.CLIENT.JS'),
            'viewer' => $facade->buildUrlToSource('LIBS.PERSPECTIVE.VIEWER.JS'),
            'datagrid' => $facade->buildUrlToSource('LIBS.PERSPECTIVE.DATAGRID.JS'),
            'charts' => $facade->buildUrlToSource('LIBS.PERSPECTIVE.CHARTS.JS')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $disabledJs = $widget->isDisabled() === true ? 'true' : 'false';
        $elementIdJs = json_encode($this->getId());
        $objectAliasJs = json_encode($widget->getMetaObject()->getAliasWithNamespace());

        return <<<JS

    (async function(viewer, data, columns, config, urls, disabled) {
        if (!viewer) {
            return;
        }
        try {
            const perspective = await exfLoadPerspective(urls);
            const schema = {};
            const normalizedData = (data || []).map(function(row) {
                const normalizedRow = {};
                columns.forEach(function(column) {
                    let value = row[column.caption];
                    if (value !== null && value !== undefined && value !== '') {
                        switch (column.type) {
                            case 'float':
                                value = Number(value);
                                break;
                            case 'boolean':
                                value = value === true || value === 1 || value === '1' || value === 'true';
                                break;
                            case 'date':
                            case 'datetime':
                                value = new Date(value);
                                break;
                        }
                        if (column.labels && Object.prototype.hasOwnProperty.call(column.labels, value)) {
                            value = column.labels[value];
                        }
                    } else {
                        value = null;
                    }
                    normalizedRow[column.caption] = value;
                });
                return normalizedRow;
            });
            columns.forEach(function(column) {
                schema[column.caption] = column.type;
            });

            if (!window.exfPerspectiveWorker) {
                window.exfPerspectiveWorker = await perspective.worker();
            }
            if (viewer.exfPerspectiveTable) {
                await viewer.exfPerspectiveTable.delete();
            }
            const table = await window.exfPerspectiveWorker.table(schema);
            if (normalizedData.length > 0) {
                await table.update(normalizedData);
            }
            viewer.exfPerspectiveTable = table;
            await viewer.load(table);
            await viewer.restore(config);
            await viewer.toggleConfig(!disabled);

            if (!viewer.exfPerspectiveEventsBound) {
                viewer.addEventListener('perspective-config-update', async function() {
                    const currentConfig = await viewer.save();
                    $(viewer).trigger('pivotrendered', {
                        element_id: {$elementIdJs},
                        object_alias: {$objectAliasJs},
                        config: currentConfig
                    });
                });
                if (disabled) {
                    viewer.addEventListener('perspective-toggle-settings-before', function(event) {
                        event.preventDefault();
                    });
                }
                viewer.exfPerspectiveEventsBound = true;
            }
        } catch (error) {
            console.error('Cannot render Perspective widget {$this->getId()}', error);
            viewer.textContent = error.message || String(error);
        }
    })(document.getElementById({$elementIdJs}), {$dataJs}, {$columnsJs}, {$configJs}, {$urlsJs}, {$disabledJs})
JS;
    }

    /**
     * Builds Perspective's initial view configuration from the PivotLayout.
     *
     * @return array
     */
    protected function buildPerspectiveConfig() : array
    {
        $layout = $this->getWidget()->getPivotLayout();
        $config = [
            'plugin' => $this->getPerspectivePlugin($layout),
            'settings' => $this->getWidget()->isDisabled() !== true,
            'group_by' => $this->getPerspectiveDimensionCaptions($layout->getPivotRows()),
            'split_by' => $this->getPerspectiveDimensionCaptions($layout->getPivotColumns()),
            'columns' => [],
            'aggregates' => new \stdClass()
        ];

        foreach ($layout->getPivotValues() as $value) {
            $caption = $value->getDataColumn()->getCaption();
            $config['columns'][] = $caption;
            if ($config['aggregates'] instanceof \stdClass) {
                $config['aggregates'] = [];
            }
            $config['aggregates'][$caption] = $this->getPerspectiveAggregator($value->getAggregator());
        }

        $columnConfig = $this->buildPerspectiveColumnConfig($layout);
        if (! empty($columnConfig)) {
            $config['columns_config'] = $columnConfig;
        }

        return $config;
    }

    /**
     * Returns captions for Perspective dimensions.
     *
     * @param array $dimensions
     * @return string[]
     */
    protected function getPerspectiveDimensionCaptions(array $dimensions) : array
    {
        $captions = [];
        foreach ($dimensions as $dimension) {
            $captions[] = $dimension->getDataColumn()->getCaption();
        }
        return $captions;
    }

    /**
     * Maps a PivotTable view to a Perspective plugin.
     *
     * @param PivotLayout $layout
     * @return string
     */
    protected function getPerspectivePlugin(PivotLayout $layout) : string
    {
        switch ($layout->getView('table')) {
            case 'chart_bars':
            case 'chart_bars_stacked':
                return 'X Bar';
            case 'chart_columns':
            case 'chart_columns_stacked':
                return 'Y Bar';
            case 'chart_line':
                return 'Y Line';
            case 'chart_area':
                return 'Y Area';
            case 'chart_pies':
                return 'Sunburst';
            default:
                return 'Datagrid';
        }
    }

    /**
     * Maps ExFace aggregators to Perspective aggregate names.
     *
     * @param string $aggregator
     * @return string
     */
    protected function getPerspectiveAggregator(string $aggregator) : string
    {
        switch ($aggregator) {
            case AggregatorFunctionsDataType::AVG:
                return 'avg';
            case AggregatorFunctionsDataType::COUNT:
                return 'count';
            case AggregatorFunctionsDataType::COUNT_DISTINCT:
                return 'distinct count';
            case AggregatorFunctionsDataType::MAX:
                return 'high';
            case AggregatorFunctionsDataType::MIN:
                return 'low';
            case AggregatorFunctionsDataType::LIST_ALL:
            case AggregatorFunctionsDataType::LIST_DISTINCT:
                return 'join';
            case AggregatorFunctionsDataType::SUM:
            default:
                return 'sum';
        }
    }

    /**
     * Builds Perspective schema and enum-label metadata for all data columns.
     *
     * @return array
     */
    protected function buildPerspectiveColumnMetadata() : array
    {
        $metadata = [];
        foreach ($this->getWidget()->getColumns() as $column) {
            $type = $column->getDataType();
            $perspectiveType = 'string';
            if ($type instanceof NumberDataType) {
                $perspectiveType = 'float';
            } elseif ($type instanceof BooleanDataType) {
                $perspectiveType = 'boolean';
            } elseif (($type instanceof DateTimeDataType || $type instanceof TimeDataType) && ! $column->isCalculated()) {
                $perspectiveType = 'datetime';
            } elseif ($type instanceof DateDataType && ! $column->isCalculated()) {
                $perspectiveType = 'date';
            }

            $columnMetadata = [
                'name' => $column->getDataColumnName(),
                'caption' => $column->getCaption(),
                'type' => $perspectiveType
            ];
            if ($type instanceof EnumDataTypeInterface) {
                $columnMetadata['labels'] = $type->getLabels();
            }
            $metadata[] = $columnMetadata;
        }
        return $metadata;
    }

    /**
     * Builds formatting and heatmap options for Perspective's Datagrid plugin.
     *
     * @param PivotLayout $layout
     * @return array
     */
    protected function buildPerspectiveColumnConfig(PivotLayout $layout) : array
    {
        $config = [];
        $view = $layout->getView('table');
        foreach ($this->getWidget()->getColumns() as $column) {
            $type = $column->getDataType();
            if (! ($type instanceof NumberDataType)) {
                continue;
            }

            $options = [];
            if ($type->getPrecisionMax() !== null) {
                $options['number_format'] = [
                    'minimumFractionDigits' => $type->getPrecisionMin() ?? 0,
                    'maximumFractionDigits' => $type->getPrecisionMax(),
                    'useGrouping' => $type->getGroupSeparator() !== ''
                ];
            }
            if (in_array($view, ['heatmap', 'heatmap_per_column', 'heatmap_per_row'], true)) {
                $options['number_bg_mode'] = 'gradient';
            } elseif ($view === 'table_bar_chart') {
                $options['number_fg_mode'] = 'bar';
            }
            if (! empty($options)) {
                $config[$column->getCaption()] = $options;
            }
        }
        return $config;
    }

    /**
     * Adds the common PivotTable CSS class.
     *
     * @return string
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-pivottable exf-perspective';
    }
}
