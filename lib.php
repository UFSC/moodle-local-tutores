<?php

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->dirroot}/{$CFG->admin}/roles/lib.php");
require_once("{$CFG->dirroot}/{$CFG->admin}/tool/tutores/middlewarelib.php");

define('GRUPO_TUTORIA_TIPO_ESTUDANTE', 'E');
define('GRUPO_TUTORIA_TIPO_TUTOR', 'T');

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

        $sql = "SELECT p.* FROM {View_Usuarios} u
                  JOIN {table_Papeis} p ON(u.papel_principal = p.papel)
              GROUP BY papel_principal";

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
     * @return mixed
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
            $sql = "SELECT gt.id, gt.curso, gt.nome FROM {table_GruposTutoria} gt
                      JOIN {table_PessoasGruposTutoria} pg
                        ON (gt.id=pg.grupo AND gt.curso=:curso_ufsc)
                      JOIN {user} u
                        ON (pg.matricula=u.username AND pg.tipo=:tipo)
                     WHERE u.id IN ({$tutores})";
        }
        return $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_TUTORIA_TIPO_TUTOR));
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

        $sql = " SELECT DISTINCT u.id, CONCAT(u.firstname,' ',u.lastname) as fullname
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

}

abstract class tutor_selector_base extends user_selector_base {

    protected $grupo;

    public function __construct($name, $options) {
        $this->grupo = $options['grupo'];
        parent::__construct($name, $options);
    }

    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['grupo'] = $this->grupo;
        $options['file'] = $CFG->admin . '/tool/tutores/lib.php';
        return $options;
    }

    protected function get_allowed_roles() {
        global $CFG;
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

        $allowed_roles_sql = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_participantes_possiveis());

        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $fields = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {View_Usuarios} mid_u
                USING (username)
                WHERE $wherecondition
                  AND mnethostid = :localmnet
                  AND mid_u.papel_principal IN ({$allowed_roles_sql})
                  AND u.username NOT IN (SELECT matricula FROM {table_PessoasGruposTutoria})";

        $order = ' ORDER BY lastname ASC, firstname ASC';

        $params['localmnet'] = $CFG->mnet_localhost_id; // it could be dangerous to make remote users admins and also this could lead to other problems
        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $potentialcount = $middleware->count_records_sql($countfields . $sql, $params);
            if ($potentialcount > 100) {
                return $this->too_many_results($search, $potentialcount);
            }
        }

        $found_users = array();
        $empty = array(get_string('none') => array(), get_string('pleasesearchmore') => array());

        $papeis_tutores = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_tutores());
        $papeis_estudantes = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_estudantes());

        $tutores = (object) array('tipo' => GRUPO_TUTORIA_TIPO_TUTOR, 'nome' => 'Tutores', 'papeis' => $papeis_tutores);
        $estudantes = (object) array('tipo' => GRUPO_TUTORIA_TIPO_ESTUDANTE, 'nome' => 'Estudantes', 'papeis' => $papeis_estudantes);

        $categorias = array($tutores, $estudantes);

        foreach ($categorias as $categoria) {

            $sql = " FROM {user} u
                     JOIN {View_Usuarios} mid_u
                    USING (username)
                    WHERE $wherecondition
                      AND mnethostid = :localmnet
                      AND mid_u.papel_principal IN ({$categoria->papeis})
                      AND u.username NOT IN (SELECT matricula FROM {table_PessoasGruposTutoria})";

            $users = $middleware->get_records_sql($fields . $sql . $order, $params);
            if (!empty($users)) {

                // Acrescentar o tipo para facilitar a inclusão
                foreach ($users as $user) {
                    $user->tipo = $categoria->tipo;
                }
                $found_users[$categoria->nome] = $users;
            }
        }

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

        $order = ' ORDER BY lastname ASC, firstname ASC';

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
        $empty = array(get_string('none') => array(), get_string('pleasesearchmore') => array());

        $papeis_estudantes = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_estudantes());
        $papeis_tutores = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_tutores());
        $to_query = array('Tutores' => $papeis_tutores, 'Estudantes' => $papeis_estudantes);

        foreach ($to_query as $categoria => $papeis) {
            $sql = " FROM {user} u
                     JOIN {table_PessoasGruposTutoria} pg
                       ON (u.username=pg.matricula)
                     JOIN {View_Usuarios} mid_u
                    USING (username)
                    WHERE $wherecondition
                      AND mnethostid = :localmnet
                      AND pg.grupo=:grupo
                      AND mid_u.papel_principal IN ({$papeis})";

            $users = $middleware->get_records_sql($fields . $sql . $order, $params);
            if (!empty($users)) {
                $found_users[$categoria] = $users;
            }
        }

        return empty($found_users) ? $empty : $found_users;
    }

}