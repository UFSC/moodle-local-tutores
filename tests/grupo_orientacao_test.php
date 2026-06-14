<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Testes para a camada de grupos de orientação (local_tutores_grupo_orientacao).
 *
 * Espelha grupos_tutoria_test.php para o lado orientador. Cobre a lógica
 * auto-contida: localização do relationship pela tag grupo_orientacao, accessors
 * plurais de relationship_cohorts do papel orientador, listagens, "orientador
 * responsável" e formatação. NÃO cobre as funções que dependem de report_unasus
 * (caminhos com filtro de get_grupos_orientacao_by_userid / _new).
 *
 * @package    local_tutores
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
// tag/lib.php precisa estar carregado antes do lib.php do relationship porque
// relationship_add_relationship() chama tag_set() (mesma ordem do distribution_test).
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/local/relationship/lib.php');
require_once($CFG->dirroot . '/local/tutores/lib.php');

/**
 * @group local_tutores
 */
class local_tutores_grupo_orientacao_testcase extends advanced_testcase {

    /** @var stdClass categoria que guarda os relationships (a "turma"). */
    protected $category;
    /** @var context_coursecat */
    protected $catcontext;
    /** @var int id da categoria usado como $categoria_turma nas chamadas. */
    protected $categoria_turma;

    /** @var int */
    protected $studentroleid;
    /** @var int */
    protected $orientadorroleid;

    /** @var int id do relationship com tag grupo_orientacao. */
    protected $relationshipid;
    /** @var int relationship_cohorts.id do papel estudante. */
    protected $rc_estudante;
    /** @var int relationship_cohorts.id do papel orientador. */
    protected $rc_orientador;

    /** @var int relationship_groups.id do grupo A. */
    protected $grupo_a;
    /** @var int relationship_groups.id do grupo B. */
    protected $grupo_b;

    /** @var stdClass orientador do grupo A. */
    protected $orientador_a;
    /** @var stdClass orientador do grupo B. */
    protected $orientador_b;
    /** @var stdClass estudante no grupo A. */
    protected $estudante_a1;
    /** @var stdClass segundo estudante no grupo A. */
    protected $estudante_a2;
    /** @var stdClass estudante no grupo B. */
    protected $estudante_b1;

    protected function setUp() {
        global $DB;

        $this->resetAfterTest();

        $gen = $this->getDataGenerator();

        // Papéis: usamos shortnames padrão do Moodle e os declaramos na config do plugin.
        $this->studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'), MUST_EXIST);
        $this->orientadorroleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        set_config('local_tutores_student_roles', 'student');
        set_config('local_tutores_tutor_roles', 'teacher');
        set_config('local_tutores_orientador_roles', 'editingteacher');

        // Categoria + contexto que hospedam o relationship.
        $this->category = $gen->create_category();
        $this->categoria_turma = $this->category->id;
        $this->catcontext = context_coursecat::instance($this->category->id);

        // Um cohort por papel, ambos no contexto da categoria.
        $cohort_estudantes = $gen->create_cohort(array('contextid' => $this->catcontext->id));
        $cohort_orientadores = $gen->create_cohort(array('contextid' => $this->catcontext->id));

        // Relationship marcado com a tag grupo_orientacao (é o que get_relationship procura).
        $this->relationshipid = $this->create_tagged_relationship(
            $this->catcontext->id, 'Grupos de Orientação', 'grupo_orientacao');

        $this->rc_estudante = relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort_estudantes->id,
            'roleid' => $this->studentroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));
        $this->rc_orientador = relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort_orientadores->id,
            'roleid' => $this->orientadorroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));

        // Dois grupos. A: orientador_a + estudante_a1/a2. B: orientador_b + estudante_b1.
        $this->grupo_a = relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid, 'name' => 'Grupo A',
            'userlimit' => 0, 'uniformdistribution' => 0));
        $this->grupo_b = relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid, 'name' => 'Grupo B',
            'userlimit' => 0, 'uniformdistribution' => 0));

        $this->orientador_a = $gen->create_user(array('firstname' => 'Olga', 'lastname' => 'Orientadora'));
        $this->orientador_b = $gen->create_user(array('firstname' => 'Otávio', 'lastname' => 'Orientador'));
        $this->estudante_a1 = $gen->create_user(array('firstname' => 'Ana', 'lastname' => 'Aluna'));
        $this->estudante_a2 = $gen->create_user(array('firstname' => 'Bruno', 'lastname' => 'Aluno'));
        $this->estudante_b1 = $gen->create_user(array('firstname' => 'Carla', 'lastname' => 'Aluna'));

        relationship_add_member($this->grupo_a, $this->rc_orientador, $this->orientador_a->id);
        relationship_add_member($this->grupo_a, $this->rc_estudante, $this->estudante_a1->id);
        relationship_add_member($this->grupo_a, $this->rc_estudante, $this->estudante_a2->id);
        relationship_add_member($this->grupo_b, $this->rc_orientador, $this->orientador_b->id);
        relationship_add_member($this->grupo_b, $this->rc_estudante, $this->estudante_b1->id);
    }

    /**
     * Cria um relationship com a tag informada. Ver nota em grupos_tutoria_test.php:
     * relationship_add_relationship() dispara um debugging() legado via tag_set(); no
     * setUp() o aviso não é capturado no buffer assertável (só impresso). Quando este
     * helper é chamado no CORPO de um teste, o chamador deve consumir o debugging.
     */
    protected function create_tagged_relationship($contextid, $name, $tag) {
        return relationship_add_relationship((object) array(
            'contextid' => $contextid,
            'name' => $name,
            'tags' => array($tag),
        ));
    }

    // -----------------------------------------------------------------
    // Localização do relationship por tag.
    // -----------------------------------------------------------------

    public function test_get_relationship_orientacao_encontra_pelo_tag() {
        $relationship = local_tutores_grupo_orientacao::get_relationship_orientacao($this->categoria_turma);
        $this->assertEquals($this->relationshipid, $relationship->id);
        $this->assertEquals('Grupos de Orientação', $relationship->nome);
    }

    public function test_get_relationship_tag_inexistente_dispara_erro() {
        // Não há relationship com a tag grupo_tutoria nesta categoria.
        $this->setExpectedException('moodle_exception');
        local_tutores_base_group::get_relationship($this->categoria_turma, 'grupo_tutoria');
    }

    public function test_get_relationship_ambiguo_dispara_erro() {
        // Dois relationships com a MESMA tag em subcategorias cujo path contém o
        // id da categoria-pai. Consultar pela pai deve casar ambos e estourar.
        $gen = $this->getDataGenerator();
        $pai = $gen->create_category();

        foreach (array('Turma 1', 'Turma 2') as $nome) {
            $filha = $gen->create_category(array('parent' => $pai->id));
            $filhactx = context_coursecat::instance($filha->id);
            $this->create_tagged_relationship($filhactx->id, $nome, 'grupo_orientacao');
        }
        // Consome o debugging legado de tag_set() das duas criações no corpo do teste.
        $this->assertCount(2, $this->getDebuggingMessages());
        $this->resetDebugging();

        $this->setExpectedException('moodle_exception');
        local_tutores_grupo_orientacao::get_relationship_orientacao($pai->id);
    }

    // -----------------------------------------------------------------
    // Accessors plurais de relationship_cohorts (papel orientador).
    // -----------------------------------------------------------------

    public function test_get_relationship_cohorts_orientadores_retorna_keyed_array() {
        $cohorts = local_tutores_grupo_orientacao::get_relationship_cohorts_orientadores($this->relationshipid);
        $this->assertCount(1, $cohorts);
        $this->assertArrayHasKey($this->rc_orientador, $cohorts);
    }

    public function test_get_relationship_cohorts_orientadores_suporta_multiplos_cohorts() {
        // Segundo cohort no MESMO papel orientador → o accessor plural devolve os dois.
        $cohort2 = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        $rc2 = relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort2->id,
            'roleid' => $this->orientadorroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));

        $cohorts = local_tutores_grupo_orientacao::get_relationship_cohorts_orientadores($this->relationshipid);
        $this->assertCount(2, $cohorts);
        $this->assertArrayHasKey($this->rc_orientador, $cohorts);
        $this->assertArrayHasKey($rc2, $cohorts);
    }

    public function test_get_relationship_cohort_orientadores_singular_dispara_debugging_com_multiplos() {
        // O wrapper singular existe só por retrocompatibilidade: deve chamar
        // debugging() quando há mais de um cohort no papel.
        $cohort2 = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort2->id,
            'roleid' => $this->orientadorroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));

        $cohort = local_tutores_grupo_orientacao::get_relationship_cohort_orientadores($this->relationshipid);
        $this->assertDebuggingCalled();
        $this->assertNotEmpty($cohort->id);
    }

    public function test_get_relationship_cohorts_orientadores_sem_cohort_dispara_erro() {
        // Relationship novo, sem nenhum cohort de orientador.
        $rid = $this->create_tagged_relationship($this->catcontext->id, 'Vazio', 'grupo_orientacao');
        // Consome o debugging legado de tag_set() da criação no corpo do teste.
        $this->assertDebuggingCalled();
        $this->setExpectedException('moodle_exception');
        local_tutores_grupo_orientacao::get_relationship_cohorts_orientadores($rid);
    }

    // -----------------------------------------------------------------
    // Listagens (menu id => fullname).
    // -----------------------------------------------------------------

    public function test_get_estudantes_lista_todos_os_alunos() {
        $estudantes = local_tutores_grupo_orientacao::get_estudantes($this->categoria_turma);
        $this->assertCount(3, $estudantes);
        $this->assertArrayHasKey($this->estudante_a1->id, $estudantes);
        $this->assertArrayHasKey($this->estudante_a2->id, $estudantes);
        $this->assertArrayHasKey($this->estudante_b1->id, $estudantes);
        $this->assertEquals('Ana Aluna', $estudantes[$this->estudante_a1->id]);
    }

    public function test_get_orientadores_lista_somente_orientadores() {
        $orientadores = local_tutores_grupo_orientacao::get_orientadores($this->categoria_turma);
        $this->assertCount(2, $orientadores);
        $this->assertArrayHasKey($this->orientador_a->id, $orientadores);
        $this->assertArrayHasKey($this->orientador_b->id, $orientadores);
        // Estudantes não podem aparecer na lista de orientadores.
        $this->assertArrayNotHasKey($this->estudante_a1->id, $orientadores);
    }

    public function test_get_orientadores_grupos_lista_os_grupos() {
        $grupos = local_tutores_grupo_orientacao::get_orientadores_grupos($this->categoria_turma);
        $this->assertCount(2, $grupos);
        $this->assertEquals('Grupo A', $grupos[$this->grupo_a]);
        $this->assertEquals('Grupo B', $grupos[$this->grupo_b]);
    }

    // -----------------------------------------------------------------
    // Orientador responsável por um estudante (join pelo grupo compartilhado).
    // -----------------------------------------------------------------

    public function test_get_orientador_responsavel_estudante_retorna_orientador_do_mesmo_grupo() {
        // estudante_a1 e orientador_a estão no Grupo A.
        $resp = local_tutores_grupo_orientacao::get_orientador_responsavel_estudante(
            $this->categoria_turma, $this->estudante_a1->id);
        $this->assertEquals($this->orientador_a->id, $resp->id);
        $this->assertEquals('Olga Orientadora', $resp->fullname);
    }

    public function test_get_orientador_responsavel_estudante_isola_por_grupo() {
        // estudante_b1 está no Grupo B → responsável é orientador_b, nunca orientador_a.
        $resp = local_tutores_grupo_orientacao::get_orientador_responsavel_estudante(
            $this->categoria_turma, $this->estudante_b1->id);
        $this->assertEquals($this->orientador_b->id, $resp->id);
    }

    // -----------------------------------------------------------------
    // Formatação do agrupamento.
    // -----------------------------------------------------------------

    public function test_grupo_orientacao_to_string_inclui_nome_e_orientador() {
        $str = local_tutores_grupo_orientacao::grupo_orientacao_to_string(
            $this->categoria_turma, $this->grupo_a);
        $this->assertContains('Grupo A', $str);
        $this->assertContains('Olga Orientadora', $str);
    }

    public function test_grupo_orientacao_to_string_sem_orientador() {
        // Grupo sem nenhum orientador associado: o rótulo "Sem Orientador
        // Responsável" deve aparecer (o JOIN interno faz o grupo vazio devolver
        // zero responsáveis).
        $grupo_vazio = relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid, 'name' => 'Grupo Sem Orientador',
            'userlimit' => 0, 'uniformdistribution' => 0));

        $str = local_tutores_grupo_orientacao::grupo_orientacao_to_string(
            $this->categoria_turma, $grupo_vazio);
        $this->assertContains('Grupo Sem Orientador', $str);
        $this->assertContains('Sem Orientador Responsável', $str);
    }

    public function test_grupo_orientacao_to_string_lista_todos_os_orientadores() {
        // Grupo com mais de um orientador: todos os nomes devem aparecer (sem
        // GROUP BY colapsando para um único responsável).
        $orientador_extra = $this->getDataGenerator()->create_user(
            array('firstname' => 'Olavo', 'lastname' => 'Orientador'));
        relationship_add_member($this->grupo_a, $this->rc_orientador, $orientador_extra->id);

        $str = local_tutores_grupo_orientacao::grupo_orientacao_to_string(
            $this->categoria_turma, $this->grupo_a);
        $this->assertContains('Olga Orientadora', $str);
        $this->assertContains('Olavo Orientador', $str);
    }
}
