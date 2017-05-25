<?php
namespace exface\Core\Interfaces;

interface DebuggerInterface
{

    /**
     * Returns a human readable representation of the exception
     *
     * @param \Throwable $exception            
     * @return string
     */
    public function printException(\Throwable $exception, $use_html = true);

    /**
     *
     * @return boolean
     */
    public function getPrettifyErrors();

    /**
     *
     * @param boolean $value            
     * @return \exface\Core\Interfaces\DebuggerInterface
     */
    public function setPrettifyErrors($value);

    /**
     * Returns a human-readable string dump of the given variable (similar to var_dump(), but returning a string)
     *
     * @param mixed $anything            
     * @return string
     */
    public function printVariable($anything, $use_html = true);
}
