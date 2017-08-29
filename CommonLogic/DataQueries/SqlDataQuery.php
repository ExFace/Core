<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\DebugMessage;

class SqlDataQuery extends AbstractDataQuery
{

    private $sql = '';

    private $result_array = null;

    private $result_resource = null;

    private $connection = null;

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
    public function toString()
    {
        return \SqlFormatter::format($this->getSql(), false);
    }

    /**
     *
     * {@inheritdoc} The SQL query creates a debug panel showing a formatted SQL statement.
     *              
     * @see \exface\Core\CommonLogic\DataQueries\AbstractDataQuery::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $page = $debug_widget->getPage();
        $sql_tab = $debug_widget->createTab();
        $sql_tab->setCaption('SQL');
        $sql_tab->setNumberOfColumns(1);
        /* @var $sql_widget \exface\Core\Widgets\Html */
        $sql_widget = WidgetFactory::create($page, 'Html', $sql_tab);
        $sql_widget->setValue('<div style="padding:10px;">' . \SqlFormatter::format($this->getSql()) . '</div>');
        $sql_widget->setWidth('100%');
        $sql_tab->addWidget($sql_widget);
        $debug_widget->addTab($sql_tab);
        return $debug_widget;
    }
}
