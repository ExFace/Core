<?php
namespace exface\Core\QueryBuilders;

/**
 * This query builder represents whatever query builder is configured for the model data source.
 * 
 * Which query builder it actually represents is determined by the configuration in
 * the system config (`System.config.json`) in `METAMODEL.QUERY_BUILDER`. 
 * 
 * @author Andrej Kabachnik
 *        
 */
class ModelLoaderQueryBuilder extends DummyQueryBuilder
{}