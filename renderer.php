<?php

defined('MOODLE_INTERNAL') || die();

class tool_tutores_renderer extends plugin_renderer_base {

    private $curso_ativo = null;

    public function assign_page() {
        // Tabela
        $table = new html_table();
        $table->head = array(get_string('grupos_tutoria', 'tool_tutores'), get_string('member_count', 'tool_tutores'), get_string('tutores', 'tool_tutores'));
        $table->tablealign = 'center';
        $table->data = array();

        // Dummy data
        for ($i = 1; $i <= 20; $i++) {
            $url = new moodle_url('/admin/tool/tutores/assign.php?id=' . $i);
            $table->data[] = array(html_writer::link($url, "Grupo {$i}"), rand(40, 50), rand(1, 3));
        }

        // Output
        $output = $this->page_header('assign');
        $output .= $this->heading('Por favor, escolha um grupo para atribuir participantes', 3);
        $output .= html_writer::table($table);
        $output .= $this->page_footer();
        return $output;
    }

    public function index_page($grupos) {

        // Tabela
        $table = new html_table();
        $table->head = array(get_string('grupos_tutoria', 'tool_tutores'), get_string('edit'));
        $table->tablealign = 'center';
        $table->data = array();

        $controls = get_action_icon('groups.php', 'edit', get_string('edit'), get_string('edit')) . get_action_icon('#', 'delete', get_string('delete'), get_string('delete'));
        $groups_add_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc_id' => $this->curso_ativo));
        $add_controls = $this->heading('<a href="' . $groups_add_url . '">' . get_string('addnewgroup', 'tool_tutores') . '</a>');

        foreach($grupos as $grupo) {
            $table->data[] = array($grupo->nome, $controls);
        }

        // Output
        $output = $this->page_header('index');
        $output .= $add_controls;
        $output .= html_writer::table($table);
        $output .= $add_controls;
        $output .= $this->page_footer();
        return $output;
    }

    public function page_header($currenttab) {
        $this->init();
        $output = '';

        // Configura seletor de cursos UFSC
        $select = new single_select(new moodle_url('/admin/tool/tutores/index.php'), 'curso_ufsc_id', $this->cursos, $this->curso_ativo, null, 'switch_curso_ufsc');
        $select->set_label(get_string('curso', 'tool_tutores') . ':');
        $select->class = 'cursos_ufsc_select generalbox';

        // Imprime cabeçalho da página
        $output .= $this->header();
        $output .= $this->heading(get_string('grupos_tutoria', 'tool_tutores'));

        // Imprime seletor de curso UFSC ativo
        $output .= $this->render($select);

        $toprow = array();
        $toprow[] = new tabobject('index', new moodle_url('/admin/tool/tutores/index.php'), get_string('gerenciar_tutores', 'tool_tutores'));
        $toprow[] = new tabobject('assign', new moodle_url('/admin/tool/tutores/assign.php'), get_string('designar_participantes', 'tool_tutores'));
        $toprow[] = new tabobject('permission', new moodle_url('/admin/tool/tutores/permissions.php'), get_string('definir_permissoes', 'tool_tutores'));
        $tabs = array($toprow);

        $output .= print_tabs($tabs, $currenttab, null, null, true);

        return $output;
    }

    public function page_footer() {
        return $this->footer();
    }

    private function init() {

        // Carrega informações sobre cursos UFSC
        $this->cursos = get_cursos_ativos_list();
        $this->curso_ativo = get_curso_ufsc_id();
    }

}