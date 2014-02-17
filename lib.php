<?php

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->dirroot}/{$CFG->admin}/roles/lib.php");
require_once("{$CFG->dirroot}/local/tutores/middlewarelib.php");

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
            $grupostutoria_node = $category_node->add(get_string('grupos_tutoria', 'local_tutores'), null, navigation_node::TYPE_CONTAINER);
            $grupostutoria_node->add(get_string('manage_groups', 'local_tutores'), new moodle_url('/local/tutores/index.php', array('categoryid' => $PAGE->context->instanceid)));
            $grupostutoria_node->add(get_string('bulk_upload_groups', 'local_tutores'), new moodle_url('/local/tutores/bulk.php', array('categoryid' => $PAGE->context->instanceid)));
        }
    }
}

class grupos_tutoria {

    /**
     * Retorna os papéis atribuídos no middleware a pelo menos uma pessoa deste contexto UFSC
     *
     * @return array papéis disponíveis para seleção
     */
    static function get_papeis_ufsc() {
        $middleware = Middleware::singleton();

        if (!$middleware->configured())
            return false;

        $sql = "SELECT p.papel, p.descricao
                  FROM {view_Papeis} p";

        try {
            return $middleware->get_records_sql_menu($sql);
        } catch (dml_read_exception $e) {
            return false;
        }
    }

    /**
     * Retorna os papéis que estão sendo considerados como estudantes
     *
     * @static
     * @return array
     */
    static function get_papeis_estudantes() {
        global $CFG;

        return explode(',', $CFG->estudantes_allowed_roles);
    }

    /**
     * Retorna os papéis que estão sendo considerados como tutores
     *
     * @static
     * @return array
     */
    static function get_papeis_tutores() {
        global $CFG;

        return explode(',', $CFG->tutores_allowed_roles);
    }

    /**
     * Retorna os papéis que estão sendo considerados como coordenadores
     *
     * @static
     * @return array
     */
    static function get_papeis_coordenadores() {
        global $CFG;

        return explode(',', $CFG->coordenadores_allowed_roles);
    }

    /**
     * Retorna lista de todos os papéis que são considerados ou tutor ou estudante
     *
     * @static
     * @return array
     */
    static function get_papeis_participantes_possiveis() {
        $papeis = array_merge(self::get_papeis_tutores(), self::get_papeis_estudantes());
        return $papeis;
    }

    /**
     * Retorna lista de estudantes inscritos em algum grupo de tutoria de um determinado curso ufsc.
     *
     * @param string $curso_ufsc
     * @return array [userid => fullname]
     */
    static function get_estudantes_curso_ufsc($curso_ufsc) {
        $middleware = Middleware::singleton();

        $sql = " SELECT DISTINCT u.id, CONCAT(u.firstname,' ',u.lastname) as fullname
                   FROM {user} u
                   JOIN {table_PessoasGruposTutoria} pg
                     ON (pg.matricula=u.username AND pg.tipo=:tipo)
                   JOIN {table_GruposTutoria} gt
                     ON (gt.id=pg.grupo)
                  WHERE gt.curso=:curso_ufsc";

        return $middleware->get_records_sql_menu($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_TUTORIA_TIPO_ESTUDANTE));
    }

    /**
     * Retorna lista de grupos de tutoria de um determinado curso ufsc
     * @param string $curso_ufsc
     * @return array
     */
    static function get_grupos_tutoria($curso_ufsc, $tutores = null) {
        $middleware = Middleware::singleton();
        $sql;
        if(is_null($tutores))
            $sql = "SELECT * FROM {table_GruposTutoria} WHERE curso=:curso_ufsc ORDER BY nome";
        else{
            $tutores = int_array_to_sql($tutores);
            $sql = "SELECT gt.id, gt.curso, gt.nome
                      FROM {table_GruposTutoria} gt
                      JOIN {table_PessoasGruposTutoria} pg
                        ON (gt.id=pg.grupo AND gt.curso=:curso_ufsc)
                      JOIN {user} u
                        ON (pg.matricula=u.username AND pg.tipo=:tipo)
                     WHERE u.id IN ({$tutores})";
        }
        return $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_TUTORIA_TIPO_TUTOR));
    }

    /**
     * Retorna lista orientadores de um determinado curso ufsc
     * @param string $curso_ufsc
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
     * Utilizado para a criacao do filtro Tutores
     *
     * @param string $curso_ufsc
     * @return array
     */
    static function get_chave_valor_grupos_tutoria($curso_ufsc) {
        $middleware = Middleware::singleton();
        $sql = " SELECT DISTINCT u.id as user_id,  CONCAT(u.firstname,' ',u.lastname) as fullname, pg.grupo
                   FROM {user} u
                   JOIN {table_PessoasGruposTutoria} pg
                     ON (pg.matricula=u.username AND pg.tipo=:tipo)
                   JOIN {table_GruposTutoria} gt
                     ON (gt.id=pg.grupo)
                  WHERE gt.curso=:curso_ufsc";

        $tutores = $middleware->get_recordset_sql($sql, array('curso_ufsc' => $curso_ufsc,
            'tipo' => GRUPO_TUTORIA_TIPO_TUTOR));




        $dados = new GroupArray();
        foreach ($tutores as $tutor) {
            $dados->add($tutor->grupo, array($tutor->id => $tutor->fullname));
        }
        $dados = $dados->get_assoc();


        $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc);

        foreach ($grupos_tutoria as $grupo) {
            if(array_key_exists($grupo->id, $dados)){

            }else{
                $dados[$grupo->id] = $grupo->nome;
            }
        }
        return $dados;
    }

    /**
     * Retorna lista de tutores inscritos em algum grupo de tutoria de um determinado curso ufsc.
     *
     * @param string $curso_ufsc
     * @return mixed
     */
    static function get_tutores_curso_ufsc($curso_ufsc) {
        $middleware = Middleware::singleton();

        $sql = " SELECT DISTINCT u.id, CONCAT(u.firstname,' ',u.lastname) as fullname
                   FROM {user} u
                   JOIN {table_PessoasGruposTutoria} pg
                     ON (pg.matricula=u.username AND pg.tipo=:tipo)
                   JOIN {table_GruposTutoria} gt
                     ON (gt.id=pg.grupo)
                  WHERE gt.curso=:curso_ufsc";

        return $middleware->get_records_sql_menu($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_TUTORIA_TIPO_TUTOR));
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

    /**
     * Coloca aspas em uma listagem de papeis para o MySQL.
     *
     * Este método tenta corrigir uma deficiência do Moodle, que não aceita um array
     * como parâmetro para uma prepared query portanto estamos emulando a colocação das aspas
     * para clausula IN
     *
     * @static
     * @param $papeis array Listagem de papéis em um array simples ([$i => $codigo_papel])
     * @return string Listagem de papéis, separados por vírgula e com aspas entre eles.
     */
    static function escape_papeis_sql($papeis) {
        $allowed_roles = $papeis;

        $allowed_roles_sql = array();
        foreach ($allowed_roles as $role) {
            $allowed_roles_sql[] = "'$role'";
        }
        $allowed_roles_sql = implode(',', $allowed_roles_sql);

        return $allowed_roles_sql;
    }

    static function grupo_tutoria_to_string($curso_ufsc, $id) {
        $middleware = Middleware::singleton();
        $sql = " SELECT DISTINCT u.id as user_id, CONCAT(u.firstname,' ',u.lastname) as fullname
                   FROM {user} u
                   JOIN {table_PessoasGruposTutoria} pg
                     ON (pg.matricula=u.username AND pg.tipo=:tipo AND pg.grupo=:grupo_id)
                   JOIN {table_GruposTutoria} gt
                     ON (gt.id=pg.grupo)
                  WHERE gt.curso=:curso_ufsc";

        $tutores = $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc,
            'tipo' => GRUPO_TUTORIA_TIPO_TUTOR,
            'grupo_id' => $id));

        $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc);

        $string = '<strong>' . $grupos_tutoria[$id]->nome . '</strong>';
        if (empty($tutores)) {
            return $string . " - Sem Tutor Responsável";
        } else {
            foreach ($tutores as $tutor) {
                $string.= ' - ' . $tutor->fullname . ' ';
            }
        }
        return $string;
    }

    static function grupo_orientacao_to_string($curso_ufsc, $id) {
        $middleware = Middleware::singleton();
        $sql = "SELECT DISTINCT u.id as user_id, CONCAT(u.firstname,' ',u.lastname) as fullname
                      FROM {view_Alunos_Orientadores} ao
                      JOIN {user} u
                        ON (ao.username_orientador=u.username)";

        $orientadores = $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc,
            'grupo_id' => $id));

        $string = '<strong>'. 'Orientador(a) - ' . '<strong>'. $orientadores[$id]->fullname.'</strong>';
        return $string;
    }

}

abstract class tutor_selector_base extends user_selector_base {

    protected $grupo;
    protected $curso;

    public function __construct($name, $options) {
        $this->grupo = $options['grupo'];
        $this->curso = $options['curso'];
        parent::__construct($name, $options);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['grupo'] = $this->grupo;
        $options['curso'] = $this->curso;
        $options['file'] = 'local/tutores/lib.php';
        return $options;
    }

    protected function get_allowed_roles() {
        $papeis = grupos_tutoria::get_papeis_ufsc();
        $allowed_roles = grupos_tutoria::get_papeis_participantes_possiveis();

        $named_roles = array();
        foreach ($allowed_roles as $role) {
            $named_roles[$role] = $papeis[$role];
        }

        return $named_roles;
    }

}

class usuarios_tutoria_potential_selector extends tutor_selector_base {

    /**
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options) {
        parent::__construct($name, $options);
    }

    public function find_users($search) {
        global $CFG;

        $middleware = Middleware::singleton();

        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $sql = "FROM {user} u
                JOIN {view_UsuariosFuncoesCursos} ufc USING (username)
                LEFT JOIN (SELECT pg.matricula, pg.grupo, pg.tipo
                             FROM {table_GruposTutoria} gt
                             JOIN {table_PessoasGruposTutoria} pg ON (pg.grupo = gt.id)
                            WHERE gt.curso = :curso1
                            GROUP BY pg.matricula
                          ) par
                  ON (par.matricula = u.username)
               WHERE {$wherecondition}
                 AND ufc.curso = :curso2
                 AND u.username NOT IN (SELECT matricula FROM {table_PessoasGruposTutoria} WHERE grupo = :grupo)
                 AND (ISNULL(par.matricula) OR par.tipo = :tipo)";

        $params['localmnet'] = $CFG->mnet_localhost_id; // it could be dangerous to make remote users admins and also this could lead to other problems
        $params['curso1'] = $this->curso;
        $params['curso2'] = $this->curso;
        $params['tipo'] = GRUPO_TUTORIA_TIPO_TUTOR;
        $params['grupo'] = $this->grupo;

        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $allowed_roles_sql = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_participantes_possiveis());
            $countfields = 'SELECT COUNT(u.username) ';
            $sql_count = $countfields . $sql . " AND ufc.papel IN ({$allowed_roles_sql}) ";

            $potentialcount = $middleware->count_records_sql($sql_count, $params);
            if ($potentialcount > 100) {
                return $this->too_many_results($search, $potentialcount);
            }
        }

        $papeis_tutores = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_tutores());
        $papeis_estudantes = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_estudantes());
        $tutores_nao_alocados = (object) array('tipo' => GRUPO_TUTORIA_TIPO_TUTOR,
            'nome' => 'Tutores ainda não alocados',
            'papeis' => $papeis_tutores,
            'condicao' => 'ISNULL(par.grupo)');
        $tutores_alocados = (object) array('tipo' => GRUPO_TUTORIA_TIPO_TUTOR,
            'nome' => 'Tutores já alocados em outros grupos',
            'papeis' => $papeis_tutores,
            'condicao' => 'NOT ISNULL(par.grupo)');
        $estudantes = (object) array('tipo' => GRUPO_TUTORIA_TIPO_ESTUDANTE,
            'nome' => 'Estudantes',
            'papeis' => $papeis_estudantes,
            'condicao' => '');
        $categorias = array($tutores_nao_alocados, $tutores_alocados, $estudantes);

        $fields = 'SELECT ' . $this->required_fields_sql('u') . ', par.grupo ';
        $order = ' ORDER BY u.firstname, u.lastname';

        $found_users = array();
        foreach ($categorias as $categoria) {
            $condicao = empty($categoria->condicao) ? '' : ' AND ' . $categoria->condicao;
            $sql_cat = $fields . $sql . " AND ufc.papel IN ({$categoria->papeis}) " . $condicao . $order;
            $users = $middleware->get_records_sql($sql_cat, $params);
            if (!empty($users)) {
                // Acrescentar o tipo para facilitar a inclusão
                foreach ($users as $user) {
                    $user->tipo = $categoria->tipo;
                }
                $found_users[$categoria->nome] = $users;
            }
        }

        $empty = array(get_string('none') => array(), get_string('pleasesearchmore') => array());
        return empty($found_users) ? $empty : $found_users;
    }

}

class usuarios_tutoria_existing_selector extends tutor_selector_base {

    /**
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($name, $options) {
        parent::__construct($name, $options);
    }

    public function find_users($search) {
        global $CFG, $DB;

        $middleware = Middleware::singleton();

        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $fields = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {table_PessoasGruposTutoria} pg
                   ON (u.username=pg.matricula)
                WHERE $wherecondition
                  AND mnethostid = :localmnet
                  AND pg.grupo=:grupo
                 ";

        $order = ' ORDER BY firstname ASC, lastname ASC';

        $params['localmnet'] = $CFG->mnet_localhost_id; // it could be dangerous to make remote users admins and also this could lead to other problems
        $params['grupo'] = $this->grupo;


        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $potentialcount = $middleware->count_records_sql($countfields . $sql, $params);
            if ($potentialcount > 100) {
                return $this->too_many_results($search, $potentialcount);
            }
        }

        $found_users = array();
        $to_query = array('Tutores' => GRUPO_TUTORIA_TIPO_TUTOR, 'Estudantes' => GRUPO_TUTORIA_TIPO_ESTUDANTE);

        foreach ($to_query as $categoria => $tipo) {
            $sql = " FROM {user} u
                     JOIN {table_PessoasGruposTutoria} pg
                       ON (u.username=pg.matricula)
                     JOIN {View_Usuarios} mid_u
                    USING (username)
                    WHERE $wherecondition
                      AND mnethostid = :localmnet
                      AND pg.grupo=:grupo
                      AND pg.tipo=:tipo";

            $params['tipo'] = $tipo;
            $users = $middleware->get_records_sql($fields . $sql . $order, $params);
            if (!empty($users)) {
                $found_users[$categoria] = $users;
            }
        }

        $empty = array(get_string('none') => array(), get_string('pleasesearchmore') => array());
        return empty($found_users) ? $empty : $found_users;
    }

}