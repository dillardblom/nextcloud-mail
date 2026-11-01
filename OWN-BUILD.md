# Own build (v5.12.6-db)

Own build of Nextcloud Mail: upstream tag v5.12.6 plus the changes below, while they wait for
review upstream. This branch is never submitted as a pull request; every change also lives on its
own branch (against `main` where it has been submitted upstream).

The installable package is attached to the matching release of this repository. It keeps the
upstream version number, so Nextcloud replaces it with the next official Mail release from the app
store; install the next own build instead if you want to keep these changes. Use it at your own
risk and keep a copy of the `mail` app folder you replace.

The last column refers to the item in our own wish list.

## In this build

| Change | Branch | Upstream | List |
|---|---|---|---|
| Fold an account to a single line, indent its mailboxes | `feat/fold-accounts` | PR #13736, open | 1 |
| Optional account label in combined mailboxes, label as mailbox name, label in the navigation | `feat/account-label` (+ `v5.12.3-db-account-label-name`) | not submitted | 2 |
| Maximized composer uses the screen and is remembered after a reload | `feat/composer-maximize` | not submitted | 3 |
| Reply to the open message with Ctrl+R / Cmd+R | `feat/reply-shortcut` | not submitted | 4 |
| Mailbox search: subqueries instead of joins, correlated subqueries, tag ids as integers | `perf/search-recipient-subqueries` (deleted) | PR #13803, **merged**; backported to stable5.13 (#13848) and stable5.12 (#13851) | 5 |
| Fetch preview body parts in batches; analyze structure before attachment names | `perf/preview-enhancer-attachments` | not submitted | 5 |
| Cache the HTML body fetched on a cache miss | `perf/cache-html-body-miss` | not submitted | 5 |
| Abort superseded envelope list requests | `perf/cancel-stale-envelope-loads` | not submitted | 5 |
| Fetch the text parts of a message in one request | `perf/message-open-single-fetch` | not submitted | 5 |
| Apply the limit once across body search chunks | `perf/body-search-global-limit` | not submitted | 5 |
| Sync: at most one page of new messages when the client knows none (fixed the sync OOM loop) | `fix/sync-without-known-ids` | PR #13823, open | – |
| Show the own build version in About | – (build only) | never | – |
| No draft of a reply or forward the user didn't edit | `fix/no-draft-without-edits` | not submitted (main port needed) | 9 |
| Reply actions named after what they do ("Reply all" / "Reply") | `fix/no-draft-without-edits` | not submitted | 11 |
| Reply from the message list again | `feat/list-reply` | not submitted | 16 |
| Type to find an account or alias in the From field | `feat/searchable-from` | not submitted | 8 |
| Convert non-UTF-8 subjects and recipients before insert | `fix/utf8-recipient-labels` | PR #13782, open | – |
| `mail:account:list` and better account commands | `feat/account-commands-help` (local only) | not submitted | – |
| Number of messages of a thread in the message list | `feat/thread-count` | not submitted | 13a |
| Select across date groups, and "Select all" | `feat/select-across-groups` | not submitted | 15 |
| Open an office attachment in the office app (click or menu), on its editor page; PDFs too with Euro-Office/ONLYOFFICE | `feat/open-attachment-in-office` | not submitted | 14 |
| Configurable single-key shortcuts of the message list, and a key to mark as spam | `feat/configurable-shortcuts` | not submitted | – |
| Reply among the configurable shortcuts, Ctrl/Cmd combinations (e.g. R to reply, Ctrl+R to refresh); own keys kept after a reload | `feat/configurable-reply-refresh` | not submitted | 21 |
| Favorite among the actions shown when hovering a message | `feat/hover-favorite` | not submitted | 20 |
| Refresh the open message list every 5/15/30/60 minutes (default 15) or manually | `feat/auto-refresh` | not submitted | 19 |

## Not in this build

Nothing. Check this table against `git log vX.Y.Z..vX.Y.Z-db` after every rebuild on a new
upstream version.

## Install

Download `mail-v5.12.6-db.tar.gz` from the release, then on the server:

1. Make a copy of the current `apps/mail` or `custom_apps/mail` folder.
2. Unpack the package next to that folder as the web server user, and swap the two folders.
3. Run `occ upgrade`, and make browsers load the new scripts (they keep the old ones while the
   version number stays the same): raise the theming cachebuster with
   `occ config:app:set theming cachebuster --value=<current value + 1>`.

The code integrity check will report the mail app as changed; that is expected for a build that is
not signed by Nextcloud.

## Next base

Wait for upstream 5.13 (same Vue 2 line), merge it into a new `v5.13.x-db` branch and carry the
changes above. 6.0 (Vue 3, `main`) only once it is stable; the UI changes then need porting.
