# Plano: hierarquia de branches, cascata por rebase e alias `main`

## Contexto

O `local_tutores` é mantido compatível com várias versões do Moodle em paralelo, através de uma
cadeia linear de branches por versão estável:

```
MOODLE_30_STABLE → MOODLE_31_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE
```

> **`MOODLE_31_STABLE` ainda não foi criado.** Enquanto não existir, a cascata viva é
> `MOODLE_30_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE` (o `MOODLE_38_STABLE` rebaseia direto
> sobre o `MOODLE_30_STABLE`). Quando o `MOODLE_31_STABLE` for criado, ele entra **entre** o `30` e o
> `38` e a cadeia volta a ser a completa acima. O `MOODLE_35_STABLE` **não** faz parte da cascata.

As três branches existentes (`MOODLE_30_STABLE`, `MOODLE_38_STABLE`, `MOODLE_401_STABLE`) foram
criadas a partir do mesmo commit (`d11cb2c`), então começam **idênticas** — a cascata só passa a ter
efeito real quando código específico de versão divergir. `main` é tratado como **alias** de
`MOODLE_30_STABLE` — sempre aponta para o mesmo commit. Ao terminar a cascata, voltar para o branch
que estava ativo quando o commit foi solicitado.

Este `docs/branch-cascade.md` espelha a seção "Branch hierarchy and cross-version cascade" do
`CLAUDE.md`. Sempre que a seção lá mudar, atualizar este arquivo em paralelo.

## Remote (um único upstream no GitHub)

Diferente do `local_relationship` (espelhado em GitLab UFSC **e** GitHub), o `local_tutores` vive num
**único** upstream: o repo GitHub `UFSC/moodle-local-tutores`. Ele é alcançado por dois remotes que
apontam para o **mesmo** repo — `origin` (HTTPS) e `stream` (SSH) — então um único push atualiza
tudo; não há segundo mirror para manter em sincronia. Os exemplos abaixo usam `origin`.

## `main`: alias de `MOODLE_30_STABLE`

`main` sempre aponta para o mesmo commit de `MOODLE_30_STABLE`. Não recebe commits diretamente, não
participa da cascata de rebase. Quando `MOODLE_30_STABLE` mover, `main` segue por **fast-forward**
(`git merge --ff-only MOODLE_30_STABLE` + push normal). Se `MOODLE_30_STABLE` tiver sido reescrito
(amend/rebase), o sync vira `git reset --hard MOODLE_30_STABLE` + `git push --force-with-lease`.

Historicamente o branch padrão era `master`; ele foi renomeado para `main` e removido do remote. Não
reintroduzir.

## Conteúdo da seção no CLAUDE.md

Reproduzindo a seção atual do CLAUDE.md:

> ## Branch hierarchy and cross-version cascade
>
> The plugin is maintained against several Moodle versions in parallel. The release branches form a
> strictly linear, ordered chain (oldest → newest):
>
> ```
> MOODLE_30_STABLE → MOODLE_31_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE
> ```
>
> **`MOODLE_31_STABLE` is not yet created.** Until it exists, the live cascade is
> `MOODLE_30_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE`. When it is added it slots between `30`
> and `38`. `MOODLE_35_STABLE` is **not** part of the cascade. All branches were bootstrapped from the
> same commit, so they start identical.
>
> ### `main`: alias for `MOODLE_30_STABLE`
>
> `main` is kept strictly aligned with `MOODLE_30_STABLE` — it always points at the exact same commit.
> It does **not** receive commits directly and is **not** part of the cascade chain. Whenever
> `MOODLE_30_STABLE` moves, `main` must be fast-forwarded to it and pushed. Historically the default
> branch was `master`; it was renamed to `main` and removed. Do not reintroduce it.
>
> ### Remote (single GitHub upstream)
>
> `local_tutores` lives on a single upstream — the GitHub repo `UFSC/moodle-local-tutores` — reachable
> through `origin` (HTTPS) and `stream` (SSH), both pointing at the same repo. A single push updates
> everything; there is no second mirror to keep in sync.
>
> ### Cascade rule
>
> When a change lands on any branch in the chain:
>
> 1. Remember the originally-active branch so you can return to it at the end.
> 2. Push the branch where the commit landed.
> 3. **If** the commit landed on `MOODLE_30_STABLE`: fast-forward `main` to it and push. (If the commit
>    landed elsewhere, skip this step — `MOODLE_30_STABLE` did not move.)
> 4. For every branch *downstream* of where the commit landed, rebase it onto its immediately preceding
>    chain neighbour, in order, and force-push.
> 5. Return to the branch you remembered in step 1.
>
> Example — a fix committed on `MOODLE_30_STABLE` (live chain while `MOODLE_31_STABLE` does not exist):
>
> ```bash
> # 0. Remember where we started.
> ORIGINAL_BRANCH=$(git branch --show-current)
>
> # 1. Land the change on MOODLE_30_STABLE, push.
> git checkout MOODLE_30_STABLE
> # ... edit, git add, git commit ...
> git push origin MOODLE_30_STABLE
>
> # 2. main follows MOODLE_30_STABLE (fast-forward).
> git checkout main && git merge --ff-only MOODLE_30_STABLE
> git push origin main
>
> # 3. Cascade downstream. (When MOODLE_31_STABLE exists, insert it here between 30 and 38.)
> git checkout MOODLE_38_STABLE && git rebase MOODLE_30_STABLE
> git push --force-with-lease origin MOODLE_38_STABLE
>
> git checkout MOODLE_401_STABLE && git rebase MOODLE_38_STABLE
> git push --force-with-lease origin MOODLE_401_STABLE
>
> # 4. Return to the original branch.
> git checkout "$ORIGINAL_BRANCH"
> ```
>
> If the change lands on a non-base branch, skip step 2 (`MOODLE_30_STABLE` was not touched, `main` is
> already in sync), push only that branch, cascade downstream from there, then return.
>
> ### Notes
>
> - Always cascade in order, never skip a hop. Each branch rebases onto the previous chain branch as
>   just updated, not onto the branch where the original change was made.
> - `main` tracks `MOODLE_30_STABLE` by fast-forward in the normal case; by `git reset --hard` +
>   `git push --force-with-lease` if `MOODLE_30_STABLE` was rewritten.
> - Always return to the branch you started on at the end (step 5).
> - Upstream branches (to the left of where you committed) are not updated automatically — backporting
>   is a separate, explicit decision.
> - Prefer `git push --force-with-lease` over `git push --force`.
> - Resolve rebase conflicts the normal way (`git add` + `git rebase --continue`); do not abandon the
>   cascade halfway.

## Verificação

1. Abrir `CLAUDE.md` e conferir que a seção "Branch hierarchy and cross-version cascade" reflete o
   conteúdo acima, com as subseções (alias `main`, remote, cascade rule, notes).
2. `git rev-parse main MOODLE_30_STABLE` retorna o mesmo SHA duas vezes.
3. `git ls-remote --heads origin main MOODLE_30_STABLE` mostra o mesmo SHA dos dois lados (e, como
   `stream` é o mesmo repo, `git ls-remote --heads stream ...` coincide).
4. Após uma cascata: cada `MOODLE_*_STABLE` em sync com seu respectivo remote.

## Riscos e pontos de atenção

- **`MOODLE_31_STABLE` documentado mas inexistente:** a cadeia canônica cita o `31`, mas ele ainda não
  foi criado. Quem rodar a cascata deve seguir a cadeia viva (`30 → 38 → 401`) até o branch existir.
- **Branches idênticas no início:** as três branches partem do mesmo commit; a cascata é um
  placeholder inerte enquanto não houver divergência por versão.
- **`main` rastreia a versão mais antiga:** por convenção (herdada do `local_relationship`), o branch
  padrão acompanha `MOODLE_30_STABLE`, não a versão mais nova.
- **Convenção textual, não enforcement:** a regra é descrita mas não há hook/automação que force a
  cascata ou o sync do alias. Se for desejado, um hook futuro pode ser criado.
- **Idioma:** seção do `CLAUDE.md` em inglês; este `docs/branch-cascade.md` em português, como o plano
  equivalente no `local_relationship`.
