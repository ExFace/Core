<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;

/**
 *
 * @author Georg Bieger
 */
class AggregatePlaceholder implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;

    private ?DataSheetInterface $dataSheet = null;

    private ?array $aggregatorFunctions = null;

    private string $prefix = "~data";

    const VAR_ALIAS = "alias";

    const VAR_PLACEHOLDER = "placeholder";

    /**
     * @return string
     */
    public function getPrefix() : string
    {
        return $this->prefix;
    }

    /**
     * Returns all supported aggregator functions.
     * Uses caching.
     *
     * @return array
     */
    public function getAggregatorFunctions() : array
    {
        if($this->aggregatorFunctions === null) {
            $this->aggregatorFunctions = AggregatorFunctionsDataType::getValuesStatic();
        }

        return $this->aggregatorFunctions;
    }

    /**
     *
     * @param DataSheetInterface $dataSheet
     * @param string $prefix
     */
    public function __construct(DataSheetInterface $dataSheet, string $prefix = "~data:")
    {
        $this->prefix = $prefix;
        $this->dataSheet = $dataSheet;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $result = [];
        $processedData = $this->filterPlaceholders($placeholders);

        if(count($processedData) === 0) {
            return $result;
        }

        $this->dataSheet->dataRead();
        $columns = $this->dataSheet->getColumns();
        foreach ($processedData as $aggregator => $data) {
            if($col = $columns->getByExpression($data[self::VAR_ALIAS])) {
                $aggregate = $col->aggregate($aggregator);
                $aggregate = str_replace(' ', '_', $aggregate);
                $aggregate = StringDataType::convertCaseUnderscoreToPascal($aggregate);
                $aggregate = str_replace([',', ';', ':'], '_', $aggregate);
                $result[$data[self::VAR_PLACEHOLDER]] = $aggregate;
            } else {
                throw new DataSheetColumnNotFoundError($this->dataSheet, "Column '{$data[self::VAR_ALIAS]}' for placeholder '{$data[self::VAR_PLACEHOLDER]}' not found in data sheet and it could not be loaded automatically.");
            }
        }

        return $result;
    }

    /**
     *
     *
     * @param array $placeholders
     * @return array
     */
    protected function filterPlaceholders(array $placeholders) : array
    {
        $result = array();
        $prefix = $this->getPrefix();

        foreach ($placeholders as $placeholder) {
            if(StringDataType::startsWith($placeholder, $prefix)) {
                $args = explode(':', $this->stripPrefix($placeholder, $prefix));

                // No aggregator found, means we got nothing to do. Continue with next placeholder.
                if(count($args) === 1) {
                    continue;
                }

                // Try to read aggregator.
                $success = false;
                foreach ($this->getAggregatorFunctions() as $aggregator) {
                    if($args[1] === $aggregator) {
                        $result[$aggregator][self::VAR_ALIAS] = $args[0];
                        $result[$aggregator][self::VAR_PLACEHOLDER] = $placeholder;
                        $success = true;
                        break;
                    }
                }

                // Aggregator argument found, but could not match any aggregator function to it.
                if(!$success) {
                    throw new InvalidArgumentException("Cannot resolve placeholder '{$placeholder}' because '{$args[1]}' is not a supported aggregator.");
                }
            }
        }

        return $result;
    }
}