# Changelog

## 1.11.0 (TBD)
* Added: Support for Mai Display Taxonomy plugin in CCA settings.
* Added: Pagination setting for archive content areas.
* Added: Support for multiple top-level elements in-content.
* Added: "Include child entries" and "Exclude child entries" options to also show/hide a content area on descendants of the included/excluded entries.
* Changed: Updated the updater.
* Changed: [Performance] Memoized keyword content processing and primed post meta on the content-area cache rebuild.
* Changed: Uses `maicca_is_editor()` helper instead of just `is_admin()`.
* Fixed: Content areas showing on search results regardless of settings.
* Fixed: Non-existent taxonomies not being removed from taxonomy list.
* Fixed: Nested empty values not being filtered from transient cache.
* Fixed: Taxonomy query using incorrect type for public parameter.
* Fixed: Disabled taxonomy filter on ACF relationship fields.

## 1.10.1 (12/5/24)
* Changed: Updated the updater.
* Changed: [Performance] Only run ACF filters in the back end for performance.
* Changed: [Performance] Switch get_terms to only return id and name
* Fixed: Make sure post type exists before displaying the label in admin columns.

## 1.10.0 (4/9/24)
* Added: New sitewide locations for global content areas.
* Added: New "After Footer" location. Also adds notice about using After Footer location with form plugins.
* Changed: Updated the updater.
* Fixed: Conditions on blog page were not working in some configurations.

## 1.9.6 (1/19/24)
* Fixed: Encoded special characters were displayed on the front end in some configurations.

## 1.9.5 (1/18/24)
* Fixed: Custom post type archive conditions not working in some configurations.
* Fixed: Remove unnecessary encoding in PHP's DOMDocument which was unintentionally encoding some special characters from non-English languages.

## 1.9.4 (11/29/23)
* Fixed: CCA's showing up on different archives than set in location settings.

## 1.9.3 (11/27/23)
* Fixed: CCA's showing up on different post types than set in location settings.

## 1.9.2 (11/27/23)
* Changed: Updated the updater.
* Changed: Updated character encoding function for PHP 8.2 compatibility.
* Fixed: Location field group minor CSS tweaks.

## 1.9.1 (8/17/23)
* Fixed: CCAs not displaying on individual terms archives.

## 1.9.0 (8/8/23)
* Added: Content Archives now support displaying CCAs on Search Results.
* Changed: Settings tabs are now above the actual settings fields.

## 1.8.0 (8/2/23)
* Added: [Developers] New `maicca_get_page_ccas` helper function to get all the CCAs displayed on the current page.
* Added: [Developers] New `maicca_caa` action hook that runs after the display logic but before adding the action hook.

## 1.7.1 (6/9/23)
* Fixed: Archive CCAs showing incorrect archives in some scenarios.

## 1.7.0 (5/5/23)
* Fixed: Invalid markup in some scenarios.
* Fixed: Added `in_the_loop()` check to make sure in-content CCAs only display in the primary loop.

## 1.6.3 (4/25/23)
* Fixed: Some elements were unexpectedly skipped in some instances.

## 1.6.2 (4/19/23)
* Fixed: Blocks parsing too early and breaking things in some configurations.

## 1.6.1 (3/31/23)
* Fixed: CCAs shown on single posts were getting double processed and causing layout issues in some configurations.

## 1.6.0 (3/14/23)
* Added: [Developers] New `maicca_content` filter to modify markup on-demand.

## 1.5.0 (1/30/23)
* Added: New `maicca_show_cca` filter to conditionally hide a CCA after settings and conditions have been checked.

## 1.4.0 (12/27/22)
* Changed: Updated to support block.json and the v2 block API.
* Changed: Updated updater.

## 1.3.0 (7/28/22)
* Changed: Updated updater.
* Fixed: Compatibility for archive entries on Mai Engine v2.22.0 or later.

## 1.2.2 (4/5/22)
* Changed: Default to full alignment in Mai CCA block.

## 1.2.1 (3/18/22)
* Added: Active label in Dashboard anytime a CCA has content since it may be displayed via the block now.
* Added: Link to edit Content Areas in the block settings.

## 1.2.0 (3/11/22)
* Added: Support for WooCommerce Shop, Product Category, and Product page display.

## 1.1.0 (3/9/22)
* Added: Mai Custom Content Area block to manually display a content area anywhere.
* Added: Entry author conditions to content areas.
* Added: Archive conditions to show content areas between entries.
* Fixed: Archive conditions showing in Mai Grid blocks in some configurations.
* Fixed: Archive conditions label display throwing an error in the Dashboard.
* Fixed: Closing markup mismatch in some scenarios.

## 1.0.0 (1/31/22)
* Initial release
