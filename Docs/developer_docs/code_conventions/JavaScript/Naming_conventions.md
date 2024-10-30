# Javascript naming conventions

Javascript is a loosely typed language. You could even call it untyped. There no way to enforce types for function parameter or variables in general. Therefore it is absolutely crucial to use implicit type hints in variable names, comments, etc. and to take greate care of following them.

## Variable names

Always use type-based name prefixes for JS variables:
- `oState` - object
- `aStates` - array
- `sState` - string
- `iState` - integer
- `fState` - float
- `bOffline` - boolean
- `mState` - mixed
- `domDiv` - a `<div>` node
- `jqDiv` - same `<div>`, but wrapped in a jQuery element
