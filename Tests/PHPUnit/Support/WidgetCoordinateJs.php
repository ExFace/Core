<?php
namespace exface\Core\Tests\PHPUnit\Support;

require dirname(__DIR__) . '\\bootstrap.php';

use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\WidgetLinkCoordinate;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryDataTablesTrait;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataTable;

/**
 * Restricts the fixture to value generation without starting the facade lifecycle.
 */
abstract class WidgetCoordinateJs extends AbstractJqueryElement
{
    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJs()
     */
    public function buildJs()
    {
        throw new \LogicException('Full rendering is outside this fixture.');
    }

    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildHtml()
     */
    public function buildHtml()
    {
        throw new \LogicException('Full rendering is outside this fixture.');
    }

    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsBusyIconShow()
     */
    public function buildJsBusyIconShow()
    {
        throw new \LogicException('Busy state is outside this fixture.');
    }

    /**
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsBusyIconHide()
     */
    public function buildJsBusyIconHide()
    {
        throw new \LogicException('Busy state is outside this fixture.');
    }
}

$workbench = new Workbench();
$page = UiPageFactory::createEmpty($workbench);
$table = new DataTable($page);
$table->setMetaObject(new MetaObject(new Model($workbench)));
foreach (['Col1', 'Col2'] as $name) {
    $column = new DataColumn($page, $table);
    $column->setId($name);
    $column->setDataColumnName($name);
    $table->addColumn($column);
}
$table->setUidColumnId('Col1');

$elements = [
    'table' => new class($table) extends WidgetCoordinateJs {
        use JqueryDataTablesTrait;

        /**
         * Supplies real widget metadata without initializing an unrelated facade.
         *
         * @param WidgetInterface $widget
         */
        public function __construct(WidgetInterface $widget, ?FacadeInterface $facade = null)
        {
            $this->setWidget($widget);
        }

        /**
         * {@inheritDoc}
         * @see AbstractJqueryElement::getId()
         */
        public function getId() : string
        {
            return 'fixture';
        }
    },
    'spreadsheet' => new class($table) extends WidgetCoordinateJs {
        use JExcelTrait;

        /**
         * Supplies real widget metadata without initializing an unrelated facade.
         *
         * @param WidgetInterface $widget
         */
        public function __construct(WidgetInterface $widget, ?FacadeInterface $facade = null)
        {
            $this->setWidget($widget);
        }

        /**
         * Returns the spreadsheet fixture's DOM adapter.
         *
         * @return string
         */
        public function buildJsJqueryElement() : string
        {
            return 'jqSpreadsheet';
        }
    }
];
$result = [];
foreach ($elements as $name => $element) {
    foreach (['Col1$~loaded:LIST', 'Col1$~selected:LIST', 'Col2$~loaded:SUM', 'Col2$~selected:SUM', 'Col2$2', 'Col2$1', 'Col1$99', 'Col2$~loaded:AVG', 'Col1$~selected:LIST_DISTINCT'] as $expression) {
        $result[$name][$expression] = $element->buildJsValueGetter(new WidgetLinkCoordinate($expression));
    }
    $result[$name]['default'] = $element->buildJsValueGetter();
}
echo json_encode($result, JSON_THROW_ON_ERROR);
