# Adaptive Review Administrator Setup

## Requirements

- Moodle 5.0 or later.
- A supported PHP and database combination for the installed Moodle release.
- The plugin directory named `adaptivereview` under `mod/`.

## Installation and upgrade

1. Place the plugin in `mod/adaptivereview`.
2. Sign in as a site administrator.
3. Open **Site administration → Notifications** and complete the upgrade.
4. Purge Moodle caches if the site uses long-lived application or reverse-proxy caches.
5. Open an Adaptive Review activity and confirm that the dashboard loads.

Upgrades preserve existing activities, card identities, mastery, sessions, and
attempt history. The global-repository migration creates one reusable set per
legacy activity without merging cards that happen to have identical text.

Before upgrading a production site, use the institution's normal database and
Moodle-data backup process. There is no destructive down migration.

## Repository access

The repository is protected by the system-level capability
`mod/adaptivereview:manageglobalrepository`.

- Site administrators have access automatically.
- Moodle's Manager archetype receives the capability by default.
- Teachers and editing teachers do not receive repository-wide editing access
  merely because they manage a course.
- Students never need repository access. They encounter shared cards through
  Adaptive Review activities in their courses.

### Create a dedicated repository-author role

1. Open **Site administration → Users → Permissions → Define roles**.
2. Select **Add a new role**.
3. Use no archetype, or use Manager only if the institution wants the role to
   inherit all other Manager permissions.
4. Name the role, for example **Adaptive Review Repository Author**.
5. Enable **System** as an assignable context type.
6. Allow **Manage the global Adaptive Review repository**
   (`mod/adaptivereview:manageglobalrepository`).
7. Save the role.
8. Open **Site administration → Users → Permissions → Assign system roles**
   and assign the role to approved authors.

A repository author still needs normal access to a course containing an
Adaptive Review activity to enter the repository from that activity's
navigation. Institutions can use a dedicated authoring course for this purpose.

## Instructor workflow

When adding or editing an Adaptive Review activity, an instructor can:

- create a new repository set for that activity; or
- select an existing active set.

The activity's Set Mastery goal remains course-specific. Reusing a set does not
clone its cards, and the student's lifelong mastery follows the student-card
pair into the new activity.

Only authorized repository authors can create, edit, duplicate, reorder,
archive, or restore global sets and cards. Editing a shared card changes it in
every set that references the same card. Duplicate the set first when an
independent variation is required.

## Privacy and backup policy

- Lifelong mastery and repository authorship are exported in system context.
- Activity privacy deletion removes that activity's sessions and attempts but
  preserves lifelong mastery used by other courses.
- System-level user erasure removes lifelong mastery and anonymizes authorship
  while preserving shared instructional content.
- Activity backup creates an isolated set and card copy on restore.
- A user-data backup includes only mastery for students who participated in the
  backed-up activity, plus their activity sessions and attempts.
- Archived cards remain archived after restore, and archival never deletes
  mastery or attempt history.

## Post-installation check

1. Create an Adaptive Review activity with its tutorial set.
2. Enrol a test student and complete one New-card review session.
3. Confirm that the reviewed cards display `0.5` mastery and a one-day next
   interval.
4. As an administrator or repository author, open **Adaptive Review
   Repository** from the activity navigation.
5. Confirm that **Back to [activity name]** returns to the student dashboard.

