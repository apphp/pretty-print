<?php

declare(strict_types=1);

use Apphp\PrettyPrint\Env;
use function Apphp\PrettyPrint\{pprint, pdiff, pcompare};

require __DIR__ . '/../../vendor/autoload.php';

Env::setCliOverride(false); // force HTML mode

echo '<h1>PrettyPrint HTML Demo</h1>';
echo '<p>All outputs below are rendered in web mode (<code>&​lt;pre&​gt;</code> wrapping).</p>';

// 1) Scalars
echo '<h2>1) Scalars</h2>';
pprint('Hello', 123, 4.56);

// 2) 2D matrix with mixed types (strings in arrays are quoted)
echo '<h2>2) 2D Matrix (mixed types)</h2>';
$matrix = [
    [1, "a'b", true, null],
    [2.5, 'engaged', false, '-'],
];
pprint($matrix, label: 'mixed');

// 3) Associative rows (normalized to positional columns)
echo '<h2>3) Associative Rows</h2>';
$assocRows = [
    ['distance' => 1.0440306508911, 'label' => 'engaged'],
    ['distance' => 2.2360679774998, 'label' => 'engaged'],
    ['distance' => 4.2720018726588, 'label' => 'engaged'],
];
pprint($assocRows, headRows: 1, tailRows: 1, headCols: 1, tailCols: 1);

// 4) 2D summarization
echo '<h2>4) 2D Summarization</h2>';
$big2d = [
    [1,2,3,4,5,6],
    [7,8,9,10,11,12],
    [13,14,15,16,17,18],
    [19,20,21,22,23,24],
];
pprint($big2d, headRows: 1, tailRows: 1, headCols: 2, tailCols: 2);

// 5) 3D summarization
echo '<h2>5) 3D Tensor Summarization</h2>';
$tensor3d = [
    [[1,2,3,4],[5,6,7,8],[9,10,11,12]],
    [[13,14,15,16],[17,18,19,20],[21,22,23,24]],
    [[25,26,27,28],[29,30,31,32],[33,34,35,36]],
];
pprint($tensor3d, headB: 1, tailB: 1, headRows: 1, tailRows: 1, headCols: 1, tailCols: 1);

// 6) rowsOnly / colsOnly
echo '<h2>6) rowsOnly / colsOnly</h2>';
pprint($big2d, rowsOnly: '2-4', colsOnly: '1,3-4');

// 7) pdiff
echo '<h2>7) pdiff()</h2>';
$a = [[1,2,3],[4,5,6]];
$b = [[1,9,3],[0,5,7]];
pdiff($a, $b);

// 8) pcompare (HTML colors)
echo '<h2>8) pcompare()</h2>';
$a = [
    [1.12344, 3, 6, 7, True],
    [2, 4, 5, 6, False],
    [0.54, 33, 66, null],
];

$b = [
    [1.12344, 3, 3, 5, 6, 4],
    [1, 2, 3, "a", False, []],
    [0.54, 32, 44, null, 33, 3333, 3],
];
pcompare($a, $b);

// 9) return=true (manual safe output)
echo '<h2>9) return=true</h2>';
$returned = pprint($assocRows, return: true, end: '');
echo '<pre>' . htmlspecialchars($returned, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';


$matrix = [
    [1, 'x', 2.5, '4'],
    [3, 'y', 4.5, 6],
    ['n/a', 'z', 1.0, null],
];

pprint($matrix, colsSummary: true, rowsSummary: true);

Env::setCliOverride(null);
