<?php

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_once('locallib.php');
require_once('bulk_form.php');

$iid         = optional_param('iid', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

// aumenta limites de timeout e memória
@set_time_limit(60*60); // 1 hour should be enough
raise_memory_limit(MEMORY_HUGE);

// login e permissões
require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
admin_externalpage_setup('tooltutoresbulk');

/** @var $renderer tool_tutores_renderer */
$renderer = $PAGE->get_renderer('tool_tutores');
$curso_ufsc = get_curso_ufsc_id();
$base_url = new moodle_url('/admin/tool/tutores/bulk.php', array('curso_ufsc' => $curso_ufsc));

// Exibe o seletor de cursos caso não exista um curso informado em $curso_ufsc
if (empty($curso_ufsc)) {
    echo $renderer->choose_curso_ufsc_page('/admin/tool/tutores/bulk.php');
    die();
}

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

    $columns = validate_upload_grupos_tutoria($cir, $base_url);

    echo $renderer->preview_bulk_upload($cir, $previewrows, $columns);

} else {
    // exibe formulário inicial
    echo $renderer->page_header();

    $mform->display();

    echo $renderer->page_footer();
    die();
}