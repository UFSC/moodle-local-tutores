<?php

require_once("{$CFG->dirroot}/{$CFG->admin}/roles/lib.php");
require_once("{$CFG->dirroot}/{$CFG->admin}/tool/tutores/middlewarelib.php");

class grupos_tutoria {

    /**
     * Retorna os papéis atribuídos no middleware a pelo menos uma pessoa deste contexto UFSC
     *
     * @return array papéis disponíveis para seleção
     */
    static function get_papeis_ufsc() {
        $middleware = Academico::singleton();

        $sql = "SELECT p.* FROM {$middleware->view_usuarios} u
                  JOIN {$middleware->table_papeis} p ON(u.papel_principal = p.papel)
              GROUP BY papel_principal";

        return $middleware->db->get_records_sql_menu($sql);
    }

    static function get_papeis_estudantes() {
        global $CFG;

        return self::escape_papeis_sql(explode(',', $CFG->estudantes_allowed_roles));
    }

    static function get_papeis_tutores() {
        global $CFG;

        return self::escape_papeis_sql(explode(',', $CFG->tutores_allowed_roles));
    }

    static function get_papeis_participantes_possiveis() {
        global $CFG;

        $papeis = array_merge(explode(',', $CFG->tutores_allowed_roles), explode(',', $CFG->estudantes_allowed_roles));
        return $papeis;
    }

    /**
     * Coloca aspas em uma listagem de papeis para o MySQL.
     *
     * Este método tenta corrigir uma deficiência do Moodle, que não aceita um array
     * como parâmetro para uma prepared query portanto estamos emulando a colocação das aspas
     * para clausula IN
     *
     * @static
     * @param $papeis Listagem de papéis separados por vírgula
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
        $options['multiselect'] = false;
        parent::__construct($name, $options);
    }

    public function find_users($search) {
        global $CFG, $DB;

        $middleware = Academico::singleton();

        $allowed_roles_sql = grupos_tutoria::escape_papeis_sql(grupos_tutoria::get_papeis_participantes_possiveis());

        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $fields = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {$middleware->view_usuarios} mid_u
                USING (username)
                WHERE $wherecondition
                  AND mnethostid = :localmnet
                  AND mid_u.papel_principal IN ({$allowed_roles_sql})";

        $order = ' ORDER BY lastname ASC, firstname ASC';

        $params['localmnet'] = $CFG->mnet_localhost_id; // it could be dangerous to make remote users admins and also this could lead to other problems


        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $potentialcount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialcount > 100) {
                return $this->too_many_results($search, $potentialcount);
            }
        }

        $allowed_roles = $this->get_allowed_roles();
        $found_users = array();
        foreach ($allowed_roles as $role_key => $role_name) {
            $sql = " FROM {user} u
                     JOIN {$middleware->view_usuarios} mid_u
                    USING (username)
                    WHERE $wherecondition
                      AND mnethostid = :localmnet
                      AND mid_u.papel_principal=:papel
                      AND u.username NOT IN (SELECT matricula FROM {$middleware->table_pessoas_funcoes_grupos_tutoria})";

            $params['papel'] = $role_key;
            $users = $DB->get_records_sql($fields . $sql . $order, $params);
            if (!empty($users)) {
                $found_users[$role_name] = $users;
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
        $options['multiselect'] = false;
        parent::__construct($name, $options);
    }

    public function find_users($search) {
        global $CFG, $DB;

        $middleware = Academico::singleton();

        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $fields = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {$middleware->table_pessoas_funcoes_grupos_tutoria} pg
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
            $potentialcount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialcount > 100) {
                return $this->too_many_results($search, $potentialcount);
            }
        }

        $allowed_roles = $this->get_allowed_roles();
        $found_users = array();
        foreach ($allowed_roles as $role_key => $role_name) {
            $sql = " FROM {user} u
                     JOIN {$middleware->table_pessoas_funcoes_grupos_tutoria} pg
                       ON (u.username=pg.matricula)
                     JOIN {$middleware->view_usuarios} mid_u
                    USING (username)
                    WHERE $wherecondition
                      AND mnethostid = :localmnet
                      AND pg.grupo=:grupo
                      AND mid_u.papel_principal=:papel";

            $params['papel'] = $role_key;

            $users = $DB->get_records_sql($fields . $sql . $order, $params);
            if (!empty($users)) {
                $found_users[$role_name] = $users;
            }
        }

        $empty = array(get_string('none') => array(), get_string('pleasesearchmore') => array());

        return empty($found_users) ? $empty : $found_users;
    }
}