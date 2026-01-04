0.6.0 / 
 - Changed default values of sep: `PHP_EOL` and end: `PHP_EOL . PHP_EOL`
 - Added new function pcompare() for side-by-side (stacked) matrix comparison with colored cells
 - Added badges to README
 - Added `rowsOnly` / `colsOnly` options to `pprint` / `PrettyPrint` for selecting specific rows/columns in 2D/3D outputs

0.5.1 / 2025-12-12
 - Improve tensor block formatting in `Formatter` for PyTorch-style visualization
 - Refactor `PrettyPrint` for simplified default formatting, remove redundant label-based handling
 - Added new function pdiff() for array comparison

0.5.0 / 2025-12-08
 - Refactored `ppd` function for improved CLI test handling with conditional exit strategy    
 - Added support for automatic object-to-array conversion in `PrettyPrint` (`asArray()`/`toArray()`)
 - Added namespace declaration to global helper functions

0.4.3 / 2025-12-05
 - Default precision increased from 2 to 4
 - Refactor `PrettyPrint` for modularity: extract formatting logic into dedicated private methods
 - Added string return support in `PrettyPrint` and extend format options for flexibility
 - Added new parmeter `return` in `PrettyPrint`

0.4.2 / 2025-12-04
 - Refactor `PrettyPrint` to delegate `format3DTorch` logic to `Formatter` for consistency
 - Added and update tests in `FormatterTest`
 - Refactor `Formatter` and `PrettyPrint` to use `Env::isCli()` for CLI detection
 - Introduced `Env` utility class with tests; update `FormatterTest` to validate new behavior
 - Add support for custom separators in `PrettyPrint`; update tests to cover new functionality

0.4.1 / 2025-12-02 
 - Enhance `PrettyPrint::formatValue` to handle objects, resources, and unknown types
 - Revise `PrettyPrint` to output top-level strings without quotes
 - Refactor `PrettyPrint` to delegate `format2DSummarized` logic to `Formatter`
 - Revise tests to align with updated string output conventions
 - Refactor `PrettyPrint` to delegate `format2DTorch` logic to `Formatter` and add corresponding tests in `FormatterTest`
 - Refactor `Formatter` to extract cell formatting logic into `formatCell` for reuse and clarity
 - Refactor `PrettyPrint` to delegate `formatForArray` logic to `Formatter` and remove the private method `formatForArray`
 - Refactor `PrettyPrint` to simplify argument formatting by delegating logic to `Formatter::formatCell`
 - Update `Formatter::formatCell` visibility to public for reuse
 - Refactor `Formatter` and `PrettyPrint` to unify string formatting logic, ensuring CLI output omits quotes for top-level strings
 
0.4.0 / 2025-11-28
 - Renamed `Helper` to `Validator` and moved formatting logic to new `Formatter` class
 - Added `is3D` support in `Validator`
 - Updated tests for `Validator` and introduced `FormatterTest`
 - Adjusted autoload configuration in `composer.json`
 - Improved `PrettyPrintTest` with proper `ob` level handling
 - Updated `CHANGELOG` for version `0.4.0`
 - Refactor PrettyPrint: remove unused `is2DNumeric` method and simplify array handling logic
 - Refactor PrettyPrint: delegate 2D matrix formatting to `Formatter::format2DAligned`, added tests in `FormatterTest`

0.3.2 / 2025-11-28
 - Adjusted is1D and is2D to support `int|float|string|bool|null`
 - Extracted some functions to separate helper class
 - Created test class for Helper

0.3.1 / 2025-11-27
 - Adjust default formatting dimensions in `PrettyPrint` tensor methods
 - `README.md` example updates
 - Add precision option to PrettyPrint for float formatting
 - Refactor `PrettyPrint` for coding standards compliance and improve script configurations in composer.json
 - Added MAX limitation for arguments
 - Allowed formatted printing of numbers and strings together

0.3.0 / 2025-11-24
 - Improved documentation
 - Added "start" option to PrettyPrint for prefix control
 - Added custom "label" support for tensor formatting in PrettyPrint
 - Added web environment `<pre>` wrapping support

0.2.1 / 2025-11-22
 - Added Github Actions
 - Added PHP Lint
 - Add usage examples and options reference to `README.md`

0.2.0 / 2025-11-22
 - Added Unit Tests
 - Improved documentation

0.1.0 / 2025-11-21
 - Updated project structure
 - Added README and License
 - Implemented basic functionality
 - Extracted `pprint`, `pp` and `ppd` function to separate functions.php file

0.0.1 / 2025-11-12
 - Initial commit
