<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information
 *
 * @package local_tutores
 * @copyright 2016 Roberto Silvino <roberto.silvino@ufsc.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2017051201; // The current plugin version (Date: YYYYMMDDXX)
$plugin->component = 'local_tutores'; // Full name of the plugin (used for diagnostics)
$plugin->dependencies = array('local_relationship' => 2015020100);

$plugin->maturity  = MATURITY_BETA; // this version's maturity level
