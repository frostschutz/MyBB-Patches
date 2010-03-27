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
 * Virtual File System class.
 * Loads files into memory and allows modifications to them there.
 *
 */
class PatchesVFS
{
    /**
     * Array that stores path/to/file => contents
     */
    private $vfs = array();

    /**
     * String that is the root directory of this VFS.
     * The variable contains a trailing /.
     */
    private $root;

    /**
     * Constructing the VFS with the root directory.
     */
    function __construct($root)
    {
        if($root)
        {
            $root = realpath("{$root}/");

            if($root && is_dir($root))
            {
                $this->root = $root . "/";
                return;
            }
        }

        Throw new Exception("PatchesVFS: invalid root parameter");
    }

    /**
     * Load a file into the VFS.
     *
     * Returns the (normalized) file name, or False if it couldn't be loaded.
     *
     */
    function load($file)
    {
        $realfile = realpath($this->root . $file);

        // Check if we found a file, and not a directory,
        // and also if it's really located within $this->root.
        if($realfile && is_file($realfile)
           && strncmp($this->root, $realfile, strlen($this->root)) == 0)
        {
            // Read the file and store it in the VFS.
            $key = substr($realfile, strlen($this->root));
            $value = file($realfile);

            if($key && $value)
            {
                $this->vfs[$key] = $value;

                return $key;
            }
        }
    }
}

/* --- End of file. --- */
?>