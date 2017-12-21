<?php
namespace exface\Core\DataConnectors;

use exface\Core\ModelBuilders\SapHanaSqlModelBuilder;

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
     * @see \exface\Core\DataConnectors\AbstractSqlConnector::getModelBuilder()
     */
    public function getModelBuilder()
    {
        return new SapHanaSqlModelBuilder($this);
    }
}
?>