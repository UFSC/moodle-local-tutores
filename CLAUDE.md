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

## Branch hierarchy and cross-version cascade

The plugin is maintained against several Moodle versions in parallel. The release branches form a
strictly linear, ordered chain (oldest → newest):

```
MOODLE_30_STABLE → MOODLE_31_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE
```

> **`MOODLE_31_STABLE` is not yet created.** Until it exists, the live cascade is
> `MOODLE_30_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE` (i.e. `MOODLE_38_STABLE` rebases directly
> onto `MOODLE_30_STABLE`). When `MOODLE_31_STABLE` is added it slots **between** `30` and `38`, and
> the cascade reverts to the full chain above. `MOODLE_35_STABLE` is **not** part of the cascade.

All branches were bootstrapped from the same commit, so they start **identical**; the cascade only
becomes meaningful once version-specific code actually diverges.

### `main`: alias for `MOODLE_30_STABLE`

`main` is kept strictly aligned with `MOODLE_30_STABLE` — it always points at the exact same commit.
It does **not** receive commits directly and is **not** part of the cascade chain. Whenever
`MOODLE_30_STABLE` moves, `main` must be fast-forwarded to it and pushed (see the cascade workflow
below).

> Historically the default branch was `master`. It was renamed to `main` and removed from the
> remote. Do not reintroduce it.

### Remote (single GitHub upstream)

Unlike `local_relationship` (which is mirrored on GitLab UFSC **and** GitHub), `local_tutores` lives
on a **single** upstream: the GitHub repo `UFSC/moodle-local-tutores`. It is reachable through two
remotes that point at the **same** repo — `origin` (HTTPS) and `stream` (SSH) — so a single push
updates everything; there is no second mirror to keep in sync. Examples below use `origin`.

### Cascade rule

When a change lands on any branch in the chain:

1. Remember the originally-active branch so you can return to it at the end.
2. Push the branch where the commit landed.
3. **If** the commit landed on `MOODLE_30_STABLE`: fast-forward `main` to it and push. (If the commit
   landed elsewhere, skip this step — `MOODLE_30_STABLE` did not move.)
4. For every branch *downstream* of where the commit landed (to the right of it in the chain), rebase
   it onto its immediately preceding chain neighbour, in order, and force-push.
5. Return to the branch you remembered in step 1.

Example — a fix committed on `MOODLE_30_STABLE` (the base of the chain, so the full workflow runs).
Reflects the live chain while `MOODLE_31_STABLE` does not exist (`38` rebases directly onto `30`):

```bash
# 0. Remember where we started.
ORIGINAL_BRANCH=$(git branch --show-current)

# 1. Land the change on MOODLE_30_STABLE, push.
git checkout MOODLE_30_STABLE
# ... edit, git add, git commit ...
git push origin MOODLE_30_STABLE

# 2. main follows MOODLE_30_STABLE (fast-forward).
git checkout main && git merge --ff-only MOODLE_30_STABLE
git push origin main

# 3. Cascade downstream — each branch rebases onto the previous one as just updated.
#    (When MOODLE_31_STABLE exists, insert it here between 30 and 38.)
git checkout MOODLE_38_STABLE && git rebase MOODLE_30_STABLE
git push --force-with-lease origin MOODLE_38_STABLE

git checkout MOODLE_401_STABLE && git rebase MOODLE_38_STABLE
git push --force-with-lease origin MOODLE_401_STABLE

# 4. Return to the original branch.
git checkout "$ORIGINAL_BRANCH"
```

If the change lands on a non-base branch (e.g., `MOODLE_38_STABLE`), skip step 2 —
`MOODLE_30_STABLE` was not touched, so `main` is already in sync. Push only the branch where the
commit landed, then cascade downstream from there, then return.

### Notes

- Always cascade in order, never skip a hop. Each branch rebases onto the **previous chain branch as
  just updated**, not onto the branch where the original change was made.
- `main` tracks `MOODLE_30_STABLE` by fast-forward (`git merge --ff-only MOODLE_30_STABLE` + plain
  `git push`) in the normal case. If `MOODLE_30_STABLE` was rewritten (e.g., amend or rebase of an
  existing commit), `main` needs `git reset --hard MOODLE_30_STABLE` + `git push --force-with-lease`
  instead.
- Always return to the branch you started on at the end of the cascade (step 5). Skipping this leaves
  you parked on `MOODLE_401_STABLE` (or whichever was last) and a follow-up session may accidentally
  continue work on the wrong branch.
- Upstream branches (to the left of where you committed) are **not** updated automatically —
  backporting to older versions is a separate, explicit decision.
- Prefer `git push --force-with-lease` over `git push --force` (or `-f`). It refuses to overwrite
  remote work that appeared since your last fetch. Use the unsafer `--force` only when you have a
  specific reason and have confirmed no one else is working on the branch.
- Resolve any conflicts during rebase the normal way (`git add` + `git rebase --continue`); do not
  abandon the cascade halfway — leaving downstream branches out of sync is the failure mode this rule
  exists to prevent.
- This section is mirrored by `docs/branch-cascade.md`. Whenever it changes, update that file in
  parallel.

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
**turma** category, via `turma_ufsc($courseid)`: it returns the category whose context **hosts** a
relationship carrying that tag, with **no dependence on `idnumber`**. Conceptually curso ⊇ turma, but
they are the **same** category when the relationship is created directly on the curso category. The
`FIXME`'s "new structure" is precisely this tag-/category-based `turma_ufsc` path, which dispenses
with `curso_ufsc`/`idnumber` entirely.

`turma_ufsc()` is **not** the same as `get_relationship()`: `turma_ufsc($courseid)` takes a *course*
and **discovers** the turma category by walking the path + joining `{course}`; `get_relationship(
$categoria_turma, $tag)` takes an *already-resolved category* and returns the *relationship* in it.
Covering one does not cover the other.

**The two axes are independent — think of the pair `(curso_ufsc, turma_ufsc)`** (exactly what
`report_unasus`'s `factory.php` computes in two separate calls). Each is set or `false` on its own:

| state | `curso_ufsc` (idnumber) | `turma_ufsc` (tag) |
|---|---|---|
| curso empty + tagged relationship present | `false` | **set** |
| curso empty + no tagged relationship | `false` | `false` |
| curso set + no tagged relationship | set | `false` |
| curso set + tagged relationship (normal) | set | set |

Nuance: `turma_ufsc()` matches **either** tag (`t.name IN ('grupo_orientacao', 'grupo_tutoria')`), so
a course with only a *grupo_orientacao* relationship (no *grupo_tutoria*) still has a turma category;
`turma_ufsc` is only `false` when **neither** tag is present in the path. Covered by
`categoria_turma_test.php` (`curso_ufsc` itself is covered by `categoria_test.php`).

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
