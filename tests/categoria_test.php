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
 * Testes para local_tutores\categoria::curso_ufsc().
 *
 * Cobre a resolução do "Curso UFSC" pelo marcador course_categories.idnumber =
 * "curso_<N>": o marcador pode estar em QUALQUER ancestral do path do curso (não
 * só na raiz), e um path inteiro sem marcador resolve para false. Ver a análise em
 * README.md / CLAUDE.md ("o que um idnumber vazio implica").
 *
 * Estes testes exercitam o branch baseado em idnumber, que só vale quando o
 * local_inscricoes NÃO fornece a classe inscricao_ufsc (caso contrário curso_ufsc
 * ignora o idnumber e usa as atividades de inscrição). Por isso pulamos quando
 * essa classe existe.
 *
 * @package    local_tutores
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @group local_tutores
 */
class local_tutores_categoria_testcase extends advanced_testcase {

    protected function setUp() {
        $this->resetAfterTest();

        // O branch de idnumber só roda quando o local_inscricoes não expõe esta
        // classe; se ela existir, curso_ufsc() usa inscricoes_activities e estes
        // testes não se aplicam.
        if (class_exists('local_inscricoes\\inscricao_ufsc')) {
            $this->markTestSkipped('local_inscricoes presente: curso_ufsc() usa o branch de inscrições, não o idnumber.');
        }
    }

    /**
     * Cria um curso na categoria informada e devolve seu id.
     */
    protected function course_in_category($categoryid) {
        return $this->getDataGenerator()->create_course(array('category' => $categoryid))->id;
    }

    public function test_curso_ufsc_acha_marcador_em_ancestral_intermediario() {
        $gen = $this->getDataGenerator();
        // raiz (sem marcador) > meio (curso_55) > folha (sem marcador); curso na folha.
        $raiz = $gen->create_category();
        $meio = $gen->create_category(array('parent' => $raiz->id, 'idnumber' => 'curso_55'));
        $folha = $gen->create_category(array('parent' => $meio->id));
        $courseid = $this->course_in_category($folha->id);

        // O marcador não está na raiz nem na folha, e ainda assim deve ser achado.
        $this->assertEquals($meio->id, \local_tutores\categoria::curso_ufsc($courseid));
    }

    public function test_curso_ufsc_acha_marcador_na_raiz() {
        $gen = $this->getDataGenerator();
        $raiz = $gen->create_category(array('idnumber' => 'curso_77'));
        $folha = $gen->create_category(array('parent' => $raiz->id));
        $courseid = $this->course_in_category($folha->id);

        $this->assertEquals($raiz->id, \local_tutores\categoria::curso_ufsc($courseid));
    }

    public function test_curso_ufsc_retorna_false_quando_nenhum_ancestral_tem_marcador() {
        $gen = $this->getDataGenerator();
        // Nenhuma categoria do path tem idnumber 'curso_%'.
        $raiz = $gen->create_category();
        $folha = $gen->create_category(array('parent' => $raiz->id));
        $courseid = $this->course_in_category($folha->id);

        $this->assertFalse(\local_tutores\categoria::curso_ufsc($courseid));
    }

    public function test_curso_ufsc_ignora_idnumber_que_nao_e_de_curso() {
        $gen = $this->getDataGenerator();
        // idnumber preenchido, mas sem o prefixo curso_ → não é marcador de curso.
        $raiz = $gen->create_category(array('idnumber' => 'departamento_x'));
        $folha = $gen->create_category(array('parent' => $raiz->id));
        $courseid = $this->course_in_category($folha->id);

        $this->assertFalse(\local_tutores\categoria::curso_ufsc($courseid));
    }
}
