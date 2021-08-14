Changes
=======

V3.11.1.0
---------
1. Moodle 3.11 version.
2. Added downgrade_me.php script - helps with development.
3. Improved logit debugging method to output a stack trace, helps to identify higher up callers.
4. Tidied code.

1.1 - 27/08/20 - MINOR change to rendering
------------------------------------------
Put the renderer function code into classes/output/view.php
Called the template by the same name and now use the core renderer to display the page content.
Removed the renderer.php file.
