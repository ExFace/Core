<?php

use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\RangeDataType;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;

require dirname(__DIR__, 2) . '/PHPUnit/bootstrap.php';

$workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
$facade = new class extends AbstractAjaxFacade {
    public function __construct()
    {
    }

    public function buildResponseData(DataSheetInterface $data_sheet, WidgetInterface $widget = null)
    {
        throw new LogicException('Not used by the formatter fixture.');
    }

    protected function getPageTemplateFilePathDefault() : string
    {
        throw new LogicException('Not used by the formatter fixture.');
    }
};
$date = DataTypeFactory::createFromPrototype($workbench, DateDataType::class)->setFormat('dd.MM.yyyy');
$dateTime = DataTypeFactory::createFromPrototype($workbench, DateTimeDataType::class)->setFormat('dd.MM.yyyy HH:mm:ss');
$range = DataTypeFactory::createFromPrototype($workbench, RangeDataType::class);
$plain = $facade->getDataTypeFormatter($range);
$dates = $facade->getDataTypeFormatter((clone $range)->setEndpointDataType($date));
$timestamps = $facade->getDataTypeFormatter((clone $range)->setEndpointDataType($dateTime));

echo json_encode([
    'parse' => $dates->buildJsFormatParser('input'),
    'format' => $dates->buildJsFormatter('input'),
    'validate' => $dates->buildJsValidator('input'),
    'filter' => $dates->buildJsFilterParser('input', 'null'),
    'timestampFilter' => $timestamps->buildJsFilterParser('input', "'..'"),
    'plain' => $plain->buildJsFormatParser('input'),
    'ordinaryFilter' => $facade->getDataTypeFormatter($date)->buildJsFilterParser('input', "'..'"),
], JSON_THROW_ON_ERROR);
