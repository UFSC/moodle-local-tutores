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
 * Testes para a camada de grupos de tutoria (local_tutores_grupos_tutoria / base_group).
 *
 * Cobre a lógica auto-contida do plugin: localização do relationship por tag,
 * accessors plurais de relationship_cohorts, listagens de estudantes/tutores,
 * "tutor responsável" e formatação. NÃO cobre as funções que dependem de
 * report_unasus (get_estudantes_grupo_tutoria e os caminhos com filtro de
 * *_by_userid / *_new) — essa superfície fica intencionalmente de fora para
 * manter os testes isolados deste componente.
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
class local_tutores_grupos_tutoria_testcase extends advanced_testcase {

    /** @var stdClass categoria que guarda os relationships (a "turma"). */
    protected $category;
    /** @var context_coursecat */
    protected $catcontext;
    /** @var int id da categoria usado como $categoria_turma nas chamadas. */
    protected $categoria_turma;

    /** @var int */
    protected $studentroleid;
    /** @var int */
    protected $tutorroleid;

    /** @var int id do relationship com tag grupo_tutoria. */
    protected $relationshipid;
    /** @var int relationship_cohorts.id do papel estudante. */
    protected $rc_estudante;
    /** @var int relationship_cohorts.id do papel tutor. */
    protected $rc_tutor;

    /** @var int relationship_groups.id do grupo A. */
    protected $grupo_a;
    /** @var int relationship_groups.id do grupo B. */
    protected $grupo_b;

    /** @var stdClass tutor do grupo A. */
    protected $tutor_a;
    /** @var stdClass tutor do grupo B. */
    protected $tutor_b;
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
        $this->tutorroleid = $DB->get_field('role', 'id', array('shortname' => 'teacher'), MUST_EXIST);
        set_config('local_tutores_student_roles', 'student');
        set_config('local_tutores_tutor_roles', 'teacher');
        set_config('local_tutores_orientador_roles', 'editingteacher');

        // Categoria + contexto que hospedam o relationship.
        $this->category = $gen->create_category();
        $this->categoria_turma = $this->category->id;
        $this->catcontext = context_coursecat::instance($this->category->id);

        // Um cohort por papel, ambos no contexto da categoria.
        $cohort_estudantes = $gen->create_cohort(array('contextid' => $this->catcontext->id));
        $cohort_tutores = $gen->create_cohort(array('contextid' => $this->catcontext->id));

        // Relationship marcado com a tag grupo_tutoria (é o que get_relationship procura).
        $this->relationshipid = $this->create_tagged_relationship(
            $this->catcontext->id, 'Grupos de Tutoria', 'grupo_tutoria');

        $this->rc_estudante = relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort_estudantes->id,
            'roleid' => $this->studentroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));
        $this->rc_tutor = relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort_tutores->id,
            'roleid' => $this->tutorroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));

        // Dois grupos. A: tutor_a + estudante_a1/a2. B: tutor_b + estudante_b1.
        $this->grupo_a = relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid, 'name' => 'Grupo A',
            'userlimit' => 0, 'uniformdistribution' => 0));
        $this->grupo_b = relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid, 'name' => 'Grupo B',
            'userlimit' => 0, 'uniformdistribution' => 0));

        $this->tutor_a = $gen->create_user(array('firstname' => 'Tânia', 'lastname' => 'Tutora'));
        $this->tutor_b = $gen->create_user(array('firstname' => 'Tobias', 'lastname' => 'Tutor'));
        $this->estudante_a1 = $gen->create_user(array('firstname' => 'Ana', 'lastname' => 'Aluna'));
        $this->estudante_a2 = $gen->create_user(array('firstname' => 'Bruno', 'lastname' => 'Aluno'));
        $this->estudante_b1 = $gen->create_user(array('firstname' => 'Carla', 'lastname' => 'Aluna'));

        relationship_add_member($this->grupo_a, $this->rc_tutor, $this->tutor_a->id);
        relationship_add_member($this->grupo_a, $this->rc_estudante, $this->estudante_a1->id);
        relationship_add_member($this->grupo_a, $this->rc_estudante, $this->estudante_a2->id);
        relationship_add_member($this->grupo_b, $this->rc_tutor, $this->tutor_b->id);
        relationship_add_member($this->grupo_b, $this->rc_estudante, $this->estudante_b1->id);
    }

    /**
     * Cria um relationship com a tag informada.
     *
     * relationship_add_relationship() chama o tag_set()/tag_assign() legado, que
     * dispara um debugging() ("você deveria passar component/contextid"). Quando
     * isso ocorre dentro do setUp() o aviso não é capturado no buffer assertável do
     * PHPUnit (só é impresso), então não quebra os testes. Já quando este helper é
     * chamado no CORPO de um teste, o chamador precisa consumir o debugging com
     * assertDebuggingCalled()/assertDebuggingCalledCount() antes de prosseguir.
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

    public function test_get_relationship_tutoria_encontra_pelo_tag() {
        $relationship = local_tutores_grupos_tutoria::get_relationship_tutoria($this->categoria_turma);
        $this->assertEquals($this->relationshipid, $relationship->id);
        $this->assertEquals('Grupos de Tutoria', $relationship->nome);
    }

    public function test_get_relationship_tag_inexistente_dispara_erro() {
        // Não há relationship com a tag grupo_orientacao nesta categoria.
        $this->setExpectedException('moodle_exception');
        local_tutores_base_group::get_relationship($this->categoria_turma, 'grupo_orientacao');
    }

    public function test_get_relationship_ambiguo_dispara_erro() {
        global $DB;

        // Dois relationships com a MESMA tag em subcategorias cujo path contém o
        // id da categoria-pai. Consultar pela pai deve casar ambos e estourar.
        $gen = $this->getDataGenerator();
        $pai = $gen->create_category();
        $paictx = context_coursecat::instance($pai->id);

        foreach (array('Turma 1', 'Turma 2') as $nome) {
            $filha = $gen->create_category(array('parent' => $pai->id));
            $filhactx = context_coursecat::instance($filha->id);
            $this->create_tagged_relationship($filhactx->id, $nome, 'grupo_tutoria');
        }
        // Consome o debugging legado de tag_set() das duas criações no corpo do teste.
        $this->assertCount(2, $this->getDebuggingMessages());
        $this->resetDebugging();

        $this->setExpectedException('moodle_exception');
        local_tutores_grupos_tutoria::get_relationship_tutoria($pai->id);
    }

    // -----------------------------------------------------------------
    // Accessors plurais de relationship_cohorts.
    // -----------------------------------------------------------------

    public function test_get_relationship_cohorts_tutores_retorna_keyed_array() {
        $cohorts = local_tutores_grupos_tutoria::get_relationship_cohorts_tutores($this->relationshipid);
        $this->assertCount(1, $cohorts);
        $this->assertArrayHasKey($this->rc_tutor, $cohorts);
    }

    public function test_get_relationship_cohorts_estudantes_retorna_keyed_array() {
        $cohorts = local_tutores_base_group::get_relationship_cohorts_estudantes($this->relationshipid);
        $this->assertCount(1, $cohorts);
        $this->assertArrayHasKey($this->rc_estudante, $cohorts);
    }

    public function test_get_relationship_cohorts_tutores_suporta_multiplos_cohorts() {
        global $DB;

        // Segundo cohort no MESMO papel tutor → o accessor plural deve devolver os dois.
        $cohort2 = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        $rc2 = relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort2->id,
            'roleid' => $this->tutorroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));

        $cohorts = local_tutores_grupos_tutoria::get_relationship_cohorts_tutores($this->relationshipid);
        $this->assertCount(2, $cohorts);
        $this->assertArrayHasKey($this->rc_tutor, $cohorts);
        $this->assertArrayHasKey($rc2, $cohorts);
    }

    public function test_get_relationship_cohort_tutores_singular_dispara_debugging_com_multiplos() {
        // O wrapper singular existe só por retrocompatibilidade: deve chamar
        // debugging() quando há mais de um cohort no papel.
        $cohort2 = $this->getDataGenerator()->create_cohort(array('contextid' => $this->catcontext->id));
        relationship_add_cohort((object) array(
            'relationshipid' => $this->relationshipid,
            'cohortid' => $cohort2->id,
            'roleid' => $this->tutorroleid,
            'allowdupsingroups' => 0,
            'uniformdistribution' => 0,
        ));

        $cohort = local_tutores_grupos_tutoria::get_relationship_cohort_tutores($this->relationshipid);
        $this->assertDebuggingCalled();
        $this->assertNotEmpty($cohort->id);
    }

    public function test_get_relationship_cohorts_tutores_sem_cohort_dispara_erro() {
        // Relationship novo, sem nenhum cohort de tutor.
        $rid = $this->create_tagged_relationship($this->catcontext->id, 'Vazio', 'grupo_tutoria');
        // Consome o debugging legado de tag_set() da criação no corpo do teste.
        $this->assertDebuggingCalled();
        $this->setExpectedException('moodle_exception');
        local_tutores_grupos_tutoria::get_relationship_cohorts_tutores($rid);
    }

    // -----------------------------------------------------------------
    // Listagens (menu id => fullname).
    // -----------------------------------------------------------------

    public function test_get_estudantes_lista_todos_os_alunos() {
        $estudantes = local_tutores_base_group::get_estudantes($this->categoria_turma);
        $this->assertCount(3, $estudantes);
        $this->assertArrayHasKey($this->estudante_a1->id, $estudantes);
        $this->assertArrayHasKey($this->estudante_a2->id, $estudantes);
        $this->assertArrayHasKey($this->estudante_b1->id, $estudantes);
        $this->assertEquals('Ana Aluna', $estudantes[$this->estudante_a1->id]);
    }

    public function test_get_tutores_lista_somente_tutores() {
        $tutores = local_tutores_grupos_tutoria::get_tutores($this->categoria_turma);
        $this->assertCount(2, $tutores);
        $this->assertArrayHasKey($this->tutor_a->id, $tutores);
        $this->assertArrayHasKey($this->tutor_b->id, $tutores);
        // Estudantes não podem aparecer na lista de tutores.
        $this->assertArrayNotHasKey($this->estudante_a1->id, $tutores);
    }

    public function test_get_grupos_tutoria_menu_lista_os_grupos() {
        $grupos = local_tutores_grupos_tutoria::get_grupos_tutoria_menu($this->categoria_turma);
        $this->assertCount(2, $grupos);
        $this->assertEquals('Grupo A', $grupos[$this->grupo_a]);
        $this->assertEquals('Grupo B', $grupos[$this->grupo_b]);
    }

    // -----------------------------------------------------------------
    // Tutor responsável por um estudante (join pelo grupo compartilhado).
    // -----------------------------------------------------------------

    public function test_get_tutor_responsavel_estudante_retorna_tutor_do_mesmo_grupo() {
        // estudante_a1 e tutor_a estão no Grupo A.
        $resp = local_tutores_grupos_tutoria::get_tutor_responsavel_estudante(
            $this->categoria_turma, $this->estudante_a1->id);
        $this->assertEquals($this->tutor_a->id, $resp->id);
        $this->assertEquals('Tânia Tutora', $resp->fullname);
    }

    public function test_get_tutor_responsavel_estudante_isola_por_grupo() {
        // estudante_b1 está no Grupo B → responsável é tutor_b, nunca tutor_a.
        $resp = local_tutores_grupos_tutoria::get_tutor_responsavel_estudante(
            $this->categoria_turma, $this->estudante_b1->id);
        $this->assertEquals($this->tutor_b->id, $resp->id);
    }

    public function test_get_responsavel_estudante_aceita_stdclass_legado() {
        global $DB;

        // O lado responsável aceita um único stdClass (formato single-cohort legado).
        $relationship = local_tutores_grupos_tutoria::get_relationship_tutoria($this->categoria_turma);
        $cohort_tutor = $DB->get_record('relationship_cohorts', array('id' => $this->rc_tutor));

        $resp = local_tutores_base_group::get_responsavel_estudante(
            $relationship, $cohort_tutor, $this->estudante_a1->id);
        $this->assertEquals($this->tutor_a->id, $resp->id);
    }

    // -----------------------------------------------------------------
    // Formatação do agrupamento.
    // -----------------------------------------------------------------

    public function test_grupo_tutoria_to_string_inclui_nome_e_tutor() {
        $str = local_tutores_grupos_tutoria::grupo_tutoria_to_string(
            $this->categoria_turma, $this->grupo_a);
        $this->assertContains('Grupo A', $str);
        $this->assertContains('Tânia Tutora', $str);
    }

    public function test_grupo_tutoria_to_string_sem_tutor() {
        // Grupo sem nenhum tutor associado: o rótulo "Sem Tutor Responsável" deve
        // aparecer (o JOIN interno faz o grupo vazio devolver zero responsáveis).
        $grupo_vazio = relationship_add_group((object) array(
            'relationshipid' => $this->relationshipid, 'name' => 'Grupo Sem Tutor',
            'userlimit' => 0, 'uniformdistribution' => 0));

        $str = local_tutores_grupos_tutoria::grupo_tutoria_to_string(
            $this->categoria_turma, $grupo_vazio);
        $this->assertContains('Grupo Sem Tutor', $str);
        $this->assertContains('Sem Tutor Responsável', $str);
    }

    public function test_grupo_tutoria_to_string_lista_todos_os_tutores() {
        // Grupo com mais de um tutor: todos os nomes devem aparecer (sem GROUP BY
        // colapsando para um único responsável).
        $tutor_extra = $this->getDataGenerator()->create_user(
            array('firstname' => 'Teodoro', 'lastname' => 'Tutor'));
        relationship_add_member($this->grupo_a, $this->rc_tutor, $tutor_extra->id);

        $str = local_tutores_grupos_tutoria::grupo_tutoria_to_string(
            $this->categoria_turma, $this->grupo_a);
        $this->assertContains('Tânia Tutora', $str);
        $this->assertContains('Teodoro Tutor', $str);
    }

    // -----------------------------------------------------------------
    // Tradução curso UFSC ↔ categoria (idnumber = "curso_<N>").
    // -----------------------------------------------------------------

    public function test_get_category_from_curso_ufsc_resolve_pelo_idnumber() {
        $curso = $this->getDataGenerator()->create_category(array('idnumber' => 'curso_42'));
        $catid = local_tutores_base_group::get_category_from_curso_ufsc(42);
        $this->assertEquals($curso->id, $catid);
    }

    public function test_get_curso_ufsc_id_extrai_da_categoria_raiz() {
        $gen = $this->getDataGenerator();
        $raiz = $gen->create_category(array('idnumber' => 'curso_77'));
        $filha = $gen->create_category(array('parent' => $raiz->id));
        $course = $gen->create_course(array('category' => $filha->id));

        $this->assertEquals('77', local_tutores_base_group::get_curso_ufsc_id($course->id));
    }

    public function test_get_curso_ufsc_id_retorna_false_sem_idnumber_de_curso() {
        $gen = $this->getDataGenerator();
        $cat = $gen->create_category(); // sem idnumber curso_*
        $course = $gen->create_course(array('category' => $cat->id));

        $this->assertFalse(local_tutores_base_group::get_curso_ufsc_id($course->id));
    }
}
