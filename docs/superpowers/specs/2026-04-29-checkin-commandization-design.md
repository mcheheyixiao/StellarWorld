# Checkin Reward Commandization Design

Date: 2026-04-29
Project: StellarWorld website checkin delivery
Scope: Website-only change for command-based reward delivery compatibility

## Summary

The current checkin delivery chain writes reward records on the website, then relies on the Minecraft plugin to poll `/api/plugin/checkin/deliveries` and acknowledge execution. The plugin only executes `reward.commands`, but the website currently stores primary rewards in `coins` and `items`, with `commands` often empty. This causes checkin tasks to be queued or even marked delivered without actually granting the in-game reward.

This change makes the website produce plugin-executable command payloads while preserving the existing UI preview data and website coin accounting. The website will automatically convert `coins` and `items` into `eco give` and `minecraft:give` commands for new checkins, and it will also backfill missing commands for historical delivery records when the plugin pulls them.

## Goals

- Make newly created checkin rewards executable by the existing plugin without plugin changes.
- Make historical checkin delivery records with empty `commands` executable when polled.
- Preserve reward preview data on the website (`coins`, `items`, `commands`).
- Preserve existing website-side coin accumulation into `users.coins` after successful ACK.
- Keep the change atomic: no database migration, no plugin code changes, no Realtime dependency changes.

## Non-Goals

- Changing the plugin polling or ACK protocol.
- Moving reward execution responsibility to StellarRealtime.
- Removing or replacing website-side `users.coins` updates.
- Backfilling historical database rows in-place through a migration job.
- Changing checkin delivery state transitions.

## Current Problem

### Existing chain

1. User signs in on the website.
2. Website creates a `checkin_records` row and a `checkin_reward_deliveries` row.
3. Plugin polls `/api/plugin/checkin/deliveries`.
4. Plugin validates and executes only `reward.commands`.
5. Plugin ACKs success or failure back to the website.
6. On success, the website marks the delivery as `delivered` and also increments `users.coins`.

### Mismatch

- The website stores primary rewards in `coins` and `items`.
- The plugin only executes `commands`.
- Default reward seed data currently creates `coins=120`, `items=[iron_ingot x10]`, and `commands=[]`.
- Historical records may already exist with empty `commands`.
- Some admin-created rules may include placeholder or non-granting commands that mark the delivery successful without giving the intended reward.

## Recommended Approach

Implement commandization in the website at two boundaries:

1. **Reward snapshot generation for new checkins**
   Convert `coins` and `items` into executable commands when building `reward_snapshot_json`.

2. **Delivery response compatibility for historical records**
   When formatting delivery payloads for the plugin, if `reward.commands` is empty or missing, derive commands from the stored `coins` and `items` before returning the payload.

This keeps the plugin unchanged, fixes new and old deliveries, and avoids mutating data during GET requests.

## Detailed Design

### 1. Reward normalization model

Reward payloads will continue to expose:

- `coins`
- `points`
- `items`
- `commands`
- `scope`
- `generated_at`

The semantic change is that `commands` becomes the canonical execution list returned to the plugin, even when it is derived automatically from `coins` and `items`.

The website UI remains free to render `coins` and `items` as before.

### 2. Command derivation rules

The website will derive commands with this fixed order:

1. Coin command from `coins`
2. Item commands from `items`
3. Existing admin-configured `commands`

Derived commands:

- Coins:
  - `eco give {player} <coins>`
- Items:
  - `minecraft:give {player} <item_id> <amount>`

Generation rules:

- Skip coin command when `coins <= 0`.
- Skip items whose id is blank.
- Clamp item amount to at least `1`.
- Preserve existing placeholder support using `{player}` and `{uuid}` in admin-configured commands.
- Normalize command strings by trimming whitespace and removing leading `/` before deduplication.

### 3. Deduplication strategy

Use precise string-based deduplication only.

Normalization before dedupe:

- `trim()`
- remove leading `/`

Deduplication behavior:

- preserve first occurrence order
- remove later exact duplicates after normalization
- do not attempt semantic matching between different command spellings

This keeps the behavior predictable and low risk.

### 4. New checkin behavior

When a new user checkin is created:

- `reward_snapshot_json` keeps the display fields (`coins`, `items`, `commands`)
- `commands` in the snapshot will now include the derived `eco give` and `minecraft:give` commands
- the delivery row stores this enriched snapshot

Result:

- New delivery tasks are immediately executable by the current plugin
- The plugin still sees a normal `reward.commands` array

### 5. Historical delivery compatibility

For delivery rows already stored before this change:

- Do not rewrite database records during polling
- During `/api/plugin/checkin/deliveries` formatting, inspect each reward payload
- If `reward.commands` is missing or empty, derive commands from stored `coins` and `items`
- Return the augmented payload to the plugin for execution

This covers:

- `pending` rows created before the fix
- `failed` rows that are eligible for retry
- `delivering` rows that later time out and are retried

### 6. Website coin accounting

Website-side coin accumulation remains unchanged.

After plugin ACK success:

- delivery status still becomes `delivered`
- checkin record status still becomes `delivered`
- `users.coins` still increments by the reward snapshot's `coins`

This means testing will grant:

- website coins through existing database logic
- in-game economy through `eco give`

This duplication is intentional for the current requirement because the user explicitly wants to preserve website coins while also granting in-game economy for testing.

### 7. Default data and admin defaults

Update default reward data so new environments do not seed a broken empty-command rule.

Changes:

- Default seed rule should include executable command content consistent with the derived command rules.
- Admin-side default template values should use real grant commands instead of placeholder `say` commands when representing default examples for testing.

This avoids recreating the mismatch after deployment or in new environments.

## Affected Areas

### Website model layer

- `app/models/Checkin.php`

Responsibilities:

- derive executable commands from reward fields
- apply the logic during snapshot generation
- reuse the same normalization helper for compatibility formatting when possible

### Website API layer

- `app/controllers/ApiController.php`

Responsibilities:

- ensure plugin delivery payloads always include a usable `reward.commands` array
- apply compatibility augmentation only to the response payload, not database state

### Website admin/default behavior

- seed/default reward initialization in `Checkin.php`
- admin-side default reward template script if it currently suggests placeholder commands

## Error Handling

- If `coins` is zero or invalid, no economy command is generated.
- If an item entry is malformed, skip only that malformed item rather than failing the whole snapshot.
- If the existing admin-configured `commands` array contains blank values, filter them out.
- If the resulting command list is empty after normalization, the payload remains empty; this is still observable in tests and logs and indicates a rule definition problem.

No change is made to plugin ACK failure semantics.

## State Semantics

No delivery state changes are introduced.

The existing state machine remains:

- `pending`
- `delivering`
- `delivered`
- `failed`
- `cancelled`

Only the payload content changes so that the plugin can actually execute intended rewards.

## Testing Strategy

### Automated tests

Add website-side tests for:

1. **Reward snapshot generation**
   - when `coins` and `items` exist and `commands` is empty, generated snapshot includes `eco give` and `minecraft:give`
   - when admin commands also exist, derived commands appear before admin commands
   - duplicate command strings are removed while order is preserved

2. **Historical delivery compatibility formatting**
   - when stored reward has empty `commands` but valid `coins/items`, API formatting returns derived commands
   - when stored reward already has commands, API preserves the stored command list and does not inject additional derived commands

3. **Website coin preservation**
   - successful ACK still increments `users.coins` by the stored `coins` amount

### Manual verification

Expected manual flow after deployment:

1. User signs in on the website.
2. Website shows queued delivery as before.
3. Plugin polls and receives a non-empty `reward.commands` array.
4. Server console executes:
   - `eco give <player> <coins>`
   - `minecraft:give <player> <item_id> <amount>`
5. Plugin ACKs success.
6. Website status changes to `delivered`.
7. Website user coins also increase.

## Rollback Strategy

Rollback is limited to website code only.

To rollback:

- revert command derivation logic
- revert delivery compatibility augmentation
- revert default seed/template command changes

No schema rollback is needed because no database structure is changed.

## Risks

### Double-grant risk

If an admin already configured manual `eco give` or `minecraft:give` commands that exactly duplicate the derived commands, both could execute unless deduplication catches them. Exact normalized string deduplication reduces this risk but does not eliminate semantically different duplicates.

### Environment command compatibility

`eco give` assumes the installed economy plugin exposes that command and Vault-backed behavior is already working in the server environment.

`minecraft:give` assumes standard command availability on the target server version.

### Display vs execution divergence

The website continues to display rewards from `coins/items`, while execution is delegated through generated commands. This is acceptable for now because the command generation is deterministic from the same source data.

## Implementation Notes

Use one shared helper path for reward command derivation where possible to avoid drift between:

- new snapshot generation
- historical delivery response formatting

Preferred helper responsibilities:

- normalize items
- derive coin command
- derive item commands
- merge with existing commands
- normalize and deduplicate final commands

## Approval Outcome

Approved design direction:

- Approach `3`
- Preserve website coin increments
- Keep changes website-only
- Make both new and historical deliveries executable through `give` / `eco` commandization
