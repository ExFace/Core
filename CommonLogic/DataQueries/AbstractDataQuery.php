<?php
namespace exface\Core\CommonLogic\DataQueries;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;

/**
 * This is the base class for data queries.
 * It includes a default UXON importer.
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractDataQuery implements DataQueryInterface
{
    use ImportUxonObjectTrait;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToString::exportString()
     */
    public function exportString()
    {
        return $this->exportUxonObject()->toJson(true);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToString::importString()
     */
    public function importString($string)
    {}

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataQueryInterface::countAffectedRows()
     */
    public function countAffectedRows()
    {
        return 0;
    }

    /**
     * Returns a human-redable description of the data query.
     *
     * By default it is the corresponding JSON-export of it's UXON-representation, but it is advisable to override this method
     * to print the actual queries in a format that can be used to reproduce the query with another tool: e.g. SQL-based queries
     * should print the SQL (so it can be run through a regular SQL front-end), URL-based queries should print the ready-made
     * URL, and so on.
     *
     * @see \exface\Core\Interfaces\iCanBePrinted::toString()
     */
    public function toString()
    {
        return $this->exportUxonObject()->toJson(true);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        return $debug_widget;
    }
}