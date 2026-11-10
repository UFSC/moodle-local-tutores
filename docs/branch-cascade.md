# Hierarquia de branches, propagação por cherry-pick e alias `main`

## Contexto

O `local_tutores` é mantido compatível com várias versões do Moodle em paralelo, com uma branch por
versão estável, da mais antiga para a mais nova:

```
MOODLE_30_STABLE → MOODLE_31_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE → MOODLE_405_STABLE
```

> **`MOODLE_31_STABLE` ainda não foi criado.** Enquanto não existir, a ordem viva é
> `MOODLE_30_STABLE → MOODLE_38_STABLE → MOODLE_401_STABLE → MOODLE_405_STABLE`. Quando o
> `MOODLE_31_STABLE` for criado, ele entra **entre** o `30` e o `38`. O `MOODLE_35_STABLE` **não** faz
> parte da cadeia.

Este `docs/branch-cascade.md` espelha a seção "Branch hierarchy and cross-version propagation" do
`CLAUDE.md`. Sempre que a seção lá mudar, atualizar este arquivo em paralelo.

## As branches NÃO formam uma cadeia de ancestrais — propaga-se por cherry-pick

As branches nasceram do mesmo commit (`d11cb2c`), mas **divergiram**: cada uma tem os seus números
no `version.php` e código específico de versão (API do PHPUnit, classes depreciadas do core). Medido
em 10/11/2026: nenhuma de `30 → 38 → 401 → 405` é ancestral da seguinte. As correções têm sido
propagadas por **`git cherry-pick -x`** em cada branch (os commits trazem
`(cherry picked from commit …)`), e não por rebase.

**Não rebasear as branches umas sobre as outras, nem rodar o `moodle-cascade` aqui.** O rebase
reescreveria o histórico de todas as branches a jusante, e os superprojetos Moodle fixam o
`local/tutores` nas **pontas** dessas branches (por exemplo, os superprojetos do unasus3 e do
unasus-dev): reescrevê-las deixa esses gitlinks apontando para commits fora de qualquer branch. Com
as branches locais desatualizadas, um push com `--force-with-lease` ainda passaria e **apagaria
commits que só existem no remoto** — foi o que o dry-run do `moodle-cascade` planejou em 10/11/2026.

## `main`: alias de `MOODLE_30_STABLE`

`main` sempre aponta para o mesmo commit de `MOODLE_30_STABLE` e não recebe commits diretamente.
Quando `MOODLE_30_STABLE` mover, empurra-se a mesma ponta para o `main`, por fast-forward.

Historicamente o branch padrão era `master`; ele foi renomeado para `main` e removido do remote. Não
reintroduzir.

## Remote (um único upstream no GitHub)

Diferente do `local_relationship` (espelhado em GitLab UFSC **e** GitHub), o `local_tutores` vive num
**único** upstream: o repo GitHub `UFSC/moodle-local-tutores`. Ele é alcançado por dois remotes que
apontam para o **mesmo** repo — `origin` (HTTPS) e `stream` (SSH) — então um único push atualiza
tudo; não há segundo mirror para manter em sincronia.

## Regra de propagação

1. Commitar na branch **mais antiga** que precisa da mudança e empurrá-la (push simples,
   fast-forward).
2. **Se** essa branch for a `MOODLE_30_STABLE`: empurrá-la também para o `main`
   (`git push stream MOODLE_30_STABLE:main`).
3. Para cada branch **a jusante** que também precise da mudança, em ordem: partir da ponta **remota**
   (`stream/<branch>`, não da branch local, que pode estar velha), `git cherry-pick -x <sha>`, rodar
   os testes naquela versão de Moodle e empurrar com push simples (`HEAD:<branch>`). **Nunca
   force-push.**
4. Mudança que só faz sentido numa versão fica só naquela branch (por exemplo, um teste reescrito para
   o PHPUnit 4.8 do Moodle 3.0).

Exemplo — mudança commitada na `MOODLE_30_STABLE` que vale para todas as versões:

```bash
git fetch stream
git checkout MOODLE_30_STABLE           # precisa estar igual a stream/MOODLE_30_STABLE antes do commit
# ... editar, git add, git commit ...
git push stream MOODLE_30_STABLE
git push stream MOODLE_30_STABLE:main   # main acompanha a 30 (fast-forward)

SHA=$(git rev-parse HEAD)
git worktree add --detach ../tutores-pick stream/MOODLE_38_STABLE
for b in MOODLE_38_STABLE MOODLE_401_STABLE MOODLE_405_STABLE; do
    git -C ../tutores-pick checkout --detach "stream/$b"
    git -C ../tutores-pick cherry-pick -x "$SHA"   # em conflito, parar e resolver
    git -C ../tutores-pick push stream "HEAD:$b"
done
git worktree remove ../tutores-pick
```

## Verificação

1. `git ls-remote --heads stream main MOODLE_30_STABLE` mostra o mesmo SHA dos dois lados.
2. Depois de propagar: em cada branch a jusante, o commit novo está sobre a ponta anterior do remoto
   (o push foi fast-forward, sem `forced update`).
3. Os superprojetos que fixam o `local/tutores` continuam apontando para commits alcançáveis a partir
   de alguma branch (`git branch -r --contains <gitlink>`).

## Riscos e pontos de atenção

- **Conflito no `version.php` é esperado** ao fazer cherry-pick de um bump: cada branch tem o seu
  número. Resolver pondo a versão nova daquela branch; não carregar o número de outra.
- **Branches locais velhas:** as `MOODLE_*` locais de um clone costumam estar atrás do remoto. Por isso
  se parte de `stream/<branch>`, numa worktree destacada.
- **Backport não é automático:** branches mais antigas do que aquela em que se commitou não recebem a
  mudança; levar a elas é uma decisão separada e explícita.
- **Realinhar a cadeia** (um rebase deliberado, uma única vez, seguido da atualização dos gitlinks dos
  superprojetos) é possível, mas exige reescrever esta regra antes de qualquer rebase.
- **`MOODLE_31_STABLE` documentado mas inexistente:** seguir a ordem viva até o branch existir.
- **Idioma:** seção do `CLAUDE.md` em inglês; este `docs/branch-cascade.md` em português, como o plano
  equivalente no `local_relationship`.
