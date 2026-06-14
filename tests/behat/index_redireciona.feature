@local_tutores
Feature: Redirecionamento do index dos grupos de tutoria
  O local/tutores/index.php exige a capability local/tutores:manage na categoria,
  resolve o curso UFSC pelo idnumber (curso_<N>) e, achando o relationship de
  tutoria da categoria, redireciona para a página de grupos do relationship.

  Background:
    Given the following "categories" exist:
      | name             | category | idnumber |
      | Raiz Tutoria     | 0        | RAIZTUT  |
      | Curso Com Numero | RAIZTUT  | curso_99 |
    And the following "users" exist:
      | username | firstname | lastname |
      | gerente  | Gabriela  | Gerente  |
    # A capability não é concedida a nenhum papel por padrão (sem archetypes),
    # então atribuímos um papel na raiz e concedemos as permissões via override.
    And the following "role assigns" exist:
      | user    | role    | contextlevel | reference |
      | gerente | teacher | Category     | RAIZTUT   |
    And the following "permission overrides" exist:
      | capability              | permission | role    | contextlevel | reference |
      | local/tutores:manage    | Allow      | teacher | Category     | RAIZTUT   |
      | local/relationship:view | Allow      | teacher | Category     | RAIZTUT   |

  Scenario: Gestor autorizado é redirecionado para os grupos do relationship
    Given a tutoria relationship exists in category "Curso Com Numero"
    And I log in as "gerente"
    And I visit the tutoria index for category "Curso Com Numero"
    # Caímos na página de grupos do relationship, cujo cabeçalho exibe o nome dele.
    Then I should see "Grupos de Tutoria"
