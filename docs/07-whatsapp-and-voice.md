# 7. WhatsApp and voice AI assistant (Phases 5–6)

**Status: built, not tested.** Everything is **off by default**. Nothing is received or sent until staff configure and enable it in **Platform admin → WhatsApp & voice**.

## 7.1 Decision: where the assistant runs

The assistant is a module of the central plugin (`AMC\Whatsapp`, `AMC\Whatsapp_Agent`, `AMC\Dealer_Agent`, `AMC\Speech`, `AMC\Telephony`), not a separate service. Reasons: one place for credentials, quotas and audit; it reuses the existing Claude integration (`AMC\Assistant`) and the signed channel to each dealer website built in Phase 4. Nothing in the design prevents moving it to a separate service later: it only talks to dealers through signed HTTP.

## 7.2 How a message flows

```
WhatsApp user ──► Meta Cloud API ──► POST /wp-json/marketplace/v1/whatsapp/webhook
                                      │ X-Hub-Signature-256 verified (app secret)
                                      │ stored in amc_wa_messages (deduplicated by message id)
                                      ▼
                               WP-Cron: Whatsapp_Agent::process()
                                      │ voice note? → download from Meta → Speech::transcribe()
                                      │ who is it? amc_ai_contacts (number → dealer + CRM user)
             ┌────────────────────────┴───────────────────────┐
     registered dealer team member                       unknown number
             │                                                 │
     Dealer_Agent (Claude + 11 tools)                  public buyer assistant
             │ each tool = signed POST                  (catalog search, same as website)
             ▼
   dealer site /wp-json/dealer/v1/ai/execute
     runs AS that CRM user, with their permissions
```

## 7.3 Dealer tools and confirmations

| Tool | Effect | Confirmation |
|---|---|---|
| `crm_summary`, `search_vehicles`, `get_vehicle`, `list_leads`, `rentals_overview`, `check_availability` | Read | — |
| `create_lead`, `add_lead_note` | Create data | — |
| `update_lead_status`, `update_vehicle_status`, `update_vehicle_price` | Change important data | **Required** |

Confirmation is enforced by code, not by the model:

1. The model calls a sensitive tool → the central plugin calls the dealer site with `confirm=false` → the dealer site checks the user's permission and returns a **preview** (`requires_confirmation`, summary). Nothing changes.
2. The pending action is stored with the conversation. The person reads the summary.
3. Their next message is matched **outside the model**: `OUI / YES / WAAW / OK` → the action is sent with `confirm=true`; `NON / NO / DEEDET / ANNULE` → cancelled; anything else → the pending action is dropped. Confirmations expire after 15 minutes.
4. The dealer site refuses sensitive tools without `confirm=true`, re-checks the permission and logs the action in the CRM activity log ("WhatsApp: …").

Identity: the dealer website is the authority. A number is linked to a person by that person, in their own CRM (Marketplace → My WhatsApp number). The dealer site re-checks on every call that the user still exists, is a team member, has CRM access and still has that number registered. The dealer id is never taken from the message or from the model.

Quotas: the plan's `ai_quota` = WhatsApp messages per month per dealership (0 = not included). Flood limit: 40 messages per number per hour. Global daily cap: the existing `ai_daily_cap` setting.

## 7.4 Setting up WhatsApp (Meta Cloud API)

1. Meta Business account verified; in **Meta for Developers** create an app → add **WhatsApp**; register the business phone number.
2. Create a **system user** with a permanent token (`whatsapp_business_messaging`, `whatsapp_business_management`).
3. Prefer `wp-config.php`:
   ```php
   define( 'AMC_WA_ACCESS_TOKEN', '…' );
   define( 'AMC_WA_APP_SECRET', '…' );
   ```
   (or paste them in the screen: they are stored encrypted).
4. Platform admin → WhatsApp & voice: phone number ID, save (a verify token is generated).
5. Meta app → WhatsApp → Configuration → Webhook: callback URL and verify token shown on the screen; subscribe to **messages**.
6. Write to the business number from your phone, then **Send a test message**, then tick **Enable the WhatsApp assistant**.

Limits to know: free-text replies are only allowed within 24 hours after the person's last message (the assistant only ever replies, so this is respected). Proactive notifications would need approved templates — not implemented.

## 7.5 Voice notes

Provider-independent (`AMC\Speech`). Built-in option: any API implementing `POST {base}/audio/transcriptions` (multipart `file`, `model` → `{ "text": … }`). Key: `define( 'AMC_STT_API_KEY', '…' );`.

**Wolof is not assumed to work.** Before enabling for dealers: record ~50 real voice notes (Wolof, French, mixed) from native speakers, transcribe them, and measure the error rate. Keep the provider "Disabled" if the result is not usable — the assistant then asks people to write.

Speech synthesis (spoken replies) is not implemented for WhatsApp: replies are text.

## 7.6 Phone calls (voice gateway)

AutoBuzy does not run a SIP stack. A gateway you operate or rent (Asterisk/FreeSWITCH, a programmable-voice provider) answers the call, does speech recognition and synthesis, and exchanges text with AutoBuzy:

```
POST /wp-json/marketplace/v1/voice/events
X-AMC-Timestamp:       unix time (±300 s)
X-AMC-Voice-Signature: hex(HMAC-SHA256(secret, timestamp + "." + raw body))

{ "event": "call.started", "call_ref": "abc123", "from": "+221771234567", "to": "+221338000000" }
{ "event": "utterance",    "call_ref": "abc123", "from": "+221771234567", "text": "est-ce que la Corolla est disponible" }
   → 200 { "say": "Oui, la Toyota Corolla 2019 est disponible à 9 500 000 FCFA." }
{ "event": "call.ended",   "call_ref": "abc123", "duration_seconds": 74 }
```

Same brain, permissions, quotas and spoken yes/no confirmations as WhatsApp. Calls and transcripts are logged (`amc_call_logs`, 90 days). Secret: `define( 'AMC_VOICE_SECRET', '…' );`.

**Before enabling:** verify Senegalese number availability and cost, inbound rules, and the legal requirements for recording/transcribing calls (consent message at the start of the call, ARTP and CDP rules). Human handoff (transfer to the dealer) is the gateway's job: route to a human when the caller asks or when AutoBuzy returns an apology twice.

## 7.7 Data retention

| Data | Kept |
|---|---|
| WhatsApp messages, call transcripts | 90 days |
| Conversation memory | 6 hours of context, deleted after 30 days |
| Marketplace requests to dealers | 180 days |
| Contact requests | 1 year |

Exports and erasure: WordPress **Tools → Export / Erase Personal Data** (central: requests by email; dealer CRM: customers and leads by email, anonymised to keep accounting history).
