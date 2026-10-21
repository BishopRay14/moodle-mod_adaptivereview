# Global Repository Migration Contract

Date: 2026-10-14
Status: Approved design input for Step 2; no production schema changes are made
by this document.

## Product invariants

1. A card has one stable global identity.
2. Student mastery belongs to the student-card pair, not to a course or set.
3. Sets reference cards; activities reference sets.
4. The mastery goal belongs to the activity's use of a set. A shared set may
   therefore have different goals in different courses.
5. Students access repository content through activities in their courses.
6. Authorized repository authors and administrators may edit all shared cards
   and sets.
7. Content with mastery or attempt history is archived rather than physically
   deleted.
8. Existing cards are never merged automatically based on matching text.

## Target relationships

```text
adaptivereview_cards
    ^
    |
adaptivereview_set_cards --> adaptivereview_sets
                                  ^
                                  |
                            adaptivereview

user + card --> adaptivereview_mastery
session + card --> adaptivereview_attempts
```

## Target tables and fields

Final XMLDB names and field lengths will be validated during Step 2. The
contract requires the following concepts.

### `adaptivereview_cards`

- Stable numeric `id` retained from the legacy item.
- Question, answer, hint, and category content.
- Active/archived state.
- Creator, last modifier, creation time, and modification time.

### `adaptivereview_sets`

- Stable set ID.
- Name and optional description.
- Optional default goal used only as an activity-form suggestion.
- Active/archived state.
- Creator, last modifier, creation time, and modification time.

### `adaptivereview_set_cards`

- Set ID and card ID.
- Card order within the set.
- A unique set-card pair.

### `adaptivereview`

- Existing course activity fields.
- Required set ID after migration.
- Activity-specific mastery goal.

### `adaptivereview_mastery`

- User ID and global card ID form the unique mastery identity.
- Mastery and scheduling summary fields remain mutable projections of history.

### `adaptivereview_sessions` and `adaptivereview_attempts`

- A session retains its activity context.
- An attempt references the stable global card ID.
- Existing attempt rows remain append-only and retain their IDs and timestamps.

## Upgrade strategy

The migration must favor renaming and relationship creation over copying data:

1. Rename `adaptivereview_items` to `adaptivereview_cards`. This preserves all
   card IDs referenced by mastery and attempt history.
2. Add repository metadata and archival fields to the promoted card table.
3. Create `adaptivereview_sets` and `adaptivereview_set_cards`.
4. For every existing activity, create exactly one set using the activity name.
5. Create one membership row for every former activity-card relationship,
   preserving the activity's configured item order where available.
6. Add `setid` and `masterygoal` to the activity and populate them before making
   `setid` required.
7. Rename `adaptivereview_mastery.itemid` to `cardid` without recreating the
   mastery rows.
8. Rename `adaptivereview_attempts.itemid` to `cardid` without recreating the
   attempt rows.
9. Replace foreign keys and indexes with global-card equivalents.
10. Remove the promoted card table's legacy `adaptivereviewid` only after every
    source relationship has a set-membership row and validation has succeeded.
11. Save the plugin upgrade point only after all validations pass.

The upgrade must be transactional where Moodle and the database permit it. It
must never advance the plugin version after a partial or invalid migration.

## Required migration assertions

For a fixture containing multiple activities, duplicate card text, multiple
users, mastery, sessions, and attempts:

- Activity count is unchanged.
- Card count is unchanged.
- Mastery count is unchanged.
- Session count is unchanged.
- Attempt count is unchanged.
- Every activity has exactly one migrated set.
- Every former activity-card relationship has exactly one membership row.
- Every mastery row references the same numeric card ID as before migration.
- Every attempt references the same numeric card ID as before migration.
- Duplicate card text from different legacy activities remains separate cards.
- Session activity context is unchanged.
- No active content becomes archived during migration.
- Re-running validation does not create duplicate sets or memberships.

## Permissions contract

Step 2 introduces a system-level capability named conceptually
`mod/adaptivereview:manageglobalrepository`.

- Administrators receive it automatically.
- The institution may assign it at system context to an Adaptive Review Author
  role.
- The capability permits management of every card and set in its context.
- Course access alone does not grant repository-wide editing.
- Student access remains governed by activity visibility and the existing view
  capability.

## Editing and archival contract

- Editing a shared card updates it everywhere and must show a sharing warning.
- A set can be duplicated to create an independent variation.
- Cards and sets referenced by history or activities are archived, not deleted.
- Archived cards remain resolvable for history, privacy export, and backup.
- Formal content revisioning and approval workflows are outside the MVP.

## Backup and restore direction

The final policy is implemented in Step 6, but Step 2 must not prevent it:

- Activity backups must identify the referenced set and cards.
- Restore must not silently connect to unrelated content merely because text
  matches.
- A restored activity may reuse an exact recognized repository identity or
  create an isolated imported set, depending on the later approved policy.

## Rollback and recovery

- Tests operate only on PHPUnit tables and dataroot.
- Before a development-database rehearsal, capture a database backup.
- The production upgrade does not include a destructive down migration.
- If validation fails, preserve the original table and IDs and stop the upgrade
  with an actionable exception.
