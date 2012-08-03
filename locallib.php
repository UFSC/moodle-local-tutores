<?php

require_once($CFG->libdir . '/moodlelib.php');
require_once("{$CFG->dirroot}/{$CFG->admin}/tool/tutores/middlewarelib.php");
require_once("{$CFG->dirroot}/{$CFG->admin}/tool/tutores/lib.php");

/**
 * Adiciona uma pessoa a um grupo de tutoria
 *
 * @param $grupo id do grupo de tutoria
 * @param $matricula código de matrícula do participante
 * @return bool
 */
function add_member_grupo_tutoria($grupo, $matricula) {
    $middleware = Academico::singleton();
    $sql = "INSERT INTO {$middleware->table_pessoas_funcoes_grupos_tutoria}
                        (grupo, matricula)
                 VALUES (:grupo, :matricula)";
    $params = array('grupo' => $grupo, 'matricula' => $matricula);
    return $middleware->db->execute($sql, $params);
}

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
    $sql = "SELECT * FROM {$middleware->table_grupos_tutoria} WHERE id=:id";
    return $middleware->db->get_record_sql($sql, array('id' => $id));
}

function get_grupos_tutoria($curso_ufsc) {
    $middleware = Academico::singleton();
    $sql = "SELECT * FROM {$middleware->table_grupos_tutoria} WHERE curso=:curso ORDER BY nome";
    return $middleware->db->get_records_sql($sql, array('curso' => $curso_ufsc));
}

function get_grupos_tutoria_with_members_count($curso_ufsc) {
    global $CFG;

    $middleware = Academico::singleton();
    $papeis_estudantes = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_estudantes());
    $papeis_tutores = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_tutores());

    $sql = "SELECT gt.id as grupo, gt.nome, IFNULL(estudante.quantidade, 0) as estudantes, IFNULL(tutor.quantidade, 0) as tutores
              FROM {$middleware->table_grupos_tutoria} gt
         LEFT JOIN (
                  SELECT gt.id as grupo, COUNT(*) as quantidade
                    FROM {$middleware->table_grupos_tutoria} gt
                    JOIN {$middleware->table_pessoas_funcoes_grupos_tutoria} pg
                      ON (gt.id=pg.grupo)
                    JOIN {$middleware->view_usuarios} u
                      ON (u.username=pg.matricula)
                   WHERE papel_principal IN ({$papeis_estudantes})
                GROUP BY pg.grupo
                   ) as estudante
                ON (estudante.grupo=gt.id)
         LEFT JOIN (
                  SELECT gt.id as grupo, COUNT(*) as quantidade
                    FROM {$middleware->table_grupos_tutoria} gt
                    JOIN {$middleware->table_pessoas_funcoes_grupos_tutoria} pg
                      ON (gt.id=pg.grupo)
                    JOIN {$middleware->view_usuarios} u
                      ON (u.username=pg.matricula)
                   WHERE papel_principal IN ({$papeis_tutores})
                GROUP BY pg.grupo
                  ) as tutor
               ON (tutor.grupo=gt.id)
            WHERE gt.curso=:curso
         ORDER BY gt.nome";

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

/**
 * Retorna a lista de participantes de um grupo de tutoria
 *
 * @param $grupo id do grupo de tutoria
 * @return array lista de participantes ou false em caso de falha
 */
function get_members_grupo_tutoria($grupo) {
    $middleware = Academico::singleton();

    $sql = "SELECT u.*
              FROM {user} u
              JOIN {$middleware->table_pessoas_funcoes_grupos_tutoria} pg
                ON (u.username=pg.matricula)
             WHERE pg.grupo=:grupo";

    $params = array('grupo' => $grupo);
    return $middleware->db->get_records_sql($sql, $params);
}

/**
 * Remove um participante de um grupo de tutoria
 *
 * @param $grupo id do grupo de tutoria
 * @param $matricula código de matrícula do participante
 * @return bool
 */
function remove_member_grupo_tutoria($grupo, $matricula) {
    $middleware = Academico::singleton();
    $sql = "DELETE FROM {$middleware->table_pessoas_funcoes_grupos_tutoria}
                  WHERE grupo=:grupo AND matricula=:matricula";
    $params = array('grupo' => $grupo, 'matricula' => $matricula);
    return $middleware->db->execute($sql, $params);
}

function update_grupo_tutoria($curso_ufsc, $grupo, $nome) {
    $middleware = Academico::singleton();
    $sql = "UPDATE {$middleware->table_grupos_tutoria} SET nome=? WHERE curso=? AND id=?";
    return $middleware->db->execute($sql, array($nome, $curso_ufsc, $grupo));
}

function redirect_to_gerenciar_tutores() {
    redirect(new moodle_url('/admin/tool/tutores/index.php', array('curso_ufsc' => get_curso_ufsc_id())));
}