# MVP Release Audit

Audit date: 2026-10-20

## Candidate scope

This audit covers the global repository, reusable sets, lifelong
student-card mastery, the `mastery-v1` scheduler, authoritative student
dashboard, privacy behavior, and activity backup and restore.

## Passing gates

| Gate | Result |
| --- | --- |
| PHP syntax | Passed for every plugin PHP file |
| Plugin structure validation | Passed |
| Upgrade savepoint validation | Passed; 8 upgrade blocks match and are ordered |
| PHPDoc checker | Completed without an error exit |
| PHP Mess Detector | Completed without errors; advisory complexity findings remain |
| Front-end production build | Passed with no tracked bundle changes |
| PHPUnit | Passed: 36 tests, 242 assertions |
| Browser scenarios | Passed: 2 scenarios, 47 steps |
| Legacy migration rehearsal | Passed: 1 test, 20 assertions |
| Local Moodle upgrade | Passed for plugin version `2026102000` |
| Development data preservation | Confirmed: the test student's two cards remained at 0.5 mastery after upgrade |
| Live browser smoke test | Passed: activity, repository, and return navigation loaded without console errors |

The migration rehearsal converts an isolated database fixture back to the
legacy activity-owned schema, runs the complete upgrade, and verifies that
activity, card, mastery, session, and attempt identities and counts survive.

## Open release gates

### Moodle CodeSniffer

The official Moodle coding-standard scan currently reports 785 errors and 198
warnings across 34 files. Of those findings, 580 are automatically fixable.
This is broad prototype-era formatting and documentation debt rather than a
failure in the scheduler or migration behavior, but it means the repository's
configured Code Checker CI job will not pass yet.

Treat this as a release gate. Resolve it in a focused standards-cleanup change,
then rerun the entire test matrix so formatting fixes and manual variable and
documentation changes cannot mask regressions.

### Mustache lint

The local Docker PHP image does not contain Node.js, so the official Mustache
lint command could not start (`npm` was unavailable). The production front-end
build passed locally. The GitHub Actions workflow installs the complete
Moodle-plugin CI environment and remains the authoritative Mustache-lint gate.

### CI and pull request

The four-job GitHub Actions matrix still needs to pass on Moodle 5.0, 5.2, and
5.3 after the branch is pushed. A pull request to
`biblemesh/moodle-mod_adaptivereview:bm-main` should remain a draft until the
CodeSniffer baseline and all hosted checks are green.

## Non-blocking observations

- PHP Mess Detector reports advisory complexity and method-length findings in
  several established service, upgrade, privacy, and test methods.
- The front-end build reports an old `caniuse-lite` database and a missing
  Moodle-root TypeScript alias file; neither prevented the production build.
- Production deployment should still follow the institution's normal database
  and Moodle-data backup policy before upgrade.
