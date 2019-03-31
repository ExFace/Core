<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for metamodel loader selectors.
 * 
 * A metamodel loader can be identified by 
 * - file path (absolute or relative to the vendor folder): 
 * e.g. new ModelLoaderSelector('exface/Core/Facades/HttpFileServerFacade/HttpFileServerFacade.php')
 * - qualified class name of the facade's PHP class: 
 * e.g. new ModelLoaderSelector(exface/Core/Facades/HttpFileServerFacade/HttpFileServerFacade::class)
 * 
 * @author Andrej Kabachnik
 *
 */
interface ModelLoaderSelectorInterface extends PrototypeSelectorInterface
{}