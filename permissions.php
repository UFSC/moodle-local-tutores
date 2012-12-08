<?php
require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/roles/lib.php');
require_once('locallib.php');


require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
admin_externalpage_setup('tooltutores');

$renderer = $PAGE->get_renderer('local_tutores');

// Select de usuários

$admisselector = new admins_existing_selector();
$admisselector->set_extra_fields(array('username', 'email'));

$potentialadmisselector = new admins_potential_selector();
$potentialadmisselector->set_extra_fields(array('username', 'email'));

//
// Output
//
// Cabeçalho

echo $renderer->page_header('permission');

// Conteúdo
?>
<div id="addadmisform">
    <?php echo $OUTPUT->heading(get_string('definir_permissoes_curso', 'local_tutores', 'Saúde da Família'), 3); ?>

    <form id="assignform" method="post" action="<?php echo $PAGE->url ?>">
        <div>
            <input type="hidden" name="sesskey" value="<?php p(sesskey()); ?>"/>

            <table class="generaltable generalbox roleassigntable boxaligncenter" summary="">
                <tr>
                    <td id='existingcell'>
                        <p>
                            <label for="removeselect">Administradores dos grupos de tutoria</label>
                        </p>
                        <?php $admisselector->display(); ?>
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
                            <label for="addselect">Possíveis administradores</label>
                        </p>
                        <?php $potentialadmisselector->display(); ?>
                    </td>
                </tr>
            </table>
        </div>
    </form>
</div>

<?php
// Restante da página
echo $renderer->page_footer();