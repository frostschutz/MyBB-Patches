<?php
/**
 * This file is part of Patches plugin for MyBB.
 * Copyright (C) 2010 Andreas Klauer <Andreas.Klauer@metamorpher.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// Disallow direct access to this file for security reasons.
if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * Some parts of this code are based on admin/modules/config/plugins.php
 * due to similarity of functionality (list of patches vs. list of plugins,
 * tab integration into the plugins page).
 *
 */

/* --- Defines: --- */
define('MYBB_PATCHES', MYBB_ROOT.'inc/patches/');

/* --- Global Variables: --- */

global $patches_list, $patches_info;
$patches_list = array();
$patches_info = array();

/* --- Hooks: --- */

global $plugins;

$plugins->add_hook('admin_page_output_nav_tabs_start', 'patches_tabs_start');
$plugins->add_hook('admin_config_plugins_begin', 'patches_plugins_begin');

/* --- Plugin API: --- */

/**
 * Return information about the patches plugin.
 */
function patches_info()
{
    return array(
        'name'          => 'Patches',
        'description'   => 'Manage modifications to MyBB core files using patches.',
        'website'       => 'http://www.mybboard.net',
        'author'        => 'Andreas Klauer',
        'authorsite'    => 'mailto:Andreas.Klauer@metamorpher.de',
        'version'       => '0.1',
        'guid'          => '',
        'compatibility' => '16*',
    );
}

/**
 * Check if the plugin is installed.
 */
function patches_is_installed()
{
    global $db;

    return $db->table_exists('patches');
}

/**
 * Install the plugin.
 */
function patches_install()
{
    global $db;

    $collation = $db->build_create_table_collation();

    if(!$db->table_exists('patches'))
    {
        $db->write_query('CREATE TABLE '.TABLE_PREFIX.'patches(
                              hid INT UNSIGNED NOT NULL AUTO_INCREMENT,
                              patch VARCHAR(40) NOT NULL,
                              version VARCHAR(10) NOT NULL,
                              file VARCHAR(100) NOT NULL,
                              size BIGINT NOT NULL,
                              date BIGINT NOT NULL,
                              ins INT NOT NULL,
                              del INT NOT NULL,
                              from_a INT NOT NULL,
                              from_b INT,
                              to_a INT NOT NULL,
                              to_b INT,
                              diff BLOB NOT NULL,
                              KEY (patch),
                              KEY (file, size, date),
                              PRIMARY KEY (hid)
                          ) TYPE=MyISAM'.$collation.';');
    }
}

/**
 * Uninstall the plugin.
 */
function patches_uninstall()
{
    global $db;

    if($db->table_exists('patches'))
    {
        $db->drop_table('patches');
    }
}

/**
 * Activate the plugin.
 */
function patches_activate()
{
    // do nothing
}

/**
 * Deactivate the plugin.
 */
function patches_deactivate()
{
    // do nothing
}

/* --- Patches page: --- */

/**
 * Add Patches tab on the plugins page.
 */
function patches_tabs_start($arguments)
{
    global $mybb;

    if($mybb->input['module'] == 'config-plugins')
    {
        $arguments['patches'] = array('title' => 'Patches',
                                      'link' => 'index.php?module=config-plugins&amp;action=patches',
                                      'description' => 'This section allows you to manage available patches.');
    }
}

/**
 * Output tabs with Patches as active.
 */
function patches_output_tabs()
{
    global $page, $lang;

    $sub_tabs['plugins'] = array(
        'title' => $lang->plugins,
        'link' => 'index.php?module=config-plugins',
        'description' => $lang->plugins_desc
    );

    $sub_tabs['update_plugins'] = array(
        'title' => $lang->plugin_updates,
        'link' => 'index.php?module=config-plugins&amp;action=check',
        'description' => $lang->plugin_updates_desc
    );

    // The missing Patches tab will be added in the tab_start hook.

    $page->output_nav_tabs($sub_tabs, 'patches');
}

/**
 * Output header.
 */
function patches_output_header()
{
    global $page;

    $page->output_header('Patches');
}

/**
 * Handle active Patches tab case on the plugins page.
 */
function patches_plugins_begin()
{
    global $mybb, $lang, $page;

    if($mybb->input['action'] != 'patches')
    {
        return;
    }

    $page->add_breadcrumb_item('Patches', 'index.php?module=config-plugins&amp;action=patches');

    $page->extra_header .= '
<style type="text/css">
<!--

ins {
    background: #dfd;
    text-decoration: none;
}

del {
    background: #fdd;
    text-decoration: none;
}

-->
</style>
';

    if($mybb->input['view'])
    {
        patches_page_view();
    }

    else if($mybb->input['check'])
    {
        patches_page_check();
    }

    else if($mybb->input['apply'])
    {
        patches_page_apply();
    }

    else if($mybb->input['revert'])
    {
        patches_page_revert();
    }

    else
    {
        patches_page();
    }
}

/**
 * Output the patches main page.
 */
function patches_page()
{
    global $mybb, $page;

    patches_output_header();
    patches_output_tabs();

    $page->output_success('Success');
    $page->output_alert('Alert');
    $page->output_inline_message('Inline Message');
    $page->output_error('Error');
    $page->output_inline_error('Inline Error');


    $table = new Table;
    $table->construct_header('Patch');
    $table->construct_header('Controls',
                             array('colspan' => 4,
                                   'class' => 'align_center',
                                   'width' => 300));

    $list = patches_get_list();

    foreach($list as $patch)
    {
        $name = $version = $compatibility = $author = '';

        $info = patches_get_info($patch);

        $name = $info['name'];

        if($info['website'])
        {
            $name = '<a href="'.$info['website'].'">'.$name.'</a>';
        }

        if($info['version'])
        {
            $version = '('.$info['version'].')';
        }

        if(!$info['compatibility'])
        {
            $compatibility = '<span style="color: red;">[Incompatible with MyBB '.$mybb->version_code.']</span>';
        }

        $author = $info['author'];

        if($info['authorsite'])
        {
            $author = '<a href="'.$info['authorsite'].'">'.$author.'</a>';
        }

        $table->construct_cell("<strong>$name</strong> $version $compatibility<br />
                                <small>{$info['description']}</small><br />
                                <i><small>Created by {$author}</small></i>");

        $urlpatch = urlencode($patch);

        $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches&amp;view='.$urlpatch.'">View</a>',
                               array('class' => 'align_center', 'width' => 75));
        $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches&amp;check='.$urlpatch.'">Check</a>',
                               array('class' => 'align_center', 'width' => 75));
        $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches&amp;apply='.$urlpatch.'">Apply</a>',
                               array('class' => 'align_center', 'width' => 75));
        $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches&amp;revert='.$urlpatch.'">Revert</a>',
                               array('class' => 'align_center', 'width' => 75));
        $table->construct_row();
    }

    $table->output('Patches');

    echo 'Get more patches <a href="http://www.mybboard.net">here</a>.';

    $page->output_footer();
}

/**
 * Output the patches view page.
 */
function patches_page_view()
{
    global $mybb, $page;

    $page->add_breadcrumb_item('View '.$mybb->input['view'], 'index.php?module=config-plugins&amp;action=patches&amp;view='.urlencode($mybb->input['view']));

    patches_output_header();
    patches_output_tabs();

    $patch = $mybb->input['view'];

    // $page->output_inline_error(array('inline error', 'foo bar'));

    if($patch && patches_get_info($mybb->input['view']))
    {
        $parse = patches_parse($patch, $error, $comment);

        if(count($error))
        {
            $page->output_inline_error($error);
        }

        if(count($comment))
        {
            $page->output_error(implode("<br />\n", $comment));
        }

        patches_print($parse);
    }

    else
    {
        echo 'Unknown patch.<br>';
    }

    $page->output_footer();
}

/**
 * Output the patches check page.
 */
function patches_page_check()
{
    echo 'check';
}

/**
 * Output the patches apply page.
 */
function patches_page_apply()
{
    echo 'apply';
}

/**
 * Output the patches revert page.
 */
function patches_page_revert()
{
    echo 'revert';
}

/* --- Patchfile handling: --- */

/**
 * Get a list of available patches and return them.
 */
function patches_get_list()
{
    global $patches_list;

    if(!$patches_list)
    {
        $dir = @opendir(MYBB_PATCHES);

        if($dir)
        {
            while($file = readdir($dir))
            {
                if($file[0] == '.' || $file[0] == '#')
                {
                    // ignore hidden or temporary files
                    continue;
                }

                $ext = get_extension($file);

                if($ext == 'patch')
                {
                    // remove the file extension
                    $file = my_substr($file, 0, -6);

                    // add to list
                    $patches_list[] = $file;
                }
            }

            @sort($patches_list);
        }

        @closedir($dir);
    }

    return $patches_list;
}

/**
 * Get info for a patch and return it.
 */
function patches_get_info($patch)
{
    global $mybb, $patches_info;

    $list = patches_get_list();

    if(!in_array($patch, $patches_info) && in_array($patch, $list))
    {
        // Initialize with standard info.
        $patches_info[$patch] = array('name' => $patch,
                                      'author' => 'Unknown',
                                      'compatibility' => true);

        // Fetch more detailed info from the ini file, if available.
        $p = @parse_ini_file(MYBB_PATCHES."{$patch}.ini");

        if(is_array($p))
        {
            // Merge this info into the patch info array.
            $patches_info[$patch] = array_merge($patches_info[$patch], $p);

            // Verify compatibility:
            if($p['compatibility'])
            {
                $compatibility = explode(',', $p['compatibility']);
                $patches_info[$patch]['compatibility'] = false;

                foreach($compatibility as $version)
                {
                    $version = trim($version);
                    $version = str_replace('\\*', '.+', preg_quote($version));

                    if(preg_match("#{$version}#i", $mybb->version_code))
                    {
                        $patches_info[$patch]['compatibility'] = true;
                        break;
                    }
                }
            }
        }
    }

    return $patches_info[$patch];
}

/**
 * prints a patch
 */
function patches_print($parse)
{
    $files = array_keys($parse);
    sort($files);

    foreach($files as $file)
    {
        $sumins = 0;
        $sumdel = 0;
        $dots = false;

        $table = new Table;

        foreach($parse[$file] as $hid=>$hunk)
        {
            $sumins += $hunk['ins'];
            $sumdel += $hunk['del'];

            $line_from = $hunk['from_a'];
            $line_to = $hunk['to_a'];

            $from = array();
            $to = array();
            $diff = array();

            foreach($hunk['diff'] as $line)
            {
                $mark = $line[0];
                $line = substr($line, 1);
                $line = trim($line, "\r\n");

                if($mark == '+')
                {
                    $from[] = '';
                    $to[] = sprintf('%5d', $line_to++);
                    $diff[] = "<ins>".htmlspecialchars_uni($line)."</ins>";
                }

                else if($mark == '-')
                {
                    $from[] = sprintf('%5d', $line_from++);
                    $to[] = '';
                    $diff[] = "<del>".htmlspecialchars_uni($line)."</del>";
                }

                else
                {
                    $from[] = sprintf('%5d', $line_from++);
                    $to[] = sprintf('%5d', $line_to++);
                    $diff[] = htmlspecialchars_uni($line);
                }
            }

            $table->construct_cell('<pre>'.implode("\n", $from)."\n</pre>", array('style' => 'width: 40px; min-width: 40px; max-width: 40px;'));
            $table->construct_cell('<pre>'.implode("\n", $to)."\n</pre>", array('style' => 'width: 40px; min-width: 40px; max-width: 40px;'));
            $table->construct_cell('<pre><code>'.implode("\n", $diff)."\n</code></pre>");
            $table->construct_row();
        }

        $table->output(htmlspecialchars_uni($file). " ($sumins inserts, $sumdel deletions)");
    }
}

/* --- End of file. --- */
?>