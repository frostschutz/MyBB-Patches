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
                              `pid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                              `ptitle` VARCHAR(100),
                              `pdescription` VARCHAR(200),
                              `pfile` VARCHAR(150) NOT NULL,
                              `psize` BIGINT,
                              `pdate` BIGINT,
                              `psearch` TEXT,
                              `pbefore` TEXT,
                              `pafter` TEXT,
                              `preplace` INT(1),
                              KEY (pfile, psize),
                              PRIMARY KEY (pid)
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

    switch((string)$mybb->input['action'])
    {
        case 'patches':
            patches_page();
            break;

        case 'patches-edit':
            patches_page_edit();
            break;

        case 'patches-activate':
            patches_page_activate();
            break;

        case 'patches-deactivate':
            patches_page_deactivate();
            break;

        case 'patches-apply':
            patches_page_apply();
            break;

        case 'patches-revert':
            patches_page_apply(true);
            break;
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
                             array('colspan' => 3,
                                   'class' => 'align_center',
                                   'width' => '30%'));

    $query = $db->simple_select('patches', 'pid,pfile,psize,pdate,ptitle', '',
                                array('order_by' => 'pfile,ptitle'));

    $file = '';

    while($row = $db->fetch_array($query))
    {
        if($row['pfile'] != $file)
        {
            $file = $row['pfile'];
            $table->construct_cell('<strong>'.htmlspecialchars($row['pfile']).'</strong>');
            $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches-apply&amp;file='.$row['pfile'].'&amp;my_post_key='.$mybb->post_code.'">Apply</a>',
                                   array('class' => 'align_center',
                                         'width' => '15%'));
            $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches-revert&amp;file='.$row['pfile'].'&amp;my_post_key='.$mybb->post_code.'">Revert</a>',
                                   array('class' => 'align_center'));
            $table->construct_row();
        }

        $table->construct_cell('<div style="padding-left: 40px;"><a href="index.php?module=config-plugins&amp;action=patches-edit&amp;patch='.$row['pid'].'">'.htmlspecialchars($row['ptitle'])."</a></div>");

        if(!$row['psize'])
        {
            $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches-activate&amp;patch='.$row['pid'].'&amp;my_post_key='.$mybb->post_code.'">Activate</a>',
                                   array('class' => 'align_center',
                                         'width' => '15%'));
        }

        else
        {
            $table->construct_cell('<a href="index.php?module=config-plugins&amp;action=patches-deactivate&amp;patch='.$row['pid'].'&amp;my_post_key='.$mybb->post_code.'">Deactivate</a>',
                                   array('class' => 'align_center',
                                         'width' => '15%'));
        }

        if(!$row['psize'])
        {
            $table->construct_cell('-', array('class' => 'align_center'));
        }

        else if(intval($row['psize']) === @filesize(MYBB_ROOT.$file) &&
                intval($row['pdate']) === @filemtime(MYBB_ROOT.$file))
        {
            $table->construct_cell('OK', array('class' => 'align_center'));
        }

        else
        {
            $table->construct_cell('FAIL', array('class' => 'align_center'));
        }

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

    $patch = intval($mybb->input['patch']);

    if($mybb->request_method == 'post')
    {
        echo "<pre>".htmlspecialchars(print_r($mybb->input,true))."</pre>";

        $patch = intval($mybb->input['patch']);

        if($patch && $mybb->input['delete'])
        {
            // delete patch
            $db->delete_query('patches', "pid={$patch}");
            flash_message('success', 'success');
            admin_redirect('index.php?module=config-plugins&amp;action=patches');
        }

        // validate input

        $errors = array();

        $file = patches_normalize_file($mybb->input['pfile']);

        if(!is_file(MYBB_ROOT.$file))
        {
            $errors[] = 'file does not exist';
        }

        $title = trim($mybb->input['ptitle']);

        if(!$title)
        {
            $errors[] = 'missing title';
        }

        $description = trim($mybb->input['pdescription']);
        // description is optional

        $search = patches_normalize_search($mybb->input['psearch']);

        if(!$search)
        {
            $errors[] = 'empty search pattern';
        }

        $search = implode("\n", $search);

        $before = trim($mybb->input['pbefore']);
        $after = trim($mybb->input['pafter']);
        $replace = intval($mybb->input['preplace']);

        if(!($before || $after || $replace))
        {
            $errors[] = 'no edit specified';
        }

        if(!$errors)
        {
            $data = array(
                'ptitle' => $db->escape_string($title),
                'pdescription' => $db->escape_string($description),
                'psearch' => $db->escape_string($search),
                'pbefore' => $db->escape_string($before),
                'pafter' => $db->escape_string($after),
                'preplace' => $replace,
                'pfile' => $db->escape_string($file)
                );

            if($patch)
            {
                $update = $db->update_query('patches',
                                            $data,
                                            "pid={$patch}");
            }

            if(!$update)
            {
                $db->insert_query('patches', $data);
            }

            flash_message('success', 'success');
            admin_redirect('index.php?module=config-plugins&amp;action=patches');
        }
    }

    else if($patch > 0)
    {
        // fetch info of existing patch
        $query = $db->simple_select('patches',
                                    'pfile,ptitle,pdescription,psearch,pbefore,pafter,preplace',
                                    "pid='{$patch}'");
        $row = $db->fetch_array($query);

        if($row)
        {
            $mybb->input = array_merge($mybb->input, $row);
        }
    }

    $page->add_breadcrumb_item('Patches', 'index.php?module=config-plugins&amp;action=patches');
    $page->add_breadcrumb_item('Edit Patch', 'index.php?module=config-plugins&amp;action=patches-edit');

    // Header stuff.
    patches_output_header();
    patches_output_tabs();

    if($errors)
    {
        $page->output_inline_error($errors);
    }

    $form = new Form('index.php?module=config-plugins&amp;action=patches-edit', 'post');
    $form_container = new FormContainer('Edit Patch');

    echo $form->generate_hidden_field('patch',
                                      intval($mybb->input['patch']),
                                      array('id' => 'patch'));

    $form_container->output_row(
        'Filename',
        'filename...',
        $form->generate_text_box('pfile',
                                 $mybb->input['pfile'],
                                 array('id' => 'pfile')),
        'pfile'
        );

    $form_container->output_row(
        'Title',
        'title...',
        $form->generate_text_box('ptitle',
                                 $mybb->input['ptitle'],
                                 array('id' => 'ptitle')),
        'ptitle'
        );

    $form_container->output_row(
        'Description',
        'description...',
        $form->generate_text_box('pdescription',
                                 $mybb->input['pdescription'],
                                 array('id' => 'pdescription')),
        'pdescription'
        );

    // normalize search before it goes back into the form
    if($mybb->input['psearch'])
    {
        $search = patches_normalize_search($mybb->input['psearch']);
        $mybb->input['psearch'] = implode("\n", $search);
    }

    $form_container->output_row(
        'Search',
        'search...',
        $form->generate_text_area('psearch',
                                  $mybb->input['psearch'],
                                  array('id' => 'psearch')),
        'psearch'
        );

    $form_container->output_row(
        'Before',
        'before...',
        $form->generate_text_area('pbefore',
                                  $mybb->input['pbefore'],
                                  array('id' => 'pbefore')),
        'pbefore'
        );

    $form_container->output_row(
        'After',
        'after...',
        $form->generate_text_area('pafter',
                                  $mybb->input['pafter'],
                                  array('id' => 'pafter')),
        'pafter'
        );

    // set to 0, otherwise the yes no defaults to yes...
    $mybb->input['preplace'] = intval($mybb->input['preplace']);

    $form_container->output_row(
        'Replace',
        'replace...',
        $form->generate_yes_no_radio('preplace',
                                     $mybb->input['preplace']),
        'preplace');

    $form_container->end();

    $buttons[] = $form->generate_submit_button('Save Patch');

    if(intval($mybb->input['patch']))
    {
        $buttons[] = $form->generate_submit_button('Delete Patch', array('name' => 'delete'));
    }

    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

/**
 * Activate a patch
 */
function patches_page_activate()
{
    global $mybb, $db;

    if(!verify_post_check($mybb->input['my_post_key']))
    {
        flash_message('bad key', 'error');
        admin_redirect('index.php?module=config-plugins&amp;action=patches');
    }

    $patch = intval($mybb->input['patch']);

    if($patch > 0)
    {
        $db->update_query('patches',
                          array('pdate' => 1,
                                'psize' => 1),
                          "pid={$patch}");

        flash_message('success', 'success');
        admin_redirect('index.php?module=config-plugins&amp;action=patches');
    }

    flash_message('patch not specified', 'error');
    admin_redirect('index.php?module=config-plugins&amp;action=patches');
}

/**
 * Deactivate a patch
 */
function patches_page_deactivate()
{
    global $mybb, $db;

    if(!verify_post_check($mybb->input['my_post_key']))
    {
        flash_message('bad key', 'error');
        admin_redirect('index.php?module=config-plugins&amp;action=patches');
    }

    $patch = intval($mybb->input['patch']);

    if($patch > 0)
    {
        $db->update_query('patches',
                          array('pdate' => 0,
                                'psize' => 0),
                          "pid={$patch}");

        flash_message('success', 'success');
        admin_redirect('index.php?module=config-plugins&amp;action=patches');
    }

    flash_message('patch not specified', 'error');
    admin_redirect('index.php?module=config-plugins&amp;action=patches');
}

/**
 * Apply a patch
 */
function patches_page_apply($revert=false)
{
    global $mybb, $db, $PL;

    if(!verify_post_check($mybb->input['my_post_key']))
    {
        flash_message('bad key', 'error');
        admin_redirect('index.php?module=config-plugins&amp;action=patches');
    }

    $file = patches_normalize_file($mybb->input['file']);

    if($file)
    {
        $edits = array();

        if(!$revert)
        {
            $dbfile = $db->escape_string($file);

            $query = $db->simple_select('patches',
                                        '*',
                                        "pfile='{$dbfile}' AND psize > 0");

            while($row = $db->fetch_array($query))
            {
                $search = patches_normalize_search($row['psearch']);

                $edits[] = array(
                    'search' => $search,
                    'before' => $row['pbefore'],
                    'after' => $row['pafter'],
                    'replace' => $row['preplace'],
                    'patchid' => intval($row['pid'])
                    );
            }
        }

        $PL or require_once PLUGINLIBRARY;

        $result = $PL->edit_core('patches', $file, &$edits, true);

        if($result === true)
        {
            $update = array(
                'psize' => max(@filesize(MYBB_ROOT.$file), 1),
                'pdate' => max(@filemtime(MYBB_ROOT.$file), 1)
                );

            $db->update_query('patches',
                              $update,
                              "pfile='{$dbfile}' AND psize > 0");

            flash_message('success', 'success');
            admin_redirect('index.php?module=config-plugins&amp;action=patches');
        }

        else if(is_string($result))
        {
            flash_message('file not writable', 'error');
            admin_redirect('index.php?module=config-plugins&amp;action=patches');
        }

        else
        {
            patches_page_debug($edits);
        }
    }

    flash_message('file not specified', 'error');
    admin_redirect('index.php?module=config-plugins&amp;action=patches');
}

/**
 * Debug patch
 */
function patches_page_debug($edits)
{
    echo '<pre>'.htmlspecialchars(print_r($edits, true)).'</pre>';
    exit;
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

/**
 * Normalize file name
 */
function patches_normalize_file($file)
{
    $file = trim($file);
    $file = realpath(MYBB_ROOT.$file);
    $root = realpath(MYBB_ROOT).'/';

    if(strpos($file, $root) === 0)
    {
        return substr($file, strlen($root));
    }

    // file outside MYBB_ROOT
    return false;
}

/* --- End of file. --- */
?>