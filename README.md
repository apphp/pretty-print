# Pretty Pprint
Callable pretty-printer for PHP arrays with Python-like formatting.
PrettyPrint is a small, zero-dependency PHP utility that formats numeric arrays in a clean, readable, PyTorch-inspired style. It supports aligned 2D tables, summarized tensor views, and flexible output options – making it ideal for ML experiments, debugging, logging, and educational projects.

## Installation
```bash
composer require apphp/pretty-print
```

## Usage

Note: When used in web (non-CLI) environments, output is automatically wrapped in `<pre>` to preserve spacing. In CLI, no wrapping is applied.

### Global helper functions

Print scalars/strings
```php
pprint('Hello', 123, 4.56);            
// Hello 123 4.5600
```

Print multiple 1D rows aligned as a 2D table
```php
pprint([1, 23, 456], [12, 3, 45]);
// [[ 1, 23, 456],
//  [12,  3,  45]]
```

Label + 2D matrix
```php
pprint('Confusion matrix:', [[1, 23], [456, 7]]);
// Confusion matrix:
// [[  1, 23],
//  [456,  7]]
```

2D tensor-style formatting
```php
$matrix = [
    [1,2,3,4,5],
    [6,7,8,9,10],
    [11,12,13,14,15],
];
pprint($matrix);
// tensor([
//   [ 1,  2,  3,  4,  5],
//   [ 6,  7,  8,  9, 10],
//   [11, 12, 13, 14, 15]
// ])
```

Custom label instead of "tensor"
```php
pprint($matrix, label: 'arr');
// arr([
//   [ 1,  2,  3,  4,  5],
//   [ 6,  7,  8,  9, 10],
//   [11, 12, 13, 14, 15]
// ])
```

2D tensor-style formatting with summarization
```php
$matrix = [
    [1,2,3,4,5],
    [6,7,8,9,10],
    [11,12,13,14,15],
];
pprint($matrix, headRows: 1, tailRows: 1, headCols: 2, tailCols: 2);
// tensor([
//   [ 1,  2, ...,  4,  5],
//   ...,
//   [11, 12, ..., 14, 15]
// ])
```

3D tensor with head/tail blocks (PyTorch-like)
```php
$tensor3d = [
    [[1,2,3],[4,5,6]],
    [[7,8,9],[10,11,12]],
    [[13,14,15],[16,17,18]],
];
pprint($tensor3d, headB: 1, tailB: 1, headRows: 1, tailRows: 1, headCols: 1, tailCols: 1);
// tensor([
//  [[1, ..., 3],
//   [4, ..., 6]],
//
//  ...,
//
//  [[13, ..., 15],
//   [16, ..., 18]]
// ])
```

Postfix and prefix control
```php
// No newline at the end (like Python's end="")
pprint('Same line', end: '');
// Added newline at the end after printing
pprint('Add line');
pprint('Add line', end: "\n");
// Added addedional 2 newlines at the end after printing
pprint('Add 2 lines', end: "\n\n");

// Add a prefix at the start of the printed string
pprint('Tabbed', start: "\t");
// Combine with end to avoid newline
pprint('Prompted', start: '>>> ', end: '');
```

Print and then exit the script
```php
ppd('Fatal error');
```

### As an object

```php
use Apphp\PrettyPrint\PrettyPrint;

$pp = new PrettyPrint();
$pp('Hello', 42);       // same as pprint('Hello', 42)

$tensor3d = [
    [[1,2,3],[4,5,6]],
    [[7,8,9],[10,11,12]],
    [[13,14,15],[16,17,18]],
];

// Named options are supported
$pp($tensor3d, headB: 2, tailB: 1, headRows: 1, tailRows: 1, headCols: 1, tailCols: 1);

// Label + 2D
$pp('Metrics:', [[0.91, 0.02], [0.03, 0.88]]);
```

### Options reference

- **start**: string. Prefix printed before the content. Example: `pprint('Hello', ['start' => "\t"])`.
- **end**: string. Line terminator, default to new line. Example: `pprint('no newline', ['end' => '']);`
- **label**: string. Prefix label for 2D/3D formatted arrays, default `tensor`. Example: `pprint($m, ['label' => 'arr'])`.
- **headB / tailB**: ints. Number of head/tail 2D blocks shown for 3D tensors.
- **headRows / tailRows**: ints. Rows shown per 2D slice with ellipsis between.
- **headCols / tailCols**: ints. Columns shown per 2D slice with ellipsis between.

All options can be passed as:
- trailing array: `pprint($m, ['headRows' => 1, ...])`
- named args (PHP 8+): `$pp($m, headRows: 1, ...)`
