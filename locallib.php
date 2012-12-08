<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/moodlelib.php');
require_once("{$CFG->dirroot}/local/tutores/middlewarelib.php");
require_once("{$CFG->dirroot}/local/tutores/lib.php");

/**
 * Adiciona uma pessoa a um grupo de tutoria
 *
 * @param int $grupo id do grupo de tutoria
 * @param string $matricula código de matrícula do participante
 * @return bool true caso o membro seja adicionado e false caso ocorra um problema
 */
function add_member_grupo_tutoria($grupo, $matricula, $tipo) {

    $tipos_validos = array('E', 'T');
    if (!in_array($tipo, $tipos_validos)) {
        throw new Exception('tipo inválido informado');
    }

    $middleware = Middleware::singleton();

    $sql = "INSERT INTO {table_PessoasGruposTutoria}
                        (grupo, matricula, tipo)
                 VALUES (:grupo, :matricula, :tipo)";

    $params = array('grupo' => $grupo, 'matricula' => $matricula, 'tipo' => $tipo);

    return (bool) $middleware->insert_record_sql($sql, $params);
}

/**
 * Cria um grupo de tutoria
 *
 * @param string $curso_ufsc
 * @param string $nome
 * @return int|bool retorna o código do grupo de tutoria ou false caso ocorra um problema
 */
function create_grupo_tutoria($curso_ufsc, $nome) {
    $middleware = Middleware::singleton();
    $sql = "INSERT INTO {table_GruposTutoria} (nome, curso) VALUES(:nome, :curso)";
    $params = array('nome' => $nome, 'curso' => $curso_ufsc);

    return $middleware->insert_record_sql($sql, $params);
}

/**
 * @param string $curso_ufsc
 * @param int $grupo
 * @return ADORecordSet|bool
 */
function delete_grupo_tutoria($curso_ufsc, $grupo) {
    $middleware = Middleware::singleton();

    $sql = "DELETE FROM {table_PessoasGruposTutoria}
                  WHERE grupo=:grupo";

    $result = $middleware->execute($sql, array('grupo' => $grupo));

    $sql = "DELETE FROM {table_GruposTutoria}
             WHERE curso=:curso AND id=:grupo";

    $result2 = $middleware->execute($sql, array('curso' => $curso_ufsc, 'grupo' => $grupo));

    return ($result && $result2);
}

function get_action_icon($url, $icon, $alt, $tooltip) {
    global $OUTPUT;
    return '<a title="' . $tooltip . '" href="' . $url . '">' .
            '<img src="' . $OUTPUT->pix_url('t/' . $icon) . '" class="iconsmall" alt="' . $alt . '" /></a> ';
}

function get_category_context_from_curso_ufsc($curso_ufsc) {
    global $DB;

    $categoryid = $DB->get_field('course_categories', 'id', array('idnumber' => "curso_{$curso_ufsc}"));
    return context_coursecat::instance($categoryid);
}

function get_grupo_tutoria($id) {
    $middleware = Middleware::singleton();
    $sql = "SELECT * FROM {table_GruposTutoria} WHERE id=:id";
    return $middleware->get_record_sql($sql, array('id' => $id));
}

function get_grupos_tutoria_select($curso_ufsc) {
    $middleware = Middleware::singleton();
    $sql = "SELECT id, nome FROM {table_GruposTutoria} WHERE curso=:curso ORDER BY nome";
    return $middleware->get_records_sql_menu($sql, array('curso' => $curso_ufsc));
}

function get_grupos_tutoria_with_members_count($curso_ufsc) {
    $middleware = Middleware::singleton();
    $papeis_estudantes = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_estudantes());
    $papeis_tutores = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_tutores());

    $sql = "SELECT gt.id as grupo, gt.nome, IFNULL(estudante.quantidade, 0) as estudantes, IFNULL(tutor.quantidade, 0) as tutores
              FROM {table_GruposTutoria} gt
         LEFT JOIN (
                  SELECT gt.id as grupo, COUNT(*) as quantidade
                    FROM {table_GruposTutoria} gt
                    JOIN {table_PessoasGruposTutoria} pg
                      ON (gt.id=pg.grupo)
                    JOIN {view_Usuarios} u
                      ON (u.username=pg.matricula)
                   WHERE papel_principal IN ({$papeis_estudantes})
                GROUP BY pg.grupo
                   ) as estudante
                ON (estudante.grupo=gt.id)
         LEFT JOIN (
                  SELECT gt.id as grupo, COUNT(*) as quantidade
                    FROM {table_GruposTutoria} gt
                    JOIN {table_PessoasGruposTutoria} pg
                      ON (gt.id=pg.grupo)
                    JOIN {view_Usuarios} u
                      ON (u.username=pg.matricula)
                   WHERE papel_principal IN ({$papeis_tutores})
                GROUP BY pg.grupo
                  ) as tutor
               ON (tutor.grupo=gt.id)
            WHERE gt.curso=?
         ORDER BY gt.nome";

    return $middleware->get_records_sql($sql, array($curso_ufsc));
}

function get_cursos_ativos_list() {
    $middleware = Middleware::singleton();

    $sql = "SELECT curso, nome_sintetico
              FROM {View_Cursos_Ativos}";

    return $middleware->get_records_sql_menu($sql);
}

function get_curso_ufsc_id() {
    $categoryid = required_param('categoryid', PARAM_INT);
    $category = get_course_category($categoryid);

    if (!$category->idnumber) {
        return false;
    }

    return str_replace('curso_', '', $category->idnumber, $count);
}

/**
 * Retorna a lista de participantes de um grupo de tutoria
 *
 * @param int $grupo id do grupo de tutoria
 * @return array lista de participantes ou false em caso de falha
 */
function get_members_grupo_tutoria($grupo) {
    $middleware = Middleware::singleton();

    $sql = "SELECT u.*
              FROM {user} u
              JOIN {table_PessoasGruposTutoria} pg
                ON (u.username=pg.matricula)
             WHERE pg.grupo=:grupo";

    $params = array('grupo' => $grupo);
    return $middleware->get_records_sql($sql, $params);
}

function redirect_to_gerenciar_tutores() {
    $categoryid = required_param('categoryid', PARAM_INT);
    redirect(new moodle_url('/local/tutores/index.php', array('categoryid' => $categoryid)));
}

/**
 * Remove um participante de um grupo de tutoria
 *
 * @param int $grupo id do grupo de tutoria
 * @param string $matricula código de matrícula do participante
 * @return bool
 */
function remove_member_grupo_tutoria($grupo, $matricula) {
    $middleware = Middleware::singleton();
    $sql = "DELETE FROM {table_PessoasGruposTutoria}
                  WHERE grupo=:grupo AND matricula=:matricula";

    $params = array('grupo' => $grupo, 'matricula' => $matricula);
    return (bool) $middleware->execute($sql, $params);
}


function get_moodle_group_members($groupid) {
    $middleware = Middleware::singleton();

    $sql = "SELECT u.*, vu.papel_principal
              FROM {user} u
              JOIN {groups_members} gm
                ON (gm.userid=u.id AND gm.groupid = ?)
              JOIN {view_Usuarios} vu
                ON (vu.username=u.username)
          ORDER BY u.firstname, u.lastname";

    return $middleware->get_records_sql($sql, array($groupid));
}

/**
 * Atualiza os dados de um grupo de tutoria existente
 *
 * @param string $curso_ufsc
 * @param int $grupo
 * @param string $nome
 * @return bool
 */
function update_grupo_tutoria($curso_ufsc, $grupo, $nome) {
    $middleware = Middleware::singleton();
    $sql = "UPDATE {table_GruposTutoria} SET nome=? WHERE curso=? AND id=?";
    return (bool) $middleware->execute($sql, array($nome, $curso_ufsc, $grupo));
}

/**
 * Realiza a validação das colunas informadas no CSV de importação em lote de participantes
 *
 * @param $cir
 * @param $STD_FIELDS
 * @param $base_url
 * @return array
 */
function validate_upload_grupos_tutoria($cir, $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }

    foreach ($columns as $key=>$field) {
        if ($key != 'username') {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
    }

    return $columns;
}