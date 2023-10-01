<?php
namespace exface\Core\Interfaces\Filesystem;

interface FileInfoInterface extends \Stringable
{
    const FILE = 'file';
    
    const DIR = 'dir';
    
    const LINK = 'link';
    
    /**
     * Gets the path without filename
     * 
     * @param bool $withFilename
     * @return string
     */
    public function getPath() : string;
    
    /**
     * 
     * @return string|NULL
     */
    public function getBasePath() : ?string;
    
    /**
     *
     * @return bool
     */
    public function isPathAbsolute() : bool;
    
    /**
     * 
     * @param bool $withFilename
     * @return string
     */
    public function getPathAbsolute() : string;
    
    /**
     * 
     * @param bool $withFilename
     * @return string|NULL
     */
    public function getPathRelative() : ?string;
    
    /**
     * Gets the filename
     * 
     * @param bool $withExtension
     * @return string
     */
    public function getFilename(bool $withExtension = true) : string;
    
    /**
     * Gets the file extension
     * 
     * @return string a string containing the file extension, or an
     * empty string if the file has no extension.
     */
    public function getExtension() : string;
    
    /**
     * Gets file size in bytes or NULL if it cannot be determined
     * 
     * @return int|NULL
     */
    public function getSize() : ?int;
    
    /**
     * Gets the last modified time as a Unix timestamp or NULL if not known
     * 
     * @return int|NULL
     */
    public function getMTime() : ?int;
    
    /**
     * 
     * @return \DateTimeInterface|NULL
     */
    public function getModifiedOn() : ?\DateTimeInterface;
    
    /**
     * Gets the inode change time as a Unix timestamp or NULL if not known
     * 
     * @return int|NULL
     */
    public function getCTime() : ?int;
    
    /**
     * 
     * @return \DateTimeInterface|NULL
     */
    public function getCreatedOn() : ?\DateTimeInterface;
    
    /**
     * 
     * @return string
     */
    public function getType() : string;
    
    /**
     * Tells if the entry is writable
     * 
     * @return bool true if writable, false otherwise;
     */
    public function isWritable() : bool;
    
    /**
     * Tells if file is readable
     * 
     * @return bool true if readable, false otherwise.
     */
    public function isReadable() : bool;
    
    /**
     * Tells if the object references a regular file
     * 
     * @return bool true if the file exists and is a regular file (not a link), false otherwise.
     */
    public function isFile() : bool;
    
    /**
     * Tells if the file is a directory
     * 
     * @return bool true if a directory, false otherwise.
     */
    public function isDir() : bool;
    
    /**
     * Tells if the file is a link
     * 
     * @return bool true if the file is a link, false otherwise.
     */
    public function isLink() : bool;
    
    /**
     * Returns TRUE if the file/folder currently exists
     * 
     * @return bool
     */
    public function exists() : bool;
    
    /**
     * Gets the target of the filesystem link
     * 
     * @return string|NULL
     */
    public function getLinkTarget() : ?string;
    
    /**
     * Returns the folder name
     * 
     * @return string|NULL
     */
    public function getFolderName() : ?string;
    
    /**
     * 
     * @return string|NULL
     */
    public function getFolderPath() : ?string;
    
    /**
     * 
     * @return FileInfoInterface|NULL
     */
    public function getFolderInfo() : ?FileInfoInterface;
    
    /**
     * The mode for opening the file. See the fopen documentation for descriptions of possible modes. 
     * The default is read only.
     * 
     * @param string $mode
     * @return FileInterface
     */
    public function openFile(string $mode = null) : FileInterface;
    
    /**
     *
     * @return string
     */
    public function getDirectorySeparator() : string;
    
    /**
     * 
     * @return string|NULL
     */
    public function getMimetype() : ?string;
}