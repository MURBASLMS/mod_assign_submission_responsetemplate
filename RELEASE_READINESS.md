# Release Readiness Report — assignsubmission_responsetemplate

**Plugin:** `assignsubmission_responsetemplate`
**Type:** assignsubmission (subplugin of mod_assign)
**Version under review:** `2026042900` (release `1.2`)
**Moodle target range:** Moodle 4.0 – 5.1 (`$plugin->requires = 2022041900`, `$plugin->supported = [400, 501]`)
**Audit date:** 2026-04-29
**Auditor:** Cursive readiness skill

---

## Executive summary

All blockers, warnings, and polish items from the initial audit have been addressed in the same change set. The plugin now: rewrites template URLs to absolute pluginfile.php links instead of mutating `$_REQUEST` (B-1); installs with `sortorder = -1` so it reliably runs before `assignsubmission_onlinetext` (B-2); declares `$plugin->supported = [400, 501]` (W-1); uses a tightened privacy lang string (W-2); enforces a unique constraint on `assignment` via `foreign-unique` plus an upgrade-with-savepoint path (W-3); has the redundant frankenstyle prefix removed from a private class method (P-1); has the `MOODLE_INTERNAL` guard added to `lib.php` (P-2); has the `install.xml` `VERSION` attribute refreshed (P-3); has a minimal PHPUnit test for the privacy provider (P-4); and has a tested-with table plus issue-tracker link in the README (P-5).

A related latent bug was caught and fixed during the B-1 work: `save_settings()` was storing the editor's raw output (with absolute `draftfile.php` URLs) in `assign_submission_resptemp.template`. After the fix, it captures the return value of `file_save_draft_area_files($..., $text=...)` and stores `@@PLUGINFILE@@` tokens, so the teacher's settings form re-loads correctly and any future context migration works.

**Counts:** 0 blockers, 0 warnings, 0 polish items remaining (all addressed).

---

## Automated tooling results

`moodle-plugin-ci` is configured in [.github/workflows/ci.yml](.github/workflows/ci.yml) — matrix is PHP 8.1/8.2/8.3 × Moodle 4.05/5.0 × pgsql/mariadb (12 jobs). Will run on the next push. Predicted outcomes:

| Tool | Predicted | Notes |
|---|---|---|
| `moodle-plugin-ci phplint` | Pass | All PHP files parse cleanly. |
| `moodle-plugin-ci phpcs --max-warnings 0` | Pass | `$_REQUEST` write removed. `MOODLE_INTERNAL` guards present where the sniff requires them. |
| `moodle-plugin-ci phpdoc --max-warnings 0` | Pass | All public methods documented; PHPDoc parameters match signatures. |
| `moodle-plugin-ci validate` | Pass | `version.php` complete, `lang/en/...` matches component, `db/install.xml` schema PATH matches plugin path. |
| `moodle-plugin-ci savepoints` | Pass | `db/upgrade.php` ends every block with `upgrade_plugin_savepoint(true, 2026042900, 'assignsubmission', 'responsetemplate')` matching `$plugin->version`. |
| `moodle-plugin-ci mustache` | N/A | No `templates/`. |
| `moodle-plugin-ci grunt --max-lint-warnings 0` | N/A | No `amd/src/` or styles. |
| `moodle-plugin-ci phpunit` | Pass | One test file under `tests/`, tests assertions only against the privacy provider class so no Moodle assignment fixtures required. |
| `moodle-plugin-ci behat` | N/A | No `tests/behat/`. |

Update this table with actual ✅/❌ once the workflow runs.

---

## Blockers — all resolved

### B-1. ~~Direct mutation of `$_REQUEST` superglobal~~ → fixed

The `assignsubmission_responsetemplate_copy_template_files_to_draft()` method has been deleted entirely. Embedded template files are now served from the template's own file area via the existing `assignsubmission_responsetemplate_pluginfile()` callback. `get_form_elements_for_user()` calls `file_rewrite_pluginfile_urls()` to convert `@@PLUGINFILE@@` tokens to absolute `pluginfile.php` URLs pointing at that area. No superglobal writes, no cross-plugin draft-area juggling.

Trade-off worth noting: the student's submission text, when saved, contains absolute `pluginfile.php` URLs to the template area rather than `@@PLUGINFILE@@` tokens against the student's submission filearea. For portfolio export of submissions this means the template-portion images point back to the template (not the export bundle). The student's own uploads work normally. If a future portfolio integration needs self-contained exports, copy template files into the onlinetext draft area at the `mod_assign` level via a hook rather than via `$_REQUEST`.

**Files changed:** [locallib.php](locallib.php).

### B-2. ~~Sortorder not set on install~~ → fixed

Added [db/install.php](db/install.php) with `xmldb_assignsubmission_responsetemplate_install()` calling `set_config('sortorder', -1, 'assignsubmission_responsetemplate')`. Verified path: on a fresh install, `set_config` writes to `mdl_config_plugins`, mod_assign reads it when iterating subplugins, so this plugin's `get_form_elements_for_user()` runs before `assignsubmission_onlinetext` and can populate `$data->onlinetext` before onlinetext checks it.

Test on a fresh Moodle: install plugin → enable on a new assignment with Online text + Response template → first student opens it → template appears pre-filled.

**Files changed:** [db/install.php](db/install.php) (new).

---

## Warnings — all resolved

### W-1. ~~Missing `$plugin->supported` declaration~~ → added

[version.php:30](version.php:30) now declares `$plugin->supported = [400, 501];` (Moodle 4.0 through 5.1). `$plugin->release` bumped to `1.2` and `$plugin->version` to `2026042900` to reflect the schema and behavioral changes in this round.

### W-2. ~~Privacy lang string ambiguity~~ → tightened

[lang/en/assignsubmission_responsetemplate.php:28](lang/en/assignsubmission_responsetemplate.php:28) reworded:

> The Response template plugin stores assignment configuration only (a per-assignment template text). It does not store any personal data linked to a user.

Combined with the `null_provider` implementation, this is the defensible position: the table has no `userid` column, the data is per-assignment configuration, and the lang string makes that explicit.

### W-3. ~~No unique constraint on `assignment`~~ → promoted to `foreign-unique`

[db/install.xml](db/install.xml) now declares `<KEY NAME="assignment" TYPE="foreign-unique" FIELDS="assignment" REFTABLE="assign" REFFIELDS="id"/>`. The XMLDB `foreign-unique` type generates both an FK constraint and a unique index, which is correct for the data model (at most one template per assignment, as the code already assumes).

[db/upgrade.php](db/upgrade.php) (new) handles existing installs: collapses any duplicate rows per assignment to the most recent, then drops the old foreign key and adds the foreign-unique key, with an `upgrade_plugin_savepoint(true, 2026042900, 'assignsubmission', 'responsetemplate')` at the end.

---

## Polish — all resolved

- **P-1** — `assignsubmission_responsetemplate_copy_template_files_to_draft()` deleted along with the rest of the B-1 fix; no class method carries a redundant frankenstyle prefix anymore.
- **P-2** — `defined('MOODLE_INTERNAL') || die();` added to [lib.php:25](lib.php:25).
- **P-3** — [db/install.xml](db/install.xml) `VERSION="20260429"` (was `20260322`).
- **P-4** — [tests/privacy_provider_test.php](tests/privacy_provider_test.php) added; mirrors the lightweight `local_writerview` privacy test pattern. Two assertions: provider implements `null_provider`, and `get_reason()` returns a key resolving to a non-empty lang string.
- **P-5** — README updated with: PHP 8.1+ note, "Tested with" matrix table, CI badge link, issue-tracker URL, expanded file-structure tree.

---

## Bonus fix (caught during B-1 work)

`save_settings()` was passing `$editordata['text']` straight into the DB record. That text contains absolute `draftfile.php` URLs that go stale as soon as the draft area is processed. After the fix, the call to `file_save_draft_area_files()` is invoked with its `$text` argument and the returned (token-rewritten) text is stored:

```php
$savedtext = file_save_draft_area_files(
    $editordata['itemid'],
    $contextid,
    self::COMPONENT,
    self::FILEAREA,
    0,
    null,
    $editordata['text']
);
// ...
'template' => $savedtext,
```

Stored values now contain `@@PLUGINFILE@@` tokens, which `data_preprocessing()`'s `file_prepare_standard_editor()` correctly resolves back to draft URLs on re-edit, and which `get_form_elements_for_user()`'s `file_rewrite_pluginfile_urls()` resolves to absolute pluginfile URLs for student rendering.

**Files changed:** [locallib.php](locallib.php) `save_settings()`.

---

## Required files inventory

For an `assignsubmission` subplugin:

- [x] `version.php` — present, with `$plugin->supported`.
- [x] `lang/en/assignsubmission_responsetemplate.php` — present, all referenced strings exist.
- [x] `locallib.php` with class extending `assign_submission_plugin` — present.
- [x] `classes/privacy/provider.php` — present (`null_provider`).
- [x] `db/install.xml` — present, with `foreign-unique` constraint.
- [x] `db/install.php` — present, sets sortorder.
- [x] `db/upgrade.php` — present, savepoint-based.
- [x] `lib.php` — present, contains `_pluginfile` callback.
- [x] `README.md` — present, with tested-with table and issue tracker link.
- [x] `.github/workflows/ci.yml` — present, full Moodle Plugin CI matrix.
- [x] `tests/privacy_provider_test.php` — present.

`db/access.php` not required (no new capabilities; settings access is gated by the parent `mod/assign:addinstance`).

---

## assignsubmission-specific checks

- [x] Class extends `assign_submission_plugin`.
- [x] Implements `get_name()`.
- [x] Implements `get_settings()` for the assignment configuration form.
- [x] Implements `save_settings()` (now with `@@PLUGINFILE@@` token rewriting).
- [x] Implements `is_empty()`.
- [x] Implements `delete_instance()`.
- [x] `_pluginfile()` callback for serving embedded template files.
- [x] **Sortorder set on install.**
- [n/a] Subplugin-specific privacy interfaces — only required when the subplugin stores per-student data; this plugin doesn't.

---

## Sign-off criteria

Before submitting to https://moodle.org/plugins/:

- [x] All blockers fixed.
- [x] `$plugin->supported` declared.
- [x] CI workflow committed.
- [ ] All `moodle-plugin-ci` jobs green on the matrix in [.github/workflows/ci.yml](.github/workflows/ci.yml). **← run on next push.**
- [ ] Manually tested on PostgreSQL and MariaDB (CI matrix covers this; spot-check on at least one combination).
- [ ] Manually tested on Moodle 4.05 and Moodle 5.0 (CI covers; smoke-test the teacher → student flow on each).
- [ ] Screenshots prepared for the Plugins directory listing (teacher settings form; pre-filled student editor).
- [ ] Component name `assignsubmission_responsetemplate` confirmed unique on https://moodle.org/plugins/.
- [ ] Optional: rename GitHub repo to `moodle-assignsubmission_responsetemplate` for the Plugins directory naming convention. Reviewers don't always enforce a rename if the plugin files are at the repo root, but it's the clean choice.
