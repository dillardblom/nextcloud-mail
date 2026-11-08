# Own build (v5.13.1-db, branch `branch-5-13`)

Own build of Nextcloud Mail: upstream tag v5.13.1 plus the changes below, while they wait for
review upstream. This branch is never submitted as a pull request; every change also lives on its
own branch (against `main` where it has been submitted upstream).

The installable package is attached to the matching release of this repository. Its version is
5.13.1.2: above the upstream number, so the database changes of this build run, and still
below 5.13.2, so Nextcloud replaces it with the next official Mail release from the app store;
install the next own build instead if you want to keep these changes. Nextcloud does not install a
lower version over it, so going back to an older package needs the version in its
`appinfo/info.xml` raised. Use it at your own
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
| `mail:account:list` and better account commands | `feat/account-commands-help` | not submitted | – |
| Number of messages of a thread in the message list, also in the unread dot | `feat/thread-count` | not submitted | 13a |
| Select across date groups, and "Select all" | `feat/select-across-groups` | not submitted | 15 |
| Open an office attachment in the office app (click or menu), on its editor page; PDFs too with Euro-Office/ONLYOFFICE; also CSV, and TXT, MD, TSV, Pages, Numbers and Keynote with Euro-Office/ONLYOFFICE; LOG, JSON, XML, YAML, conf, HTML and PHP open in Text as source, in the Viewer over Mail | `feat/open-attachment-in-office` | not submitted | 14 |
| Configurable single-key shortcuts of the message list, and a key to mark as spam | `feat/configurable-shortcuts` | not submitted | – |
| Reply among the configurable shortcuts, Ctrl/Cmd combinations (e.g. R to reply, Ctrl+R to refresh); own keys kept after a reload; a plain reply key can still be typed in the composer | `feat/configurable-reply-refresh` | not submitted | 21 |
| Favorite among the actions shown when hovering a message | `feat/hover-favorite` | not submitted | 20 |
| Refresh the open message list every 5/15/30/60 minutes (default 15) or manually | `feat/auto-refresh` | not submitted | 19 |
| In a thread, save or open the attachment of its own message (was: same attachment number of the selected message, e.g. an .eml instead of a PDF) | `fix/attachment-of-its-own-message` (upstream: `fix/attachment-save-own-message`) | PR #14007, **merged** in `main` ("Save to Files" part; opening in office is own-build only) | 23 |
| Archive several selected threads at once, each to the archive mailbox of its own account | `feat/archive-selection` | not submitted | 24 |
| Play audio attachments in the message (player in the attachment, click plays or pauses) | `feat/play-audio-attachments` | not submitted | 25 |
| Table-based HTML signatures keep their layout (table and cell attributes and styles, linked images, no figure or cell paragraph margins) | `fix/signature-html-formatting` | not submitted | – |
| Composer shows images from hosts the admin allows (setting `composer_image_domains`, see below) | `fix/signature-html-formatting` | not submitted | – |
| Phone number links (`tel:`) in received HTML messages stay clickable | `fix/html-tel-links` (against `main`) | not submitted | – |
| Sync: no message skipped for good when it arrives during a sync (token taken before the search; new messages filtered by UID, so a missing one below the highest cached UID is synced too) | `fix/sync-token-before-search` (against `main`) | PR #14030, open | – |
| Saving to Files leaves no empty file behind when the write is blocked (e.g. by the antivirus app) | `fix/no-empty-file-after-blocked-save` | not submitted | – |
| Openable attachments are copied to "Mail attachments" when a message is opened (not from junk, up to 25 MB), so a click opens at once; copies unused for 7 days and unchanged move to the trash bin, changed ones stay | `feat/prefetch-attachments` | not submitted | – |
| Virus scan of received mail with ClamAV: an infected message has its attachments blocked (download, preview, zip, Save to Files, open, forward, API) and moves to junk or trash; settings in Administration > Groupware (see below) | `feat/attachment-virus-scan` | proposed in issue #14091 | – |

## Settings

| Setting | Default | Purpose |
|---|---|---|
| `composer_image_domains` | empty | Host names (comma separated) the Mail page may load images from over https, e.g. the host of a signature generator. Without it, a pasted signature with images hosted elsewhere shows broken images in the composer (recipients do see them). |

```
occ config:app:set mail composer_image_domains --value=signatures.example.com
```

Only plain host names are accepted; anything with a scheme, path, port or wildcard is ignored.

The virus scan is set up in Administration > Groupware > Virus scan. It is off by default. Without
an own ClamAV address, Mail uses the daemon (`tcp://host:port`) or socket of the Antivirus for files
app; its `clamscan` executable mode is not supported. Messages larger than the stream limit of
ClamAV count as not scannable; `antivirus_max_size` (bytes) overrides the limit taken from the
antivirus app and should stay at or below `StreamMaxLength` in `clamd.conf`.

| Setting | Default | Purpose |
|---|---|---|
| `antivirus_enabled` | off | Scan received messages with attachments |
| `antivirus_infected_action` | `junk` | Move an infected message to `junk`, `trash`, or leave it (`none`) |
| `antivirus_allow_infected_download` | off | Users may still download, open and forward infected attachments |
| `antivirus_allow_unscanned` | off | Attachments that could not be scanned are available with a warning instead of blocked |
| `antivirus_clamd_address` | empty | ClamAV daemon, `tcp://host:port` or `unix:///path/to/clamd.sock` |
| `antivirus_max_size` | 0 | Largest message sent to ClamAV in bytes; 0 takes the limit of the antivirus app, else 25 MiB |

`.log` attachments open in Text only when the server maps that extension to a text type. Add this
to `config/mimetypemapping.json` (keep what is already there), then update the database:

```
{ "log": ["text/plain"] }
occ maintenance:mimetype:update-db --repair-filecache
```

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

## Branches

Every branch of our own, also those not in this build. Keep this list up to date with every new
branch, so it stays clear which changes are ours and where they live. All are on the fork
(`dillardblom/nextcloud-mail`). "5.12" means based on the 5.12 line and merged into the own
builds; "main" means against upstream `main`, for a pull request.

| Branch | Base | In this build | Note |
|---|---|---|---|
| `branch-5-13` | v5.13.1 | – | this build |
| `v5.12.3-db` … `v5.12.6-db` | 5.12 | merged | earlier own builds; `v5.12.6-db` still takes later work |
| `v5.12.2-db` | 5.12 | no | first own build; local and fork copies differ, the fork is the newer one |
| `feat/fold-accounts` | main | yes | PR #13736 |
| `v5.12.2-db-pr13736`, `v5.12.2-db-pr13736-align` | 5.12 | no | 5.12 versions of #13736 tried in the build; replaced by `feat/fold-accounts` |
| `pr-13736-screenshots` | main | no | screenshots for #13736 |
| `feat/account-label`, `v5.12.3-db-account-label-name` | main / 5.12 | yes | the fork copy of `feat/account-label` has one commit more |
| `feat/composer-maximize` | main | yes | |
| `feat/reply-shortcut` | main | yes | |
| `perf/preview-enhancer-attachments` | main | yes | |
| `perf/cache-html-body-miss` | main | yes | |
| `perf/cancel-stale-envelope-loads` | main | yes | |
| `perf/message-open-single-fetch` | main | yes | |
| `perf/body-search-global-limit` | main | yes | |
| `fix/sync-without-known-ids` | main | yes | PR #13823 |
| `fix/sync-init-reconcile-known` | main | no | follow-up on #13823: reconcile known messages on the initial sync; not submitted |
| `fix/partial-sync-bound` | main | no | at most `MAX_NEW_MESSAGES` new messages per partial sync (upstream issue #13778); not submitted |
| `fix/sync-token-before-search` | main | yes | PR #14030 |
| `fix/utf8-recipient-labels` | main | yes | PR #13782; the fork copy is the rebased PR, the local copy the older 5.12 version |
| `feat/account-commands-help` | main | yes | |
| `fix/no-draft-without-edits` | 5.12 | yes | needs a port to `main` |
| `fix/no-draft-signature-only` | 5.12 | yes | |
| `feat/list-reply` | 5.12 | yes | |
| `feat/searchable-from` | 5.12 | yes | |
| `feat/thread-count` | 5.12 | yes | |
| `feat/select-across-groups` | 5.12 | yes | |
| `feat/open-attachment-in-office` | 5.12 | yes | |
| `feat/configurable-shortcuts` | 5.12 | yes | |
| `feat/configurable-reply-refresh` | 5.12 | yes | on top of `feat/configurable-shortcuts` |
| `feat/hover-favorite` | 5.12 | yes | |
| `feat/auto-refresh` | 5.12 | yes | |
| `fix/attachment-of-its-own-message` | 5.12 | yes | build version, with opening in office |
| `fix/attachment-save-own-message` | main | – | PR #14007, merged |
| `feat/archive-selection` | 5.12 | yes | |
| `feat/play-audio-attachments` | 5.12 | yes | |
| `fix/signature-html-formatting` | 5.12 | yes | see Notes for upstream |
| `fix/html-tel-links` | main | yes | |
| `fix/no-empty-file-after-blocked-save` | 5.12 | yes | |
| `feat/prefetch-attachments` | 5.12 | yes | on top of `feat/open-attachment-in-office` |
| `feat/attachment-virus-scan` | 5.12 | yes | on top of `feat/prefetch-attachments`; issue #14091 |

Not in the list: `backup/*` (local states before a squash or rewrite) and
`upstream-pr-13206` / `v5.12.2-db-pr13206` (someone else's pull request #13206, tried in a build).

## Install

Download the newest `mail-v5.13.1-db.N.tar.gz` from the [releases](https://github.com/dillardblom/nextcloud-mail/releases), then on the server:

1. Make a copy of the current `apps/mail` or `custom_apps/mail` folder.
2. Unpack the package next to that folder as the web server user, and swap the two folders.
3. Run `occ upgrade`, and make browsers load the new scripts (they keep the old ones while the
   version number stays the same): raise the theming cachebuster with
   `occ config:app:set theming cachebuster --value=<current value + 1>`.

The code integrity check will report the mail app as changed; that is expected for a build that is
not signed by Nextcloud.

## Next base

Base `branch-5-13` follows the stable5.13 tags. To carry later work from the 5.12 build:
`git merge v5.12.6-db`. For a new upstream 5.13.x, merge that tag.

Merge with care between the 5.12 and 5.13 lines: their merge base predates 5.12, so git reports
conflicts in files upstream did not change. Merge per file with the previous upstream tag as the base.

6.0 (Vue 3, `main`) only once it is stable; the UI changes then need porting.
