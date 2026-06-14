<?php

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Passos de Behat específicos do local_tutores.
 *
 * O nome do arquivo PRECISA bater com o nome da classe para o Moodle carregar
 * isto como um contexto de definição de passos.
 */
class behat_tutores extends behat_base {

    /**
     * Visita o index.php dos grupos de tutoria para a categoria de nome informado.
     *
     * O gerador de dados do Behat não expõe o id numérico da categoria, e o
     * index.php é endereçado por ?categoryid=<id>; então resolvemos o id pelo
     * nome da categoria aqui e montamos a URL.
     *
     * @Given /^I visit the tutoria index for category "([^"]*)"$/
     * @param string $categoryname
     */
    public function i_visit_the_tutoria_index_for_category($categoryname) {
        global $DB;
        $categoryid = $DB->get_field('course_categories', 'id',
            array('name' => $categoryname), MUST_EXIST);
        $url = new moodle_url('/local/tutores/index.php', array('categoryid' => $categoryid));
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Cria um relationship de tutoria (tag grupo_tutoria) com o nome informado na
     * categoria informada.
     *
     * É o mínimo que o index.php precisa para redirecionar: get_relationship_tutoria()
     * apenas localiza o relationship pela tag na categoria, sem exigir cohorts ou
     * membros. Não há gerador de Behat para relationships, então criamos via API.
     *
     * O nome é parametrizado de propósito: o teste de redirecionamento deve afirmar
     * um nome DISTINTO do pluginname ('Grupos de Tutoria'), que o index.php renderiza
     * como heading mesmo em páginas de erro/acesso-negado — senão a asserção passaria
     * sem que o redirecionamento de fato ocorresse.
     *
     * @Given /^a tutoria relationship named "([^"]*)" exists in category "([^"]*)"$/
     * @param string $relationshipname
     * @param string $categoryname
     */
    public function a_tutoria_relationship_named_exists_in_category($relationshipname, $categoryname) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/tag/lib.php');
        require_once($CFG->dirroot . '/local/relationship/lib.php');

        $categoryid = $DB->get_field('course_categories', 'id',
            array('name' => $categoryname), MUST_EXIST);
        $context = context_coursecat::instance($categoryid);
        relationship_add_relationship((object) array(
            'contextid' => $context->id,
            'name' => $relationshipname,
            'tags' => array('grupo_tutoria'),
        ));
    }
}
