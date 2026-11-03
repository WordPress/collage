# Reader tests

These suites and `TestRunner.php` come from the same
[port-libs revision as the readers](https://github.com/adamziel/port-libs/tree/0e89f7f4e19cefb961e5ee2de61145b4f0c018fc).
The suite files came from `lanes/pandoc/tests/` and `lanes/markerpdf/tests/`;
the runner class came from `tools/TestRunner.php`.

This is a subset of the upstream suites: it covers the PHP readers and helpers
Collage bundles, without external fixtures, OCR, or upstream comparison tools.
The WordPress integration and upload tests separately cover the plugin's
import and draft flow. Reader tests are not included in the plugin ZIP.
