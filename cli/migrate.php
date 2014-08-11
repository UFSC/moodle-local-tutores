<?php

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once(__DIR__.'/../lib.php');
require_once(__DIR__.'/../locallib.php');
require_once($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');
require_once($CFG->dirroot.'/local/tutores/middlewarelib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
        array(
                'group' => null,
                'cursoufsc' => null,
                'execute' => false,
                'list' => false,
                'help' => false,
        ),
        array(
                'l' => 'list',
                'h' => 'help',
        )
);

if (empty($CFG->version)) {
    cli_error(get_string('missingconfigversion', 'debug'));
}

if ($options['list']) {
    if (!isset($options['cursoufsc'])) {
        cli_error('Informe o CÓDIGO do Curso UFSC que receberá o grupo de tutoria: "--cursoufsc=CURSOID"');
    }

    local_tutores_cli_list($options);
} elseif ($options['execute']) {
    if (!isset($options['cursoufsc'])) {
        cli_error('Informe o CÓDIGO do Curso UFSC que receberá o grupo de tutoria: "--cursoufsc=CURSOID"');
    }

    local_tutores_cli_execute($options);
} else {
    local_tutores_cli_help();
}

function local_tutores_cli_help() {
    $help = <<<HELP
Migra os dados de alunos e tutores para relationship.

Options:
--cursoufsc=CURSOID   Curso UFSC que terá o grupo migrado.
--list                Apenas lista os participantes que serão migrados
--execute             Executa a migração (cria grupo e inscreve participantes)
-h, --help            Imprime esta ajuda.

Exemplo:
\$ sudo -u www-data /usr/bin/php local/tutores/cli/migrate.php
HELP;

    echo $help;
    exit(0);
}

function local_tutores_cli_list($options) {
    $curso_ufsc = $options['cursoufsc'];

    $grupos_tutoria = local_tutores_cli_get_grupos_tutoria($curso_ufsc);
    $category = get_category_context_from_curso_ufsc($curso_ufsc);

    $relationship = local_tutores_cli_get_relationship_tutoria($category->id);

    cli_heading("Categoria: {$category->id}");
    cli_heading("Grupos de Tutoria existentes:");
    foreach ($grupos_tutoria as $grupo) {
        echo "* {$grupo->nome} \n";
    }

    echo "\n";

    cli_heading("Relationships");

    if (!$relationship) {
        echo 'Nenhum relationship foi encontrado';
    } else {
        var_dump($relationship);
    }

    exit(0);
}

function local_tutores_cli_execute($options) {
    $curso_ufsc = $options['cursoufsc'];

    cli_heading(get_string('cliheading', 'local_tutores'));

    $category = get_category_context_from_curso_ufsc($curso_ufsc);
    $relationship = local_tutores_cli_get_relationship_tutoria($category->id);

    if (!$relationship) {
        cli_heading("Criando relationship para categoria: {$category->id}");

        $relationshipid = local_tutores_cli_create_relationship($category->id);
    } else {
        cli_heading("Relationship já existente: {$relationship->id}, categoria: {$category->id}");
        $relationshipid = $relationship->id;
    }

    $cohorts = relationship_get_cohorts($relationshipid);
    if (!$cohorts) {
        cli_heading("Criando cohorts para relationship: {$relationshipid}");

        local_tutores_cli_create_cohorts($relationshipid);
    } else {
        cli_heading("Cohorts já existentes para relationship: {$relationshipid}");
    }

    $grupos = relationship_get_groups($relationshipid);
    if (!$grupos) {
        cli_heading("Criando grupos para relationship: {$relationshipid}");

        $grupos_tutoria = local_tutores_cli_get_grupos_tutoria($curso_ufsc);
        local_tutores_cli_create_groups($relationshipid, $grupos_tutoria);
    } else {
        cli_heading("Grupos já existentes para relationship: {$relationshipid}");
    }

    exit(0);
}

/**
 * Retorna lista de grupos de tutoria de um determinado curso ufsc
 *
 * @param string $curso_ufsc
 * @param array $tutores
 * @return array
 */
function local_tutores_cli_get_grupos_tutoria($curso_ufsc, $tutores = null) {
    $middleware = Middleware::singleton();

    if (is_null($tutores))
        $sql = "SELECT * FROM {table_GruposTutoria} WHERE curso=:curso_ufsc ORDER BY nome";
    else {
        $tutores = int_array_to_sql($tutores);
        $sql = "SELECT gt.id, gt.curso, gt.nome
                      FROM {table_GruposTutoria} gt
                      JOIN {table_PessoasGruposTutoria} pg
                        ON (gt.id=pg.grupo AND gt.curso=:curso_ufsc)
                      JOIN {user} u
                        ON (pg.matricula=u.username AND pg.tipo=:tipo)
                     WHERE u.id IN ({$tutores})";
    }
    return $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_TUTORIA_TIPO_TUTOR));
}