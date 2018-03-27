<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for metamodel loader selectors.
 * 
 * A metamodel loader can be identified by 
 * - file path (absolute or relative to the vendor folder): 
 * e.g. new ModelLoaderSelector('exface/Core/Templates/HttpFileServerTemplate/HttpFileServerTemplate.php')
 * - qualified class name of the template's PHP class: 
 * e.g. new ModelLoaderSelector(exface/Core/Templates/HttpFileServerTemplate/HttpFileServerTemplate::class)
 * 
 * @author Andrej Kabachnik
 *
 */
interface ModelLoaderSelectorInterface extends PrototypeSelectorInterface
{}