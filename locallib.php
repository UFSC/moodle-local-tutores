<?php

require_once($CFG->libdir . '/moodlelib.php');
require_once('middlewarelib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/roles/lib.php');

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

class tutores_potential_selector extends user_selector_base {

    /**
     * @param string $name control name
     * @param array $options should have two elements with keys groupid and courseid.
     */
    public function __construct($tutores_ids = null) {
        global $CFG, $USER;

        // Precisamos sobrescrever o arquivo Javascript responsavel pelo seletor ajax
        self::$jsmodule['fullpath'] = '/admin/tool/tutores/selector/module.js';

        parent::__construct('addselect', array('multiselect' => false, 'exclude' => $tutores_ids));
    }

    public function find_users($search) {
        global $CFG, $DB;

        $papeis_tutores = '46';

        list($wherecondition, $params) = $this->search_sql($search, '');

        $fields = 'SELECT ' . $this->required_fields_sql('');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user}
                WHERE $wherecondition AND mnethostid = :localmnet";
        $order = ' ORDER BY lastname ASC, firstname ASC';

        $params['localmnet'] = $CFG->mnet_localhost_id; // it could be dangerous to make remote users admins and also this could lead to other problems
        // Check to see if there are too many to show sensibly.
        if (!$this->is_validating()) {
            $potentialcount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialcount > 100) {
                return $this->too_many_results($search, $potentialcount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);

        if (empty($availableusers)) {
            return array();
        }

        if ($search) {
            $groupname = get_string('potusersmatching', 'role', $search);
        } else {
            $groupname = get_string('potusers', 'role');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        global $CFG;
        $options = parent::get_options();
        $options['file'] = $CFG->admin . '/roles/lib.php';
        return $options;
    }

}

