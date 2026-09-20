# Contributing to quiz_livequizmonitor

This document covers how stories are developed and merged, and the practices
we've adopted to keep those merges painless. It also maintains a log of what
has been merged into the integration branch, `CTP-6466-Live_quiz_monitoring`,
so anyone can see current status at a glance.

## Branch workflow

- `main` is the stable base. Every story branch (`CTP-XXXX-description`) is
  branched from `main`, never from another story branch or from
  `CTP-6466-Live_quiz_monitoring` directly — this is a university workflow
  requirement, not a technical one, and it's not going to change. Each story
  must be independently reviewable against `main`.
- `CTP-6466-Live_quiz_monitoring` is the accumulative integration branch. It
  contains a merge of every approved story branch. It is not itself a story
  branch and is not the target of a PR against `main` until the whole set of
  stories is ready to ship together.
- Story branches are peer-reviewed and approved independently. Once
  approved, they are merged into `CTP-6466-Live_quiz_monitoring` (not
  rebased or squashed onto it) so that each story's review history is
  preserved intact within the merge commit's ancestry.

## Writing a story with a future merge in mind

Because every story eventually merges into `CTP-6466-Live_quiz_monitoring`
alongside several others, a story's design choices have consequences beyond
its own branch. The points below aren't about changing *what* a story does
— they're about how it's built, so that combining it with everything else
later doesn't require redesigning it mid-merge.

### 1. Compute state once, on the server. Never duplicate logic in JS.

If a value (a sort order, a status flag, a badge condition) is computed in
`monitor_manager.php` or the renderer, the JS layer should only *display*
what it's given — it should never independently recompute the same logic.
Duplicated logic drifts: when one side changes, the other goes stale
silently, with no error, until someone notices the UI is wrong. This has
been the single most common source of real bugs across story merges so far
(see: sort order silently breaking because a status-rank list existed in
both PHP and JS, and only one got updated).

### 2. Check the existing registry before inventing a new shape.

Several concerns already have a single source of truth:

- **Table columns** — `classes/local/column_helper.php`. If your story adds,
  hides, or reorders a column, extend this registry rather than building a
  parallel list of column ids/labels.
- **Overrides** — `classes/local/manager/overrides_manager.php`. If your
  story needs to know whether a student has a user or group override,
  call `get_override_map()`; don't write a new query against
  `{quiz_overrides}`.

If your story needs a *new* shared concept that doesn't have a home yet,
consider giving it one (a manager class, a registry) rather than defining it
inline in the first place that happens to need it. The next story that
touches the same concept will thank you.

### 3. Before writing UI in a shared region, check what's already there.

The student table header row, the student row's cells, and the filter
toolbar are shared UI regions that multiple stories touch. Before adding
markup to `student_table.mustache`, `student_row.mustache`, or
`monitor_page.mustache`, check `CTP-6466-Live_quiz_monitoring`'s current
version of that file (not just `main`'s) to see what's already landed there
from other approved stories. This avoids two stories independently inventing
different context shapes for the same template region (for example: one
branch building `tableheaders` with sort metadata, another independently
building `columns` with visibility metadata — neither aware of the other,
both needing reconciliation at merge time).

If your story adds an interactive element (a button, a toggle) inside an
element that's already clickable for another purpose (e.g. a sortable
column header), make sure clicks on your new element don't also trigger the
existing behaviour — test this explicitly, since automated tests written in
isolation on your own branch won't catch it.

### 4. Never hand-edit compiled/build output.

`amd/build/*.min.js` and `*.min.js.map` are generated from `amd/src/*.js` by
`grunt amd`. If a merge conflicts in a `.min.js` file, resolve the conflict
in the corresponding `.src.js` file only, then rebuild:

```
cd $MOODLE; $GRUNT; cd $DIR
```

Never resolve a `.min.js`/`.map` conflict by hand-editing the minified
output directly.

### 5. Shared Behat step definitions need care during merges.

`tests/behat/behat_quiz_livequizmonitor.php` defines custom steps used
across multiple feature files from different stories. When this file
conflicts during a merge, confirm that **both** sides' custom step
definitions survive the resolution — it's easy for a conflict resolution to
silently keep only one side's steps, which then surfaces later as
"undefined step" Behat failures in a feature file that wasn't even part of
the merge you were resolving.

### 6. Optional: merge `CTP-6466` into your story branch periodically, for your own visibility.

Nothing in the university's process prevents you from privately running
`git merge CTP-6466-Live_quiz_monitoring` into your own story branch during
development, purely so you can see what's already landed and avoid
collisions before they happen. This is not part of the official review path
and should not appear in the PR diff against `main` — check with the repo
owner on the right way to keep it out (e.g. resetting before opening the
PR) if you use this technique.

## Merge history: CTP-6466-Live_quiz_monitoring

This table tracks which story branches have been merged into
`CTP-6466-Live_quiz_monitoring`, in merge order. To regenerate/verify it
against the actual repository history, run:

```
git log --merges --first-parent --date=short \
  --pretty=format:"| %ad | %s | %h |" \
  CTP-6466-Live_quiz_monitoring
```

| Date | Merge | Commit |
|---|---|---|
| *(unverified — run command above)* | Merge `CTP-6605-Duration_from_open_close_time` | |
| *(unverified — run command above)* | Merge `CTP-6606-Show_quiz_password` | |
| *(unverified — run command above)* | Merge `CTP-6607-Link_to_detailed_logs` | |
| *(unverified — run command above)* | Merge `CTP-6608-Link_to_quiz_attempts` | |
| 2026-09-17 | Merge `CTP-6609-Show_inactive_students` | `5d7055a` |
| *(unverified — run command above)* | Merge `CTP-6610-Make_columns_sortable` | |
| 2026-09-18 | Merge `CTP-6660-Respect_group_restrictions` | `2e39713` |
| 2026-09-19 | Merge `CTP-6724-Filter_group_overrides` (includes `CTP-6723-Filter_user_overrides`, merged into 6724 first) | `bd2921a` |
| 2026-09-19 | Merge `CTP-6735-Hide_show_columns` | `f6dc4d4` |

**Not yet merged into `CTP-6466-Live_quiz_monitoring`:** none — all current
story branches have been merged as of the dates above. New story branches
should be added to this table when merged.

> The four rows marked "unverified" were merged prior to this log being
> created and their exact commit hashes/dates weren't recorded at the time.
> Run the command above once and fill them in — after that, keep the table
> updated as part of each merge.
