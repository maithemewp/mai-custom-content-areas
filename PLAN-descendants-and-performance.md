# Plan: Descendant ("page tree") targeting for Custom Content Areas

## Context

Two feature requests came in for the Mai Custom Content Areas plugin:

1. **Show a content area on selected pages *and their descendants*** (ancestry / page-tree match). Today the "Include entries" relationship field (`maicca_single_entries`) matches only the *exact* selected posts — no parent/child awareness. This is the real work.
2. **Show on posts by specific author(s).** **Already fully implemented** (`maicca_single_authors` user field + matching logic at `includes/functions.php:212-219`), shipped in v1.1.0. No work required — listed here only for completeness.

Decisions confirmed with the user:
- Behavior: **override** (same as the existing "Include entries" field) — when the current entry is a selected entry *or a descendant of one*, the CCA shows regardless of post type / taxonomy / author conditions.
- Scope: any **hierarchical content** (pages + hierarchical CPTs); depth resolved via `get_post_ancestors()`.
- UI: a **checkbox modifier on the existing "Include entries" field** — *not* a second post picker. Debated trade-off: a single CCA can't mix "exact include" + "include with descendants" (use two CCAs for that rare case); accepted in exchange for minimal UI, zero migration, and fit with the plugin's modifier-style conventions.
- **A symmetric checkbox on the "Exclude entries" field**, working the same way (hide on excluded entries *and their descendants*). Exclude continues to take precedence over include (existing behavior — exclude is evaluated first and returns early).
- Author feature: leave as-is.

## Approach

Reuse the existing `maicca_single_entries` / `maicca_single_exclude_entries` selections. Add one true/false ACF field directly after each. When checked, the matching additionally covers any entry whose ancestor chain contains one of the already-selected entries. Implemented by extending the existing `$exclude` early-return and `$include` calculation in `maicca_do_single_cca()` — no new branch in the display flow, so all downstream behavior is inherited unchanged. Both opt-in, default off → no regression for existing CCAs.

## Changes

### 1. `includes/register.php` — new checkbox field (immediately after the `maicca_single_entries` block, ~line 455, before "Exclude entries")

```php
[
    'label'         => __( 'Include child entries', 'mai-custom-content-areas' ),
    'instructions'  => __( 'Also show on any child/descendant entries of the entries selected above.', 'mai-custom-content-areas' ),
    'key'           => 'maicca_single_entries_descendants',
    'name'          => 'maicca_single_entries_descendants',
    'type'          => 'true_false',
    'required'      => 0,
    'message'       => __( 'Include descendants of the included entries', 'mai-custom-content-areas' ),
    'default_value' => 0,
    'conditional_logic' => [
        [
            [
                'field'    => 'maicca_single_entries',
                'operator' => '!=empty',
            ],
        ],
    ],
],
```

Then, immediately after the `maicca_single_exclude_entries` block, add the symmetric exclude checkbox:

```php
[
    'label'         => __( 'Exclude child entries', 'mai-custom-content-areas' ),
    'instructions'  => __( 'Also hide on any child/descendant entries of the entries excluded above.', 'mai-custom-content-areas' ),
    'key'           => 'maicca_single_exclude_entries_descendants',
    'name'          => 'maicca_single_exclude_entries_descendants',
    'type'          => 'true_false',
    'required'      => 0,
    'message'       => __( 'Exclude descendants of the excluded entries', 'mai-custom-content-areas' ),
    'default_value' => 0,
    'conditional_logic' => [
        [
            [
                'field'    => 'maicca_single_exclude_entries',
                'operator' => '!=empty',
            ],
        ],
    ],
],
```

(`conditional_logic` hides each checkbox until at least one entry is selected in its companion field — keeps the UI clean.)

### 2. `includes/functions.php` — feed the new field into args

- `maicca_get_ccas()` data array (~`functions.php:524`, next to `'include'`/`'exclude'`): add
  `'include_descendants' => get_field( 'maicca_single_entries_descendants' ),`
  `'exclude_descendants' => get_field( 'maicca_single_exclude_entries_descendants' ),`
- `maicca_do_single_cca()` defaults (`functions.php:111`): add `'include_descendants' => false,` and `'exclude_descendants' => false,`
- Sanitize block (`functions.php:129`): add
  `'include_descendants' => (bool) $args['include_descendants'],`
  `'exclude_descendants' => (bool) $args['exclude_descendants'],`

### 3. `includes/data.php` — memoized ancestors helper

`get_post_ancestors()` is not memoized as a function (it re-walks `post_parent` on every call, though each `get_post()` hop is object-cached). `mai_do_ccas()` loops over every CCA and each could call it up to twice, so wrap it in a per-request static keyed by post ID:

```php
/**
 * Gets a post's ancestor IDs, memoized per request.
 *
 * @since TBD
 *
 * @param int $post_id The post ID.
 *
 * @return int[]
 */
function maicca_get_post_ancestors( $post_id ) {
    static $cache = [];

    if ( ! isset( $cache[ $post_id ] ) ) {
        $cache[ $post_id ] = array_map( 'absint', get_post_ancestors( $post_id ) );
    }

    return $cache[ $post_id ];
}
```

### 4. `includes/functions.php` — matching logic

**Exclude early-return (replace lines 145-147):**

```php
// Bail if excluding this entry (or, optionally, a descendant of an excluded entry).
if ( $args['exclude'] ) {
    if ( in_array( $post_id, $args['exclude'] ) ) {
        return;
    }

    if ( $args['exclude_descendants'] && array_intersect( maicca_get_post_ancestors( $post_id ), $args['exclude'] ) ) {
        return;
    }
}
```

**Include calc (replace line 150):**

```php
// If including this entry (optionally also descendants of selected entries).
$include = $args['include'] && in_array( $post_id, $args['include'] );

if ( ! $include && $args['include'] && $args['include_descendants'] ) {
    $include = (bool) array_intersect( maicca_get_post_ancestors( $post_id ), $args['include'] );
}
```

`maicca_get_post_ancestors()` returns ancestor IDs (nearest-first, any depth, any hierarchical post type); the ancestor chain is constant for the whole request, so it is computed at most once regardless of how many CCAs are evaluated. Exclude is still evaluated before include, so exclude continues to win when an entry matches both. Setting `$include = true` reuses the existing override path (lines 152-219 are all gated on `! $include`) — no other display code changes. Non-hierarchical selected posts have no ancestors, so each checkbox is a no-op for them.

### 5. `CHANGES.md` — under `## 1.11.0 (TBD)`

`* Added: "Include child entries" and "Exclude child entries" options to also show/hide a content area on descendants of the included/excluded entries.`

## Out of scope

- Author targeting (#2) — already implemented.
- Archive content areas — request is single entries only.
- Mixing exact-only + with-descendants in one CCA — use two CCAs (accepted trade-off).

## Verification

1. WP admin → edit a Custom Content Area (`mai_template_part`): set a Single location, leave post-type/taxonomy conditions empty, select a top-level parent page in "Include entries". Confirm the new "Include child entries" checkbox appears only once an entry is selected. Check it, save (clears `mai_ccas` transient via `acf/save_post`).
2. Visit the selected parent page → CCA renders (exact include, unchanged behavior).
3. Visit a child and a grandchild of that parent → CCA renders (descendant match, any depth).
4. Visit an unrelated page → CCA does **not** render.
5. Uncheck the box, save, revisit a child page → CCA no longer renders there (override still exact-only).
6. Override semantics: with the box checked, add a conflicting post-type condition (e.g. only "post"); confirm the CCA still shows across the page tree.
7. Exclude side: on a CCA that otherwise matches a page tree (e.g. via post type), select that tree's parent in "Exclude entries" and check "Exclude child entries". Confirm the parent and all descendants are hidden, and an unrelated matching page still shows. Uncheck → only the parent itself is excluded.
8. Precedence: put the same page in both "Include entries" and an excluded tree with "Exclude child entries" on → confirm exclude wins (CCA hidden).
9. Regression: CCAs that don't use the new checkboxes behave exactly as before for include / exclude / taxonomy / keyword / author conditions.

---

# Part B — Performance pass (independent workstream)

> Separate from the feature above; can be reviewed and shipped on its own (own commit/release line). No dependency on Part A, though both share the same per-request memoization pattern.

## Context

A front-end hot-path audit of the plugin surfaced a few real inefficiencies. Several raw audit findings were rejected after scrutiny and are recorded here so they aren't revisited:

- **Do NOT add `is_admin()` guards to the `acf/init` hooks** (`register.php:183`, `acf-location.php:6`). The local field-group registration is what tells ACF how to format `get_field()` returns on the front end (relationship → IDs, `user` field, etc.) inside `maicca_get_ccas()`. Guarding it would change/break front-end field formatting.
- **Do NOT swap transients for `wp_cache_*`.** `get_transient()` already uses a persistent object cache when present; without one, the DB-backed transient is the desired cross-request persistence.
- **Skip `has_term()` memoization.** WP core already caches object-term relationships per post per request after the first call; gain is marginal.
- The "N+1 `get_field()` / thousands of lookups" finding is real but only on **cache-miss rebuild** of the `mai_ccas` transient (≤ once/hour), not per request — scoped accordingly below.

## Changes

### B1. Memoize keyword content processing — HIGH ROI, low risk

`includes/functions.php:159-166` runs `do_shortcode()` + `strip_tags()` on the full post content **per keyword-CCA per request**; multiple keyword CCAs reprocess the same post. Extract a request-memoized helper (place in `includes/data.php` next to `maicca_get_post_ancestors`):

```php
function maicca_get_searchable_content( $post_id ) {
    static $cache = [];
    if ( ! isset( $cache[ $post_id ] ) ) {
        $post              = get_post( $post_id );
        $cache[ $post_id ] = $post ? maicca_strtolower( strip_tags( do_shortcode( trim( $post->post_content ) ) ) ) : '';
    }
    return $cache[ $post_id ];
}
```

Then `functions.php:159-166` uses `$post_content = maicca_get_searchable_content( $post_id );` instead of inline reprocessing.

### B2. Prime post meta on the cache-rebuild query — medium

`maicca_get_ccas()` query (`functions.php:~481`) sets `update_post_meta_cache => false`, then calls ~19 `get_field()` per post in the loop → many individual meta SELECTs on every cache miss. Set `'update_post_meta_cache' => true` so WP batch-loads all post meta once before the loop. (Affects only the rebuild path; verify transient payload unchanged.)

### B3. ~~Memoize the parsed DOM in `maicca_add_cca`~~ — DROPPED

Call-path analysis: `maicca_add_cca()` runs once per content-location CCA inside a per-CCA `the_content` filter. With one content CCA there is a single parse (nothing to memoize); with multiple, each filter receives the post content *after the previous CCA was inserted*, so the input string differs every pass and a hash-of-input cache never hits. The only repeatedly-parsed value is each CCA's small own content — negligible. Input-hash memoization yields ~zero cache hits and is not worth the risk. The real cost ("N content CCAs → N full-content parses") is inherent to the sequential `the_content`-filter design; eliminating it would require a separate single-pass-insertion redesign, to be considered only if profiling later justifies it.

### B4. `CHANGES.md`

`* Changed: [Performance] Memoized keyword content processing and primed post meta on the content-area cache rebuild.`

## Verification

1. Install Query Monitor (or enable `SAVEQUERIES`). On a page with 2+ keyword-condition CCAs targeting the same post, confirm post content shortcode/strip processing happens once (add a temporary counter or Xdebug profile), and page output is byte-identical to before.
2. Force a `mai_ccas` cache rebuild (save any CCA → `acf/save_post` clears the transient), load a page, and compare DB query count before/after B2; confirm the rebuilt transient payload and all CCA matching behavior are unchanged.
3. Regression: full pass of Part A verification + spot-check keyword, taxonomy, author, include/exclude conditions still match correctly.
4. (If B3 done) Page with multiple content-location CCAs renders identically; confirm no double-escaped/garbled markup from DOM reuse.
