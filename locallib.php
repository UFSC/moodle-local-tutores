<?php

require_once($CFG->libdir . '/moodlelib.php');
require_once('middlewarelib.php');

function create_grupo_tutoria($curso_ufsc, $nome) {
	$middleware = Academico::singleton();
	$sql = "INSERT INTO {$middleware->table_grupos_tutoria} (nome, curso) VALUES(?,?)";
	return $middleware->db->execute($sql, array($nome, $curso_ufsc));
}

function delete_grupo_tutoria($curso_ufsc, $grupo) {
	$middleware = Academico::singleton();
	$sql = "DELETE FROM {$middleware->table_grupos_tutoria} WHERE curso=? AND id=?";
	return $middleware->db->execute($sql, array($curso_ufsc, $grupo));	
}

function get_action_icon($url, $icon, $alt, $tooltip) {
    global $OUTPUT;
    return '<a title="' . $tooltip . '" href="' . $url . '">' .
          '<img src="' . $OUTPUT->pix_url('t/' . $icon) . '" class="iconsmall" alt="' . $alt . '" /></a> ';
}

function get_grupo_tutoria($id) {
    $middleware = Academico::singleton();
    $sql = "SELECT * FROM {$middleware->table_grupos_tutoria} WHERE id=?";
    return $middleware->db->get_record_sql($sql, array('id' => $id));
}

function get_grupos_tutoria($curso_ufsc) {
    $middleware = Academico::singleton();
    $sql = "SELECT * FROM {$middleware->table_grupos_tutoria} WHERE curso=? ORDER BY nome";
    return $middleware->db->get_records_sql($sql, array('curso' => $curso_ufsc));
}

function get_cursos_ativos_list() {
    $middleware = Academico::singleton();
    $sql = "SELECT curso, nome_sintetico FROM {$middleware->view_cursos_ativos}";
    return $middleware->db->get_records_sql_menu($sql);
}

function get_curso_ufsc_id() {
    return optional_param('curso_ufsc', null, PARAM_INT);
}


function update_grupo_tutoria($curso_ufsc, $grupo, $nome) {
	$middleware = Academico::singleton();
	$sql = "UPDATE {$middleware->table_grupos_tutoria} SET nome=? WHERE curso=? AND id=?";
	return $middleware->db->execute($sql, array($nome, $curso_ufsc, $grupo));
}

function redirect_to_gerenciar_tutores() {
	redirect(new moodle_url('/admin/tool/tutores/index.php', array('curso_ufsc' => get_curso_ufsc_id())));
}
