# Global Repository MVP Roadmap

Date: 2026-10-14

## Outcome

Adaptive Review will provide globally stable card identities, reusable sets,
course-integrated set selection, lifelong student-card mastery, and the
`mastery-v1` scheduler in a form that can be exercised through PHPUnit and the
Moodle browser interface.

Students continue to enter Adaptive Review through their course. They do not
need to visit the repository. Authorized repository authors and administrators
can manage all shared cards and sets.

## Step 1: Test baseline and migration contract

- Require Moodle 5.0 or later and test representative Moodle 5 releases.
- Run PHPUnit with an isolated table prefix and dataroot.
- Preserve a reproducible legacy fixture containing activities, cards,
  mastery, sessions, and attempts.
- Lock the repository schema and migration invariants before changing the
  production schema.
- Do not modify production data during this step.

Exit criteria:

- Existing plugin tests pass on the local Moodle 5 checkout.
- CI describes the supported Moodle 5 matrix.
- The legacy fixture passes and contains every relationship the migration must
  preserve.
- The migration contract in `global-repository-migration-contract.md` is
  reviewable and unambiguous.

## Step 2: Repository schema and migration

- Promote existing activity-owned items to global cards without changing card
  IDs.
- Add sets and ordered set membership.
- Create one migrated set per existing activity.
- Attach each activity to its migrated set and add its course-specific mastery
  goal.
- Rename mastery and attempt references from `itemid` to `cardid` while
  preserving rows.
- Add repository capabilities and archival state.
- Prove the upgrade with migration-focused PHPUnit coverage.

## Step 3: Instructor integration

- Add an activity-form set chooser.
- Add basic global card and set management for authorized authors.
- Support search, creation, editing, ordering, duplication, and archival.
- Warn authors that edits to shared cards are visible in every consuming set.

## Step 4: `mastery-v1` scheduler

- Implement elapsed-time mastery gain and the approved interval bands.
- Implement non-punitive failures and recovery scheduling.
- Keep same-session remediation ineligible for mastery.
- Store sufficient precision for calculations while displaying one decimal.
- Cover boundary, early, due, late, recovery, repeated-failure, and maximum
  mastery scenarios with deterministic tests.

## Step 5: Authoritative student experience

- Drive New, Due Today, Recently Learned, and All Cards from global card state.
- Calculate Set Mastery from the cards in the activity's selected set.
- Calculate Progress Toward Goal against the activity-specific goal.
- Keep browsing distinct from retrieval evidence.

## Step 6: Moodle lifecycle coverage

- Update privacy export and deletion.
- Update activity backup and restore behavior for shared repository content.
- Enforce repository capabilities and student visibility.
- Add browser coverage for set selection and the complete student review flow.
- Verify archival never destroys mastery or attempt history.

## Step 7: MVP release candidate

- Run lint, Moodle Code Checker, PHPDoc, XMLDB validation, PHPUnit, and browser
  tests.
- Exercise a copy of legacy-shaped data through the complete upgrade.
- Confirm the development database is preserved.
- Document administrator setup and repository-author role assignment.
- Prepare a focused pull request to `biblemesh/moodle-mod_adaptivereview:bm-main`.
