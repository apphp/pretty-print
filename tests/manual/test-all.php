<?php

declare(strict_types=1);

// Manual runner to exercise PrettyPrint in many variants
// Usage: php tests-all.php

include __DIR__ . '/../vendor/autoload.php';

use Apphp\PrettyPrint\PrettyPrint;
use function Apphp\PrettyPrint\{pprint, pp, ppd};

// Ensure ppd does not exit during this script
//putenv('APP_ENV=test');

function section(string $title): void {
    echo "\n==== {$title} ====\n";
}

$pp = new PrettyPrint();

// 1) Scalars and strings
section('Scalars and strings');
$pp('Hello', 123, 4.56);

// 2) Multiple 1D rows aligned
section('Multiple 1D rows aligned');
$pp([1, 23, 456], [12, 3, 45]);

// 3) Label + aligned 2D matrix
section('Label + 2D matrix');
$pp('Confusion matrix:', [[1, 23], [456, 7]]);

// 4) 2D tensor default formatting
section('2D tensor default');
$pp([[1, 2], [3, 4]]);

// 5) 2D tensor summarized (head/tail rows/cols)
section('2D summarized with ellipses');
$pp([[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]], ['headRows' => 1, 'tailRows' => 1, 'headCols' => 1, 'tailCols' => 1]);

// 6) 3D tensor small
section('3D tensor small');
$pp([ [[1, 2], [3, 4]], [[5, 6], [7, 8]] ]);

// 7) 3D tensor summarized with block ellipsis and inner ellipses
section('3D summarized (headB/tailB) with inner 2D ellipses');
$block = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
$pp([$block, $block, $block], ['headB' => 1, 'tailB' => 1, 'headRows' => 1, 'tailRows' => 1, 'headCols' => 1, 'tailCols' => 1]);

// 8) Custom label for 2D and 3D
section('Custom label 2D');
$pp([[1, 2], [3, 4]], ['label' => 'arr']);
section('Custom label 3D');
$pp([ [[1, 2], [3, 4]], [[5, 6], [7, 8]] ], ['label' => 'ndarray']);

// 9) Precision overrides
section('Precision via named arg (scalar)');
$pp(3.14159, precision: 2);
section('Precision via trailing array (scalar)');
$pp(3.14159, ['precision' => 6]);
section('Precision applied in 2D');
$pp([[1.2, 3.4567], [9.0, 10.9999]], precision: 2);

// 10) Start/End options
section('Trailing start/end options');
$pp('Hello', ['start' => '>>> ', 'end' => "\n\n"]);
section('Named start/end options');
$pp('World', start: '>>> ', end: ''); echo "\n"; // ensure newline after empty end

// 11) Unknown named args removal
section('Unknown named args removed');
$pp('Hello', foo: 'bar', baz: 123);

// 12) Variadic args limit (prints only first 32)
section('MAX_ARGS limiting');
$many = range(1, 40);
$pp(...$many);

// 13) Global functions pprint / pp / ppd
section('Global pprint');
pprint('Global Hello');
section('Global pp alias');
pp(1, 2, 3);

// 14) Mixed arrays and fallback formatting
section('Non-numeric 2D fallback to generic');
$pp([['a', 2], [3, 4]]);

// 15) Multiple 1D rows with label
section('Multiple 1D rows with label');
$pp('Label', [1, 2], [3, 4]);

// 16) Default behavior with mixed arguments
section('Default behavior with mixed args');
$pp(true, false, null, [1, 2], [[1, 2], [3, 4]]);

// End
section('Done');
