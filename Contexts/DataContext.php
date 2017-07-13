<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\Contexts\ContextSaveError;
use exface\Core\Exceptions\Contexts\ContextLoadError;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * The DataContext provides a unified interface to store arbitrary data in any context scope.
 * It's like storing
 * PHP variables in a specific context scope.
 *
 * To avoid name conflicts between different apps, all data is tagged with a namespace (the apps qualified alias by default)
 *
 * @author Andrej Kabachnik
 *        
 */
class DataContext extends AbstractContext
{

    private $variables = array();

    /**
     * Returns the value stored under the given name
     *
     * @param string $namespace            
     * @param string $variable_name            
     * @return mixed
     */
    public function getVariable($namespace, $variable_name)
    {
        return $this->variables[$namespace][$variable_name];
    }

    /**
     * Stores a value under the given name
     *
     * @param string $namespace            
     * @param string $variable_name            
     * @param mixed $value            
     * @return \exface\Core\Contexts\DataContext
     */
    public function setVariable($namespace, $variable_name, $value)
    {
        $this->variables[$namespace][$variable_name] = $value;
        return $this;
    }

    /**
     * Removes the given variable from the data context
     *
     * @param string $namespace            
     * @param string $variable_name            
     * @return \exface\Core\Contexts\DataContext
     */
    public function unsetVariable($namespace, $variable_name)
    {
        unset($this->variables[$namespace][$variable_name]);
        return $this;
    }

    /**
     * Removes the given variable from the data context
     *
     * @param AppInterface $app            
     * @param string $variable_name            
     * @return \exface\Core\Contexts\DataContext
     */
    public function unsetVariableForApp(AppInterface $app, $variable_name)
    {
        unset($this->variables[$app->getAliasWithNamespace()][$variable_name]);
        return $this;
    }

    /**
     *
     * @param AppInterface $app            
     * @param string $variable_name            
     * @param mixed $value            
     * @return \exface\Core\Contexts\DataContext
     */
    public function setVariableForApp(AppInterface $app, $variable_name, $value)
    {
        return $this->setVariable($app->getAliasWithNamespace(), $variable_name, $value);
    }

    /**
     *
     * @param AppInterface $app            
     * @param string $variable_name            
     * @return mixed
     */
    public function getVariableForApp(AppInterface $app, $variable_name)
    {
        return $this->getVariable($app->getAliasWithNamespace(), $variable_name);
    }

    /**
     * Returns an array with all variables from the given namespace
     *
     * @param string $namespace            
     * @return mixed[]
     */
    public function getVariablesFromNamespace($namespace)
    {
        $vars = $this->variables[$namespace];
        if (! is_array($vars)) {
            $vars = array();
        }
        return $vars;
    }

    /**
     *
     * @param AppInterface $app            
     * @return mixed[]
     */
    public function getVariablesForApp(AppInterface $app)
    {
        return $this->getVariablesFromNamespace($app->getAliasWithNamespace());
    }

    /**
     *
     * @return string[]
     */
    public function getNamespacesActive()
    {
        return array_keys($this->variables);
    }

    /**
     * The default scope of the data context is the window.
     * Most apps will run in the context of a single window,
     * so two windows running one app are independant in general.
     *
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->context()->getScopeWindow();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        foreach ($uxon as $namespace => $vars) {
            if (! $vars || count($vars) <= 0) {
                continue;
            }
            
            foreach ($vars as $variable_name => $value) {
                $this->importUxonForVariable($namespace, $variable_name, $value);
            }
        }
    }

    /**
     * The data context is exported to the following UXON structure:
     * {
     * namespace1:
     * {
     * var_name1: var_value1,
     * var_name2: var_value2,
     * },
     * namespace2: ...
     * }
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = $this->getWorkbench()->createUxonObject();
        foreach ($this->getNamespacesActive() as $namespace) {
            if (count($this->getVariablesFromNamespace($namespace)) <= 0) {
                continue;
            }
            
            $namespace_uxon = $this->getWorkbench()->createUxonObject();
            foreach ($this->getVariablesFromNamespace($namespace) as $var => $value) {
                $namespace_uxon = $this->exportUxonForVariable($namespace_uxon, $var, $value);
            }
            
            $uxon->setProperty($namespace, $namespace_uxon);
        }
        return $uxon;
    }

    protected function exportUxonForVariable(UxonObject $uxon_container, $variable_name, $variable_value)
    {
        if ($variable_value instanceof UxonObject || (! is_object($variable_value) && ! is_array($variable_value))) {
            $uxon_container->setProperty($variable_name, $variable_value);
        } elseif (is_array($variable_value)) {
            $uxon_container->setProperty($variable_name, $this->getWorkbench()->createUxonObject());
            foreach ($variable_value as $var => $value) {
                $this->exportUxonForVariable($uxon_container->getProperty($variable_name), $var, $value);
            }
        } else {
            throw new ContextSaveError($this, 'Cannot save data context in for "' . $this->getScope()->getName() . '": invalid variable value type for "' . get_class($variable_name) . '"!', '6T5E3ID');
        }
        return $uxon_container;
    }

    protected function importUxonForVariable($namespace, $variable_name, $value)
    {
        if (is_array($value) || $value instanceof \stdClass) {
            $this->setVariable($namespace, $variable_name, (array) $value);
        } elseif (! is_object($value)) {
            $this->setVariable($namespace, $variable_name, $value);
        } else {
            throw new ContextLoadError($this, 'Cannot load context data for "' . $this->getScope()->getName() . '": invalid variable value type for "' . get_class($variable_name) . '"!', '6T5E400');
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon()
    {
        return Icons::DATABASE;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getName()
     */
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DATA.NAME');
    }
}
?>