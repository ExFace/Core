<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * 
 * @author andrej.kabachnik
 *
 */
interface MessageInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    /**
     * @return string
     */
    public function getCode() : string;

    /**
     * @param string|null $default
     * @return string
     */
    public function getType(?string $default = null) : string;

    /**
     * @param string $value
     * @return MessageInterface
     */
    public function setType(string $value) : MessageInterface;

    /**
     * @return string
     */
    public function getTitle() : string;

    /**
     * @param string $value
     * @return MessageInterface
     */
    public function setTitle(string $value) : MessageInterface;

    /**
     * @return string|null
     */
    public function getHint() : ?string;

    /**
     * @param string $value
     * @return MessageInterface
     */
    public function setHint(string $value) : MessageInterface;

    /**
     * @return string|null
     */
    public function getDescription() : ?string;

    /**
     * @param string $markdown
     * @return MessageInterface
     */
    public function setDescription(string $markdown) : MessageInterface;

    /**
     * @return string|null
     */
    public function getDocsPath() : ?string;

    /**
     * @param string $value
     * @return MessageInterface
     */
    public function setDocsPath(string $value) : MessageInterface;

    /**
     * @return AppSelectorInterface|null
     */
    public function getAppSelector() : ?AppSelectorInterface;

    /**
     * @param $stringOrSelector
     * @return MessageInterface
     */
    public function setAppSelector($stringOrSelector) : MessageInterface;

    /**
     * @return AppInterface|null
     */
    public function getApp() : ?AppInterface;
}