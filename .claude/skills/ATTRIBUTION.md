# Skill Sources

Thirteen agent skills vendored into `.claude/skills/`, one flagship skill per upstream repository.
Each directory is a verbatim copy of the upstream skill (including any supporting files it ships),
except where noted.

| Directory | Upstream | Path in upstream | License |
|---|---|---|---|
| `impeccable/` | [pbakaus/impeccable](https://github.com/pbakaus/impeccable) | `plugin/skills/impeccable/` | Apache-2.0 |
| `ui-ux-pro-max/` | [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) | `.claude/skills/ui-ux-pro-max/` | MIT |
| `design-taste-frontend/` | [Leonxlnx/taste-skill](https://github.com/Leonxlnx/taste-skill) | `skills/taste-skill/` | MIT |
| `design-motion-principles/` | [kylezantos/design-motion-principles](https://github.com/kylezantos/design-motion-principles) | `skills/design-motion-principles/` | MIT |
| `design-md/` | [VoltAgent/awesome-design-md](https://github.com/VoltAgent/awesome-design-md) | `design-md/<brand>/DESIGN.md` → `references/` | MIT — **synthesized SKILL.md, see below** |
| `test-driven-development/` | [obra/superpowers](https://github.com/obra/superpowers) | `skills/test-driven-development/` | MIT |
| `coding-standards/` | [affaan-m/ECC](https://github.com/affaan-m/ECC) | `skills/coding-standards/` | MIT |
| `how-it-works/` | [thedotmack/claude-mem](https://github.com/thedotmack/claude-mem) | `plugin/skills/how-it-works/` | Apache-2.0 |
| `ponytail/` | [DietrichGebert/ponytail](https://github.com/DietrichGebert/ponytail) | `skills/ponytail/` | MIT |
| `brag/` | [latent-spaces/brag](https://github.com/latent-spaces/brag) | `skills/brag/` | MIT |
| `skill-creator/` | [anthropics/skills](https://github.com/anthropics/skills) | `skills/skill-creator/` | Apache-2.0 (`LICENSE.txt` inside the skill dir) |
| `agent-browser/` | [shanraisshan/claude-code-best-practice](https://github.com/shanraisshan/claude-code-best-practice) | `.claude/skills/agent-browser/` | MIT |
| `ai-native-cli/` | [sickn33/agentic-awesome-skills](https://github.com/sickn33/agentic-awesome-skills) | `skills/ai-native-cli/` | MIT |

The full upstream license text for each skill is vendored next to it as `LICENSE.upstream` (as
`LICENSE.txt` for `skill-creator`, keeping the upstream filename). **All thirteen sources are
MIT or Apache-2.0.**

## Selection notes

These repositories are *collections*, not single skills. Each ships many (ECC has 293,
agentic-awesome-skills has 2,547, impeccable ships the same skill across ~20 agent-specific
directories). One representative skill per repository was copied, once, from its canonical path.
Agent-specific duplicates (`.cursor/`, `.kiro/`, `.agents/`, …) were not copied.

`obra/superpowers` also ships `using-superpowers`, its bootstrap meta-skill. It was deliberately
**not** selected: it issues standing instructions that a skill must be invoked before any response,
which changes agent behavior globally rather than adding a capability. `test-driven-development` is
the most self-contained and widely applicable skill in that repository.

`taste-skill/` was renamed to `design-taste-frontend/` so the directory name matches the `name:`
field in the skill's frontmatter, as Claude Code expects. The contents are unmodified.

`brag/` is the largest entry at ~17 MB: it bundles 228 `.ogg` music tracks under `assets/` that are
only used when the skill runs with music enabled. It is vendored verbatim; drop `brag/assets/` if
repository size matters more than keeping the skill intact.

## `design-md/` is synthesized

[VoltAgent/awesome-design-md](https://github.com/VoltAgent/awesome-design-md) contains **no
`SKILL.md`**. It is a corpus of 74 `DESIGN.md` brand design-system analyses. The `SKILL.md` in
`design-md/` was written for this repository; only `references/*.md` (eight brands) are verbatim
upstream content. To add another brand, copy its `DESIGN.md` from upstream into `references/` and
update the list in the skill.

## Licensing

All thirteen sources are MIT or Apache-2.0, and each vendored skill carries its upstream license
text alongside it. Two notes on how that was determined:

- **anthropics/skills** has no `LICENSE` file at the repository root, but the `skill-creator/`
  directory ships its own `LICENSE.txt` containing Apache-2.0. That is the license that applies to
  the vendored copy.
- **Apache-2.0:** `impeccable`, `how-it-works` (claude-mem), and `skill-creator` (anthropics/skills).
- **MIT:** the remaining ten.

Each vendored license file was diffed against the corresponding file in its upstream repository and
is byte-identical.
