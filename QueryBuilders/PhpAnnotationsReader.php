<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\CommonLogic\DataQueries\PhpAnnotationsDataQuery;
use Wingu\OctopusCore\Reflection\ReflectionMethod;
use Wingu\OctopusCore\Reflection\ReflectionClass;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Exceptions\QueryBuilderException;
use Wingu\OctopusCore\Reflection\ReflectionDocComment;
use exface\Core\Exceptions\DataSources\DataQueryFailedError;

/**
 * A query builder to read annotations for PHP classes, their methods and properties.
 * Reads general comments and any specified annotation tags.
 *
 * @uxon-config {
 * "annotation_level": "class|method|property",
 * "ignore_comments_without_matching_tags": false
 * }
 *
 * @author Andrej Kabachnik
 *        
 */
class PhpAnnotationsReader extends AbstractQueryBuilder
{

    const ANNOTATION_LEVEL_METHOD = 'method';

    const ANNOTATION_LEVEL_CLASS = 'class';

    const ANNOTATION_LEVEL_PROPERTY = 'property';

    private $result_rows = array();

    private $result_totals = array();

    private $result_total_rows = 0;

    private $last_query = null;

    /**
     *
     * @return PhpAnnotationsDataQuery
     */
    protected function buildQuery()
    {
        $query = new PhpAnnotationsDataQuery();
        $query->setBasePath($this->getWorkbench()->filemanager()->getPathToVendorFolder());
        
        // Look for filters, that can be processed by the connector itself
        foreach ($this->getFilters()->getFilters() as $qpart) {
            switch (mb_strtolower($qpart->getAttribute()->getDataAddress())) {
                case 'filename-relative':
                    $query->setPathRelative($qpart->getCompareValue());
                    break;
                case 'filename':
                    $query->setPathAbsolute($qpart->getCompareValue());
                    break;
                case 'class':
                    $query->setClassNameWithNamespace($qpart->getCompareValue());
                    break;
                case 'fqsen':
                    $class_name = substr($qpart->getCompareValue(), 0, strpos($qpart->getCompareValue(), '::'));
                    if (strpos($class_name, '\\') !== 0) {
                        $class_name = '\\' . $class_name;
                    }
                    $query->setClassNameWithNamespace($class_name);
                // No break; here because we only use the beginning of the value, so the part after :: should be filtered after reading
                default:
                    $qpart->setApplyAfterReading(true);
            }
        }
        
        // All the sorting must be done locally
        foreach ($this->getSorters() as $qpart) {
            $qpart->setApplyAfterReading(true);
        }
        
        return $query;
    }

    function getResultRows()
    {
        return $this->result_rows;
    }

    function getResultTotals()
    {
        return $this->result_totals;
    }

    function getResultTotalRows()
    {
        return $this->result_total_rows;
    }

    function setResultRows(array $array)
    {
        $this->result_rows = $array;
        return $this;
    }

    function setResultTotals(array $array)
    {
        $this->result_totals = $array;
        return $this;
    }

    function setResultTotalRows($value)
    {
        $this->result_total_rows = $value;
        return $this;
    }

    protected function getAnnotationLevel()
    {
        return $this->getMainObject()->getDataAddressProperty('annotation_level');
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(AbstractDataConnector $data_connection = null)
    {
        $result_rows = array();
        $annotation_level = $this->getAnnotationLevel();
        
        // Check if force filtering is enabled
        if (count($this->getFilters()->getFiltersAndNestedGroups()) < 1) {
            return false;
        }
        
        $query = $data_connection->query($this->buildQuery());
        $this->setLastQuery($query);
        /* @var $class \Wingu\OctopusCore\Reflection\ReflectionClass */
        if ($class = $query->getReflectionClass()) {
            // Read class annotations
            if (! $annotation_level || $annotation_level == $this::ANNOTATION_LEVEL_CLASS) {
                $row = $this->buildRowFromClass($class, array());
                if (count($row) > 0) {
                    $result_rows[] = $row;
                }
            }
            
            // Read method annotations
            if (! $annotation_level || $annotation_level == $this::ANNOTATION_LEVEL_METHOD) {
                foreach ($class->getMethods() as $method) {
                    $row = $this->buildRowFromMethod($class, $method, array());
                    if (count($row) > 0) {
                        $result_rows[] = $row;
                    }
                }
            }
            
            // Read property annotations
            if (! $annotation_level || $annotation_level == $this::ANNOTATION_LEVEL_PROPERTY) {
                if ($annotation_level == $this::ANNOTATION_LEVEL_PROPERTY) {
                    throw new QueryBuilderException('Annotations on property level are currently not supported in "' . get_class($this) . '"');
                }
            }
            
            $result_rows = $this->applyFilters($result_rows);
            $this->result_total_rows = count($result_rows);
            $result_rows = $this->applySorting($result_rows);
            $result_rows = $this->applyPagination($result_rows);
        }
        
        if (! $this->getResultTotalRows()) {
            $this->setResultTotalRows(count($result_rows));
        }
        
        $this->setResultRows($result_rows);
        return $this->getResultTotalRows();
    }

    /**
     *
     * @param \ReflectionClass $class            
     * @param array $row            
     * @return string
     */
    protected function buildRowFromClass(\ReflectionClass $class, array $row)
    {
        $file_pathname_absolute = $this->getFilePathnameAbsolute($class);
        $file_pathname_relative = $this->getFilePathnameRelative($class);
        
        foreach ($this->getAttributesMissingInRow($row) as $qpart) {
            if (! $qpart->getDataAddress())
                continue;
            if (! array_key_exists($qpart->getAlias(), $row)) {
                // First fill in the fields, any annotation row will need to know about it's class
                switch ($qpart->getDataAddress()) {
                    case 'class':
                        $row[$qpart->getAlias()] = $class->getName();
                        break;
                    case 'namespace':
                        $row[$qpart->getAlias()] = $class->getNamespaceName();
                        break;
                    case 'filename':
                        $row[$qpart->getAlias()] = $file_pathname_absolute;
                        break;
                    case 'filename-relative':
                        $row[$qpart->getAlias()] = $file_pathname_relative;
                        break;
                }
                
                // If we are specificlally interesten in the class annotations, search for fields
                // in the class comment specifically
                if ($this->getAnnotationLevel() == $this::ANNOTATION_LEVEL_CLASS) {
                    if ($comment = $class->getReflectionDocComment("\n\r\0\x0B")) {
                        $row = $this->buildRowFromCommentTags($class, $comment, $row);
                        $row = $this->buildRowFromComment($class, $comment, $row);
                    }
                    // Add the FQSEN (Fully Qualified Structural Element Name) if we are on class level
                    foreach ($this->getAttributesMissingInRow($row) as $qpart) {
                        if (strcasecmp($qpart->getDataAddress(), 'FQSEN') === 0) {
                            $row[$qpart->getAlias()] = $class->getName();
                        }
                    }
                }
            }
        }
        return $row;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @param ReflectionDocComment $comment            
     * @param unknown $row            
     * @return string
     */
    protected function buildRowFromCommentTags(ReflectionClass $class, ReflectionDocComment $comment, $row)
    {
        // Loop through all attributes to find exactly matching annotations
        foreach ($this->getAttributesMissingInRow($row) as $qpart) {
            // Only process attributes with data addresses
            if (! $qpart->getDataAddress())
                continue;
            // Do not overwrite already existent values (could happen when processing a parent class)
            if (array_key_exists($qpart->getAlias(), $row))
                continue;
            
            // First look through the real tags for exact matches
            try {
                foreach ($comment->getAnnotationsCollection()->getAnnotations() as $tag) {
                    if ($tag->getTagName() == $qpart->getDataAddress()) {
                        $row[$qpart->getAlias()] = $tag->getDescription();
                        break;
                    }
                }
            } catch (\Exception $e) {
                throw new DataQueryFailedError($this->getLastQuery(), 'Cannot read annotation "' . $comment->getOriginalDocBlock() . '": ' . $e->getMessage(), null, $e);
            } catch (\ErrorException $e) {
                throw new DataQueryFailedError($this->getLastQuery(), 'Cannot read annotation "' . $comment->getOriginalDocBlock() . '": ' . $e->getMessage(), null, $e);
            }
        }
        return $row;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @param ReflectionMethod $method            
     * @param array $row            
     * @return string
     */
    protected function buildRowFromMethod(ReflectionClass $class, ReflectionMethod $method, array $row)
    {
        // First look for exact matches among the tags within the comment
        $comment = $method->getReflectionDocComment("\n\r\0\x0B");
        $row = $this->buildRowFromCommentTags($class, $comment, $row);
        
        // If at least one exact match was found, this method is a valid row.
        // Now add enrich the row with general comment fields (description, etc.) and fields from the class level
        if (! $this->getIgnoreCommentsWithoutMatchingTags() || count($row) > 0) {
            $row = $this->buildRowFromClass($class, $row);
            $row = $this->buildRowFromComment($class, $comment, $row);
            // Add the FQSEN (Fully Qualified Structural Element Name) if we are on method level
            foreach ($this->getAttributesMissingInRow($row) as $qpart) {
                if (strcasecmp($qpart->getDataAddress(), 'fqsen') === 0) {
                    $row[$qpart->getAlias()] = $class->getName() . '::' . $method->getName() . '()';
                }
            }
        }
        
        return $row;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @param ReflectionDocComment $comment            
     * @param array $row            
     * @return string
     */
    protected function buildRowFromComment(ReflectionClass $class, ReflectionDocComment $comment, array $row)
    {
        foreach ($this->getAttributesMissingInRow($row) as $qpart) {
            if (! array_key_exists($qpart->getAlias(), $row)) {
                switch ($qpart->getDataAddress()) {
                    case 'desc':
                        $row[$qpart->getAlias()] = $this->prepareCommentText($comment->getFullDescription());
                        break;
                    case 'desc-short':
                        $row[$qpart->getAlias()] = $this->prepareCommentText($comment->getShortDescription());
                        break;
                    case 'desc-long':
                        $row[$qpart->getAlias()] = $this->prepareCommentText($comment->getLongDescription());
                        break;
                }
            }
        }
        return $row;
    }

    /**
     * Removes single line breaks while leaving empty lines.
     *
     * @param string $string            
     * @return string
     */
    protected function prepareCommentText($string)
    {
        $string = preg_replace('/([^\r\n])\R([^{}\s\r\n#=-])/', '$1$2', $string);
        return $string;
    }

    /**
     *
     * @param array $row            
     * @return \exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute[]
     */
    protected function getAttributesMissingInRow(array $row)
    {
        $result = array();
        foreach ($this->getAttributes() as $qpart) {
            // Only process attributes with data addresses
            if (! $qpart->getDataAddress())
                continue;
            // Do not overwrite already existent values (could happen when processing a parent class)
            if (array_key_exists($qpart->getAlias(), $row))
                continue;
            // Otherwise add the query part to the resulting array
            $result[] = $qpart;
        }
        return $result;
    }

    /**
     *
     * @param ReflectionClass $class            
     * @return string
     */
    protected function getFilePathnameRelative(ReflectionClass $class)
    {
        return Filemanager::pathNormalize(str_replace($this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR, '', $class->getFileName()));
    }

    /**
     *
     * @param ReflectionClass $class            
     * @return string
     */
    protected function getFilePathnameAbsolute(ReflectionClass $class)
    {
        return Filemanager::pathNormalize($class->getFileName());
    }

    /**
     *
     * @return boolean
     */
    protected function getIgnoreCommentsWithoutMatchingTags()
    {
        return $this->getMainObject()->getDataAddressProperty('ignore_comments_without_matching_tags') ? true : false;
    }

    protected function getLastQuery()
    {
        return $this->last_query;
    }

    protected function setLastQuery(PhpAnnotationsDataQuery $value)
    {
        $this->last_query = $value;
        return $this;
    }
}
?>