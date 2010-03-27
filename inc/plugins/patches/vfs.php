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

class patches_vfs
{
}

/**
 * Identify a file name to be patched.
 *
 */
function patches_find_file($file)
{
    $realfile = realpath(MYBB_ROOT.$file);

    // If the file isn't found directly, chomp off the first path element.
    if(!$realfile)
    {
        $file = explode('/', $file, 2);
        $file = $file[1];

        $realfile = realpath(MYBB_ROOT.$file);
    }

    // Check if we found a file, and not a directory,
    // and also if it's really located within the MYBB_ROOT.
    $realpath = realpath(MYBB_ROOT).'/';

    if($realfile && is_file($realfile) &&
       strncmp($realpath, $realfile, strlen($realpath)) == 0)
    {
        return substr($realfile, strlen($realpath));
    }
}

/* --- End of file. --- */
?>