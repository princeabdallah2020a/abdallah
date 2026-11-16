---
name: agent-coaching
description: "Use when reviewing the performance of AI agents (including the CEO) over a period, scoring their work, finding root causes of recurring mistakes, and writing lessons that make them better the next day."
---

# Agent coaching (performance review of AI agents)

Think like a principal engineer + head of quality + executive coach: strict on facts, fair on people, obsessed with measurable progress.

## 1. Collect evidence (never judge from memory)
- WORKLOG entries, commits + diffs, test outputs, CEO validation decisions (✅ / 🔁), previous report.
- For each agent, list tasks done, claims made, and the evidence behind each claim.

## 2. Score (0–5 per criterion, with proof)
| Criterion | 5 = | 0 = |
|---|---|---|
| Correctness | does exactly what was asked, no bug found | broken or wrong task |
| Evidence & honesty | every claim backed by a command/screenshot | invented result (→ critical, always reported to owner) |
| Security & rules | CLAUDE.md §4–§5 fully respected | secret leaked, unsafe code shipped |
| Process | review → security → QA → CEO gate followed, WORKLOG written | skipped gates |
| Efficiency | right model, no rework, short output | repeated rework, wrong model, token waste |
| Business value | moved the top priority | busy work |
Track the trend vs the previous report (↑ ↓ =).

## 3. Diagnose root causes (5 whys, briefly)
Classify each recurring mistake: missing knowledge · unclear instruction · skipped step · wrong tool/model · bad handoff between agents · CEO brief too vague. Fix the cause, not the symptom: if the brief was bad, coach the CEO, not the executor.

## 4. Write lessons that change behaviour
- Concrete, testable, one line: trigger → action → why. Example: "When editing templates, run `php -l` on them too (missed in commit abc123)."
- Max 8 per agent; delete lessons already mastered for 7 days; merge duplicates.
- No vague advice ("be careful", "improve quality").

## 5. Verify improvement
- Every lesson gets an id and a check date; next reports say whether it was applied (✅ applied / ❌ repeated).
- Same mistake 3 times → escalate in "Proposals for the owner" (change role, model, skill or process).

## 6. Calibration
- Praise in one line what is genuinely good — reinforce it.
- Don't punish an agent for a failure caused upstream.
- Never inflate scores; never lower the bar to show progress.
