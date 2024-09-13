# Formula syntax

Many properties of widgets, behaviors and actions support formulas similarly to Microsoft Excel. A formula MUST start with `=` and may contain other formulas, model aliases, scalar values and mathematical/logical operators.

Each formula has its own set of arguments. Some of them are mandatory, others are optional or may have default values.

Formulas are resolved in the back-end. This means, the values of a formula, that you see in the UI actually date back to the time where the formula was evaluated: e.g. the values for a calculated data column are evaluated when the data is read from the data source.

## Using formulas

To use a formula, simply start your value with `=` and use at least one formula after it. 

**NOTE:** for a pure math calculation without the use of named formula, use `=Calc()`: e.g. `=1+2` will not be evaluated as a formula, but `=Calc(1+2)` will work. This is a side-effect of having to distinguish between formulas and [widget links](Expressions_and_formulas.md).

## Static and data-driven formulas

- static formulas can be resolved at any time - e.g. when a widget is rendered, etc. 
- data-driven formulas are resolved by the workbench when data is read - i.e. when reading input data for actions or mappers, on widget prefill, etc.

## Scalar values

Formulas support numeric values and strings. Strings must be quoted with `"` or `'`.

## Model aliases

You can use attribute aliases in formulas directly without any explicit quotation: e.g. `=Date(CREATED_ON)` or `=calc(SOME_ATTR:MIN - SOME_ATTR:MAX)`.

**NOTE:** Using at least one alias makes your formula non-static though. You can only use attribute aliases when data is available.

## Placeholders

Some formulas also support placeholders in their string arguments. Additionally there is the `=ReplacePlaceholders()` formula, that allows to use placeholders virtually anywhere! Note, that using placeholders will most likely make any formula non-static.

## Operators

Mathematic calculations in formulas support many operators.

### Arithmetic Operators

- `+` (addition)
- `-` (subtraction)
- `*` (multiplication)
- `/` (division)
- `%` (modulus)
- `**` (pow)

For example: `=Calc((ATTR1 + ATTR2) / 2)`

This will yield `4` if `ATTR1` is `5` and `ATTR2` is `3` in the selected input data row.

### Bitwise Operators

- `&` (and)
- `|` (or)
- `^` (xor)

### Comparison Operators

- `==` (equal)
- `===` (identical)
- `!=` (not equal)
- `!==` (not identical)
- `<` (less than)
- `>` (greater than)
- `<=` (less than or equal to)
- `>=` (greater than or equal to)
- `matches` (regex match)
- `contains`
- `starts with`
- `ends with`

### Logical Operators

- `not` or `!`
- `and` or `&&`
- `or` or `||`

For example: `= Calc(ATTR1 < ATTR2 or ATTR1 < ATTR3)`

### String Operators

- `~` (concatenation)

For example: `=Calc(FIRSTNAME~" "~LASTNAME')`

### Numeric Operators

- `..` (range)

For example: `=Calc(AGE in 18..45)`. This will evaluate to true, because user.age is in the range from 18 to 45.

### Ternary Operators

- `foo ? 'yes' : 'no'`
- `foo ?: 'no'` (equal to foo ? foo : 'no')
- `foo ? 'yes'` (equal to foo ? 'yes' : '')

### Other Operators

- `?.` (null-safe operator)
- `??` (null-coalescing operator)

## Operators Precedence

Operator precedence determines the order in which operations are processed in an expression. For example, the result of the expression `1 + 2 * 4` is `9` and not `12` because the multiplication operator (`*`) takes precedence over the addition operator (`+`).

To avoid ambiguities (or to alter the default order of operations) add parentheses in your expressions (e.g. `(1 + 2) * 4 or 1 + (2 * 4)`).

The following table summarizes the operators and their associativity from the highest to the lowest precedence:

| Operators	                                                 | Associativity |
| ---------------------------------------------------------- | ------------- |
| `-`, `+` (unary operators that add the number sign)	     | none
| `**`	                                                     | right
| `*`, `/`, %	                                             | left
| `not`, `!`	                                             | none
| `~`	                                                     | left
| `+`, `-`	                                                 | left
| `..`	                                                     | left
| `==`, `===`, `!=`, `!==`, `<`, `>`, `>=`, `<=`, `not in`, `in`, `contains`, `starts with`, `ends with`, `matches`	| left
| `&`	                                                     | left
| `^`	                                                     | left
| `\|`	                                                     | left
| `and`, `&&`	                                             | left
| `or`, `\|\|`	                                             | left