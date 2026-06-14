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
 * Testes para local_tutores\categoria::turma_ufsc().
 *
 * A "categoria da turma" é DEFINIDA pela existência de um relationship com a tag
 * grupo_tutoria OU grupo_orientacao no path do curso — independentemente do
 * course_categories.idnumber ("curso_<N>") que define a categoria do CURSO.
 *
 * Estes testes exercitam o par (curso_ufsc, turma_ufsc) nas combinações que provam
 * essa independência:
 *   1. curso vazio  + COM grupo_tutoria   → turma definida, curso false
 *   2. curso vazio  + sem nenhuma tag     → turma false,    curso false
 *   3. curso definido + sem nenhuma tag   → turma false,    curso definido
 *   4. curso vazio  + só grupo_orientacao → turma definida (a orientação também conta)
 *   5. relationship num ancestral do path → turma definida (resolução sobe a árvore)
 *
 * turma_ufsc() NÃO depende do local_inscricoes (só curso_ufsc desvia); por isso não
 * há skip aqui. As asserções sobre curso_ufsc que dependem do branch de idnumber
 * (cenário 3) são feitas só quando o local_inscricoes não está sobrepondo.
 *
 * @package    local_tutores
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
// tag/lib.php antes do lib.php do relationship: relationship_add_relationship() chama tag_set().
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/local/relationship/lib.php');

/**
 * @group local_tutores
 */
class local_tutores_categoria_turma_testcase extends advanced_testcase {

    protected function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Cria um relationship com a tag informada no contexto da categoria e consome o
     * debugging() legado que tag_set()/tag_assign() dispara (component/contextid).
     */
    protected function add_tagged_relationship($categoryid, $name, $tag) {
        $context = context_coursecat::instance($categoryid);
        $id = relationship_add_relationship((object) array(
            'contextid' => $context->id,
            'name' => $name,
            'tags' => array($tag),
        ));
        $this->assertDebuggingCalled();
        return $id;
    }

    protected function course_in_category($categoryid) {
        return $this->getDataGenerator()->create_course(array('category' => $categoryid))->id;
    }

    // 1. curso vazio + COM grupo_tutoria → turma definida, curso false.
    public function test_turma_definida_por_grupo_tutoria_mesmo_sem_curso() {
        $turmacat = $this->getDataGenerator()->create_category(); // sem idnumber curso_*
        $this->add_tagged_relationship($turmacat->id, 'Turma A', 'grupo_tutoria');
        $courseid = $this->course_in_category($turmacat->id);

        $this->assertEquals($turmacat->id, \local_tutores\categoria::turma_ufsc($courseid));
        // Independência: o curso não é resolvido (sem idnumber e sem inscrição).
        $this->assertFalse(\local_tutores\categoria::curso_ufsc($courseid));
    }

    // 2. curso vazio + sem nenhuma tag → turma false, curso false.
    public function test_turma_false_sem_tag_e_sem_curso() {
        $cat = $this->getDataGenerator()->create_category();
        $courseid = $this->course_in_category($cat->id);

        $this->assertFalse(\local_tutores\categoria::turma_ufsc($courseid));
        $this->assertFalse(\local_tutores\categoria::curso_ufsc($courseid));
    }

    // 3. curso definido + sem nenhuma tag → turma false, curso definido.
    public function test_turma_false_com_curso_definido_mas_sem_tag() {
        $cat = $this->getDataGenerator()->create_category(array('idnumber' => 'curso_88'));
        $courseid = $this->course_in_category($cat->id);

        $this->assertFalse(\local_tutores\categoria::turma_ufsc($courseid));
        // Independência ao contrário: o curso É resolvido pelo idnumber, a turma não.
        // A asserção do curso só vale no branch de idnumber (sem local_inscricoes).
        if (!class_exists('local_inscricoes\\inscricao_ufsc')) {
            $this->assertNotEmpty(\local_tutores\categoria::curso_ufsc($courseid));
        }
    }

    // 4. curso vazio + só grupo_orientacao → turma definida (a orientação também conta).
    public function test_turma_definida_por_grupo_orientacao() {
        $turmacat = $this->getDataGenerator()->create_category();
        $this->add_tagged_relationship($turmacat->id, 'Turma Orientação', 'grupo_orientacao');
        $courseid = $this->course_in_category($turmacat->id);

        $this->assertEquals($turmacat->id, \local_tutores\categoria::turma_ufsc($courseid));
    }

    // 5. relationship num ancestral do path → turma definida (resolução sobe a árvore).
    public function test_turma_definida_por_relationship_em_ancestral() {
        $gen = $this->getDataGenerator();
        $raiz = $gen->create_category();
        $this->add_tagged_relationship($raiz->id, 'Turma na Raiz', 'grupo_tutoria');
        $folha = $gen->create_category(array('parent' => $raiz->id));
        $courseid = $this->course_in_category($folha->id);

        $this->assertEquals($raiz->id, \local_tutores\categoria::turma_ufsc($courseid));
    }
}
