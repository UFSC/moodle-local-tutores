<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('locallib.php');
require_once('group_form.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
admin_externalpage_setup('tooltutores');

$renderer = $PAGE->get_renderer('tool_tutores');
$curso_ufsc = get_curso_ufsc_id();
$action = required_param('action', PARAM_ALPHANUMEXT);
$base_url = new moodle_url('/admin/tool/tutores/groups.php', array('curso_ufsc' => $curso_ufsc));

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
		// TODO: Acrescentar tela de confirmação de remoção
		$id_grupo = required_param('id', PARAM_INT);
		$grupo = get_grupo_tutoria($id_grupo);
		
		if (empty($grupo)) {
			echo $renderer->page_header('index');
			print_error('invalid_grupo_tutoria', 'tool_tutores');
		} else {
			delete_grupo_tutoria($curso_ufsc, $id_grupo);
			redirect_to_gerenciar_tutores();
		}
		break;

    default:
		echo $renderer->page_header('index');
        print_error('invalid_action', 'tool_tutores');
        break;
}

// Imprime o restante da página
echo $renderer->page_footer();
?>