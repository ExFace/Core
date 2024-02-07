<?php
namespace exface\Core\Interfaces\DataSources;

use exface\Core\Interfaces\Filesystem\FileInfoInterface;

/**
 * 
 * @author Andrej Kabachnik
 *        
 */
interface FileDataQueryInterface extends DataQueryInterface
{
    /**
     * 
     * @return FileInfoInterface[]
     */
    public function getFiles() : iterable;   
    
    /**
     * 
     * @return string|NULL
     */
    public function getBasePath() : ?string;
    
    /**
     *
     * @return string
     */
    public function getDirectorySeparator() : string;
}