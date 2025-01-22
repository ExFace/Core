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
    public static function printException(\Throwable $exception, $use_html = true);

    /**
     * Returns a human-readable string dump of the given variable (similar to var_dump(), but returning a string)
     *
     * @param mixed $anything
     * @param boolean $use_html
     * @param integer $expand_depth            
     * @return string
     */
    public static function printVariable($anything, $use_html = true, $expand_depth = 1);

    /**
     * Returns the timestamp of workbench initialization (request start time) in milliseconds from 01.01.1970
     * 
     * @return int
     */
    public function getTimeMsOfWorkebnchStart() : float;

    /**
     * Returns the current timestamp in milliseconds from 01.01.1970
     * @return int
     */
    public static function getTimeMsNow() : float;

    /**
     * Returns the time elapsed from workbench start in milliseconds
     * @return float
     */
    public function getTimeMsFromStart() : float;
}
