# PHPdoc and inline comments

PHP-Docs must be written in English. Every class, method and interface MUST have a PHP-Doc block with the appropriate tags!

Each docblock must have the following structure separated by empty lines (with a space following the asterisk!):

```php
/**
 * One line title followed by a blank line.
 * 
 * Optional description with multiple lines or paragraphs and markdown formatting.
 * 
 * @tags
 */
```

A description is required for the following entities:

- Interface
- Class
- Method (unless it is really trivial)

Description must show the intention behind the method, object or interface. Include responsibilities, rules for extending, and any other information that is not obvious from the code itself.

### Interface annotations

The class-level docblock of an interface must explain the responsibilities of classes implementing this interface - the role and meaning of the interface for the application.

### Class annotations

Include `@author` tag with the names of major contributors - this helps identify people to talk to if changes are needed in future.

### Method annotations

Every method MUST have a docblock with `@param`, `@return` and `@throws` tags if applicable.

```php
/**
 * One-line summary
 * 
 * Multi-line description with markdown formatting if appropriate
 * 
 * @param - if arguments exists
 * @throws - if an exception is thrown
 * @return - always!
 */
```

When overriding a parent or interface method, use a doc block like below. Omit `@return` and other tags in this case if their values do not change. This helps the reader to quickly understand, what exactly is overridden.

```php
/**
 * {@inheritDoc}
 * @see \namespace\ParentClass::methodName()
 */
```

### Inline Comments 

Developers are encouraged to use inline comments in english. 

- Complex code sections with over 20 lines should include inline comments.
- Passages with multiple `if` or `switch` statements MUST have comments explaining each decision.