<?php

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2014032500; // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2013110500; // Requires this Moodle version
$plugin->component = 'local_tutores'; // Full name of the plugin (used for diagnostics)
$plugin->dependencies = array('local_academico' => 2012081700);

$plugin->maturity  = MATURITY_BETA; // this version's maturity level
