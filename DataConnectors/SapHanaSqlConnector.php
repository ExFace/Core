<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\Modelizers\SapHanaSqlModelizer;

/**
 * SQL connector for SAP HANA based on ODBC
 *
 * @author Andrej Kabachnik
 */
class SapHanaSqlConnector extends OdbcSqlConnector
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getModelizer()
     */
    public function getModelizer()
    {
        return new SapHanaSqlModelizer($this);
    }
}
?>