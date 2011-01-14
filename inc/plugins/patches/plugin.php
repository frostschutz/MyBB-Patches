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
        'description'   => 'Manage modifications to MyBB core files.',
        'website'       => 'https://github.com/frostschutz/Patches',
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
                              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                              file VARCHAR(150) NOT NULL,
                              size BIGINT NOT NULL,
                              date BIGINT NOT NULL,
                              search TEXT NOT NULL,
                              before TEXT,
                              after TEXT,
                              replace TEXT,
                              KEY (file, size, date),
                              PRIMARY KEY (id)
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
    $table->output('Patches');

    $page->output_footer();
}

/* --- End of file. --- */
?>