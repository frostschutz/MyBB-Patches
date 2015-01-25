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
        'version'       => '1.5',
        'guid'          => '4e29f86eedf8c26540324e2396f8b43f',
        'compatibility' => '18*',
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
                pmulti INTEGER NOT NULL,
                pnone INTEGER NOT NULL,
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
    global $mybb, $db, $lang;
    global $PL;

    patches_depend();

    // Confirmation step.
    if(!$mybb->input['confirm'])
    {
        $link = $PL->url_append('index.php', array(
                                    'module' => 'config-plugins',
                                    'action' => 'deactivate',
                                    'uninstall' => '1',
                                    'plugin' => 'patches',
                                    'my_post_key' => $mybb->post_code,
                                    'confirm' => '1',
                                    ));

        flash_message("{$lang->patches_plugin_uninstall} <a href=\"{$link}\">{$lang->patches_plugin_uninstall_confirm}</a>", "error");
        admin_redirect("index.php?module=config-plugins");
    }


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
    global $db;

    patches_depend();

    // Update database table:
    if(!$db->field_exists('pmulti', 'patches'))
    {
        $db->add_column('patches', 'pmulti', 'INTEGER NOT NULL');
    }

    if(!$db->field_exists('pnone', 'patches'))
    {
        $db->add_column('patches', 'pnone', 'INTEGER NOT NULL');
    }
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

    if($PL->version < 11)
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
function patches_tabs_start(&$arguments)
{
    global $mybb, $lang;

    if($mybb->input['module'] == 'config-plugins')
    {
        $lang->load('patches');

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

            case 'import':
                patches_page_import();
                break;

            case 'export':
                patches_page_export();
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
    global $page, $lang, $PL;
    $PL or require_once PLUGINLIBRARY;

    $lang->load('patches');

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

            $error = "<img src=\"styles/{$page->style}/images/icons/error.png\" /> {$error}";
        }


        if(!count($result['matches']) && $error)
        {
            $table->construct_cell($error);
            $table->construct_row();
            continue;
        }

        if(!isset($result['patchid']))
        {
            continue;
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

        foreach((array)$result['matches'] as $match)
        {
            $rows++;

            // Highlight the code.
            $code = $match[2];
            $start = 0;
            $snippets = array();

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
    }

    $preview_file = $lang->sprintf($lang->patches_preview_file,
                                   htmlspecialchars($file));

    $table->output($preview_file);
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

    $patch = (int)$mybb->input['patch'];

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

    $patch = (int)$mybb->input['patch'];

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
                    'replace' => (int)$row['preplace'],
                    'multi' => (int)$row['pmulti'],
                    'none' => (int)$row['pnone'],
                    'patchid' => (int)$row['pid'],
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

    $patch = (int)$mybb->input['patch'];

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

    $exportids = array();

    $table = new Table;
    $table->construct_header($lang->patches_controls,
                             array('colspan' => 2,
                                   'class' => 'align_center'));
    $table->construct_header($lang->patches_patch,
                             array('width' => '100%'));

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

            $table->construct_cell("<strong><a href=\"{$reverturl}\">{$lang->patches_revert}</a></strong>",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));
            $table->construct_cell("<strong><a href=\"{$applyurl}\">{$lang->patches_apply}</a></strong>",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));
            $table->construct_cell('<strong>'.htmlspecialchars($row['pfile']).'</strong> '
                                   ."<a href=\"{$previewurl}\"><img src=\"styles/{$page->style}/images/icons/find.png\" alt=\"{$lang->patches_preview}\" title=\"{$lang->patches_preview_active}\" /></a>");
            $table->construct_row();
        }

        if(!$row['psize'])
        {
            $activateurl = $PL->url_append(PATCHES_URL,
                                           array('mode' => 'activate',
                                                 'patch' => $row['pid'],
                                                 'my_post_key' => $mybb->post_code));

            $table->construct_cell("<a href=\"{$activateurl}\">{$lang->patches_activate}</a>",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));
        }

        else
        {
            $deactivateurl = $PL->url_append(PATCHES_URL,
                                             array('mode' => 'deactivate',
                                                   'patch' => $row['pid'],
                                                   'my_post_key' => $mybb->post_code));

            $table->construct_cell("<a href=\"{$deactivateurl}\">{$lang->patches_deactivate}</a>",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));
        }

        if(!$row['psize'] && !$row['pdate'])
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/no_change.png\" alt=\"{$lang->patches_nochange}\" />",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));
        }

        else if((int)$row['psize'] === @filesize(MYBB_ROOT.$file) &&
                (int)$row['pdate'] === @filemtime(MYBB_ROOT.$file))
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/tick.png\" alt=\"{$lang->patches_tick}\" />",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));

            $exportids[] = $row['pid'];
        }

        else if((int)$row['psize'] == 0)
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/warning.png\" alt=\"{$lang->patches_warning}\" />",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));
        }

        else
        {
            $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/cross.png\" alt=\"{$lang->patches_cross}\" />",
                                   array('class' => 'align_center',
                                         'style' => 'white-space: nowrap;'));
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
            $delete = " <a href=\"{$deleteurl}\"><img src=\"styles/{$page->style}/images/icons/delete.png\" alt=\"{$lang->patches_delete}\" title=\"{$lang->patches_delete}\" /></a>";
        }

        $table->construct_cell("<div style=\"padding-left: 40px;\"><a href=\"{$editurl}\">"
                               .htmlspecialchars($row['ptitle'])
                               .'</a>'
                               .$delete
                               .'<br />'
                               .htmlspecialchars($row['pdescription'])
                               .'</div>');

        $table->construct_row();
    }

    $createurl = $PL->url_append(PATCHES_URL, array('mode' => 'edit'));
    $importurl = $PL->url_append(PATCHES_URL, array('mode' => 'import'));
    $exporturl = $PL->url_append(PATCHES_URL, array('mode' => 'export',
                                                    'patch' => implode(",", $exportids)));

    $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/increase.png\" /> <a href=\"{$importurl}\">{$lang->patches_import}</a> ",
                           array('class' => 'align_center',
                                 'style' => 'white-space: nowrap;'));
    $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/decrease.png\" /> <a href=\"{$exporturl}\">{$lang->patches_export}</a>",
                           array('class' => 'align_center',
                                 'style' => 'white-space: nowrap;'));
    $table->construct_cell("<img src=\"styles/{$page->style}/images/icons/custom.png\" /> <a href=\"{$createurl}\">{$lang->patches_new}</a> ",
                           array('class' => 'align_center'));

    $table->construct_row();

    $table->output($lang->patches);

    // legend
    echo "
<ul class=\"smalltext\">
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/no_change.png)\" />
        {$lang->patches_legend_nochange}
    </li>
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/warning.png)\" />
        {$lang->patches_legend_warning}
    </li>
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/tick.png)\" />
        {$lang->patches_legend_tick}
    </li>
    <li style=\"list-style-image: url(styles/{$page->style}/images/icons/cross.png)\" />
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

    $patch = (int)$mybb->input['patch'];

    if($mybb->request_method == 'post')
    {
        if($mybb->input['cancel'])
        {
            admin_redirect(PATCHES_URL);
        }

        $patch = (int)$mybb->input['patch'];

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
        $replace = (int)$mybb->input['preplace'];
        $multi = (int)$mybb->input['pmulti'];
        $none = (int)$mybb->input['pnone'];

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
                'pmulti' => $multi,
                'pnone' => $none,
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
                                    'pfile,ptitle,pdescription,psearch,pbefore,pafter,preplace,pmulti,pnone',
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
                                     'replace' => $replace,
                                     'multi' => $multi,
                                     'none' => $none,
                                     'patchid' => $patch,
                                     'patchtitle' => $title));
    }

    $form = new Form($editurl, 'post');
    $form_container = new FormContainer('Edit Patch');

    echo $form->generate_hidden_field('patch',
                                      (int)$mybb->input['patch'],
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
    $mybb->input['preplace'] = (int)$mybb->input['preplace'];
    $mybb->input['pmulti'] = (int)$mybb->input['pmulti'];
    $mybb->input['pnone'] = (int)$mybb->input['pnone'];

    $form_container->output_row(
        $lang->patches_replace,
        $lang->patches_replace_desc,
        $form->generate_yes_no_radio('preplace',
                                     $mybb->input['preplace']),
        'preplace');

    $form_container->output_row(
        $lang->patches_multi,
        $lang->patches_multi_desc,
        $form->generate_yes_no_radio('pmulti',
                                     $mybb->input['pmulti']),
        'pmulti');

    $form_container->output_row(
        $lang->patches_none,
        $lang->patches_none_desc,
        $form->generate_yes_no_radio('pnone',
                                     $mybb->input['pnone']),
        'pnone');

    $form_container->end();

    $buttons[] = $form->generate_submit_button($lang->patches_save);
    $buttons[] = $form->generate_submit_button($lang->patches_preview,
                                               array('name' => 'preview'));
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

/**
 * Import patch
 */
function patches_page_import()
{
    global $mybb, $db, $lang, $page, $PL;

    $importurl = $PL->url_append(PATCHES_URL, array('mode' => 'import'));

    $page->add_breadcrumb_item($lang->patches_import, $importurl);

    if($mybb->request_method == 'post')
    {
        if($mybb->input['cancel'])
        {
            admin_redirect(PATCHES_URL);
        }

        if(@is_uploaded_file($_FILES['patches']['tmp_name']))
        {
            $contents = @file_get_contents($_FILES['patches']['tmp_name']);
            @unlink($_FILES['patches']['tmp_name']);

            if($contents)
            {
                $contents = $PL->xml_import($contents);
                $inserts = array();
                $errors = 0;

                if(is_array($contents))
                {
                    foreach($contents as $pfile => $patches)
                    {
                        if(!is_string($pfile) || !is_array($patches))
                        {
                            $errors++;
                            continue;
                        }

                        $pfile = patches_normalize_file($pfile);

                        if(!$pfile)
                        {
                            $errors++;
                            continue;
                        }

                        foreach($patches as $patch)
                        {
                            if(!is_string($patch['ptitle'])
                               || !strlen($patch['ptitle'])
                               || !is_string($patch['pdescription'])
                               || !is_string($patch['psearch'])
                               || !strlen($patch['psearch'])
                               || !is_string($patch['pbefore'])
                               || !is_string($patch['pafter'])
                               || !is_bool($patch['preplace'])
                               // multi and none were added later - ignore
                               || (!strlen($patch['pbefore'])
                                   && !strlen($patch['pafter'])
                                   && !$patch['preplace']))
                            {
                                $errors++;
                                continue;
                            }

                            $search = patches_normalize_search($patch['psearch']);

                            if(!$search)
                            {
                                $errors++;
                                continue;
                            }

                            $search = implode("\n", $search);

                            $inserts[] = array(
                                'pfile' => $db->escape_string($pfile),
                                'psize' => 0,
                                'pdate' => 0,
                                'ptitle' => $db->escape_string($patch['ptitle']),
                                'pdescription' => $db->escape_string($patch['pdescription']),
                                'psearch' => $db->escape_string($search),
                                'pbefore' => $db->escape_string($patch['pbefore']),
                                'pafter' => $db->escape_string($patch['pafter']),
                                'preplace' => ($patch['preplace'] ? '1' : '0'),
                                'pmulti' => ($patch['pmulti'] ? '1' : '0'),
                                'pnone' => ($patch['pnone'] ? '1' : '0'),
                                );
                        }
                    }

                    if(count($inserts))
                    {
                        $db->insert_query_multiple('patches', $inserts);

                        $success = $lang->sprintf($lang->patches_import_success,
                                                  count($inserts));

                        if($errors)
                        {
                            $success .= $lang->sprintf($lang->patches_import_errors,
                                                       $errors);
                        }

                        flash_message($success, 'success');
                        admin_redirect(PATCHES_URL);
                    }
                }
            }
        }

        if(is_array($inserts) || $errors)
        {
            flash_message($lang->patches_import_badfile, 'error');
        }

        else
        {
            flash_message($lang->patches_import_nofile, 'error');
        }
    }

    patches_output_header();
    patches_output_tabs();

    $table = new Table;

    $table->construct_header($lang->patches);

    $form = new Form($importurl, 'post', '', 1);

    $table->construct_cell($lang->patches_import_file
                           .'<br /><br />'
                           .$form->generate_file_upload_box("patches"));
    $table->construct_row();

    $table->output($lang->patches_import_caption);

    $buttons[] = $form->generate_submit_button($lang->patches_import_button);
    $buttons[] = $form->generate_submit_button($lang->patches_cancel,
                                               array('name' => 'cancel'));
    $form->output_submit_wrapper($buttons);

    $page->output_footer();
}

/**
 * Export patch
 */
function patches_page_export()
{
    global $mybb, $db, $lang, $page, $PL;

    $PL or require_once PLUGINLIBRARY;

    $exporturl = $PL->url_append(PATCHES_URL, array('mode' => 'export'));

    $page->add_breadcrumb_item($lang->patches_export, $exporturl);

    if($mybb->request_method == 'post')
    {
        if($mybb->input['cancel'])
        {
            admin_redirect(PATCHES_URL);
        }

        if($mybb->input['filename'])
        {
            $filename = $mybb->input['filename'];
            $filename = str_replace('/', '_', $filename);
            $filename = str_replace('\\', '_', $filename);
            $filename = str_replace('.', '_', $filename);
            $filename = "patches-{$filename}.xml";
        }

        else
        {
            $filename = "patches.xml";
        }

        if($mybb->input['patches'])
        {
            $where = array();

            foreach((array)$mybb->input['patches'] as $pid)
            {
                $where[] = $db->escape_string((string)$pid);
            }

            $where = implode("','", $where);
            $where = "pid IN ('{$where}')";

            $query = $db->simple_select("patches",
                                        "pfile,ptitle,pdescription,psearch,pbefore,pafter,preplace,pmulti,pnone",
                                        $where,
                                        array('order_by' => 'pfile,ptitle,pid'));

            $patches = array();

            while($row = $db->fetch_array($query))
            {
                $file = $row['pfile'];
                unset($row['pfile']);
                $row['preplace'] = $row['preplace'] && 1; // stupid
                $row['pmulti'] = $row['pmulti'] && 1;
                $row['pnone'] = $row['pnone'] && 1;
                $patches[$file][] = $row;
            }

            if(count($patches))
            {
                $PL->xml_export($patches, $filename, 'MyBB Patches exported {time}');
                // exit on success
            }
        }

        flash_message($lang->patches_export_error, 'error');
    }

    else if($mybb->input['patch'])
    {
        $patches = array();

        foreach(explode(",", $mybb->input['patch']) as $pid)
        {
            $patches[] = htmlspecialchars($pid);
        }
    }

    patches_output_header();
    patches_output_tabs();

    // Build list of patches
    $patches_selects = array();
    $currentfile = '';

    $query = $db->simple_select('patches', 'pfile,ptitle,pid', '',
                                array('order_by' => 'pfile,ptitle,pid'));

    while($row = $db->fetch_array($query))
    {
        if($currentfile != $row['pfile'])
        {
            $currentfile = $row['pfile'];
            $patches_selects["file{$row['pid']}"] = '&nbsp;&nbsp;&nbsp;--- '.htmlspecialchars($currentfile).' ---';
        }

        $patches_selects[$row['pid']] = htmlspecialchars($row['ptitle']);
    }

    $table = new Table;

    $table->construct_header($lang->patches);

    $form = new Form($exporturl, "post");

    $table->construct_cell($lang->patches_export_select
                           .'<br /><br />'
                           .$form->generate_select_box("patches[]", $patches_selects, $patches, array('multiple' => true, 'id' => 'patches_select')));
    $table->construct_row();

    $table->construct_cell($lang->patches_export_filename
                           .'<br /><br />'
                           .$form->generate_text_box('filename', $mybb->input['filename']));
    $table->construct_row();

    $table->output($lang->patches_export_caption);

    $buttons[] = $form->generate_submit_button($lang->patches_export_button);
    $buttons[] = $form->generate_submit_button($lang->patches_cancel,
                                               array('name' => 'cancel'));
    $form->output_submit_wrapper($buttons);

    $page->output_footer();
}

/* --- End of file. --- */
?>
