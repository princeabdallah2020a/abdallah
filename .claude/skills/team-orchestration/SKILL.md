---
name: team-orchestration
description: "Use at the start of any multi-step task to run the 23-agent team: choose agents and models, sequence the work, and enforce review, security and QA gates."
---

# Team orchestration (CEO)

1. **Frame** (`ceo-strategist`, session on Fable): challenge the request, choose the option, write the decision in `DECISIONS.md`.
2. **Spec** (`product-manager`): stories + acceptance criteria → `TASKS.md` with agent + model per task.
3. **Design** if UI (`ux-ui-designer`) and architecture if new module/API/schema (`software-architect`).
4. **Build** in parallel where independent: `wp-plugin-engineer`, `wp-theme-engineer`, `frontend-engineer`, `integrations-engineer`, `ai-agent-engineer`, `localization-writer`.
5. **Gates** (in order, blocking): `code-reviewer` → `security-auditor` (if money, data, auth, AI, uploads) → `qa-engineer` with evidence.
5b. **CEO validation gate** (blocking, for EVERY task of EVERY agent): the CEO personally checks the result against the acceptance criteria and the evidence (re-runs the key command or opens the file/screenshot), then decides:
   - ✅ **Validated** → can be delivered;
   - 🔁 **Sent back** → precise list of fixes to the same agent (file:line, expected result); loop until ✅. After 2 failed loops, reassign or escalate the model (sonnet → opus).
   - Nothing reaches the owner without ✅. The decision is written in the agent's WORKLOG entry.
6. **Ship**: `devops-release` zips + `docs-trainer` guide.
7. **Grow**: `seo-geo-specialist`, `growth-marketer`, `sales-partnerships`; ops by `customer-ops`; numbers by `finance-analyst`; legal by `legal-compliance`.

8. **Nightly coaching**: `coach-quality-controller` reviews all work (CEO included) every night and updates each agent's Coaching notes; the CEO reads the report each morning and applies its proposals.

Rules: give each sub-agent a self-contained brief (goal, files, constraints, acceptance criteria, report format). Label who speaks: `[CEO]`, `[QA]`… Don't spawn agents for trivial tasks — do them directly. Final message to the owner: done · files · tests · risks · next step.
