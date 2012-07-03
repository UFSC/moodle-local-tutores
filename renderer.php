<?php

defined('MOODLE_INTERNAL') || die();

class tool_tutores_renderer extends plugin_renderer_base {

    private $curso_ativo = null;

    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        // Carrega informações sobre cursos UFSC
        $this->cursos = get_cursos_ativos_list();
        $this->curso_ativo = get_curso_ufsc_id();
    }

    public function assign_page() {
        $grupos = get_grupos_tutoria($this->curso_ativo);
        
        // Tabela
        $table = new html_table();
        $table->head = array(get_string('grupos_tutoria', 'tool_tutores'), get_string('member_count', 'tool_tutores'), get_string('tutores', 'tool_tutores'));
        $table->tablealign = 'center';
        $table->data = array();

        foreach ($grupos as $grupo) {
            $url = new moodle_url('/admin/tool/tutores/assign.php', array('curso_ufsc' => $this->curso_ativo, 'id' => $grupo->id));
            $table->data[] = array(html_writer::link($url, $grupo->nome), rand(40, 50), rand(1, 3));
        }

        // Output
        $output = $this->page_header('assign');
        $output .= $this->heading('Por favor, escolha um grupo para atribuir participantes', 3);
        $output .= html_writer::table($table);
        $output .= $this->page_footer();
        return $output;
    }

    public function choose_curso_ufsc_page() {

        // Imprime cabeçalho da página
        $output = $this->header();
        $output .= $this->heading(get_string('grupos_tutoria', 'tool_tutores'));

        $table = new html_table();
        $table->head = array(get_string('cursos_ufsc', 'tool_tutores'));
        $table->tablealign = 'center';
        $table->data = array();

        foreach ($this->cursos as $id_curso => $nome_curso) {
            $url = new moodle_url('/admin/tool/tutores/index.php?curso_ufsc=' . $id_curso);
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
        $table->head = array(get_string('grupos_tutoria', 'tool_tutores'), get_string('edit'));
        $table->tablealign = 'center';
        $table->data = array();

        $add_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc' => $this->curso_ativo, 'action' => 'add'));
        $add_controls = $this->heading('<a href="' . $add_url . '">' . get_string('addnewgroup', 'tool_tutores') . '</a>');

        foreach ($grupos as $grupo) {
            $edit_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc' => $this->curso_ativo, 'id' => $grupo->id, 'action' => 'edit'));
            $delete_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc' => $this->curso_ativo, 'id' => $grupo->id, 'action' => 'delete'));
            $controls = get_action_icon($edit_url, 'edit', get_string('edit'), get_string('edit')) .
                  get_action_icon($delete_url, 'delete', get_string('delete'), get_string('delete'));

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

    public function page_header($current_tab) {
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

        $toprow = array();
        $toprow[] = new tabobject('index', new moodle_url('/admin/tool/tutores/index.php', array('curso_ufsc' => $this->curso_ativo)), get_string('gerenciar_tutores', 'tool_tutores'));
        $toprow[] = new tabobject('assign', new moodle_url('/admin/tool/tutores/assign.php', array('curso_ufsc' => $this->curso_ativo)), get_string('designar_participantes', 'tool_tutores'));
        $toprow[] = new tabobject('permission', new moodle_url('/admin/tool/tutores/permissions.php', array('curso_ufsc' => $this->curso_ativo)), get_string('definir_permissoes', 'tool_tutores'));
        $tabs = array($toprow);

        $output .= print_tabs($tabs, $current_tab, null, null, true);

        return $output;
    }

    public function page_footer() {
        return $this->footer();
    }

}