<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors for data sources defined in the metamodel.
 * 
 * A data source can be identified by
 * - a UID
 * - a qualified alias (with app namespace)
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataSourceSelectorInterface extends UidSelectorInterface, AliasSelectorInterface
{}