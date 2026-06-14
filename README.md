Grupos de tutoria
=================

Este plugin contém rotinas para agrupamento de tutores e seus estudantes. 
 
Público alvo
------------
As rotinas desta biblioteca são utilizadas pelos relatórios UnaSUS

Instalação
----------

Este plugin deve ser instalado em "local/tutores"

Permissões
----------
Este plugin necessita que o usuário que terá acesso a este plugin,
tenha as seguintes capabilities marcadas como *permitir*:

* local/tutores:manage

Como o "Curso UFSC" é identificado (e o que um idnumber vazio implica)
---------------------------------------------------------------------

O "Curso UFSC" é marcado por `course_categories.idnumber = "curso_<N>"`. Esse
`idnumber` é apenas um **rótulo**: se ele estiver **vazio**, aquela categoria
**não é** um marcador de curso. Não há fallback que eleja outra categoria "por
padrão" — as rotinas de resolução simplesmente **retornam `false`** (curso não
resolvido). O que muda entre as rotinas é *até onde na árvore* cada uma procura o
marcador:

* `local_tutores\categoria::curso_ufsc($courseid)` (preferida): varre **todas** as
  categorias do path do curso e devolve o **ancestral** cujo `idnumber LIKE
  'curso_%'` — ou seja, o curso **não precisa** ser a raiz. Se nenhuma categoria do
  path casar, retorna `false`. **Exceção:** com o `local_inscricoes` instalado,
  esse caminho **ignora o `idnumber`** e localiza o curso pela atividade de
  inscrição habilitada no path — aí o `idnumber` vazio é irrelevante.
* `local_tutores_base_group::get_curso_ufsc_id($courseid)` (legado, `lib.php`):
  sobe até a categoria **raiz** (nível 1) e tira o `curso_` do `idnumber` *dela*;
  raiz com `idnumber` vazio/não-`curso_` → `false`.
* `local_tutores_get_curso_ufsc_id()` (`locallib.php`, usada pelo `index.php`):
  olha **somente a categoria exata** passada em `categoryid`, sem subir; `idnumber`
  vazio → `false`, o que no `index.php` vira o erro "Não é possível habilitar o
  Grupo de Tutoria neste curso".

Resumo: a rotina nova aceita o marcador `curso_<N>` em **qualquer ancestral** do
path; as legadas exigem que ele esteja na **raiz** (ou na própria categoria, no
caso do `index.php`). Unificar esse comportamento é o objetivo do `FIXME` do
`index.php`. Coberto por `tests/categoria_test.php`.

Distinção curso × turma (a tag `grupo_tutoria` não resolve o curso)
-------------------------------------------------------------------

São **duas categorias** e **duas funções** diferentes — a tag do relationship
**não** define a categoria do *curso*:

* **Categoria do CURSO** — `curso_ufsc()`: usa **apenas** `idnumber` (ou as
  inscrições). Nunca olha a tag do relationship; logo, um `idnumber` vazio **não**
  é "salvo" pela existência de um relationship marcado com `grupo_tutoria` — a
  função retorna `false` mesmo assim.
* **Categoria da TURMA** — `turma_ufsc($courseid)` (e, analogamente, o
  `get_relationship()` em `lib.php`): localiza a categoria cujo contexto
  **hospeda** um relationship com a tag `grupo_tutoria`/`grupo_orientacao`, **sem
  depender de `idnumber`**.

Conceitualmente curso ⊇ turma, mas são **a mesma** categoria quando o relationship
é criado direto na categoria do curso. A "nova estrutura" que o `FIXME` quer
adotar é justamente esse caminho baseado em tag/categoria (`turma_ufsc`), que
dispensa o `curso_ufsc`/`idnumber`.

O que acontece quando o curso não é resolvido (`false`)
-------------------------------------------------------

Quando `curso_ufsc()` retorna `false` (idnumber vazio + sem inscrições), **nada
explode** — não há exceção, e a consequência depende de **quem consome**:

* **`report_unasus`** (`factory.php`): **degrada graciosamente**. A categoria da
  turma é resolvida à parte por `turma_ufsc()` (via tag), então o relatório
  continua funcionando. O único efeito: `categoria_curso_ufsc` alimenta
  `report_unasus_get_nomes_cohorts()`, cujo `cc.path LIKE '%/<false>'` vira `'%/'`
  / `'%//%'` (não casa com nada) → o **filtro de cohorts fica vazio**, em silêncio,
  sem erro.
* **`index.php`** (pela rotina legada `local_tutores_get_curso_ufsc_id()`): faz o
  oposto — `false` cai em `print_error('Não é possível habilitar o Grupo de
  Tutoria neste curso')`, uma **tela de erro**.

