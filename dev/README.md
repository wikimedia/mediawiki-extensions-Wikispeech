# Developer files
This directory contains miscellaneous scripts and instructions useful for
developing and testing Wikispeech within a MediaWiki-Vagrant framework.

*   `10-wikispeech.php` - MediaWiki settings file to be placed under `<vagrant_dir>/settings.d/`.
*   `phpunit.sh` - Convenience script for running phpunit tests for Wikispeech inside Vagrant.
*   `phan.sh` - Convenience script for a phan analysis for Wikispeech inside Vagrant.
*   `activate_ast_on_vagrant.(md|sh)` - Phan needs `php-ast` to work, unfortunately
    MediaWiki-Vagrant does not come with easily installable. This provides step-by-step
    instructions, and an automated shell script, to install `php-ast`.

When adding a script, also add it to this list with a short description.
