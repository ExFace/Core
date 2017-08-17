<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\NameResolverError;
use exface\Core\Factories\AbstractNameResolverFactory;

/**
 * The name resolver normalizes different forms of references to PHP classes within ExFace and resolves the corresponding class name.
 *
 * There are three types of references, the name resolver can convert to class names:
 * - The dot-notation - e.g. exface.Core.FileFinderConnector (case insensitive!)
 * - File path relative to the vendor folder - e.g. exface\Core\DataConnectors\FileFinderConnector.php (case sensitive)
 * - Qualified PHP class name (with namespace) - e.g. \exface\Core\DataConnectors\FileFinderConnector (case sensitive)
 * All the above examples will be resolved to \exface\Core\DataConnectors\FileFinderConnector
 *
 * The name resolver must know, what type of object is to be resolved: a query builder, a data connector, an app, etc. This allows
 * a nice and short dot-notation, which is used whenever the reference needs to be entered by a user. Programmatic references, on
 * the other hande, often use file paths or PHP class names, that already point to the desired class more or less directly.
 *
 * The following object types are supported:
 * - Apps - located in the root of an app folder, the file name is the alias followed by "App" - e.g. exface/Core/CoreApp.php
 * - Meta object behaviors - located in the subfolder "Behaviors" of an app, the file name matches the alias
 * - Formulas - located in the subfolder "Formulas" of an app, the file name matches the alias
 * - Data connectors - located in the subfolder "DataConnectors" of an app, the file name matches the alias
 * - Query builders - located in the subfolder "QueryBuilders" of an app, the file name matches the alias
 * - CMS connectors - located in the subfolder "CmsConnectors" of an app, the file name matches the alias
 * - Actions - located in the subfolder "Actions" of an app, the file name matches the alias
 * - Widgets - located in the subfolder "Widgets" of an app, the file name matches the alias
 * - Model loaders - located in the subfolder "ModelLoaders" of an app, the file name matches the alias
 * - Templates - located in the subfolder "Template" of an app, the file name matches the alias. Every app can currently have at most one template.
 *
 * The name resolver is used by many factories:
 *
 * @see AbstractNameResolverFactory
 *
 * @author Andrej Kabachnik
 *        
 */
interface NameResolverInterface extends ExfaceClassInterface
{

    /**
     * Creates a name resolver from a give string.
     * The string must be a reference to an entity according to the definition in
     * the interface doc.
     *
     * @param string $string            
     * @param string $object_type            
     * @param Workbench $exface            
     */
    public static function createFromString($string, $object_type, Workbench $exface);

    /**
     * Returns the object type of this name resolver (i.e.
     * one of the OBJECT_TYPE_xxx constants)
     *
     * @return string
     */
    public function getObjectType();

    /**
     * Returns the alias without the namespace (e.g.
     * FileFinderConnectcor for exface.Core.FileFinderConnector)
     *
     * @return string
     */
    public function getAlias();

    /**
     * Returns the namespace (e.g.
     * exface.Core for exface.Core.FileFinderConnector)
     *
     * @return string
     */
    public function getNamespace();

    /**
     * Returns the qualified alias (including namespace) in the default dot-notation (e.g.
     * exface.Core.FileFinderConnector)
     *
     * @return string
     */
    public function getAliasWithNamespace();

    /**
     * Returns the vendor name (e.g.
     * exface for exface.Core.FileFinderConnector)
     *
     * @return string
     */
    public function getVendor();

    /**
     * Returns the resolved class name in PSR-1 notation (e.g.
     * \exface\Core\DataConnectors\FileFinderConnector for exface.Core.FileFinderConnector)
     *
     * @return string
     */
    public function getClassNameWithNamespace();

    /**
     * Returns the PHP namespace of the resolved class (e.g.
     * \exface\Core\DataConnectors for exface.Core.FileFinderConnector)
     *
     * @return string
     */
    public function getClassNamespace();

    /**
     * Returns the path to the resolved class relative to the vendor folder (e.g.
     * \exface\Core\DataConnectors for exface.Core.FileFinderConnector)
     *
     * @return string
     */
    public function getClassDirectory();

    /**
     * Returns TRUE if a class name could be resolved and the class exists and FALSE otherwise
     *
     * @return boolean
     */
    public function classExists();

    /**
     * Validates if the name resolver can instatiate the required object and throws exceptions if not so
     *
     * @throws NameResolverError
     * @return NameResolverInterface
     */
    public function validate();
    
    /**
     * Returns the alias of the app, the named instance belongs to.
     * 
     * @return string
     */
    public function getAppAlias();
}