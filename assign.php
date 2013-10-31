<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');

$groupid = required_param('id', PARAM_INT);
$categoryid = required_param('categoryid', PARAM_INT);
$context = context_coursecat::instance($categoryid);

$base_url = new moodle_url('/local/tutores/assign.php', array('categoryid' => $categoryid, 'id' => $groupid));
$backto_url = new moodle_url('/local/tutores/index.php', array('categoryid' => $categoryid));

global $PAGE;
$PAGE->set_url($base_url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_tutores'));
$PAGE->set_heading(get_string('pluginname', 'local_tutores'));

require_login();
require_capability('local/tutores:manage', $context);

/** @var local_tutores_renderer $renderer */
$renderer = $PAGE->get_renderer('local_tutores');
$curso_ufsc = get_curso_ufsc_id();
$grupo_tutoria = get_grupo_tutoria($groupid);

// Opções passadas ao seletor de usuários
$options = array('grupo' => $groupid, 'curso' => $curso_ufsc);

// Select de usuários
$membersselector = new usuarios_tutoria_existing_selector('existingmembersgrupotutoria', $options);
$membersselector->set_extra_fields(array('username', 'email'));

// Select de possíveis usuários
$potentialmembersselector = new usuarios_tutoria_potential_selector('addmembergrupotutoria', $options);
$potentialmembersselector->set_extra_fields(array('username', 'email'));

//
// Processa requisições de inclusão de membros em grupos
//
if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoassign = $potentialmembersselector->get_selected_users();
    if (!empty($userstoassign)) {

        // TODO: verificar permissões
        foreach ($userstoassign as $adduser) {
            add_member_grupo_tutoria($groupid, $adduser->username, $adduser->tipo);
        }

        $potentialmembersselector->invalidate_selected_users();
        $membersselector->invalidate_selected_users();

        // TODO: logar inscrições
        //add_to_log($course->id, 'role', 'assign', 'admin/roles/assign.php?contextid='.$context->id.'&roleid='.$roleid, $rolename, '', $USER->id);
    }
}

//
// Processa requisições de exclusão de membros em grupos
//
if (optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstounassign = $membersselector->get_selected_users();
    if (!empty($userstounassign)) {

        foreach ($userstounassign as $removeuser) {
            remove_member_grupo_tutoria($groupid, $removeuser->username);
        }

        $potentialmembersselector->invalidate_selected_users();
        $membersselector->invalidate_selected_users();

        // TODO: logar desinscrições
        //add_to_log($course->id, 'role', 'unassign', 'admin/roles/assign.php?contextid='.$context->id.'&roleid='.$roleid, $rolename, '', $USER->id);
    }
}

echo $renderer->page_header('assign');
?>


<div id="addadmisform">
    <?php echo $OUTPUT->heading(get_string('definir_permissoes_curso', 'local_tutores', $grupo_tutoria->nome), 3); ?>

    <form id="assignform" method="post" action="<?php echo $PAGE->url ?>">
        <div>
            <input type="hidden" name="sesskey" value="<?php p(sesskey()); ?>"/>

            <table class="generaltable generalbox roleassigntable boxaligncenter" summary="">
                <tr>
                    <td id='existingcell'>
                        <p>
                            <label for="removeselect">Membros do grupo de tutoria</label>
                        </p>
                        <?php $membersselector->display(); ?>
                    </td>

                    <td id="buttonscell">
                        <div id="addcontrols">
                            <input name="add" id="add" type="submit"
                                   value="<?php echo $OUTPUT->larrow() . '&nbsp;' . get_string('add'); ?>"
                                   title="<?php print_string('add'); ?>"/><br/>
                        </div>

                        <div id="removecontrols">
                            <input name="remove" id="remove" type="submit"
                                   value="<?php echo get_string('remove') . '&nbsp;' . $OUTPUT->rarrow(); ?>"
                                   title="<?php print_string('remove'); ?>"/>
                        </div>
                    </td>

                    <td id='potentialcell'>
                        <p>
                            <label for="addselect">Possíveis usuários</label>
                        </p>
                        <?php $potentialmembersselector->display(); ?>
                    </td>
                </tr>
            </table>
        </div>
    </form>
</div>


<?php
echo html_writer::start_tag('div', array('class'=>'backlink'));
echo html_writer::link($backto_url, get_string('backto_grupos_tutoria', 'local_tutores'));
echo html_writer::end_tag('div');

echo $renderer->page_footer();
