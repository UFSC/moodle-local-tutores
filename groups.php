<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');
require_once('group_form.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$categoryid = required_param('categoryid', PARAM_INT);
$context = context_coursecat::instance($categoryid);
$base_url = new moodle_url('/local/tutores/groups.php', array('categoryid' => $categoryid));

$PAGE->set_url($base_url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_tutores'));
$PAGE->set_heading(get_string('pluginname', 'local_tutores'));

require_login();
require_capability('local/tutores:manage', $context);

/** @var $renderer local_tutores_renderer */
$renderer = $PAGE->get_renderer('local_tutores');
$curso_ufsc = get_curso_ufsc_id();


switch ($action) {
    case 'add':
        $base_url->param('action', 'add');
        $form = new group_form($base_url);

        if ($form->is_cancelled()) {
            redirect_to_gerenciar_tutores();
        } elseif ($data = $form->get_data()) {
            if (create_grupo_tutoria($curso_ufsc, $data->nome))
                redirect_to_gerenciar_tutores();
        }

        echo $renderer->page_header('index');
        $form->display();
        break;

    case 'edit':
        $id_grupo = required_param('id', PARAM_INT);
        $grupo = get_grupo_tutoria($id_grupo);

        $base_url->params(array('action' => 'edit', 'id' => $id_grupo));
        $form = new group_form($base_url);
        if ($form->is_cancelled()) {
            redirect_to_gerenciar_tutores();
        } elseif ($data = $form->get_data()) {
            if (update_grupo_tutoria($curso_ufsc, $id_grupo, $data->nome))
                redirect_to_gerenciar_tutores();
        }

        $form->set_data(array('nome' => $grupo->nome));
        echo $renderer->page_header('index');
        $form->display();
        break;

    case 'delete':
        $id_grupo = required_param('id', PARAM_INT);
        $confirm = optional_param('confirm', false, PARAM_BOOL);
        $grupo = get_grupo_tutoria($id_grupo);

        if (empty($grupo)) {
            echo $renderer->page_header('index');
            print_error('invalid_grupo_tutoria', 'local_tutores');
        } elseif ($confirm) {
            delete_grupo_tutoria($curso_ufsc, $id_grupo);
            redirect_to_gerenciar_tutores();
        } else {
            $return_url = new moodle_url('/local/tutores/index.php', array('categoryid' => $categoryid));
            $confirm_url = new moodle_url('/local/tutores/groups.php', array('categoryid' => $categoryid,
                'id' => $id_grupo, 'action' => 'delete', 'confirm' => true));

            echo $renderer->delete_confirmation($return_url, $confirm_url, $grupo);
        }
        break;

    default:
        echo $renderer->page_header('index');
        print_error('invalid_action', 'local_tutores');
        break;
}

// Imprime o restante da página
echo $renderer->page_footer();