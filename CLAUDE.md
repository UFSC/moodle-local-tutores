# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`local_tutores` ("Grupos de Tutoria") is a Moodle **local plugin** (PHP 5.6 era). It is a
*library*, not a standalone application: it exposes routines for grouping tutors, orientadores
(advisors), and students, and these routines are consumed by the UnaSUS reports plugin
(`report_unasus`). Installed under `local/tutores`.

- Component: `local_tutores`
- Hard dependency: `local_relationship` (see `version.php`). Most data lives in that plugin's tables.
- Runtime dependency (not declared): `report_unasus` provides helpers called from `lib.php`
  (`report_unasus_int_array_to_sql()`, `query_alunos_relationship()`). Code here will fatal if
  those are absent.
- Access requires the `local/tutores:manage` capability at `CONTEXT_COURSECAT`.

## Commands

There is no build, lint, or unit-test harness in this repo — it's a Moodle plugin loaded by a
Moodle install. To exercise it you need a running Moodle (the repo lives inside a Moodle tree at
`local/tutores`).

- Apply DB/version changes after editing `version.php` or `db/`: bump `$plugin->version` then run
  `php admin/cli/upgrade.php` from the Moodle root (or visit Site administration → Notifications).
- Migration CLI (legacy middleware → relationship): from the Moodle root,
  `sudo -u www-data php local/tutores/cli/migrate.php --cursoufsc=CURSOID [--list|--execute]`.
- Coding standards: follow Moodle / PSR-12 conventions. The `moodle-standards` skill applies these
  automatically — prefer it over ad-hoc formatting.

## Architecture

### The relationship data model (core concept)

A "grupo de tutoria" / "grupo de orientação" is modeled as a `local_relationship` **relationship**.
The chain of tables (all owned by `local_relationship`) is:

```
relationship ──< relationship_cohorts (one row per ROLE: links a Moodle cohort + role to the relationship)
             ──< relationship_groups   (the actual tutoring/advising groups)
                  └──< relationship_members (a user, in a group, via a relationship_cohort)
```

A relationship is identified by a **tag** on it:
- `grupo_tutoria`  → tutoring groups (students + tutors)
- `grupo_orientacao` → advising groups (students + orientadores)

`get_relationship($categoria_turma, $tag_name)` in `lib.php` is the lookup hub: given a course
category id and a tag, it walks `tag → tag_instance → relationship → context → course_categories`
(matching the category in the path) to find the one relationship. It `print_error`s if zero or more
than one match.

### Roles are configurable, not hardcoded

Which Moodle roles count as "student", "tutor", or "orientador" is admin-configured in
`settings.php` and read from `$CFG`:
- `local_tutores_student_roles`  → `get_papeis_estudantes()`
- `local_tutores_tutor_roles`    → `get_papeis_tutores()`
- `local_tutores_orientador_roles` → `get_papeis_orientadores()`

These are comma-separated role **shortnames**, resolved to `relationship_cohorts` rows by joining
`relationship_cohorts → role` on shortname.

### Class hierarchy (`lib.php`)

- `local_tutores_base_group` — shared logic: student lookups, "who is responsible for this student"
  queries, and the `relationship_cohorts` accessors for the student role.
- `local_tutores_grupos_tutoria extends base` — tutor-side: groups, tutor filters,
  `grupo_tutoria_to_string()`, `get_tutor_responsavel_estudante()`.
- `local_tutores_grupo_orientacao extends base` — orientador-side: the parallel set for advising.

### Multi-cohort support (important recent change)

A single role in a relationship may now map to **multiple** `relationship_cohorts` rows. Code was
migrated from singular to **plural** accessors:
- Use `get_relationship_cohorts_estudantes()` / `_tutores()` / `_orientadores()` (plural) — they
  return an array keyed by `relationship_cohorts.id` and are built into SQL via
  `$DB->get_in_or_equal(...)` → `IN (...)`.
- The singular `get_relationship_cohort_*()` wrappers remain only for backward compatibility; they
  call `debugging()` when more than one cohort exists. **Do not introduce new callers of the
  singular form** — that defeats the multi-cohort fix. `get_responsavel_estudante()` accepts either
  an array (multi-cohort) or a single `stdClass` (legacy) for the responsável side.

### Category-tree navigation (`classes/categoria.php`, namespaced `local_tutores\categoria`)

The "curso UFSC" is encoded as `course_categories.idnumber = "curso_<N>"`, usually on a root (level 1)
category. Helpers here walk a course's category `path` to find:
- `curso_ufsc($courseid)` — the course-category id of the curso (branches on whether
  `local_inscricoes` is installed).
- `turma_ufsc($courseid)` — the category that holds the tutoria/orientacao relationships.
- `contexto_turma_ufsc($categoria_turma)` — the `context.id`, used by reports for `context.path LIKE` filters.

`locallib.php` and the older `local_tutores_*` procedural helpers in `lib.php` do similar
`curso_ufsc ↔ category` translation; note the `FIXME` in `index.php` flagging that the old
`curso_ufsc`-based flow still needs migrating to the category-based structure.

#### What an empty `idnumber` implies (resolution behaviour)

`idnumber = "curso_<N>"` is just a **marker** that labels a category as the "curso UFSC". An empty
`idnumber` means *that category is not a curso marker* — there is **no fallback** that elects a
default category; the routines simply fail to resolve and return `false`. What differs between the
three (inconsistent) resolution routines is *how far up the tree* each looks for the marker:

- `local_tutores\categoria::curso_ufsc($courseid)` (preferred) — scans **every** category in the
  course's path and returns the ancestor whose `idnumber LIKE 'curso_%'` (so the curso need **not**
  be the root). If no category in the whole path matches → `false`. **Exception:** when
  `local_inscricoes` is installed (`class_exists('local_inscricoes\inscricao_ufsc')`), this branch
  ignores `idnumber` entirely and locates the curso by the enabled inscription activity
  (`inscricoes_activities.enable = 1`) in the path — so an empty `idnumber` is irrelevant there.
- `local_tutores_base_group::get_curso_ufsc_id($courseid)` (legacy, `lib.php`) — climbs to the
  **root** (level-1) category and strips `curso_` from *its* `idnumber`; empty/non-`curso_` root → `false`.
- `local_tutores_get_curso_ufsc_id()` (`locallib.php`, used by `index.php`) — checks **only the exact
  category** passed in `categoryid`, never climbing; empty `idnumber` → `false`, which surfaces in
  `index.php` as `print_error('Não é possível habilitar o Grupo de Tutoria neste curso')`.

Net divergence: the new helper accepts the `curso_<N>` marker on **any ancestor** of the path, while
the legacy helpers require it on the **root** (or on the exact category, for `index.php`). Eliminating
this gap is the point of the `index.php` `FIXME`. Covered by `categoria_test.php`.

**Curso vs. turma — the `grupo_tutoria` tag does NOT resolve the curso.** Two different categories,
two different functions. `curso_ufsc()` (the *curso* category) only ever uses `idnumber`/inscrições —
it never looks at the relationship tag, so an empty `idnumber` is **not** rescued by the presence of a
tagged relationship. The category located *by* the `grupo_tutoria`/`grupo_orientacao` tag is the
**turma** category, via `turma_ufsc($courseid)` (and analogously `get_relationship()` in `lib.php`):
it returns the category whose context **hosts** a relationship carrying that tag, with **no dependence
on `idnumber`**. Conceptually curso ⊇ turma, but they are the **same** category when the relationship
is created directly on the curso category. The `FIXME`'s "new structure" is precisely this
tag-/category-based `turma_ufsc` path, which dispenses with `curso_ufsc`/`idnumber` entirely.

**Who consumes a `false` curso category (the consequence differs by entry point).** When
`curso_ufsc()` returns `false`, nothing throws — `get_field_sql` uses `IGNORE_MISSING` and the
consumer's property is even typed `bool|string`:
- `report_unasus` (`factory.php`) **degrades gracefully**. `categoria_turma_ufsc` is resolved
  *separately* via `turma_ufsc()` (tag-based), so the report's main pipeline keeps working. The only
  fallout: `categoria_curso_ufsc` feeds `report_unasus_get_nomes_cohorts()`, whose `cc.path LIKE
  '%/<false>'` interpolates to `'%/'`/`'%//%'` (matching nothing) → the **cohort filter dropdown comes
  up empty**, silently, with no error.
- `index.php` (via the legacy `local_tutores_get_curso_ufsc_id()`) does the opposite: `false` →
  `print_error('Não é possível habilitar o Grupo de Tutoria neste curso')`, a **hard error page**.

### Middleware (`middlewarelib.php`)

`Middleware` is a **singleton** wrapping an ADODB connection to the external UFSC "middleware"
database. It reimplements Moodle-style `get_records_sql` / `get_record_sql` / `get_field_sql` /
`get_records_sql_menu` so middleware queries read like Moodle DML. SQL uses brace placeholders that
`fix_names()` rewrites: `{view_X}`, `{geral_X}`, `{table_X}` → external DB tables/views, and `{x}`
→ the local Moodle-prefixed table. Connection config comes from `local_academico`; if unset it
falls back to the local DB with context `UNASUS_CP` (the "Capacitação" deployment that has no real
middleware). Only used by the legacy migration path (`cli/migrate.php` + `migratelib.php`).

### Entry point

`index.php` is thin: it resolves the category, finds the tutoria relationship, and redirects to
`local/relationship/groups.php`. The settings-navigation hook in `lib.php` that used to add a menu
node is currently commented out.

## Conventions to respect

- Comments, identifiers, and user strings are in **Portuguese** — match the surrounding language.
- User-facing strings go through `get_string(..., 'local_tutores')`; add keys to
  `lang/en/local_tutores.php`. Some `print_error` calls intentionally reference the `report_unasus`
  string component, not this one.
- Build SQL `IN` lists with `$DB->get_in_or_equal(..., SQL_PARAMS_NAMED, ...)` and merge the
  returned params — this is the established pattern for the multi-cohort queries.
