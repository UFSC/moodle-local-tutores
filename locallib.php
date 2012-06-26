<?php

require_once($CFG->libdir.'/moodlelib.php');
require_once('middlewarelib.php');

function get_action_icon($url, $icon, $alt, $tooltip) {
    global $OUTPUT;
    return '<a title="' . $tooltip . '" href="'. $url . '">' .
            '<img src="' . $OUTPUT->pix_url('t/' . $icon) . '" class="iconsmall" alt="' . $alt . '" /></a> ';
}

function get_grupos_tutoria() {
    $middleware = Academico::singleton();
    $sql = "SELECT * FROM {$middleware->table_grupos_tutoria}";
    return $middleware->db->get_records_sql($sql);
}

function get_cursos_ativos_list() {
    $middleware = Academico::singleton();
    $sql = "SELECT curso, nome_sintetico FROM {$middleware->view_cursos_ativos}";
    return $middleware->db->get_records_sql_menu($sql);
}

function get_curso_ufsc_id() {
    return filter_input(INPUT_GET, 'curso_ufsc_id', FILTER_VALIDATE_INT);
}