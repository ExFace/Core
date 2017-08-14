<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Actions\ActionExportDataError;
use exface\Core\Widgets\DataTable;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ExpressionFactory;

/**
 * Exports data to an xlsx file.
 * 
 * @author SFL
 *
 */
class ExportXLSX extends ExportDataFile
{

    // Der Name der Sheets in der Excel-Datei
    private $excelDataSheetName = 'Sheet1';

    private $excelInfoSheetName = 'Sheet2';

    // Bildet alexa UI-Datentypen auf Excel-Datentypen ab
    private $typeMap = [
        'Boolean' => 'integer',
        'FlagTreeFolder' => 'string',
        'Html' => 'string',
        'ImageUrl' => 'string',
        'Integer' => 'integer',
        'Json' => 'string',
        'Number' => '', // Komma wird automatisch angezeigt oder ausgeblendet
        'Price' => 'price',
        'PropertySet' => 'string',
        'Relation' => 'string',
        'RelationHierarchy' => 'string',
        'String' => 'string',
        'Text' => 'string',
        'Url' => 'string',
        'Uxon' => 'string'
        // Date und Timestamp werden in init() aus den Uebersetzungsdateien
        // hinzugefuegt
    ];

    private $rowNumberWritten = 0;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportData::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIconName(Icons::FILE_EXCEL_O);
        
        $this->typeMap['Date'] = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('DATE.FORMAT.SCREEN.EXCEL');
        $this->typeMap['Timestamp'] = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('DATETIME.FORMAT.SCREEN.EXCEL');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeHeader()
     */
    protected function writeHeader(DataSheetInterface $dataSheet)
    {
        // Den ersten Datensatz einlesen. Z.B. UIDs haben Datentyp Number sind aber
        // hexadezimale Zahlen des Formats 0x0123456789abcde..., womit Excel nicht
        // klar kommt. Sie werden daher als String gespeichert. Die einzige
        // Moeglichkeit sie von anderen Zahlen zu unterscheiden besteht darin den
        // Inhalt der Spalte zu untersuchen.
        $dataTypeSheet = $dataSheet->copy();
        $dataTypeSheet->setRowsOnPage(1);
        $dataTypeSheet->dataRead();
        
        /** @var DataTable $inputWidget */
        $inputWidget = $this->getCalledByWidget()->getInputWidget();
        $header = [];
        $output = [];
        foreach ($dataSheet->getColumns() as $col) {
            if (! $col->getHidden()) {
                // Name der Spalte
                if ($this->getWriteReadableHeader()) {
                    if (($dataTableCol = $inputWidget->getColumnByAttributeAlias($col->getAttributeAlias())) || ($dataTableCol = $inputWidget->getColumnByDataColumnName($col->getName()))) {
                        $colName = $dataTableCol->getCaption();
                    } elseif ($colAttribute = $col->getAttribute()) {
                        $colName = $colAttribute->getName();
                    } else {
                        $colName = '';
                    }
                } else {
                    $colName = $col->getName();
                }
                // Der Name muss einzigartig sein, sonst werden zu wenige Headerspalten
                // geschrieben.
                while (array_key_exists($colName, $header)) {
                    $colName = $colName . ' ';
                }
                
                // Datentyp der Spalte
                switch ($col->getDataType()->getName()) {
                    case 'Number':
                        if (substr($dataTypeSheet->getCellValue($col->getName(), 0), 0, 2) == '0x') {
                            // Hexadezimale Zahlen werden als String ausgegeben.
                            $colDataType = $this->typeMap['String'];
                        } else {
                            $colDataType = $this->typeMap[$col->getDataType()->getName()];
                        }
                        break;
                    default:
                        $colDataType = $this->typeMap[$col->getDataType()->getName()];
                }
                
                $header[$colName] = $colDataType;
                $output[] = $col->getName();
            }
        }
        
        $this->getWriter()->writeSheetHeader($this->getExcelDataSheetName(), $header);
        return $output;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeRows()
     */
    protected function writeRows(DataSheetInterface $dataSheet, array $headerKeys)
    {
        foreach ($dataSheet->getRows() as $row) {
            $rowKeys = array_keys($row);
            $outRow = [];
            foreach ($headerKeys as $key) {
                if (! (array_search($key, $rowKeys) === false)) {
                    $outRow[] = $row[$key];
                } else {
                    $outRow[] = null;
                }
            }
            if ($this->rowNumberWritten >= $this->getWriter()::EXCEL_2007_MAX_ROW) {
                throw new ActionExportDataError($this, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.EXPORTDATA.ROWOVERFLOW', array(
                    '%number%' => $this->getWriter()::EXCEL_2007_MAX_ROW
                )));
            }
            $this->getWriter()->writeSheetRow($this->getExcelDataSheetName(), $outRow);
            $this->rowNumberWritten ++;
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::writeFileResult()
     */
    protected function writeFileResult(DataSheetInterface $dataSheet)
    {
        $this->writeInfoExcelSheet($dataSheet);
        $this->getWriter()->writeToFile($this->getPathname());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportDataFile::getWriter()
     */
    protected function getWriter()
    {
        if (is_null($this->writer)) {
            $this->writer = new \XLSXWriter();
        }
        return $this->writer;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ExportData::getMimeType()
     */
    public function getMimeType()
    {
        return 'application/vnd.openxmlformats-officedocument. spreadsheetml.sheet';
    }

    /**
     * Returns the name of the excel sheet containing the data.
     * 
     * @return string
     */
    protected function getExcelDataSheetName()
    {
        return $this->excelDataSheetName;
    }

    /**
     * Returns the name of the excel sheet containing general information.
     * 
     * @return string
     */
    protected function getExcelInfoSheetName()
    {
        return $this->excelInfoSheetName;
    }

    /**
     * Write Excel Sheet2 with general information.
     * 
     * @param DataSheetInterface $dataSheet
     */
    protected function writeInfoExcelSheet(DataSheetInterface $dataSheet)
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        
        // Datentypen festlegen. Da in jeder Spalte verschiedene Datentypen vor-
        // kommem koennen werden alle verwendeten Spalten auf String gesetzt.
        $this->getWriter()->writeSheetHeader($this->getExcelInfoSheetName(), [
            $this->typeMap['String'],
            $this->typeMap['String'],
            $this->typeMap['String']
        ], true);
        
        // Benutzername
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.USERNAME'),
            $this->getWorkbench()->context()->getScopeUser()->getUserName()
        ]);
        
        // Zeitpunkt des Exports
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.TIMESTAMP'),
            date($translator->translate('DATETIME.FORMAT.SCREEN.PHP'))
        ]);
        
        // Exportiertes Objekt
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.OBJECT'),
            $dataSheet->getMetaObject()->getName() . ' (' . $dataSheet->getMetaObject()->getAliasWithNamespace() . ')'
        ]);
        
        // Verwendete Filter
        $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
            $translator->translate('ACTION.EXPORTXLSX.FILTER') . ':'
        ]);
        // Filter mit Captions von der DataTable auslesen
        $dataTableFilters = [];
        foreach ($this->getCalledByWidget()->getInputWidget()->getFilters() as $filter) {
            $dataTableFilters[$filter->getInputWidget()->getAttributeAlias()] = $filter->getInputWidget()->getCaption();
        }
        // Gesetzte Filter am DataSheet durchsuchen
        foreach ($dataSheet->getFilters()->getConditions() as $condition) {
            if (! is_null($filterValue = $condition->getValue()) && $filterValue !== '') {
                // Name
                if (array_key_exists(($filterExpression = $condition->getExpression())->toString(), $dataTableFilters)) {
                    $filterName = $dataTableFilters[$filterExpression->toString()];
                } else if ($filterExpression->isMetaAttribute()) {
                    $filterName = $dataSheet->getMetaObject()->getAttribute($filterExpression->toString())->getName();
                } else {
                    $filterName = '';
                }
                
                // Comparator
                $filterComparator = $condition->getComparator();
                if (substr($filterComparator, 0, 1) == '=') {
                    // Wird sonst vom XLSX-Writer in eine Formel umgewandelt.
                    $filterComparator = ' ' . $filterComparator;
                }
                
                // Wert, gehoert der Filter zu einer Relation soll das Label und nicht
                // die UID geschrieben werden
                if ($filterExpression->isMetaAttribute()) {
                    if (($metaAttribute = $dataSheet->getMetaObject()->getAttribute($filterExpression->toString())) && $metaAttribute->isRelation()) {
                        $relatedObject = $metaAttribute->getRelation()->getRelatedObject();
                        $filterValueRequestSheet = DataSheetFactory::createFromObject($relatedObject);
                        $filterValueRequestSheet->getColumns()->addFromAttribute($relatedObject->getUidAttribute());
                        $filterValueRequestSheet->getColumns()->addFromAttribute($relatedObject->getLabelAttribute());
                        $filterValueRequestSheet->getFilters()->addCondition(ConditionFactory::createFromExpression($this->getWorkbench(), ExpressionFactory::createFromAttribute($relatedObject->getUidAttribute()), $filterValue, $condition->getComparator()));
                        $filterValueRequestSheet->dataRead();
                        
                        if ($requestValue = implode(', ', $filterValueRequestSheet->getColumnValues($relatedObject->getLabelAttribute()->getAliasWithRelationPath()))) {
                            $filterValue = $requestValue;
                        }
                    }
                }
                
                // Zeile schreiben
                $this->getWriter()->writeSheetRow($this->getExcelInfoSheetName(), [
                    $filterName,
                    $filterComparator,
                    $filterValue
                ]);
            }
        }
    }
}
?>