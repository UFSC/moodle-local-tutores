<?php

defined('MOODLE_INTERNAL') || die();

class tool_tutores_renderer extends plugin_renderer_base {

    private $cursos;
    private $curso_ativo;

    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Carrega informações sobre cursos UFSC
        $this->cursos = get_cursos_ativos_list();
        $this->curso_ativo = get_curso_ufsc_id();

        if (!empty($this->curso_ativo)) {
            $context = get_category_context_from_curso_ufsc($this->curso_ativo);
            require_capability('tool/tutores:manage', $context, null, false);
        }
    }

    public function choose_curso_ufsc_page($destination_url) {

        // Imprime cabeçalho da página
        $output = $this->header();
        $output .= $this->heading(get_string('grupos_tutoria', 'tool_tutores'));

        $table = new html_table();
        $table->head = array(get_string('cursos_ufsc', 'tool_tutores'));
        $table->tablealign = 'center';
        $table->data = array();

        foreach ($this->cursos as $id_curso => $nome_curso) {
            $url = new moodle_url($destination_url . '?curso_ufsc=' . $id_curso);
            $table->data[] = array(html_writer::link($url, $nome_curso));
        }

        $output .= html_writer::table($table);

        $output .= $this->page_footer();
        return $output;
    }

    /**
     * Página inicial
     *
     * @param mixed $grupos Grupos de Tutoria (BD)
     * @return string HTML renderizado
     */
    public function list_groups_page($grupos) {

        // Tabela
        $table = new html_table();
        $table->head = array(
            get_string('grupos_tutoria', 'tool_tutores'),
            get_string('member_count', 'tool_tutores'),
            get_string('tutores', 'tool_tutores'),
            get_string('edit'));
        $table->tablealign = 'center';
        $table->data = array();

        $add_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc' => $this->curso_ativo, 'action' => 'add'));
        $add_controls = $this->heading(html_writer::link($add_url, get_string('addnewgroup', 'tool_tutores')));

        foreach ($grupos as $grupo) {
            $url = new moodle_url('/admin/tool/tutores/assign.php', array('curso_ufsc' => $this->curso_ativo, 'id' => $grupo->grupo));
            $edit_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc' => $this->curso_ativo, 'id' => $grupo->grupo, 'action' => 'edit'));
            $delete_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc' => $this->curso_ativo, 'id' => $grupo->grupo, 'action' => 'delete'));
            $controls = get_action_icon($edit_url, 'edit', get_string('edit'), get_string('edit')) .
                        get_action_icon($delete_url, 'delete', get_string('delete'), get_string('delete'));

            $table->data[] = array(html_writer::link($url, $grupo->nome), $grupo->estudantes, $grupo->tutores, $controls);
        }

        // Output
        $output = $this->page_header();
        $output .= $this->heading('Clique em um grupo para atribuir participantes', 3);
        $output .= html_writer::table($table);
        $output .= $add_controls;
        $output .= $this->page_footer();
        return $output;
    }

    public function page_header() {
        $output = '';

        // Configura seletor de cursos UFSC
        $select = new single_select(new moodle_url('/admin/tool/tutores/index.php'), 'curso_ufsc', $this->cursos, $this->curso_ativo, null, 'switch_curso_ufsc');
        $select->set_label(get_string('curso', 'tool_tutores') . ':');
        $select->class = 'cursos_ufsc_select generalbox';

        // Imprime cabeçalho da página
        $output .= $this->header();
        $output .= $this->heading(get_string('grupos_tutoria', 'tool_tutores'));

        // Imprime seletor de curso UFSC ativo
        $output .= $this->render($select);

        return $output;
    }

    public function page_footer() {
        return $this->footer();
    }

    public function preview_bulk_upload($cir, $previewrows, $columns) {
        global $DB, $CFG;

        $data = array();
        $cir->init();
        $linenum = 1; //column header is first line

        while($linenum <= $previewrows and $fields = $cir->next()) {
            $linenum++;
            $rowcols = array();
            $rowcols['line'] = $linenum;
            foreach($fields as $key => $field) {
                $rowcols[$columns[$key]] = s($field);
            }
            $rowcols['status'] = array();

            if (isset($rowcols['username'])) {
                $stdusername = clean_param($rowcols['username'], PARAM_USERNAME);
                if ($rowcols['username'] !== $stdusername) {
                    $rowcols['status'][] = get_string('invalidusernameupload');
                }
                if ($userid = $DB->get_field('user', 'id', array('username'=>$stdusername, 'mnethostid'=>$CFG->mnet_localhost_id))) {
                    $rowcols['username'] = html_writer::link(new moodle_url('/user/profile.php', array('id'=>$userid)), $rowcols['username']);
                } else {
                    $rowcols['status'][] = get_string('invalidusernameupload');
                }
            } else {
                $rowcols['status'][] = get_string('missingusername');
            }

            $rowcols['status'] = implode('<br />', $rowcols['status']);
            $data[] = $rowcols;
        }

        if ($fields = $cir->next()) {
            $data[] = array_fill(0, count($fields) + 2, '...');
        }
        $cir->close();

        $table = new html_table();
        $table->id = "uupreview";
        $table->attributes['class'] = 'generaltable';
        $table->tablealign = 'center';
        $table->summary = get_string('uploaduserspreview', 'tool_uploaduser');
        $table->head = array();
        $table->data = $data;

        $table->head[] = get_string('uucsvline', 'tool_uploaduser');
        foreach ($columns as $column) {
            $table->head[] = $column;
        }
        $table->head[] = get_string('status');

        $output = html_writer::tag('div', html_writer::table($table), array('class'=>'flexible-wrap'));

        return $output;
    }

    public function display_bulk_results($base_url, $numpeople, $failed) {
        $output =  $this->page_header();

        if (empty($failed)) {
            $output .= $this->box("Foram inscritas {$numpeople} pessoas");
            $output .= $this->continue_button($base_url);
        } else {
            $numfail = count($failed);
            $numsuccess = $numpeople - $numfail;
            $table = new html_table();
            $table->head = array('Linha', 'Usuários não inscritos');
            $table->data = $failed;
            $table->tablealign = 'center';

            $output .= $this->box_start();
            $output .= $this->heading("Foram inscritas {$numsuccess} pessoas com sucesso e {$numfail} falharam:", 3);
            $output .= html_writer::table($table);
            $output .= $this->box_end();
            $output .= $this->continue_button($base_url);
        }

        $output .= $this->page_footer();

        return $output;
    }

}