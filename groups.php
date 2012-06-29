<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('locallib.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
admin_externalpage_setup('tooltutores');

$renderer = $PAGE->get_renderer('tool_tutores');
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);

// Imprime o cabeçalho
echo $renderer->page_header('index');

switch ($action) {
    case 'add':
        require_once('create_group_form.php');
        $form = new create_group_form();
        $form->display();
        break;

    case 'edit':
        require_once('edit_group_form.php');
        $id_grupo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $grupo = get_grupo_tutoria($id_grupo);

        $form = new edit_group_form();
        $form->set_data(array('nome' => $grupo->nome));
        $form->display();
        break;

    default:
        print_error('invalid_action', 'tool_tutores');
        break;
}

// Imprime o restante da página
echo $renderer->page_footer();
?>