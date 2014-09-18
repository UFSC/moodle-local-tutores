<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->dirroot . '/local/tutores/lib.php');

function get_category_context_from_curso_ufsc($curso_ufsc) {
    global $DB;

    $categoryid = $DB->get_field('course_categories', 'id', array('idnumber' => "curso_{$curso_ufsc}"));
    return context_coursecat::instance($categoryid);
}

function get_curso_ufsc_id() {
    $categoryid = required_param('categoryid', PARAM_INT);
    $category = coursecat::get($categoryid);

    if (!$category->idnumber) {
        return false;
    }

    return str_replace('curso_', '', $category->idnumber, $count);
}