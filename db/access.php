<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    'tool/tutores:manage' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
        )
    )
);