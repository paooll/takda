---
name: design-md
description: Use when building or restyling UI from a design-language reference, when a project's look and feel must stay consistent across many screens, or when the user supplies or asks for a DESIGN.md. Covers the DESIGN.md format (the plain-markdown design system contract popularized by Google Stitch), how to pick a reference, how to author one for a product, and how to apply tokens instead of hand-picked values. Not for backend-only work.
version: 1.0.0
license: MIT
source: synthesized for this repo from https://github.com/VoltAgent/awesome-design-md
---

# DESIGN.md — the design contract

`DESIGN.md` is a plain-markdown design system document that a coding agent reads to generate
consistent UI. No Figma export, no JSON schema, no build step. Drop it in the project root and
an agent knows how the product should look.

Its sibling is `AGENTS.md`: `AGENTS.md` says *how to build*, `DESIGN.md` says *how it should look*.

## When to use this skill

Use it when:

- The user points at a site, product, or brand and asks for a page "that looks like this".
- Visual consistency matters across many screens and ad-hoc values are already drifting.
- You are establishing a design language for a new product or an existing one that has none.

Do not use it to describe layout requirements for a single throwaway screen, and do not use it to
replace a real component library.

## The format

A `DESIGN.md` is a YAML frontmatter block followed by prose. The frontmatter is the machine-readable
part; the prose explains the rules that numbers cannot.

```markdown
---
version: alpha
name: <Brand>-design-analysis
description: <2-4 sentences: the visual concept, the signature move, what to avoid>
colors:
  primary: "#0066cc"
  ink: "#1d1d1f"
  canvas: "#ffffff"
typography:
  hero-display:
    fontFamily: "SF Pro Display, system-ui, sans-serif"
    fontSize: 56px
    fontWeight: 600
    lineHeight: 1.07
    letterSpacing: -0.28px
spacing:
  base: 8px
rounded:
  control: 8px
components:
  button-primary:
    ...
---

# <Brand>

## Concept
## Colors
## Typography
## Spacing and rhythm
## Surfaces and elevation
## Motion
## Components
## Do / Don't
```

### Required top-level keys

`version`, `name`, `description`, `colors`, `typography`, `spacing`, `rounded`, `components`.
A file missing any of these is not a valid `DESIGN.md`; every entry in the upstream corpus carries
all eight, and downstream tooling assumes it.

### Key rules

- **Values, not vibes.** `#0066cc`, `56px`, `1.07`, `-0.28px` — never "a nice blue", "large",
  "tight". If a decision cannot be written as a number, it is not yet a decision.
- **One interactive color.** Most of these systems have exactly one action color. Everything else is
  neutral. A second accent is the fastest way to stop looking like the reference.
- **Named roles, not raw names.** `ink`, `body-muted`, `divider-soft`, `surface-black` survive
  theme changes. `#333` does not.
- **Type is a scale, not a set.** Every entry needs size, weight, line-height, and letter-spacing.
  Tight negative tracking on large display text is a recurring signature move.
- **Record the anti-patterns.** The `## Do / Don't` section is what keeps generated UI from
  drifting toward generic output. Write it even when you think it is obvious.

## Applying a reference

1. **Pick one reference and commit to it.** `references/` contains eight complete examples:
   `apple.md`, `stripe.md`, `linear.app.md`, `vercel.md`, `notion.md`, `nike.md`, `uber.md`,
   `slack.md`. Read the one that matches the requested register. Blending two is the main cause of
   incoherent results.
2. **Map to the project.** The reference's colors and typefaces are not the project's tokens.
   Translate the *roles* (`primary`, `ink`, `canvas`, `hero-display`) onto the project's existing
   token names in `AGENTS.md` or the Tailwind config. If the project has no tokens, define them
   first, from this file, then use them everywhere.
3. **Build from tokens only.** No hex literals or magic pixel values in components. If a value is
   missing, add it to the token set rather than inlining it.
4. **Check against the Don't list.** Before calling the work done, walk the reference's `Do / Don't`
   and the anti-patterns above.

## Authoring a new DESIGN.md

1. Gather the real interface: the product's live pages, its marketing site, screenshots. Extract
   actual computed values wherever possible.
2. Write `description` first, in prose, before any numbers. If the visual concept cannot be stated
   in a few sentences, stop and look more.
3. Fill the eight required keys, keeping token names semantic.
4. Add prose sections for the rules: rhythm, hierarchy, motion, and what the brand never does.
5. Sanity-check: can an agent that has never seen the product build a recognisable screen from
   this file alone? If not, the file is missing rules, not missing tokens.

## Reference corpus

Upstream (https://github.com/VoltAgent/awesome-design-md, MIT) analyses 74 brands. This skill
vendors eight so the common cases work offline. To pull in another brand, copy its `DESIGN.md` from
the upstream repository into `references/` and update the list above — keep the file name as the
brand slug.

## Note on provenance

This skill is **synthesized**, not copied. The upstream repository contains no `SKILL.md` — it is a
collection of `DESIGN.md` brand analyses. The `references/*.md` files are verbatim upstream content
under the MIT license; the instructions above were written for this repository.
