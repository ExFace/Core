# PHPdoc and inline comments

PHP-Docs must be written in English. Every class, method and interface MUST have a PHP-Doc block with the appropriate tags!

Each doc block must have the following strucutre with block separated by empty lines (with a space follwing the asterisk!):

```php
/**
 * One line title followed by a blank line.
 * 
 * Optional description with multiple lines or even paragraphs.
 * 
 * @tags
 */
```

A description is required for the following entities:

- Interface
- Class
- Public method

Descriptions are recommended, but not strictly required, for the following entities:

- Protected method
- Private method

Description must show the intention behind the method, object or interface. Include responsibilities, rules for extending, and any other information that is not obvious from the code itself.

#### Method annotations

When overriding a parent or interface method, use a doc block like below. Omit `@return` and other tags in this case if their values do not change. This helps the reader to quickly understand, what exactly is overridden.

```php
/**
 * {@inheritDoc}
 * @see \namespace\ParentClass::methodName()
 */
```

### Inline Comments 

Developers are free to use inline comments. Complex code sections with over 20 lines should include inline comments. The language can be freely chosen.