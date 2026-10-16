# Instructor Repository Workflow

## Activity setup

The Adaptive Review activity form offers two choices:

- Create a new set for the activity. This creates a repository set and the
  tutorial cards used by a new activity.
- Select an existing active set. The activity references that set directly and
  does not clone its cards.

The mastery goal remains an activity setting. Two activities may use the same
set with different goals.

## Repository access

The global repository requires the system capability
`mod/adaptivereview:manageglobalrepository`. Moodle administrators have all
capabilities. The Manager archetype receives this capability by default, and an
institution can grant it to a dedicated repository-author role at system
context.

Authorized authors can search, create, edit, order, duplicate, archive, and
restore sets and cards from **Adaptive Review Repository** in the activity
navigation.

## Shared editing

Editing a shared card changes it in every set that references the same card.
The interface displays the number of consuming sets before an edit is saved.

Duplicating a set creates new card identities as well as a new set identity.
This provides an independent variation whose future card edits do not affect
the source set.

Removing a card from a set does not delete or archive the global card. Archival
is a separate, reversible action, and mastery and attempt history are retained.
