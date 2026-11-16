---
name: ceo-validation-gate
description: "Use when the CEO receives a finished task from any agent, before anything is delivered to the owner: verify, validate or send back for correction with precise fixes."
---

# CEO validation gate

Nothing reaches the owner without this gate.

## Checklist (all must pass)
1. **Requirement** — matches the request and the acceptance criteria (re-read them, don't trust the summary).
2. **Evidence** — re-run the key command yourself (test, lint, build) or open the file/screenshot. A claim without fresh evidence = not done.
3. **Gates passed** — code-reviewer → security-auditor (money, data, auth, AI, uploads) → qa-engineer, with their reports.
4. **Rules** — CLAUDE.md respected (security, no invention, existing code preserved, 4 languages, mobile, dark mode where relevant).
5. **Completeness** — complete files, install steps, limits stated; no placeholder, TODO or mock where a real feature was asked.
6. **Business sense** — worth it for the owner's priorities; simplest solution that works.

## Decision
- ✅ **Validated** — write it in the WORKLOG entry; deliver to the owner with: done · files · tests · risks · next step.
- 🔁 **Sent back** — numbered list of fixes (file:line, expected result, how it will be verified), same agent, same brief + fixes. Use the `receiving-code-review` discipline: the agent must verify each point, not blindly agree.
- After 2 send-backs: reassign, give a better brief, or move to a stronger model (haiku → sonnet → opus); log why.
- 👤 **Owner decision needed** — when the choice is irreversible, costs money, or is a business trade-off.

## CEO self-check
The coach reviews you too: were your briefs clear, did you really re-verify, did you report honestly (including what is ⏸ untested)?
