<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataConnectors\MySqlConnector;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Widgets\DebugMessage;

/**
 * Exception thrown when a MySQL error occurs.
 *
 * This exception wraps driver specific details (SQLSTATE, error code and message)
 * and provides helper methods to resolve affected metaobjects and attributes.
 *
 * @author Andrej Kabachnik
 */
class MySqlError extends RuntimeException implements DataConnectorExceptionInterface
{
    public const SQL_ERROR_DUPLICATE_ENTRY = 1062;
    public const SQL_ERROR_ROW_IS_REFERENCED = 1451;
    public const SQL_ERROR_NO_REFERENCED_ROW = 1452;
    public const SQL_ERROR_NOT_NULL_VIOLATION = 1048;

    private MySqlConnector $connector;

    private ?string $sqlState = null;

    private ?int $sqlErrorCode = null;

    private ?string $sqlErrorMessage = null;

    private ?MetaObjectInterface $obj = null;

    private ?MetaObjectInterface $otherObj = null;

    private bool $otherObjResolved = false;

    /**
     * @param MySqlConnector $connector
     * @param string $message
     * @param string|null $alias
     * @param \Exception|null $previous
     */
    public function __construct(MySqlConnector $connector, string $message, ?string $alias = null, ?\Exception $previous = null)
    {
        $this->connector = $connector;

        if ($previous !== null) {
            $this->sqlErrorCode = (int) $previous->getCode();
            $this->sqlErrorMessage = $previous->getMessage();
            if (method_exists($previous, 'getSqlState')) {
                $this->sqlState = (string) $previous->getSqlState();
            }
        }

        parent::__construct($this->sqlErrorMessage ?? $message, $alias, $previous);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\ExceptionTrait::getDefaultLogLevel()
     */
    public function getDefaultLogLevel()
    {
        return LoggerInterface::CRITICAL;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Exceptions\ExceptionTrait::getDefaultAlias()
     */
    public function getDefaultAlias()
    {
        return '6T2T2UI';
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugMessage)
    {
        $debugMessage = parent::createDebugWidget($debugMessage);

        $tab = $debugMessage->createTab();
        $tab->setCaption('MySQL');
        $debugMessage->addTab($tab);

        $rows = [
            'SQLSTATE' => $this->getSqlState(),
            'Error code' => $this->getSqlErrorCode(),
            'Driver message' => $this->getSqlErrorMessage(),
            'Resolved table' => $this->getAffectedTableName(),
            'Referenced table' => $this->getOtherAffectedTableName(),
        ];

        $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'Markdown',
            'width' => 'max',
            'height' => '100%',
            'hide_caption' => true,
            'value' => MarkdownDataType::buildMarkdownTableFromPropertySet($rows, 'Field', 'Value'),
        ])));

        return $debugMessage;
    }

    /**
     * @return string|null
     */
    public function getSqlState() : ?string
    {
        return $this->sqlState;
    }

    /**
     * @return int|null
     */
    public function getSqlErrorCode() : ?int
    {
        return $this->sqlErrorCode;
    }

    /**
     * @return string|null
     */
    public function getSqlErrorMessage() : ?string
    {
        return $this->sqlErrorMessage;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\DataConnectorExceptionInterface::getConnector()
     */
    public function getConnector() : DataConnectionInterface
    {
        return $this->connector;
    }

    /**
     * Returns the table address involved in the error if it can be parsed.
     *
     * @return string|null
     */
    public function getAffectedTableName() : ?string
    {
        $msg = $this->getSqlErrorMessage() ?? $this->getMessage();
        if (! is_string($msg) || trim($msg) === '') {
            return null;
        }

        if (preg_match('/foreign key constraint fails \(`([^`]+)`\.`([^`]+)`/i', $msg, $m)) {
            return $m[1] . '.' . $m[2];
        }

        if (preg_match('/for key\s+\'([^\']+)\'/i', $msg, $m)) {
            $keyName = $m[1];
            $parts = explode('.', $keyName);
            if (count($parts) >= 2) {
                $table = $parts[count($parts) - 2];
                $db = $parts[count($parts) - 3] ?? null;
                return $db ? ($db . '.' . $table) : $table;
            }
        }

        return null;
    }

    /**
     * Returns the table referenced by a foreign key if available.
     *
     * @return string|null
     */
    protected function getOtherAffectedTableName() : ?string
    {
        $msg = $this->getSqlErrorMessage() ?? $this->getMessage();
        if (! is_string($msg) || trim($msg) === '') {
            return null;
        }

        if (preg_match('/REFERENCES\s+`([^`]+)`\.`([^`]+)`/i', $msg, $m)) {
            return $m[1] . '.' . $m[2];
        }

        return null;
    }

    /**
     * @return MetaObjectInterface|null
     */
    public function getAffectedObject() : ?MetaObjectInterface
    {
        if ($this->obj === null && null !== $address = $this->getAffectedTableName()) {
            $this->obj = $this->findObjectByDataAddress($address);
            if ($this->obj === null && strpos($address, '.') !== false) {
                $parts = explode('.', $address);
                $this->obj = $this->findObjectByDataAddress((string) end($parts));
            }
        }
        return $this->obj;
    }

    /**
     * @return MetaObjectInterface|null
     */
    public function getOtherAffectedObject() : ?MetaObjectInterface
    {
        if ($this->otherObjResolved) {
            return $this->otherObj;
        }

        $this->otherObjResolved = true;
        $address = $this->getOtherAffectedTableName();
        if ($address === null) {
            return null;
        }

        $this->otherObj = $this->findObjectByDataAddress($address);
        if ($this->otherObj === null && strpos($address, '.') !== false) {
            $parts = explode('.', $address);
            $this->otherObj = $this->findObjectByDataAddress((string) end($parts));
        }

        return $this->otherObj;
    }

    /**
     * Returns column addresses involved in the error with values if available.
     *
     * For MySQL, values are typically not available for FK violations, so null is used.
     *
     * @return array<string, string|null>
     */
    public function getAffectedColumnValues() : array
    {
        $msg = $this->getSqlErrorMessage() ?? $this->getMessage();
        if (! is_string($msg) || trim($msg) === '') {
            return [];
        }

        if (preg_match('/FOREIGN KEY\s*\(([^\)]+)\)/i', $msg, $m)) {
            $cols = array_map('trim', explode(',', $m[1]));
            $out = [];
            foreach ($cols as $col) {
                $col = trim($col, "` \t\n\r\0\x0B");
                if ($col !== '') {
                    $out[$col] = null;
                }
            }
            return $out;
        }

        return [];
    }

    /**
     * Returns attribute aliases and values involved in this error if resolvable.
     *
     * @return array<string, string|null>
     */
    public function getAffectedAttributeValues() : array
    {
        $attrAddresses = $this->getAffectedColumnValues();
        $obj = $this->getAffectedObject();

        if ($obj === null || empty($attrAddresses)) {
            return [];
        }

        $attrs = [];
        foreach ($attrAddresses as $address => $value) {
            foreach ($obj->getAttributes() as $attr) {
                if ($attr->getDataAddress() === $address) {
                    $attrs[$attr->getAlias()] = $value;
                    break;
                }
            }
        }

        return $attrs;
    }

    /**
     * @param string $address
     * @return MetaObjectInterface|null
     */
    protected function findObjectByDataAddress(string $address) : ?MetaObjectInterface
    {
        $found = null;

        try {
            $objSheet = DataSheetFactory::createFromObjectIdOrAlias(
                $this->getConnector()->getWorkbench(),
                'exface.Core.OBJECT'
            );

            $aliasCol = $objSheet->getColumns()->addFromExpression('ALIAS_WITH_NS');
            $objSheet->getFilters()->addConditionFromString(
                'DATA_ADDRESS',
                $address,
                ComparatorDataType::EQUALS
            );
            $objSheet->dataRead();

            foreach ($aliasCol->getValues() as $alias) {
                $obj = MetaObjectFactory::createFromString(
                    $this->getConnector()->getWorkbench(),
                    $alias
                );

                if ($obj->getDataConnection() === $this->getConnector()) {
                    if ($found !== null) {
                        switch (true) {
                            case $found->isExtendedFrom($obj):
                            case ! $found->isWritable() && $obj->isWritable():
                                break;
                            default:
                                continue 2;
                        }
                    }
                    $found = $obj;
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return $found;
    }
}
