Patches plugin for MyBB 1.6
---------------------------

Manage code modifications to MyBB core files.

Using simple search patterns, you can locate lines of code in a file, and
insert new code before and after, and optionally replace the original code.
Modifications to files can be applied and reverted. The plugin tracks the
modified files by size and timestamp, and displays the status of each patch
for each file.

Update instructions
-------------------

Please deactivate and activate the plugin after uploading the new files.

Installation instructions
-------------------------

1) This plugin depends on PluginLibrary. Please download it first.

   http://mods.mybb.com/view/pluginlibrary
   https://github.com/frostschutz/PluginLibrary

2) Upload inc/plugins/patches.php and inc/plugins/patches/plugin.php
   and inc/languages/english/admin/patches.lang.php

   If you are using a language other than English, you will also
   have to place a copy of patches.lang.php in the folders of the
   other languages. Language packs may be available on the mods
   site.

3) Activate the plugin

Usage
-----

On the plugins page, there will be a new tab called 'Patches',
which will let you create and manage patches.

Uninstallation instructions
---------------------------

You can uninstall the plugin any time, however when you do so,
you will lose all information about your patches. In that case,
changes to files have to be reverted manually.

