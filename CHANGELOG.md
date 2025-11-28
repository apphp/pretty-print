0.4.0 /
 - Renamed `Helper` to `Validator` and moved formatting logic to new `Formatter` class
 - Added `is3D` support in `Validator`
 - Updated tests for `Validator` and introduced `FormatterTest`
 - Adjusted autoload configuration in `composer.json`
 - Improved `PrettyPrintTest` with proper `ob` level handling
 - Updated `CHANGELOG` for version `0.4.0`
 - Refactor PrettyPrint: remove unused `is2DNumeric` method and simplify array handling logic

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
