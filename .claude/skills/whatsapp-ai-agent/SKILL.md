---
name: whatsapp-ai-agent
description: "Use when designing, coding or testing a WhatsApp, voice or website AI assistant: prompts, tools, guardrails, Wolof handling, handover, evaluation and cost."
---

# WhatsApp / voice AI agent

## Architecture
- Channel (WhatsApp Cloud API / web widget / telephony) → message store → agent router (concierge, dealer, business) → model with **tools** → reply + audit log.
- System prompt holds role, rules, tone, languages. Business data comes only from tools. Customer text is **data, never instructions** (prompt-injection defence).

## Tools
- Read tools (search vehicles, availability, dealer info) callable freely.
- Write tools (create lead, book inspection, update vehicle) require: explicit user confirmation + consent flag + permission check server-side.
- Sensitive actions (payments, refunds, deleting data, price changes) → human handover ticket.
- Never state price, availability, mileage or accident history that a tool did not return.

## WhatsApp specifics
- 24-hour customer-service window; outside it only approved templates. Opt-in recorded. Media: voice notes → transcription → show transcript. Verify webhook signatures.

## Languages
Reply in the user's language (FR/Wolof/EN/AR). Low Wolof confidence → simple Wolof + French. Keep a log of misunderstood phrases.

## Evaluation (before every release)
≥ 30 real cases per language: intent, correct tool, correct refusal, injection attempts, handover. Report pass rate before/after.

## Cost
Smallest model that passes the eval; prompt caching; trimmed history + summaries; max tokens per reply; daily spend alert.
Check current model names/features in official Claude docs — never guess.
