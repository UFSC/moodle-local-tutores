<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot . '/local/tutores/middlewarelib.php');

define('GRUPO_TUTORIA_TIPO_ESTUDANTE', 'E');
define('GRUPO_TUTORIA_TIPO_TUTOR', 'T');
define('GRUPO_ORIENTACAO_TIPO_ORIENTADOR', 'O');

function local_tutores_extends_settings_navigation(navigation_node $navigation) {
    global $PAGE;

    if (is_a($PAGE->context, 'context_coursecat') && has_capability('local/tutores:manage' ,$PAGE->context)) {
        $category_node = $navigation->get('categorysettings');

        // Se por algum motivo a chave for alterada em uma nova versão do Moodle,
        // Não quebrar o código.
        if ($category_node) {
            $category_node->add(
                    get_string('tutorship_groups', 'local_tutores'),
                    new moodle_url('/local/tutores/index.php', array('categoryid' => $PAGE->context->instanceid)),
                    navigation_node::TYPE_SETTING, null, null, new pix_icon('icon', '', 'local_tutores'));
        }
    }
}

class grupos_tutoria {

    /**
     * Retorna os papéis que estão sendo considerados como estudantes
     *
     * @static
     * @return array
     */
    static function get_papeis_estudantes() {
        global $CFG;

        return explode(',', $CFG->local_tutores_student_roles);
    }

    /**
     * Retorna os papéis que estão sendo considerados como tutores
     *
     * @static
     * @return array
     */
    static function get_papeis_tutores() {
        global $CFG;

        return explode(',', $CFG->local_tutores_tutor_roles);
    }

    /**
     * Retorna lista orientadores de um determinado curso ufsc
     * FIXME: orientadores não estão sendo usados na filtragem
     *
     * @param string $curso_ufsc
     * @param null $orientadores
     * @return array
     */
    static function get_grupos_orientacao($curso_ufsc, $orientadores = null) {
        $middleware = Middleware::singleton();

        $sql = "SELECT DISTINCT u.id, ao.username_orientador, u.firstname
                  FROM {view_Alunos_Orientadores} ao
                  JOIN {user} u
                    ON (ao.username_orientador=u.username)
              ORDER BY u.firstname
                ";
        return $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_ORIENTACAO_TIPO_ORIENTADOR));
    }

    /**
     * Retorna o tutor responsável em um curso_ufsc por um estudante
     *
     * @param string $curso_ufsc
     * @param string $matricula_estudante
     * @return bool|mixed
     */
    static function get_tutor_responsavel_estudante($curso_ufsc, $matricula_estudante) {
        $middleware = Middleware::singleton();

        $sql = " SELECT DISTINCT u.id, u.username, CONCAT(u.firstname,' ',u.lastname) as fullname
                   FROM {user} u
                   JOIN {table_PessoasGruposTutoria} pg
                     ON (pg.matricula=u.username AND pg.tipo=:tipo_tutor)
                   JOIN (
                         SELECT DISTINCT gt.*
                           FROM {user} u
                           JOIN {table_PessoasGruposTutoria} pg
                             ON (pg.matricula=u.username AND pg.tipo=:tipo_estudante)
                           JOIN {table_GruposTutoria} gt
                             ON (gt.id=pg.grupo)
                          WHERE gt.curso=:curso AND pg.matricula=:estudante
                         ) grupo_estudante
                     ON (grupo_estudante.id=pg.grupo)";

        $params = array('curso' => $curso_ufsc,
            'tipo_estudante' => GRUPO_TUTORIA_TIPO_ESTUDANTE,
            'estudante' => $matricula_estudante,
            'tipo_tutor' => GRUPO_TUTORIA_TIPO_TUTOR);

        return $middleware->get_record_sql($sql, $params);
    }

}
