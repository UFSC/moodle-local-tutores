<?php

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2013020400; // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2012062501; // Requires this Moodle version
$plugin->component = 'local_tutores'; // Full name of the plugin (used for diagnostics)
$plugin->dependencies = array('local_academico' => 2012081700);

$plugin->maturity  = MATURITY_BETA; // this version's maturity level
