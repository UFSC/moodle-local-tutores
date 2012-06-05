<?php

defined('MOODLE_INTERNAL') || die();

class tool_tutores_renderer extends plugin_renderer_base {

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

    public function index_page() {
        // Tabela
        $table = new html_table();
        $table->head = array(get_string('grupos_tutoria', 'tool_tutores'), get_string('edit'));
        $table->tablealign = 'center';
        $table->data = array();

        $controls = get_action_icon('groups.php', 'edit', get_string('edit'), get_string('edit')) . get_action_icon('#', 'delete', get_string('delete'), get_string('delete'));
        $groups_add_url = new moodle_url('/admin/tool/tutores/groups.php');
        $add_controls = $this->heading('<a href="' . $groups_add_url . '">' . get_string('addnewgroup', 'tool_tutores') . '</a>');

        // Dummy data
        $table->data[] = array('Grupo 1', $controls);
        $table->data[] = array('Grupo 2', $controls);
        $table->data[] = array('Grupo 3', $controls);
        $table->data[] = array('Grupo 4', $controls);
        $table->data[] = array('Grupo 5', $controls);

        // Output
        $output = $this->page_header('index');
        $output .= $add_controls;
        $output .= html_writer::table($table);
        $output .= $add_controls;
        $output .= $this->page_footer();
        return $output;
    }

    public function page_header($currenttab) {
        global $PAGE;
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(get_string('grupos_tutoria', 'tool_tutores'));

        /// Print the category selector
        $category = $PAGE->category;
        $displaylist = array();
        $notused = array();
        make_categories_list($displaylist, $notused);

        $select = new single_select(new moodle_url('/admin/tool/tutores/index.php'), 'category_id', $displaylist, $category->id, null, 'switchcategory');
        $select->set_label(get_string('curso', 'tool_tutores') . ':');
        $output .= '<div class="navbutton">';
        $output .= $this->render($select);
        $output .= '</div>';

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

}