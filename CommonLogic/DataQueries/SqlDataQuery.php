<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\RegularExpressionDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Widgets\DebugMessage;

class SqlDataQuery extends AbstractDataQuery
{
    private $sql = '';
    private ?string $sqlDialect = null;
    private $pkeyCols = [];
    
    private $result_array = null;
    private $result_resource = null;
    private $result_row_counter = null;
    private $connection = null;
    
    private $multipleStatements = false;
    private $batchDelimiter = null;

    /**
     *
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     *
     * @param string $value            
     * @return \exface\Core\CommonLogic\DataQueries\SqlDataQuery
     */
    public function setSql($value)
    {
        $this->sql = $value;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getPrimaryKeyColumns() : array
    {
        return $this->pkeyCols;
    }

    /**
     * @param string[] $colNames
     * @return $this
     */
    public function setPrimaryKeyColumns(array $colNames) : SqlDataQuery
    {
        $this->pkeyCols = $colNames;
        return $this;
    }

    public function getResultArray()
    {
        if (is_null($this->result_array)) {
            return $this->getConnection()->makeArray($this);
        }
        return $this->result_array;
    }

    public function setResultArray(array $value)
    {
        $this->result_array = $value;
        return $this;
    }

    public function getResultResource()
    {
        return $this->result_resource;
    }

    public function setResultResource($value)
    {
        $this->result_resource = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::importString()
     */
    public function importString($string)
    {
        $this->setSql($string);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('sql', $this->getSql());
        return $uxon;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataQueryInterface::countAffectedRows()
     */
    public function countAffectedRows()
    {
        return $this->getConnection()->getAffectedRowsCount($this);
    }

    public function getLastInsertId()
    {
        return $this->getConnection()->getInsertId($this);
    }
    
    /**
     * 
     * @return int|NULL
     */
    public function getResultRowCounter() : ?int
    {
        return $this->result_row_counter;
    }
    
    /**
     * 
     * @param int $number
     * @return SqlDataQuery
     */
    public function setResultRowCounter(int $number) : SqlDataQuery
    {
        $this->result_row_counter = $number;
        return $this;
    }

    /**
     *
     * @return \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    public function setConnection(SqlDataConnectorInterface $value)
    {
        $this->connection = $value;
        return $this;
    }

    public function freeResult()
    {
        $this->getConnection()->freeResult($this);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::toString()
     */
    public function toString($prettify = true)
    {
        return $prettify ? \SqlFormatter::format($this->getSql(), false) : $this->getSql();
    }
    
    public function getDialect() : ?string
    {
        if ($this->sqlDialect !== null) {
            return $this->sqlDialect;
        }
        if ($this->connection !== null) {
            $this->sqlDialect = $this->getConnection()->getSqlDialect();
        }
        return $this->sqlDialect;
    }
    
    public function setDialect(string $value) : SqlDataQuery
    {
        $this->sqlDialect = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc} The SQL query creates a debug panel showing a formatted SQL statement.
     *              
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $sql_tab = $debug_widget->createTab();
        $sql_tab->setCaption('SQL');
        $sql_tab->setNumberOfColumns(1);
        /* @var $inputCode_widget \exface\Core\Widgets\InputCode */
        
        $sql = $this->getSql();
        $dialect = $this->getDialect();
        
        $inputCode_widget = WidgetFactory::createFromUxonInParent($sql_tab, new UxonObject([
            'widget_type' => 'InputCode',
            'id' => $debug_widget->getId() . '_SqlDataQuery_InputCode',
            'width' => '100%',
            'height' => '100%',
            "language" => 'sql',
            "disabled" => true,
            "code_formatter" => [
                'language' => 'sql',
                'dialect' => $dialect,
            ],
            "value" => $sql,
        ]));
        
        $sql_tab->addWidget($inputCode_widget);
        $debug_widget->addTab($sql_tab);
        return $debug_widget;
    }
    
    /**
     * 
     * @return bool
     */
    public function isMultipleStatements() : bool
    {
        return $this->multipleStatements;
    }
    
    /**
     * 
     * @param bool $value
     * @return SqlDataQuery
     */
    public function forceMultipleStatements(bool $value) : SqlDataQuery
    {
        $this->multipleStatements = $value;
        return $this;
    }
    
    /**
     * Returns TRUE if this multi-statement query needs to be split into multiple batches
     * 
     * @return bool
     */
    public function isMultipleBatches() : bool
    {
        return $this->batchDelimiter !== null && $this->isMultipleStatements() && preg_match($this->getBatchDelimiterPattern(), $this->getSql()) === 1;
    }
    
    /**
     * Splits the query into batches using the batch delimiter pattern
     * 
     * @see setBatchDelimiterPattern()
     * @return string[]
     */
    public function getSqlBatches() : array
    {
        $fullSql = $this->getSql();
        $delim = $this->getBatchDelimiterPattern();
        $batches = preg_split($delim, $fullSql);
        if ($batches === false || empty($batches)) {
            return [$fullSql];
        }
        $filtered = [];
        foreach ($batches as $sql) {
            $sql = trim($sql);
            if ($sql !== '' && $sql !== null) {
                $filtered[] = $sql;
            }
        }
        return $filtered;
    }
    
    /**
     * Returns the regex pattern to be used to split the query into multiple batches or NULL if no split needs to be done
     * 
     * @return string|NULL
     */
    public function getBatchDelimiterPattern() : ?string
    {
        return $this->batchDelimiter;
    }
    
    /**
     * Tells data connectors supporting SQL batches, how to split this query into multiple batches
     * 
     * @param string $value
     * @return SqlDataQuery
     */
    public function setBatchDelimiterPattern(string $value) : SqlDataQuery
    {
        if (! RegularExpressionDataType::isRegex($value)) {
            $value = '/' . preg_quote($value, '/') . '/';
        }
        $this->batchDelimiter = $value;
        return $this;
    }
}