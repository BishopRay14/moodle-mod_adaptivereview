# Mastery Engine Audit

Date: 2026-08-18  
Branch: `feature/mastery-engine`

## Purpose

This audit documents the current Adaptive Review mastery path before the real
review-history, mastery-gain, and scheduling engines are implemented. It treats
the repository as the implementation source of truth and the project handoff as
the product-intent source of truth.

This document does not select a mastery formula or replace the scheduler.

## Executive finding

The current user interface demonstrates several intended learning behaviors,
but the server-side mastery engine is still a prototype. It cannot yet provide
reliable mastery, scheduling, grading, or an auditable explanation of student
progress.

The safest next implementation is an immutable review-history foundation,
backed by server-recognized review sessions. The existing
`adaptivereview_mastery` table should remain a fast, mutable summary of current
student-card state. History should become the evidence explaining how that
summary was reached.

## Current review flow

1. The dashboard requests one of four numeric categories from
   `classes/external.php`: Due Today, New, Recently Learned, or All Cards.
2. `frontend/src/App.vue` starts a review or browse session entirely in browser
   memory.
3. `frontend/src/components/Card.vue` asks for `I Know It` or `Not Yet` before
   revealing the answer.
4. On the first response for a card in that browser session, `App.vue` maps:
   - `I Know It` to the legacy numeric rating `2`.
   - `Not Yet` to the legacy numeric rating `0`.
5. `classes/external.php::submit_answer()` creates or loads one mutable
   `adaptivereview_mastery` row and passes it to
   `classes/local/scheduler.php`.
6. The prototype scheduler changes mastery by a fixed amount, assigns a fixed
   interval, updates the next-review timestamp, and derives a compatibility
   box number.
7. No review-attempt record is retained.

## Behavior already aligned with product intent

| Area | Current implementation | Assessment |
| --- | --- | --- |
| Retrieval commitment | The student chooses before seeing the answer. | Keep. |
| Response choices | The visible UI uses `I Know It` and `Not Yet`. | Keep; replace the legacy server rating contract. |
| Answer reveal | The response reveals the answer, followed by Continue. | Keep. |
| Same-session remediation | A first `Not Yet` requeues the card at the end of the browser session. | Keep the experience; enforce its meaning on the server. |
| Repeat mastery | The browser submits only the first response for a card in its current session. | Directionally correct, but not authoritative or durable. |
| Recently Learned browse | Browse mode does not call `submit_answer()`. | Keep. |
| All Cards browse | Opening a library card uses browse mode and does not update mastery. | Keep. |
| Set Mastery label | All Cards calculates and displays the mean card mastery as Set Mastery. | Directionally correct; it is currently frontend-only. |
| Goal-relative progress | All Cards progress is calculated relative to the displayed Goal. | Keep; the Goal must become an activity/set setting. |

## Prototype behavior that conflicts with product intent

| Area | Current behavior | Required direction |
| --- | --- | --- |
| Server response model | Accepts Red/Yellow/Green values `0`, `1`, and `2`. | Use an explicit two-response contract. |
| `Not Yet` | Subtracts `0.4` mastery. | Do not reduce accumulated mastery; change recovery scheduling instead. |
| Successful retrieval | Adds a fixed `0.7` every time. | Base gain on meaningful elapsed time and other approved evidence. |
| Early practice | Recently Learned practice calls the normal submission endpoint and receives full gain. | Allow practice but mark it early and prevent inappropriate gain. |
| Same-session rules | Enforced only by an in-memory JavaScript object. A refresh starts over. | Enforce using server-recognized sessions and prior attempts. |
| Failure timing | A failure updates `lastreviewdate` just like a successful retrieval. | Preserve distinct last-attempt and last-success concepts. |
| Intervals | Fixed buckets of 1, 2, 4, 7, 14, or 30 days. | Use the approved adaptive scheduler; permit very long intervals. |
| Completion state | `box_number = 5` means terminally mastered. | Remove terminal mastery assumptions from future completion/grading. |
| Card identity | Mastery belongs to an activity-specific item. | Eventually associate mastery with a reusable repository card. |

## Current database fields

### `adaptivereview_mastery`

| Field | Current use | Classification |
| --- | --- | --- |
| `id` | Mutable state-row identifier. | Real. |
| `userid` | Identifies the learner. | Real. |
| `itemid` | Identifies an activity-specific card. | Real now; migration boundary for repository cards. |
| `masteryscore` | Stores the displayed score from 0.0 to 5.9. | Real storage; prototype calculation. |
| `requiredintervaldays` | Stores the prototype interval selected from score. | Provisional. |
| `nextreviewdate` | Drives Due Today versus Recently Learned. | Real scheduling state; provisional calculation. |
| `lastreviewdate` | Set on every prototype answer. | Ambiguous and insufficient. |
| `box_number` | Derived compatibility value used by completion. | Legacy compatibility only. |
| `status` | Initialized but not meaningfully used. | Legacy/unused. |
| `count_correct` | Expected by old tests and privacy export, but not updated by the current scheduler. | Stale. |
| `count_wrong` | Expected by old tests and privacy export, but not updated by the current scheduler. | Stale. |
| `last_reviewed` | Present in schema but not updated by the current scheduler. | Stale. |

Local prototype records confirm that these two generations of fields can drift:
mastery and scheduling timestamps contain values while legacy counters and
`last_reviewed` can remain zero.

## Dashboard category audit

### New

A card is New only when no `adaptivereview_mastery` row exists. A first
`Not Yet` creates a mastery row, so the card immediately stops being New even
though no successful retrieval has occurred.

### Due Today

A card is due when it has a mastery row, a positive `lastreviewdate`, and a
positive `nextreviewdate` that is not later than the current time. This is real
timestamp filtering, but it relies on prototype dates.

### Recently Learned

Every reviewed card whose next-review date is in the future is considered
Recently Learned. There is no recency window. A card scheduled many weeks into
the future remains in this category for that entire period.

### All Cards

All Cards correctly includes every item and left-joins current learner mastery.
Browsing an individual card does not submit a response.

## Frontend session limitations

- Session identity exists only as Vue component state.
- First-response protection disappears on reload, another tab, or another
  device.
- The server cannot tell whether a response came from New, Due Today, early
  practice, or a same-session repeat.
- Answer writes are started asynchronously and the UI advances without waiting
  for persistence to succeed.
- Same-session repeats are displayed but not recorded, so there is no evidence
  of remediation or its result.

The frontend behavior is a useful prototype, but it cannot be trusted for
mastery or grade enforcement.

## Moodle integration audit

### Automated tests

The existing external tests describe the prior box behavior and do not cover
the current scheduler. Examples include treating category `0` as New, expecting
correct/wrong counters to change, and expecting box promotion/demotion. There
are no focused scheduler tests, elapsed-time tests, early-practice tests,
same-session tests, or review-history tests.

### Backup and restore

Backup includes legacy mastery fields but omits `masteryscore`,
`requiredintervaldays`, `nextreviewdate`, and `lastreviewdate`. A backup with
user data therefore cannot currently preserve the displayed mastery and
scheduling state. Review history will also need explicit backup/restore policy
and mappings.

### Privacy API

Privacy metadata still declares `cardid`, although the field is now `itemid`.
It omits the newer mastery and scheduling fields. Any review-history tables must
be included in context discovery, export, and all deletion paths.

### Activity completion

Custom completion still depends on compatibility boxes and uses terminal
"mastered"/Box 5 behavior. This conflicts with continual mastery and should not
be used as the basis of the new grade model.

### Gradebook

No Moodle gradebook feature declaration, grade item creation/update functions,
or grade writes exist. The README claim that gradebook integration is currently
available is inaccurate. Grading should wait until Set Mastery and Goal have
authoritative backend definitions.

### Goal and Set Mastery

The Goal is hardcoded to `4.0` in `frontend/src/App.vue`; there is no activity
database field or instructor form control. Set Mastery is calculated in the All
Cards component from the cards currently returned to the browser. Neither is
yet an authoritative backend value.

### Repository architecture

Every item has an `adaptivereviewid`, so cards are currently owned by one
activity. The history foundation should retain activity context explicitly and
avoid assuming this is the final card identity model.

## Recommended history architecture

Use two small tables so the server, not the browser, determines session and
attempt meaning.

### 1. `adaptivereview_sessions`

One row represents a deliberate review/practice session.

| Proposed field | Purpose |
| --- | --- |
| `id` | Internal primary key. |
| `adaptivereviewid` | Current activity/set-use context. |
| `userid` | Learner who owns the session. |
| `sessionkey` | Random opaque identifier returned to the client; unique. |
| `mode` | Due, New, early practice, or another approved session mode. |
| `timecreated` | Server start time. |
| `timecompleted` | Server completion time, or zero while open. |

The server should validate that a session belongs to the current user and
activity. It should not trust a client-provided claim that an attempt is the
first attempt or that a card was due.

### 2. `adaptivereview_attempts`

One append-only row represents one retrieval judgment. Same-session repeats are
recorded even when they are not eligible for mastery gain.

| Proposed field | Purpose |
| --- | --- |
| `id` | Internal primary key. |
| `sessionid` | Parent review session. |
| `itemid` | Current card identity; designed for later repository migration. |
| `attemptnumber` | Card attempt number within the session. |
| `response` | Explicit `I Know It` or `Not Yet` value. |
| `wasdue` | Whether the server considered the card due at session start. |
| `isremediation` | Whether this is a same-session repeat. |
| `masteryeligible` | Whether this attempt was allowed to affect mastery. |
| `masterybefore` | Snapshot immediately before processing. |
| `masterygain` | Gain applied; zero when ineligible or unsuccessful. |
| `masteryafter` | Snapshot immediately after processing. |
| `intervalbefore` | Prior required interval in days. |
| `intervalafter` | Resulting interval in days. |
| `previousreviewdate` | Prior meaningful retrieval timestamp. |
| `previousduedate` | Due timestamp that existed before this attempt. |
| `nextreviewdate` | Resulting due timestamp. |
| `algorithmversion` | Identifies the rule version that produced the result. |
| `timecreated` | Authoritative server timestamp. |

Indexes should support session-card attempt lookup, user history, activity
history, and chronological reporting. Exact XMLDB types and index names should
be finalized during implementation.

### Browse behavior

Browsing should not create review-attempt rows. If browsing analytics becomes
useful later, it should use a separate event or clearly distinct event type so
it cannot be mistaken for retrieval evidence.

## Recommended implementation order

1. Confirm the small product decisions listed below.
2. Add session and attempt tables through `install.xml`, `upgrade.php`, and a
   plugin version increment.
3. Add a server endpoint that opens an authoritative review session.
4. Replace the legacy rating parameter with an explicit response and session
   key.
5. Record first attempts and remediation attempts transactionally.
6. Preserve the current prototype mastery calculation temporarily behind a
   clearly named compatibility boundary, or disable mastery mutation until the
   approved formula is implemented.
7. Update privacy, deletion, reset, backup/restore, and tests in the same
   feature.
8. Only then implement the approved mastery-gain formula and adaptive
   scheduling.

## Product decisions needed before history implementation

1. **Reset semantics:** Should Reset Learning Progress delete review history,
   or only reset the current mastery summary while retaining history for audit?
2. **Early practice record:** The recommendation is to record it as an attempt
   with `masteryeligible = 0` unless the later formula awards limited evidence.
3. **New-card success:** Should a first successful retrieval create an initial
   mastery gain immediately, or establish a baseline that must be confirmed
   after elapsed time?
4. **Session boundary:** The recommendation is one server session per dashboard
   category selection, ending when the learner finishes or leaves it idle past
   an expiration window.
5. **Backup policy:** Should course backups with user data carry review history,
   or only the current mastery summary?

These decisions do not require selecting the full mastery formula yet.

## Explicitly deferred

- The numeric mastery-gain curve.
- The interval formula.
- Gradebook calculation.
- Reusable card/set repository schema.
- Conversion of legacy completion rules.
- Analytics and instructor reporting UI.

Deferring these prevents the history foundation from silently deciding major
learning-design or repository questions.
