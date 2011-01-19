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

if(!defined("PLUGINLIBRARY"))
{
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

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
    global $db, $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        flash_message("The selected plugin depends on <a href=\"https://github.com/frostschutz/PluginLibrary\">PluginLibrary</a>, which is missing.", "error");
        admin_redirect("index.php?module=config-plugins");
    }

    $PL or require_once PLUGINLIBRARY;

    $collation = $db->build_create_table_collation();

    if(!$db->table_exists('patches'))
    {
        $db->write_query('CREATE TABLE '.TABLE_PREFIX.'patches(
                              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                              `title` VARCHAR(100),
                              `description` VARCHAR(200),
                              `search` TEXT,
                              `before` TEXT,
                              `after` TEXT,
                              `replace` TEXT,
                              `file` VARCHAR(150) NOT NULL,
                              `size` BIGINT,
                              `date` BIGINT,
                              KEY (file, size),
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
                                      'description' => 'This section allows you to manage available modifications.');
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

    $sub_tabs['browse_plugins'] = array(
        'title' => $lang->browse_plugins,
        'link' => "index.php?module=config-plugins&amp;action=browse",
        'description' => $lang->browse_plugins_desc
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
    global $mybb;

    if($mybb->input['action'] == 'patches')
    {
        patches_page();
    }

    else if($mybb->input['action'] == 'patches-edit')
    {
        patches_page_edit();
    }
}

/**
 * Output the patches main page.
 */
function patches_page()
{
    global $mybb, $db, $page;

    $page->add_breadcrumb_item('Patches', 'index.php?module=config-plugins&amp;action=patches');

    patches_output_header();
    patches_output_tabs();

    $table = new Table;
    $table->construct_header('Patch');
    $table->construct_header('Controls',
                             array('colspan' => 4,
                                   'class' => 'align_center',
                                   'width' => 300));

    $query = $db->simple_select('patches', 'id, file, size, date, title', '',
                                array('order_by' => 'title'));

    while($row = $db->fetch_array($query))
    {
        $table->construct_cell($row['title']);
        $table->construct_cell("bah");
        $table->construct_row();
    }

    $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches-edit">Add a new Patch...</a>',
                           array('colspan' => 5));
    $table->construct_row();

    $table->output('Patches');
    $page->output_footer();
}

/**
 * Output patches edit page.
 */
function patches_page_edit()
{
    global $mybb, $db, $page;

    if($mybb->request_method == 'post')
    {
        echo "<pre>".htmlspecialchars(print_r($mybb->input,true))."</pre>";
    }

    $page->add_breadcrumb_item('Patches', 'index.php?module=config-plugins&amp;action=patches');
    $page->add_breadcrumb_item('Edit Patch', 'index.php?module=config-plugins&amp;action=patches-edit');

    // Header stuff.
    patches_output_header();
    patches_output_tabs();

    $form = new Form('index.php?module=config-plugins&amp;action=patches-edit', 'post');
    $form_container = new FormContainer('Edit Patch');

    echo $form->generate_hidden_field('patch',
                                      intval($mybb->input['patch']),
                                      array('id' => 'patch'));

    $form_container->output_row(
        'Filename',
        'filename...',
        $form->generate_text_box('filename',
                                 $mybb->input['filename'],
                                 array('id' => 'filename')),
        'filename'
        );

    $form_container->output_row(
        'Title',
        'title...',
        $form->generate_text_box('title',
                                 $mybb->input['title'],
                                 array('id' => 'title')),
        'title'
        );

    $form_container->output_row(
        'Description',
        'description...',
        $form->generate_text_box('description',
                                 $mybb->input['description'],
                                 array('id' => 'description')),
        'description'
        );

    $form_container->output_row(
        'Search',
        'search...',
        $form->generate_text_area('search',
                                  $mybb->input['search'],
                                  array('id' => 'search')),
        'search'
        );

    $form_container->output_row(
        'Before',
        'before...',
        $form->generate_text_area('before',
                                  $mybb->input['before'],
                                  array('id' => 'before')),
        'before'
        );

    $form_container->output_row(
        'After',
        'after...',
        $form->generate_text_area('after',
                                  $mybb->input['after'],
                                  array('id' => 'after')),
        'after'
        );

    $form_container->output_row(
        'Replace',
        'replace...',
        $form->generate_text_area('replace',
                                  $mybb->input['replace'],
                                  array('id' => 'replace')),
        'replace');

    $form_container->end();

    $buttons[] = $form->generate_submit_button('Create Patch');

    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

/**
 * Normalize search pattern.
 */
function patches_normalize_search($search)
{
    $search = (array)$search;

    $result = array();

    foreach($search as $val)
    {
        $lines = explode("\n", $val);

        foreach($lines as $line)
        {
            $line = trim($line);

            if($line !== '')
            {
                $result[] = $line;
            }
        }
    }

    return $result;
}

/* --- End of file. --- */
?>