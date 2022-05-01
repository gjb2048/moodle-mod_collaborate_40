A template for Moodle modules.  Updated from Moodle HQ's moodle-mod_simplemod template.

Added:

 - Mustache template.
 - Working backup/restore functionality for Moodle2.
 - No grades or events implemented.

Instructions for installing:
============================

Download the zip file or clone the repository into your moodle/mod folder using the instructions given under the button "Clone or download".

Assuming you are going to change your module name from simplemod to something more relevant, do the following.

Windows extra
===========================

Git for Windows
---------------
Install 'Git for Windows' from https://gitforwindows.org/, then you'll be able to use 'Git Bash' command line for many tasks.

This is further described in the free course:

MoodleBites for TechPrep - https://www.moodlebites.com/enrol/index.php?id=228

Line endings
------------

If on Windows then ensure the line endings are correct before starting by opening 'Git Bash' in the root of the module and typing:

<pre>
find . -type f -print0 | xargs -0 unix2dos
</pre>

PHP log file
------------

Edit the php.ini file and specify the location of the error log, this will help with debugging as the file can contain extra information:

<pre>
; Log errors to specified file. PHP's default behavior is to leave this value
; empty.
; http://php.net/error-log
; Example:
;error_log = php_errors.log
; Log errors to syslog (Event Log on Windows).
;error_log = syslog
error_log = "f:/WAMP/php74tsx64/php.log"
</pre>

Where 'f:/WAMP/php74tsx64/php.log' is an example location.  Restart the web server service to take effect, i.e. 'Apache2.4' in 'Services'.


Rename these files:
===================
All four files in backup/moodle2 should have the name of your new module.

The lang/en/simplemod.php file should be renamed to the name of your new module.


Replace simplemod with your new module name
========================================
Carry out a search and replace for "simplemod" replacing it with the name of your new module.  You can do this in a number of ways depending on your text editor.  If you don't have one handy, download Brackets (http://brackets.io/) which is free, open source and handles this stuff well.

Navigate to your admin dashboard and install the new module.

For newbie users
================
You may notice a reference to a local class debugging.  This is a simple script that allows you to output debugging information to the error log.

It looks like this"

<pre>
namespace mod_collaborate\local;

class debugging {
    public static function logit($message, $value) {
        error_log(print_r($message, true));
        error_log(print_r($value, true));
        try {
            throw new \Exception();
        } catch(\Exception $e) {
            error_log('Trace: '.$e->getTraceAsString().PHP_EOL);
        }
    }
}
</pre>

Place the above code in a file called debugging.php.

Modify the file location (mylog.log) if desired.  Anywhere you want to view the contents of an object use:
<pre>
\mod_simplemod\local\debugging::logit("What is in a widget: ", $simplemod);
</pre>

Using Xdebug
============
Brackets, Sublime, PHP Storm and many other editors or IDEs use this.  If you are using Linux, there's plenty of info to google.

Windows users
=============
Xampp is a workable development environment.  Install the basic Xampp rather than the Moodle/Xampp package.  Install Moodle under htdocs and change the existing index file if desired.

This article is helpful for installing xdebug on xampp:
https://gist.github.com/odan/1abe76d373a9cbb15bed

Further information
===================
Have fun developing for Moodle.  This activity module is an example from MoodleBites for Developers level 2.

https://www.moodlebites.com/mod/page/view.php?id=19542

Richard Jones, richardnz@outlook.com
Pirongia, NZ
August 27th, 2020.