<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors for data connections from the metamodel.
 * 
 * A data connection is basically a specifically configured connector. E.g. a
 * MySqlConnector with the corresponding configuration for a specific host
 * and dabase, would be a connection. Connections are stored in the metamodel
 * while connectors (their prototypes) are PHP classes.
 * 
 * A connection can be identified by
 * - a qualified alias (with namespace if the connectino is part of an app)
 * - a UID
 * 
 * @see DataConnectorSelectorInterface for selectors of the connection prototypes
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataConnectionSelectorInterface extends AliasSelectorWithOptionalNameSpaceInterface, UidSelectorInterface
{}