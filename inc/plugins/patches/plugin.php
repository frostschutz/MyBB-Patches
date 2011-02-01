<?php
/**
 * This file is part of Patches plugin for MyBB.
 * Copyright (C) 2011 Andreas Klauer <Andreas.Klauer@metamorpher.de>
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

define('PATCHES_URL', 'index.php?module=config-plugins&amp;action=patches');

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
    global $lang;

    $lang->load('patches');

    return array(
        'name'          => $lang->patches,
        'description'   => $lang->patches_desc,
        'website'       => 'http://mods.mybb.com/view/patches',
        'author'        => 'Andreas Klauer',
        'authorsite'    => 'mailto:Andreas.Klauer@metamorpher.de',
        'version'       => '1.0',
        'guid'          => '4e29f86eedf8c26540324e2396f8b43f',
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

    patches_depend();

    if(!$db->table_exists('patches'))
    {
        $collation = $db->build_create_table_collation();
        $prefix = TABLE_PREFIX;

        switch($db->type)
        {
            case 'sqlite':
                $quote = '"';
                $primary = 'INTEGER NOT NULL';
                break;

            case 'postgres':
                $quote = '"';
                $primary = 'SERIAL NOT NULL';
                break;

            default:
                // Assume MySQL
                $quote = '`';
                $primary = 'INTEGER NOT NULL AUTO_INCREMENT';
        }

        $db->write_query("
            CREATE TABLE {$quote}{$prefix}patches{$quote}
            (
                pid {$primary},
                ptitle VARCHAR(100) NOT NULL,
                pdescription VARCHAR(200),
                pfile VARCHAR(150) NOT NULL,
                psize BIGINT NOT NULL,
                pdate BIGINT NOT NULL,
                psearch TEXT NOT NULL,
                pbefore TEXT,
                pafter TEXT,
                preplace INTEGER NOT NULL,
                PRIMARY KEY (pid)
            ) {$collation}");

        $db->write_query("CREATE INDEX pfilesize
                          ON {$quote}{$prefix}patches{$quote}
                          (pfile, psize)");
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
    patches_depend();
}

/**
 * Deactivate the plugin.
 */
function patches_deactivate()
{
    // do nothing
}

/* --- Helpers: --- */

/**
 * Plugin Dependencies
 */
function patches_depend()
{
    global $lang, $PL;

    $lang->load('patches');

    if(!file_exists(PLUGINLIBRARY))
    {
        flash_message($lang->patches_PL, 'error');
        admin_redirect('index.php?module=config-plugins');
    }

    $PL or require_once PLUGINLIBRARY;

    if($PL->version < 2)
    {
        flash_message($lang->patches_PL_old, 'error');
        admin_redirect("index.php?module=config-plugins");
    }
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
    $root = realpath(MYBB_ROOT);

    if(strpos($file, $root.'/') === 0
       || strpos($file, $root.'\\') === 0) // Windows :-(
    {
        return substr($file, strlen($root)+1);
    }

    // file outside MYBB_ROOT
    return false;
}

/* --- Hook functions: --- */

/**
 * Add Patches tab on the plugins page.
 */
function patches_tabs_start($arguments)
{
    global $mybb, $lang;

    $lang->load('patches');

    if($mybb->input['module'] == 'config-plugins')
    {
        $arguments['patches'] = array('title' => $lang->patches,
                                      'description' => $lang->patches_tab_desc,
                                      'link' => PATCHES_URL);
    }
}

/**
 * Handle active Patches tab case on the plugins page.
 */
function patches_plugins_begin()
{
    global $mybb, $lang, $page;

    if($mybb->input['action'] == 'patches')
    {
        patches_depend();

        $lang->load('patches');
        $page->add_breadcrumb_item($lang->patches, PATCHES_URL);

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

        switch($mybb->input['mode'])
        {
            case 'activate':
                patches_action_activate();
                break;

            case 'deactivate':
                patches_action_deactivate();
                break;

            case 'apply':
                patches_action_apply();
                break;

            case 'revert':
                patches_action_apply(true);
                break;

            case 'delete':
                patches_action_delete();
                break;

            case 'preview':
                // indirect call to patches_page_preview():
                patches_action_apply(false, true);
                break;

            case 'edit':
                patches_page_edit();
                break;

            default:
                patches_page();
                break;
        }
    }
}

/* --- Output functions: --- */

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
 * Output preview
 */
function patches_output_preview($file, $search)
{
    global $page, $PL;
    $PL or require_once PLUGINLIBRARY;

    $PL->edit_core('patches', $file, $search,
                   false, // do not apply
                   $debug);

    $table = new Table;

    $ins = '/* <strong style="color: green">+</strong> */ ';
    $del = '/* <strong style="color: red">-</strong> /* ';

    foreach($debug as $result)
    {
        $error = '';

        if($result['error'])
        {
            $error = htmlspecialchars($result['error']);

            if($result['patchid'] && $result['patchtitle'])
            {
                $editurl = $PL->url_append(PATCHES_URL,
                                           array('mode' => 'edit',
                                                 'patch' => $result['patchid']));

                $error = "<a href=\"{$editurl}\">"
                    .htmlspecialchars($result['patchtitle'])."</a>: {$error}";
            }

            $error = "<img src=\"styles/{$page->style}/images/icons/error.gif\" /> {$error}";
        }

        $before = $after = '';

        if($result['before'])
        {
            $before = '<ins>'
                .$PL->_comment($ins, htmlspecialchars($result['before']))
                .'</ins>';
        }

        if($result['after'])
        {
            $after = '<ins>'
                .$PL->_comment($ins, htmlspecialchars($result['after']))
                .'</ins>';
        }

        if(is_string($result['replace']))
        {
            $after = '<ins>'
                .$PL->_comment($ins, htmlspecialchars($result['replace']))
                .'</ins>'
                .$after;
        }

        else if($result['replace'] && !$result['after'])
        {
            $after = "<ins>{$ins}</ins>";
        }

        $rows = 0;

        foreach((array)$result['matches'] as $match)
        {
            $rows++;

            // Highlight the code.
            $code = $match[2];
            $start = 0;
            $hlcode = array();

            foreach($result['search'] as $needle)
            {
                $oldstart = $start;

                // Find the needle.
                $start = strpos($code, $needle, $oldstart);

                // Code between previous and current needle
                $snippets[] = htmlspecialchars(substr($code, $oldstart, $start-$oldstart));

                // Highlight the needle
                $snippets[] = '<strong style="background:#ff0">'
                    .htmlspecialchars(substr($code, $start, strlen($needle)))
                    .'</strong>';

                // Continue after needle
                $start += strlen($needle);
            }

            // Code after the last needle
            $snippets[] = htmlspecialchars(substr($code, $start));

            $code = implode('', $snippets);

            if($result['replace'] || is_string($result['replace']))
            {
                $code = $PL->_comment($del, $code);
                $code = "<del>{$code}</del>";
            }

            $table->construct_cell("{$error}<pre>{$before}{$code}{$after}</pre>");
            $table->construct_row();
        }

        if(!$rows && $error)
        {
            $table->construct_cell($error);
            $table->construct_row();
        }
    }

    $table->output('Preview changes to '.htmlspecialchars($file));
}

/* --- Actions: --- */

/**
 * Activate a patch
 */
function patches_action_activate()
{
    global $mybb, $db, $lang;

    $lang->load('patches');

    if(!verify_post_check($mybb->input['my_post_key']))
    {
        flash_message($lang->patches_error_key, 'error');
        admin_redirect(PATCHES_URL);
    }

    $patch = intval($mybb->input['patch']);

    if($patch > 0)
    {
        $db->update_query('patches',
                          array('pdate' => 1,
                                'psize' => 1),
                          "pid={$patch}");

        flash_message($lang->patches_activated, 'success');
        admin_redirect(PATCHES_URL);
    }

    flash_message($lang->patches_error, 'error');
    admin_redirect(PATCHES_URL);
}

/**
 * Deactivate a patch
 */
function patches_action_deactivate()
{
    global $mybb, $db, $lang;

    $lang->load('patches');

    if(!verify_post_check($mybb->input['my_post_key']))
    {
        flash_message($lang->patches_error_key, 'error');
        admin_redirect(PATCHES_URL);
    }

    $patch = intval($mybb->input['patch']);

    if($patch > 0)
    {
        $db->update_query('patches',
                          array('psize' => 0),
                          "pid={$patch}");

        flash_message($lang->patches_deactivated, 'success');
        admin_redirect(PATCHES_URL);
    }

    flash_message($lang->patches_error, 'error');
    admin_redirect(PATCHES_URL);
}

/**
 * Apply a patch
 */
function patches_action_apply($revert=false, $preview=false)
{
    global $mybb, $db, $lang, $PL;

    $lang->load('patches');

    if(!verify_post_check($mybb->input['my_post_key']))
    {
        flash_message($lang->patches_error_key, 'error');
        admin_redirect(PATCHES_URL);
    }

    $file = patches_normalize_file($mybb->input['file']);
    $dbfile = $db->escape_string($file);

    if($file)
    {
        $edits = array();

        if(!$revert)
        {
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
                    'replace' => intval($row['preplace']),
                    'patchid' => intval($row['pid']),
                    'patchtitle' => $row['ptitle'],
                    );
            }
        }

        $PL or require_once PLUGINLIBRARY;

        if($preview)
        {
            patches_page_preview($file, $edits);
        }

        $result = $PL->edit_core('patches', $file, $edits, true, $debug);

        if($result === true)
        {
            // Update deactivated patches:
            $db->update_query('patches',
                              array('pdate' => 0),
                              "pfile='{$dbfile}' AND psize=0");

            // Update activated patches:
            $update = array(
                'psize' => $revert ? 1 : max(@filesize(MYBB_ROOT.$file), 1),
                'pdate' => $revert ? 1 : max(@filemtime(MYBB_ROOT.$file), 1),
                );

            $db->update_query('patches',
                              $update,
                              "pfile='{$dbfile}' AND psize!=0");

            flash_message($lang->patches_applied, 'success');
            admin_redirect(PATCHES_URL);
        }

        else if(is_string($result))
        {
            flash_message($lang->patches_error_write, 'error');
            admin_redirect(PATCHES_URL);
        }

        else
        {
            patches_action_debug($debug);
        }
    }

    flash_message($lang->patches_error_file, 'error');
    admin_redirect(PATCHES_URL);
}

/**
 * Debug patch
 */
function patches_action_debug($edits)
{
    global $mybb, $db, $lang, $page, $PL;
    $PL or require_once PLUGINLIBRARY;

    $lang->load('patches');

    $errors = $lang->patches_debug;
    $errors .= '<ul>';

    foreach($edits as $edit)
    {
        if($edit['error'] && $edit['patchid'])
        {
            $editurl = $PL->url_append(PATCHES_URL,
                                       array('mode' => 'edit',
                                             'patch' => $edit['patchid']));

            $errors .= "<li>"
                ."<a href=\"{$editurl}\">"
                .htmlspecialchars($edit['patchtitle'])
                ."</a>: "
                .htmlspecialchars($edit['error'])
                ."</li>\n";
        }
    }

    $errors .= '</ul>';

    flash_message($errors, 'error');
    admin_redirect(PATCHES_URL);
}

/**
 * Delete patch
 */
function patches_action_delete()
{
    global $mybb, $db, $lang;

    $lang->load('patches');

    if(!verify_post_check($mybb->input['my_post_key']))
    {
        flash_message($lang->patches_error_key, 'error');
        admin_redirect(PATCHES_URL);
    }

    $patch = intval($mybb->input['patch']);

    if($patch)
    {
        // delete patch
        $db->delete_query('patches', "pid={$patch}");
        flash_message($lang->patches_deleted, 'success');
    }

    admin_redirect(PATCHES_URL);
}

/* --- Page functions: --- */

/**
 * The patches main page.
 */
function patches_page()
{
    global $mybb, $db, $lang, $page, $PL;
    $PL or require_once PLUGINLIBRARY;

    patches_output_header();
    patches_output_tabs();

    $table = new Table;
    $table->construct_header($lang->patches_patch);
    $table->construct_header($lang->patches_controls,
                             array('colspan' => 3,
                                   'class' => 'align_center',
                                   'width' => '30%'));

    $query = $db->simple_select('patches', 'pid,pfile,psize,pdate,ptitle,pdescription', '',
                                array('order_by' => 'pfile,ptitle,pid'));

    $file = '';

    while($row = $db->fetch_array($query))
    {
        if($row['pfile'] != $file)
        {
            $file = $row['pfile'];

            $reverturl = $PL->url_append(PATCHES_URL,
                                         array('mode' => 'revert',
                                               'file' => $row['pfile'],
                                               'my_post_key' => $mybb->post_code));
            $applyurl = $PL->url_append(PATCHES_URL,
                                        array('mode' => 'apply',
                                              'file' => $row['pfile'],
                                              'my_post_key' => $mybb->post_code));
            $previewurl = $PL->url_append(PATCHES_URL,
                                          array('mode' => 'preview',
                                                'file' => $row['pfile'],
                                                'my_post_key' => $mybb->post_code));

            $table->construct_cell('<strong>'.htmlspecialchars($row['pfile']).'</strong> '
                                   ."<a href=\"{$previewurl}\"><img src=\"styles/{$page->style}/images/icons/find.gif\" alt=\"{$lang->patches_preview}\" title=\"{$lang->patches_preview_active}\" /></a>");
            $table->construct_cell("<strong><a href=\"{$reverturl}\">{$lang->patches_revert}</a></strong>",
                                   array('class' => 'align_center'));
            $table->construct_cell("<strong><a href=\"{$applyurl}\">{$lang->patches_apply}</a></strong>",
                                   array('class' => 'align_center',
                                         'width' => '15%'));
            $table->construct_row();
        }

        $editurl = $PL->url_append(PATCHES_URL,
                                   array('mode' => 'edit',
                                         'patch' => $row['pid']));

        $delete = '';

        if(!$row['psize'] && !$row['pdate'])
        {
            $deleteurl = $PL->url_append(PATCHES_URL,
                                         array('mode' => 'delete',
                                               'patch' => $row['pid'],
                                               'my_post_key' => $mybb->post_code));
            $delete = " <a href=\"{$deleteurl}\"><img src=\"styles/{$page->style}/images/icons/delete.gif\" alt=\"{$lang->patches_delete}\" title=\"{$lang->patches_delete}\" /></a>";
        }

        $table->construct_cell("<div style=\"padding-left: 40px;\"><a href=\"{$editurl}\">"
                               .htmlspecialchars($row['ptitle'])
                               .'</a>'
                               .$delete
                               .'<br />'
                               .htmlspecialchars($row['pdescription'])
                               .'</div>');

        if(!$row['psize'])
        {
            $activateurl = $PL->url_append(PATCHES_URL,
                                           array('mode' => 'activate',
                                                 'patch' => $row['pid'],
                                                 'my_post_key' => $mybb->post_code));

            $table->construct_cell("<a href=\"{$activateurl}\">{$lang->patches_activate}</a>",
                                   array('class' => 'align_center',
                                         'width' => '15%'));
        }

        else
        {
            $deactivateurl = $PL->url_append(PATCHES_URL,
                                             array('mode' => 'deactivate',
                                                   'patch' => $row['pid'],
                                                   'my_post_key' => $mybb->post_code));

            $table->construct_cell("<a href=\"{$deactivateurl}\">{$lang->patches_deactivate}</a>",
                                   array('class' => 'align_center',
                                         'width' => '15%'));
        }

        if(!$row['psize'] && !$row['pdate'])
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/no_change.gif\" alt=\"{$lang->patches_nochange}\" />",
                                   array('class' => 'align_center'));
        }

        else if(intval($row['psize']) === @filesize(MYBB_ROOT.$file) &&
                intval($row['pdate']) === @filemtime(MYBB_ROOT.$file))
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/tick.gif\" alt=\"{$lang->patches_tick}\" />",
                                   array('class' => 'align_center'));
        }

        else if(intval($row['psize']) == 0)
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/warning.gif\" alt=\"{$lang->patches_warning}\" />",
                                   array('class' => 'align_center'));
        }

        else
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/cross.gif\" alt=\"{$lang->patches_cross}\" />",
                                   array('class' => 'align_center'));
        }

        $table->construct_row();
    }

    $createurl = $PL->url_append(PATCHES_URL, array('mode' => 'edit'));

    $table->construct_cell("<a href=\"{$createurl}\">{$lang->patches_new}</a>",
                           array('colspan' => 5,
                                 'class' => 'align_center'));
    $table->construct_row();

    $table->output($lang->patches);

    // legend
    echo "
<ul class=\"smalltext\">
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/no_change.gif)\" />
        {$lang->patches_legend_nochange}
    </li>
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/warning.gif)\" />
        {$lang->patches_legend_warning}
    </li>
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/tick.gif)\" />
        {$lang->patches_legend_tick}
    </li>
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/cross.gif)\" />
        {$lang->patches_legend_cross}
    </li>
</ul>
";

    $page->output_footer();
}

/**
 * Patches edit page.
 */
function patches_page_edit()
{
    global $mybb, $db, $lang, $page, $PL;
    $PL or require_once PLUGINLIBRARY;

    $lang->load('patches');

    $patch = intval($mybb->input['patch']);

    if($mybb->request_method == 'post')
    {
        if($mybb->input['cancel'])
        {
            admin_redirect(PATCHES_URL);
        }

        $patch = intval($mybb->input['patch']);

        // validate input

        $errors = array();

        $file = patches_normalize_file($mybb->input['pfile']);

        if(!is_file(MYBB_ROOT.$file))
        {
            $errors[] = $lang->patches_error_file;
        }

        $title = trim($mybb->input['ptitle']);

        if(!$title)
        {
            $errors[] = $lang->patches_error_title;
        }

        $description = trim($mybb->input['pdescription']);
        // description is optional

        $search = patches_normalize_search($mybb->input['psearch']);

        if(!$search)
        {
            $errors[] = $lang->patches_error_search;
        }

        $search = implode("\n", $search);

        $before = trim($mybb->input['pbefore']);
        $after = trim($mybb->input['pafter']);
        $replace = intval($mybb->input['preplace']);

        if(!($before || $after || $replace))
        {
            $errors[] = $lang->patches_error_edit;
        }

        if(!$errors && !$mybb->input['preview'])
        {
            $data = array(
                'ptitle' => $db->escape_string($title),
                'pdescription' => $db->escape_string($description),
                'psearch' => $db->escape_string($search),
                'pbefore' => $db->escape_string($before),
                'pafter' => $db->escape_string($after),
                'preplace' => $replace,
                'pfile' => $db->escape_string($file),
                'pdate' => 1,
                );

            if($patch)
            {
                $update = $db->update_query('patches',
                                            $data,
                                            "pid={$patch}");
            }

            if(!$update)
            {
                $data['psize'] = 0;
                $data['pdate'] = 0;
                $db->insert_query('patches', $data);
            }

            flash_message($lang->patches_saved, 'success');
            admin_redirect(PATCHES_URL);
        }

        // Show a preview
        $preview = true;
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

    // Header stuff.
    $editurl = $PL->url_append(PATCHES_URL, array('mode' => 'edit'));

    $page->add_breadcrumb_item($lang->patches_edit, $editurl);

    patches_output_header();
    patches_output_tabs();

    if($errors)
    {
        $page->output_inline_error($errors);
    }

    else if($preview)
    {
        patches_output_preview($file,
                               array('search' => explode("\n", $search),
                                     'before' => $before,
                                     'after' => $after,
                                     'replace' => $replace));
    }

    $form = new Form($editurl, 'post');
    $form_container = new FormContainer('Edit Patch');

    echo $form->generate_hidden_field('patch',
                                      intval($mybb->input['patch']),
                                      array('id' => 'patch'));

    $form_container->output_row(
        $lang->patches_filename,
        $lang->patches_filename_desc,
        $form->generate_text_box('pfile',
                                 $mybb->input['pfile'],
                                 array('id' => 'pfile')),
        'pfile'
        );

    $form_container->output_row(
        $lang->patches_title,
        $lang->patches_title_desc,
        $form->generate_text_box('ptitle',
                                 $mybb->input['ptitle'],
                                 array('id' => 'ptitle')),
        'ptitle'
        );

    $form_container->output_row(
        $lang->patches_description,
        $lang->patches_description_desc,
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
        $lang->patches_search,
        $lang->patches_search_desc,
        $form->generate_text_area('psearch',
                                  $mybb->input['psearch'],
                                  array('id' => 'psearch')),
        'psearch'
        );

    $form_container->output_row(
        $lang->patches_before,
        $lang->patches_before_desc,
        $form->generate_text_area('pbefore',
                                  $mybb->input['pbefore'],
                                  array('id' => 'pbefore')),
        'pbefore'
        );

    $form_container->output_row(
        $lang->patches_after,
        $lang->patches_after_desc,
        $form->generate_text_area('pafter',
                                  $mybb->input['pafter'],
                                  array('id' => 'pafter')),
        'pafter'
        );

    // set to 0, otherwise the yes no defaults to yes...
    $mybb->input['preplace'] = intval($mybb->input['preplace']);

    $form_container->output_row(
        $lang->patches_replace,
        $lang->patches_replace_desc,
        $form->generate_yes_no_radio('preplace',
                                     $mybb->input['preplace']),
        'preplace');

    $form_container->end();

    $buttons[] = $form->generate_submit_button($lang->patches_save);
    $buttons[] = $form->generate_submit_button($lang->patches_preview,
                                               array('name' => 'preview'));

    if(intval($mybb->input['patch']))
    {
        $buttons[] = $form->generate_submit_button($lang->patches_delete,
                                                   array('name' => 'delete'));
    }

    $buttons[] = $form->generate_submit_button($lang->patches_cancel,
                                               array('name' => 'cancel'));

    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

/**
 * Preview patch
 */
function patches_page_preview($file, $debug)
{
    global $lang, $page;

    $page->add_breadcrumb_item($lang->patches_preview, PATCHES_URL);

    patches_output_header();
    patches_output_tabs();

    patches_output_preview($file, $debug);

    $page->output_footer();
}

/* --- End of file. --- */
?>
