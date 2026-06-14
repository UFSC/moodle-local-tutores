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
 * Testes de apoio ao index.php do local_tutores.
 *
 * O index.php é um script de entrada web (não testável direto por PHPUnit; o
 * caminho feliz é coberto por tests/behat/index_redireciona.feature). Aqui
 * garantimos que a chave de lang usada no caminho de erro existe e resolve —
 * na limpeza do index.php o print_error() passou de string crua para esta chave.
 *
 * @package    local_tutores
 * @copyright  2026 UFSC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @group local_tutores
 */
class local_tutores_index_testcase extends advanced_testcase {

    public function test_lang_key_curso_ufsc_nao_encontrado_resolve() {
        $s = get_string('curso_ufsc_nao_encontrado_error', 'local_tutores');
        $this->assertNotEmpty($s);
        // get_string devolve "[[chave]]" quando a chave não existe.
        $this->assertNotContains('[[', $s);
    }
}
