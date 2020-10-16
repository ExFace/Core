<?php
namespace exface\Core\DataConnectors;

/**
 * This connector represents whatever data connection is configured for the model loader.
 * 
 * The connection cannot be configured in the model. It's configuration is located in
 * the system config (`System.config.json`) in `METAMODEL.CONNECTOR` and 
 * `METAMODEL.CONNECTOR_CONFIG`. For security reasons it's configuration is
 * only visible in the config files, not in the model editor.
 *
 * @author Andrej Kabachnik
 *        
 */
class ModelLoaderConnector extends TransparentConnector
{}