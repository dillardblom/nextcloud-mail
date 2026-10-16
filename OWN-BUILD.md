# Own build (v5.12.4-db)

Own build of Nextcloud Mail for nc01: upstream tag v5.12.4 plus the changes below. This branch is
never submitted as a pull request; every change also lives on its own branch (against `origin/main`
where it has been submitted upstream). Build with `~/dev-local/mail-build/build.sh <ref>`.

Numbers in the last column refer to the user's list `Nextcloud/Nextcloud-mail - improvements.md`.

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

## Not in this build

| Change | Branch | Upstream |
|---|---|---|
| Convert non-UTF-8 subjects and recipients before insert | `fix/utf8-recipient-labels` | PR #13782, open |
| `mail:account:list` and better account commands | `feat/account-commands-help` (local only) | not submitted |

## Next base

Wait for upstream 5.13 (same Vue 2 line), merge it into a new `v5.13.x-db` branch and carry the
changes above. 6.0 (Vue 3, `main`) only once it is stable; the UI changes then need porting.
