<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\CommonLogic\DataQueries\FileFinderDataQuery;
use Symfony\Component\Finder\SplFileInfo;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilterGroup;
use exface\Core\CommonLogic\QueryBuilder\QueryPartFilter;
use exface\Core\CommonLogic\QueryBuilder\QueryPartAttribute;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryResultDataInterface;
use exface\Core\CommonLogic\DataQueries\DataQueryResultData;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class FileFinderBuilder extends AbstractQueryBuilder
{
    /**
     *
     * @return FileFinderDataQuery
     */
    protected function buildQuery()
    {
        $query = new FileFinderDataQuery();
        
        $path_pattern = $this->buildPathPatternFromFilterGroup($this->getFilters(), $query);
        $filename = $this->buildFilenameFromFilterGroup($this->getFilters(), $query);
        
        // Setup query
        $path_pattern = $path_pattern ? $path_pattern : $this->getMainObject()->getDataAddress();
        $last_slash_pos = mb_strripos($path_pattern, '/');
        if ($last_slash_pos === false) {
            $path_relative = $path_pattern;
        } else {
            $path_relative = substr($path_pattern, 0, $last_slash_pos);
            $filename = $filename ? $filename : substr($path_pattern, ($last_slash_pos + 1));
        }
        
        if (count($this->getSorters()) > 0) {
            $query->setFullScanRequired(true);
            // All the sorting is done locally
            foreach ($this->getSorters() as $qpart) {
                $qpart->setApplyAfterReading(true);
            }
        }
        
        if (! is_null($filename) && $filename !== '') {
            $query->getFinder()->name($filename);
        }
        $query->addFolder($path_relative);
        
        return $query;
    }
    
    protected function isFilename(QueryPartAttribute $qpart) : bool
    {
        $addr = mb_strtolower($qpart->getDataAddress());
        if ($addr === 'name' || $addr === 'filename') {
            return true;
        }
        return false;
    }
    
    protected function buildFilenameFromFilterGroup(QueryPartFilterGroup $qpart, FileFinderDataQuery $query) : ?string
    {
        $values = [];
        $filtersApplied = [];
        $filename = null;
        foreach ($qpart->getFilters() as $filter) {
            if ($this->isFilename($filter)) {
                switch ($filter->getComparator()) {
                    case EXF_COMPARATOR_EQUALS:
                    case EXF_COMPARATOR_IS:
                        $mask = preg_quote($filter->getCompareValue()) . (mb_strtolower($filter->getDataAddress()) === 'filename' ? '\\.' : '');
                        if ($filter->getComparator() === EXF_COMPARATOR_EQUALS) {
                            $mask = '^' . $mask . '$';
                        }
                        $values[] = $mask;
                        $filtersApplied[] = $filter;
                        break;
                    default: // Do nothing - the filters will be applied by the query builder after reading files
                }
            }
        }
        
        $values = array_unique($values);
        
        if (! empty($values)) {
            switch ($qpart->getOperator()) {
                case EXF_LOGICAL_OR:
                    $filename = '/(' . implode('|', $values) . ')/i';
                    break;
                case EXF_LOGICAL_AND: 
                    if (count($values) === 1) {
                        $filename = '/' . $values[0] . '/i';
                        break;
                    } 
                default:
                    foreach ($filtersApplied as $filter) {
                        $filter->setApplyAfterReading(true);
                        $query->setFullScanRequired(true);
                    }
            }
        }
        
        foreach ($qpart->getNestedGroups() as $group) {
            if ($filename === null) {
                $filename = $this->buildFilenameFromFilterGroup($group, $query);
            } else {
                $group->setApplyAfterReading(true, function(QueryPartFilter $filter) {
                    return $this->isFilename($filter);
                });
                $query->setFullScanRequired(true);
            }
        }
        
        return $filename;
    }
    
    protected function buildPathPatternFromFilterGroup(QueryPartFilterGroup $qpart, FileFinderDataQuery $query) : ?string
    {
        // Look for filters, that can be processed by the connector itself
        foreach ($this->getFilters()->getFilters() as $qpart) {
            if ($qpart->getAttribute()->is($this->getMainObject()->getUidAttribute())) {
                switch ($qpart->getComparator()) {
                    case EXF_COMPARATOR_IS:
                    case EXF_COMPARATOR_EQUALS:
                        $path_pattern = Filemanager::pathNormalize($qpart->getCompareValue());
                        break;
                    case EXF_COMPARATOR_IN:
                        $values = explode($qpart->getValueListDelimiter(), $qpart->getCompareValue());
                        if (count($values) === 1) {
                            $path_pattern = Filemanager::pathNormalize($values[0]);
                            break;
                        }
                        // No "break;" here to fallback to default if none of the ifs above worked
                    default:
                        $qpart->setApplyAfterReading(true);
                        $query->setFullScanRequired(true);
                }
            } else {
                $this->addAttribute($qpart->getExpression()->toString());
                $qpart->setApplyAfterReading(true);
                $query->setFullScanRequired(true);
            }
        }
        
        return $path_pattern;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::create()
     */
    public function create(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $fileArray = $this->getValue('PATHNAME_ABSOLUTE')->getValues();
        $contentArray = $this->getValue('CONTENTS')->getValues();
        return new DataQueryResultData([], $this->write($fileArray, $contentArray));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $result_rows = array();
        $pagination_applied = false;
        // Check if force filtering is enabled
        if ($this->getMainObject()->getDataAddressProperty('force_filtering') && count($this->getFilters()->getFiltersAndNestedGroups()) < 1) {
            return false;
        }
        
        $query = $this->buildQuery();
        if ($files = $data_connection->query($query)->getFinder()) {
            $rownr = - 1;
            $totalCount = count($files);
            foreach ($files as $file) {
                // If no full scan is required, apply pagination right away, so we do not even need to reed the files not being shown
                if (! $query->getFullScanRequired()) {
                    $pagination_applied = true;
                    $rownr ++;
                    // Skip rows, that are positioned below the offset
                    if (! $query->getFullScanRequired() && $rownr < $this->getOffset())
                        continue;
                    // Skip rest if we are over the limit
                    if (! $query->getFullScanRequired() && $this->getLimit() > 0 && $rownr >= $this->getOffset() + $this->getLimit())
                        break;
                }
                // Otherwise add the file data to the result rows
                $result_rows[] = $this->buildResultRow($file, $query);
            }
            $result_rows = $this->applyFilters($result_rows);
            $result_rows = $this->applySorting($result_rows);
            if (! $pagination_applied) {
                $result_rows = $this->applyPagination($result_rows);
            }
        }
        
        if (! $totalCount) {
            $totalCount = count($result_rows);
        }
        
        $rowCount = count($result_rows);
        
        return new DataQueryResultData($result_rows, $rowCount, ($totalCount > $rowCount + $this->getOffset()), $totalCount);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::count()
     */
    public function count(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        return $this->read($data_connection);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::update()
     */
    public function update(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $updatedFileNr = 0;
        
        $query = $this->buildQuery();
        if ($files = $data_connection->query($query)->getFinder()) {
            $fileArray = iterator_to_array($files, false);
            $contentArray = $this->getValue('CONTENTS')->getValues();
            $updatedFileNr = $this->write($fileArray, $contentArray);
        }
        
        return new DataQueryResultData([], $updatedFileNr);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::delete()
     */
    public function delete(DataConnectionInterface $data_connection) : DataQueryResultDataInterface
    {
        $deletedFileNr = 0;
        
        $query = $this->buildQuery();
        if ($files = $data_connection->query($query)->getFinder()) {
            foreach ($files as $file) {
                unlink($file);
                $deletedFileNr ++;
            }
        }
        
        return new DataQueryResultData([], $deletedFileNr);
    }

    /**
     * 
     * @param string[] $fileArray
     * @param string[] $contentArray
     * @throws BehaviorRuntimeError
     * @return number
     */
    private function write($fileArray, $contentArray)
    {
        $writtenFileNr = 0;
        if (count($fileArray) !== count($contentArray)) {
            throw new BehaviorRuntimeError($this->getMainObject(), 'The number of passed files doen\'t match the number of passed file contents.');
        }
        
        for ($i = 0; $i < count($fileArray); $i ++) {
            file_put_contents($fileArray[$i], $this->getValue('CONTENTS')->getDataType()->parse($contentArray[$i]));
            $writtenFileNr ++;
        }
        
        return $writtenFileNr;
    }

    protected function buildResultRow(SplFileInfo $file, FileFinderDataQuery $query)
    {
        $row = array();
        
        $file_data = $this->getDataFromFile($file, $query);
        
        foreach ($this->getAttributes() as $qpart) {
            if ($field = strtolower($qpart->getAttribute()->getDataAddress())) {
                if (array_key_exists($field, $file_data)) {
                    $value = $file_data[$field];
                } elseif (substr($field, 0, 4) === 'line') {
                    $line_nr = intval(trim(substr($field, 4), '()'));
                    if ($line_nr === 1) {
                        $value = $file->openFile()->fgets();
                    } else {
                        // TODO
                    }
                } elseif (substr($field, 0, 7) === 'subpath') {
                    // list($start_pos, $end_pos) = explode(',', trim(substr($field, 7), '()'));
                    // TODO
                } else {
                    $method_name = 'get' . ucfirst($field);
                    if (method_exists($file, $method_name)) {
                        $value = call_user_func(array(
                            $file,
                            $method_name
                        ));
                    }
                }
                $row[$qpart->getColumnKey()] = $value;
            }
        }
        
        return $row;
    }

    protected function getDataFromFile(SplFileInfo $file, FileFinderDataQuery $query)
    {
        $base_path = $query->getBasePath() ? $query->getBasePath() . '/' : '';
        $path = Filemanager::pathNormalize($file->getPath());
        $pathname = Filemanager::pathNormalize($file->getPathname());
        
        $file_data = array(
            'name' => $file->getExtension() ? str_replace('.' . $file->getExtension(), '', $file->getFilename()) : $file->getFilename(),
            'path_relative' => $base_path ? str_replace($base_path, '', $path) : $path,
            'pathname_absolute' => $file->getRealPath(),
            'pathname_relative' => $base_path ? str_replace($base_path, '', $pathname) : $pathname,
            'mtime' => TimestampDataType::cast('@' . $file->getMTime())
        );
        
        return $file_data;
    }
    
    /**
     * The FileFinderBuilder can only handle attributes of one object - no relations (JOINs) supported!
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::canReadAttribute()
     */
    public function canReadAttribute(MetaAttributeInterface $attribute) : bool
    {
        return $attribute->getRelationPath()->isEmpty();
    }
}
?>