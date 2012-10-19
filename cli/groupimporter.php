<?php

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/grouplib.php');
require_once(__DIR__.'/../locallib.php');
require_once(__DIR__.'/../lib.php');

$help =
        "Importa participantes de um grupo em um curso moodle para um Grupo de Tutoria.

Options:
--group=GROUPID       Grupo que terá seus participantes importados.
--cursoufsc=CURSOID   Curso UFSC que terá o grupo criado.
--list                Apenas lista os participantes que serão migrados
--execute             Executa a migração (cria grupo e inscreve participantes)
-h, --help            Imprime esta ajuda.

Exemplo:
\$ sudo -u www-data /usr/bin/php admin/tool/tutores/cli/groupimporter.php
";

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
    array(
        'group'             => null,
        'cursoufsc'         => null,
        'execute'           => false,
        'list'              => false,
        'help'              => false,
    ),
    array(
        'l' => 'list',
        'h' => 'help',
    )
);

if ($options['help']) {
    echo $help;
    exit(0);
}

if (empty($CFG->version)) {
    cli_error(get_string('missingconfigversion', 'debug'));
}

echo "\n".get_string('cliheading', 'tool_tutores')."\n\n";

if (!isset($options['group'])) {
    cli_error('Informe o ID do grupo que será importado utilizando: "--group=GROUPID"');
} else {
    if (!groups_group_exists($options['group'])) {
        cli_error('Grupo informado não existe, verifique o ID informado');
    } else {
        $papeis_estudantes = grupos_tutoria::get_papeis_estudantes();
        $papeis_tutores = grupos_tutoria::get_papeis_tutores();

        $group = groups_get_group($options['group']);
        $members = get_moodle_group_members($options['group']);
        $count_members = count($members);
        cli_heading("Grupo: {$group->name} ({$count_members} participantes)");
    }
}


if ($options['list']) {
    foreach ($members as $member) {
        echo "{$member->firstname} {$member->lastname}\n";
    }
}

if ($options['execute']) {
    if (!isset($options['cursoufsc'])) {
        cli_error('Informe o CÓDIGO do Curso UFSC que receberá o grupo de tutoria: "--cursoufsc=CURSOID"');
    }

    $grupo_tutoria = create_grupo_tutoria($options['cursoufsc'], $group->name);

    if (!$grupo_tutoria) {
        cli_error('Ocorreu uma falha ao tentar criar o grupo de tutoria.');
    }

    foreach ($members as $member) {
        $papel = $member->papel_principal;

        if (in_array($papel, $papeis_estudantes)) {
            $tipo = GRUPO_TUTORIA_TIPO_ESTUDANTE;
        } else if (in_array($papel, $papeis_tutores)) {
            $tipo = GRUPO_TUTORIA_TIPO_TUTOR;
        }

        $result = add_member_grupo_tutoria($grupo_tutoria, $member->username, $tipo);
        if ($result) {
            echo "{$member->firstname} {$member->lastname} \n";
        } else {
            echo "FALHOU: {$member->firstname} {$member->lastname} \n";
        }
    }
}

exit(0);

// Try target DB connection.
$problem = '';

$targetdb = moodle_database::get_driver_instance($options['dbtype'], $options['dblibrary']);
$dboptions = array();
if ($options['dbport']) {
    $dboptions['dbport'] = $options['dbport'];
}
if ($options['dbsocket']) {
    $dboptions['dbsocket'] = $options['dbsocket'];
}
try {
    $targetdb->connect($options['dbhost'], $options['dbuser'], $options['dbpass'], $options['dbname'],
        $options['prefix'], $dboptions);
    if ($targetdb->get_tables()) {
        $problem .= get_string('targetdatabasenotempty', 'tool_dbtransfer');
    }
} catch (moodle_exception $e) {
    $problem .= $e->debuginfo."\n\n";
    $problem .= get_string('notargetconectexception', 'tool_dbtransfer');
}

if ($problem !== '') {
    echo $problem."\n\n";
    exit(1);
}

$feedback = new text_progress_trace();
tool_dbtransfer_transfer_database($DB, $targetdb, $feedback);
$feedback->finished();

cli_heading(get_string('success'));
exit(0);
