<?php
namespace exface\Core\Exceptions\Configuration;

use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\ExceptionTrait;

/**
 * This trait enables an exception to output configuration specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait ConfigurationExceptionTrait {
    
    use ExceptionTrait {
		createWidget as createParentWidget;
	}

    private $configuration = null;

    public function __construct(ConfigurationInterface $configuration, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setConfiguration($configuration);
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function setConfiguration(ConfigurationInterface $value)
    {
        $this->configuration = $value;
        return $this;
    }
}
?>