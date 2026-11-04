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
| No draft of a new message with nothing but the signature (no text, subject, recipients or attachments) | `fix/no-draft-signature-only` | not submitted | 9 |
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
| In a thread, save or open the attachment of its own message (was: same attachment number of the selected message, e.g. an .eml instead of a PDF) | `fix/attachment-of-its-own-message` (upstream: `fix/attachment-save-own-message`) | PR #14007, open ("Save to Files" part; opening in office is own-build only) | 23 |
| Archive several selected threads at once, each to the archive mailbox of its own account | `feat/archive-selection` | not submitted | 24 |
| Play audio attachments in the message (player in the attachment, click plays or pauses) | `feat/play-audio-attachments` | not submitted | 25 |
| Table-based HTML signatures keep their layout (table and cell attributes and styles, linked images, no figure or cell paragraph margins) | `fix/signature-html-formatting` | not submitted | – |
| Composer shows images from hosts the admin allows (setting `composer_image_domains`, see below) | `fix/signature-html-formatting` | not submitted | – |
| Phone number links (`tel:`) in received HTML messages stay clickable | `fix/html-tel-links` (against `main`) | not submitted | – |
| Sync: no message skipped for good when it arrives during a sync (token taken before the search; new messages filtered by UID, so a missing one below the highest cached UID is synced too) | `fix/sync-token-before-search` (against `main`) | not submitted | – |

## Settings

| Setting | Default | Purpose |
|---|---|---|
| `composer_image_domains` | empty | Host names (comma separated) the Mail page may load images from over https, e.g. the host of a signature generator. Without it, a pasted signature with images hosted elsewhere shows broken images in the composer (recipients do see them). |

```
occ config:app:set mail composer_image_domains --value=signatures.example.com
```

Only plain host names are accepted; anything with a scheme, path, port or wildcard is ignored.

## Notes for upstream

Points to settle before submitting `fix/signature-html-formatting` to nextcloud/mail:

- **Two changes in one branch.** Split them into two PRs: the HTML support for table layouts
  (`htmlSupport.js`, `CellParagraphPlugin`, `LinkImage`, figure `margin: 0`) and the
  `composer_image_domains` setting.
- **The setting relaxes the page's content security policy.** Explain why it is safe: it is
  empty by default, admin-only, https and plain host names only, and received messages are
  not affected (they render in a frame with their own policy and load remote images through
  the proxy). Maintainers may prefer an admin settings field over an `occ`-only setting, or
  proxying composer images instead of allowing hosts.
- **Port to `main` (Vue 3).** The editor config moved there; check the CKEditor version and
  whether `LinkImage` is already loaded.
- **Behaviour changes to mention:** bold in a pasted signature comes back as `<strong>`;
  image figures go out with `margin: 0`; a single plain paragraph in a table cell is unwrapped
  in the sent HTML.
- **Tests:** `src/tests/unit/ckeditor/htmlSupport.spec.js` (a table-based signature survives
  a round trip) and `tests/Unit/Controller/PageControllerTest.php` (host filtering).

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
