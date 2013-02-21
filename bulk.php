<?php

/**
 * Cadastro de pessoas em grupos de tutoria (em lote)
 *
 * O cadastro em lote funciona a partir do envio de um arquivo .csv utilizando a seguinte formatação:
 *
 * - Primeira linha: deve conter os nomes dos campos:
 * -- username
 * -- tipo
 * - Segunda e demais linhas:
 * -- os respectivos dados para usuário e tipo
 * -- Os tipos que são reconhecidos como válidos são: 'E', 'Estudante', 'T', 'Tutor'
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_once('locallib.php');
require_once('bulk_form.php');

// aumenta limites de timeout e memória
@set_time_limit(60*60); // 1 hour should be enough
raise_memory_limit(MEMORY_HUGE);

$iid         = optional_param('iid', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);
$categoryid = required_param('categoryid', PARAM_INT);
$context = context_coursecat::instance($categoryid);

$base_url = new moodle_url('/local/tutores/bulk.php', array('categoryid' => $categoryid));
$backto_url = new moodle_url('/local/tutores/index.php', array('categoryid' => $categoryid));

global $PAGE;
$PAGE->set_url($base_url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_tutores'));
$PAGE->set_heading(get_string('pluginname', 'local_tutores'));

// login e permissões
require_login();
require_capability('local/tutores:manage', $context);

/** @var local_tutores_renderer $renderer */
$renderer = $PAGE->get_renderer('local_tutores');
$curso_ufsc = get_curso_ufsc_id();

if (empty($iid)) {

    // processa formulário inicial
    $mform = new admin_bulk_tutores($base_url);
    if ($formdata = $mform->get_data()) {
        $iid = csv_import_reader::get_new_iid('uploaduser');
        $cir = new csv_import_reader($iid, 'uploaduser');

        $content = $mform->get_file_content('userfile');

        $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
        unset($content);

        if ($readcount === false) {
            print_error('csvloaderror', '', $base_url);
        } else if ($readcount == 0) {
            print_error('csvemptyfile', 'error', $base_url);
        }

        $filecolumns = validate_upload_grupos_tutoria($cir, $base_url);
        // Vai continuar no form2

    } else {
        // exibe formulário inicial
        echo $renderer->page_header();

        $mform->display();

        echo $renderer->page_footer();
        die();
    }
} else {

    $cir = new csv_import_reader($iid, 'uploaduser');
    $filecolumns = validate_upload_grupos_tutoria($cir, $base_url);
}

$mform2 = new admin_bulk_tutores_confirmation($base_url, array('columns'=>$filecolumns, 'data'=>array('iid'=>$iid), 'curso_ufsc' => $curso_ufsc));

// If a file has been uploaded, then process it
if ($formdata = $mform2->is_cancelled()) {
    $cir->cleanup(true);
    redirect($base_url);

} else if ($formdata = $mform2->get_data()) {
    // realiza a inscrição em lote

    // init csv import helper
    $cir->init();
    $linenum = 1; //column header is first line

    $grupotutoria = get_grupo_tutoria($formdata->grupotutoria);

    // Verifica se o grupo de tutoria existe
    if (empty($grupotutoria))
        print_error('invalid_grupo_tutoria', 'tool_tutores', $base_url);

    $columns = array_flip($filecolumns);

    $failed = array();
    while ($line = $cir->next()) {
        $linenum++;
        $username = $line[$columns['username']];
        $tipo = $line[$columns['tipo']];

        // Hack: Trocar 'Estudante' -> E, e 'Tutor' -> 'T'
        $tipo = str_replace(array('Estudante', 'Tutor'), array(GRUPO_TUTORIA_TIPO_ESTUDANTE, GRUPO_TUTORIA_TIPO_TUTOR), $tipo);

        if (!add_member_grupo_tutoria($grupotutoria->id, $username, $tipo)) {
            $failed[] = array($linenum, $username);
        }
    }

    // Limpa os arquivos temporários utilizados neste envio
    $cir->close();
    $cir->cleanup(true);

    $numpeople = $linenum-1;
    echo $renderer->display_bulk_results($base_url, $numpeople, $failed);
    die();
}

echo $renderer->page_header();

echo $renderer->preview_bulk_upload($cir, $previewrows, $filecolumns);
$mform2->display();

echo $renderer->page_footer();