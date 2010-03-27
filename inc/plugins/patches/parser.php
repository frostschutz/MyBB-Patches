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

/* --- Classes: --- */

class PatchesParser
{
    /**
     * Errors
     */
    private $e = array();

    /**
     * Comments
     */
    private $c = array();

    /**
     * Parse
     */
    private $p = array();

    function parse($patch, $strip)
    {
        // read file
        $lines = file($patch);

        // parse loop
        $file = 0;
        $hunk = 0;
        $i = 0;

        while($i < count($lines))
        {
            $line = $lines[$i];

            // diff command, followed by new file section and hunk?
            if(strncmp($line, 'diff ', 5) == 0 &&
               $i+3 < count($lines) &&
               strncmp($lines[$i+1], '--- ', 4) == 0 &&
               strncmp($lines[$i+2], '+++ ', 4) == 0 &&
               strncmp($lines[$i+3], '@@ -', 4) == 0)
            {
                // do nothing for now

                // fallthrough to next line
                $i++;
                $line = $lines[$i];
            }

            // new file section, followed by hunk?
            if(strncmp($line, '--- ', 4) == 0 &&
               $i+2 < count($lines) &&
               strncmp($lines[$i+1], '+++ ', 4) == 0 &&
               strncmp($lines[$i+2], '@@ -', 4) == 0)
            {
                // get the source file
                strtok($line, " \t");
                $from_file_name = strtok(" \t");
                $from_file = patches_find_file($from_file_name);

                if(!$from_file)
                {
                    $this->e[$i] = "Source file not found: {$from_file_name}";
                }

                // go to the next line (target file)
                $i += 1;
                $line = $lines[$i];

                // get the target file (from the next line)
                strtok($line, " \t");
                $to_file_name = strtok(" \t");
                $to_file = patches_find_file($to_file_name);

                if(!$to_file)
                {
                    $this->e[$i] = "Target file not found: {$to_file_name}";
                }

                // set new file name
                if($from_file && $from_file == $to_file)
                {
                    $file = $from_file;
                }

                else
                {
                    $file = 0;
                }

                // fallthrough to next line
                $i++;
                $line = $lines[$i];
            }

            // new hunk?
            if(strncmp($line, '@@ -', 4) == 0)
            {
                // check if previous hunk was empty
                if($hunk && $parse[$file][$hunk]
                   && $parse[$file][$hunk]['del'] == 0
                   && $parse[$file][$hunk]['ins'] == 0)
                {
                    $this->e[$hunkline] = "Hunk #{$hunk} is empty.";
                }

                // create a new hunk.
                $hunk++;
                $hunkline = $i;

                if(!$file)
                {
                    $this->e[$i] = "Hunk #{$hunk} has no file.";
                }

                $parse[$file][$hunk]['del'] = 0;
                $parse[$file][$hunk]['ins'] = 0;

                // parse the hunk line
                $line = rtrim($line);

                $from_a = false;
                $from_b = false;
                $to_a = false;
                $to_b = false;

                if(sscanf($line, '@@ -%u,%u +%u,%u @@',
                          $from_a, $from_b, $to_a, $to_b) == 4
                   || sscanf($line, '@@ -%u,%u +%u @@',
                             $from_a, $from_b, $to_a) == 3
                   || sscanf($line, '@@ -%u +%u,%u @@',
                             $from_a, $to_a, $to_b) == 3
                   || sscanf($line, '@@ -%u +%u @@',
                             $from_a, $to_a) == 2)
                {
                    $parse[$file][$hunk]['from_a'] = $from_a;
                    $parse[$file][$hunk]['from_b'] = $from_b;
                    $parse[$file][$hunk]['to_a'] = $to_a;
                    $parse[$file][$hunk]['to_b'] = $to_b;
                }

                else
                {
                    $this->e[$i] = "Hunk #{$hunk} line range specification invalid.";
                }
            }

            // context line?
            else if($line[0] == ' ')
            {
                $parse[$file][$hunk]['diff'][] = $line;
            }

            // remove line?
            else if($line[0] == '-')
            {
                $parse[$file][$hunk]['del']++;
                $parse[$file][$hunk]['diff'][] = $line;
            }

            // insert line?
            else if($line[0] == '+')
            {
                $parse[$file][$hunk]['ins']++;
                $parse[$file][$hunk]['diff'][] = $line;
            }

            // missing end of line?
            else if(rtrim($line) == '\\ No newline at end of file')
            {
                $this->c[$i] = 'No newline at end of file: '.$file;
                $parse[$file][$hunk]['newline'] = 1;
            }

            // anything else must be a comment such as 'only in' or 'files differ'.
            else if(strncmp($line, 'Only in ', 8) == 0 ||
                    strncmp($line, 'Files ', 6) == 0)
            {
                // ignore as well
                $this->c[$i] = rtrim($line);
            }

            else
            {
                $this->e[$i] = "patch malformed: {$line}";
            }

            $i += 1;
        }

        // check if there was crap before a hunk
        if($parse[0][0])
        {
            $this->e[0] = 'patch does not start with hunk';
        }

        // check if last hunk was empty
        if($hunk && $parse[$file][$hunk]
           && $parse[$file][$hunk]['del'] == 0
           && $parse[$file][$hunk]['ins'] == 0)
        {
            $this->e[$hunkline] = 'Hunk #{$hunk} is empty.';
        }

        return $parse;
    }
}

/* --- End of file. --- */
?>
