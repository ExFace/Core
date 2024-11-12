# Using traits

Traits are not evil! They help prevent code duplication and do not harm maintainability if used carefully. They are just a way to copy-paste code without actually duplicating it. Here are a couple of rules to remember, when using traits.

## When to use a trait

If you find yourself copy-pasting entire methods between at least two classes, that have similar objectives: e.g. two widgets, two query builders, etc.

If you have behavioral interfaces (like the widget-interface "iHaveButtons"), it is a good idea to provide a common implementation of the behavior in form of an equally named trait (i.e. "iHaveButtonsTrait"). Just remember not to override other methods (not part of the interface) within your trait - see avoid-overriding-methods-rule below.

## When NOT to use a trait

If you are copy-pasting between packages that do not really depend on eachother, using a trait in one of those packages would introduce an unneeded dependency.

If you are copy-pasting methods between classes, that have different objectives. In this case, it is better to use composition and introduce a third class - perhaps a utility class for a certain task (e.g. ArchiveWrapper, Filemanager, etc.). If the copied methods are specific to a data type (e.g. string manipulation or similar) - place them in a DataType prototype class.

## Creating good traits

Here are a couple of rules, that help create usefull and maintainable traits.

### Avoid overriding methods of a parent class inside a trait, that is intended for it's child. 

Instead, provide new methods and call them in the child class, overriding parent methods there explicitly. This makes sure, it is easy to see, which methods are actually overridden. It also makes possible to change the method signature in the a specific implementation without having to modify traits (that might override that method).

Examples:

- There are many traits for javascript implementations of widgets in the AbstractAjaxFacade. Most of them will probably get used in classes inheriting from the AbstractJqueryElement. These traits should not override any methods of the AbstractcJqueryElement like `buildHtml()`. If the trait can provide a common implementation, place it in a separate method like `JqueryContainerTrait::buildHtmlForChildren()` and call the method from the classes that need it:

```php

class lteContainer extends lteAbstractElement
{
    use JqueryContainerTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildHtml()
     */
    public function buildHtml()
    {
        return $this->buildHtmlForChildren();
    }
}
```
