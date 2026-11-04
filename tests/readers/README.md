# Reader tests

These suites and `TestRunner.php` come from the same
[port-libs revision as the readers](https://github.com/adamziel/port-libs/tree/0e89f7f4e19cefb961e5ee2de61145b4f0c018fc).
The suite files came from `lanes/pandoc/tests/` and `lanes/markerpdf/tests/`;
the runner class came from `tools/TestRunner.php`. See [fixture notices](fixtures/README.md)
for the origins and terms of the documents used by the suites.

This is a subset of the upstream suites: it covers the PHP readers and helpers
Collage bundles, without downloading fixtures or using OCR or upstream comparison tools.
The CSV suite includes the direct reader and native-output cases; the generated
parity-report cases assume the upstream repo layout and were not moved.
The standalone CI job uses PHP 8.4 for the native HTML5 DOM tests. The WordPress
integration and upload tests still run on PHP 8.2. Reader tests and their fixtures
are not included in the plugin ZIP.
