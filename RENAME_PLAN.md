# Plugin Renaming Plan: Simplest Popup → Synced Pattern Popups

**New Name:** Synced Pattern Popups  
**New Slug:** `sppopups`  
**Status:** Planning Phase

---

## Overview

This document tracks the complete renaming of the plugin from "Simplest Popup" to "Synced Pattern Popups" with slug `sppopups`. The renaming is broken into phases for testing and validation.

### Naming Conventions

- **Plugin Name:** Synced Pattern Popups
- **Text Domain:** `sppopups`
- **Class Prefix:** `SPPopups_` (e.g., `SPPopups_Plugin`)
- **Function Prefix:** `sppopups_` (e.g., `sppopups_init`)
- **Constant Prefix:** `SPPOPUPS_` (e.g., `SPPOPUPS_VERSION`)
- **CSS Class Prefix:** `.sppopups-` (e.g., `.sppopups-modal`)
- **JS Variable:** `sppopups` (e.g., `sppopups.ajaxUrl`)
- **File Names:** `sppopups-*` (e.g., `sppopups.php`, `class-sppopups-plugin.php`)
- **Directory Name:** `sppopups` (final phase)

---

## Phase 1: Core PHP Constants and Class Names ✅ COMPLETE

**Status:** ✅ Complete  
**Risk Level:** Medium  
**Testing Required:** Plugin activation, basic functionality  
**Completed:** [Date will be updated when confirmed]

### Files to Modify

1. **`simplest-popup.php`** (main plugin file)
   - Plugin header: Name, Text Domain
   - Constants: `SIMPLEST_POPUP_*` → `SPPOPUPS_*`
   - Function: `simplest_popup_init()` → `sppopups_init()`
   - Class instantiation: `Simplest_Popup_Plugin` → `SPPopups_Plugin`

2. **`includes/class-simplest-popup-plugin.php`**
   - Class name: `Simplest_Popup_Plugin` → `SPPopups_Plugin`
   - Package name in header
   - All property type hints
   - All method references

3. **`includes/class-simplest-popup-pattern.php`**
   - Class name: `Simplest_Popup_Pattern` → `SPPopups_Pattern`
   - Package name in header
   - Type hints for `Simplest_Popup_Style_Collector` → `SPPopups_Asset_Collector`

4. **`includes/class-simplest-popup-cache.php`**
   - Class name: `Simplest_Popup_Cache` → `SPPopups_Cache`
   - Package name in header
   - Static method references

5. **`includes/class-simplest-popup-style-collector.php`**
   - Class name: `Simplest_Popup_Style_Collector` → `SPPopups_Asset_Collector`
   - Package name in header
   - Note: Also renaming from "Style Collector" to "Asset Collector" for clarity

6. **`includes/class-simplest-popup-ajax.php`**
   - Class name: `Simplest_Popup_Ajax` → `SPPopups_Ajax`
   - Package name in header
   - Type hints for other classes

7. **`includes/class-simplest-popup-admin.php`**
   - Class name: `Simplest_Popup_Admin` → `SPPopups_Admin`
   - Package name in header
   - Type hints

8. **`includes/class-simplest-popup-abilities.php`**
   - Class name: `Simplest_Popup_Abilities` → `SPPopups_Abilities`
   - Package name in header
   - Type hints
   - Ability category: `simplest-popup` → `sppopups`
   - Ability names: `simplest-popup/*` → `sppopups/*`

9. **`includes/class-simplest-popup-trigger-parser.php`**
   - Class name: `Simplest_Popup_Trigger_Parser` → `SPPopups_Trigger_Parser`
   - Package name in header

### Changes Summary

- **Constants:** 4 constants to rename
- **Classes:** 9 classes to rename
- **Functions:** 1 initialization function
- **Text Domain:** All `__()`, `_e()`, `_n()`, etc. calls
- **Package Names:** All `@package` docblocks

### Testing Checklist

- [ ] Plugin activates without errors
- [ ] No PHP fatal errors in error log
- [ ] Admin menu appears under Appearance
- [ ] Synced Patterns list page loads
- [ ] Frontend modal structure renders (even if not functional yet)

### Phase 1 Completion Notes

All core PHP class names, constants, and text domains have been updated:
- ✅ Main plugin file updated (constants, function name, class instantiation)
- ✅ All 9 class files updated (class names, package names, type hints)
- ✅ All text domains changed from `simplest-popup` to `sppopups`
- ✅ Ability category and ability names updated
- ✅ All class references updated throughout codebase

**Ready for testing!** Please test the plugin activation and basic functionality before proceeding to Phase 2.

### Notes

- Keep old class names in comments temporarily for reference
- This phase does NOT rename files yet
- This phase does NOT change CSS/JS class names yet

---

## Phase 2: PHP Functions, Hooks, and Filters ✅ COMPLETE

**Status:** ✅ Complete  
**Risk Level:** High  
**Testing Required:** All plugin functionality  
**Completed:** [Date will be updated when confirmed]

### Files to Modify

All PHP files in `includes/` directory:

1. **Function Names**
   - `simplest_popup_*` → `sppopups_*`
   - Filter/action hook names
   - Nonce names

2. **Filter Hooks**
   - `simplest_popup_cache_ttl` → `sppopups_cache_ttl`
   - `simplest_popup_force_load_assets` → `sppopups_force_load_assets`
   - `simplest_popup_has_triggers` → `sppopups_has_triggers`
   - `simplest_popup_widgets_have_triggers` → `sppopups_widgets_have_triggers`
   - `simplest_popup_can_access_pattern` → `sppopups_can_access_pattern`
   - `simplest_popup_the_content` → `sppopups_the_content`
   - `simplest_popup_rate_limit_requests` → `sppopups_rate_limit_requests`
   - `simplest_popup_rate_limit_window` → `sppopups_rate_limit_window`
   - `simplest_popup_max_pattern_id` → `sppopups_max_pattern_id`
   - `simplest_popup_editor_styles` → `sppopups_editor_styles`
   - `simplest_popup_editor_style_prefixes` → `sppopups_editor_style_prefixes`
   - `simplest_popup_ability_permission` → `sppopups_ability_permission`

3. **Action Hooks**
   - `simplest_popup_*` → `sppopups_*` (if any exist)

4. **Nonce Names**
   - `simplest_popup_support_metabox` → `sppopups_support_metabox`
   - `simplest_popup_support_nonce` → `sppopups_support_nonce`

5. **AJAX Actions**
   - `wp_ajax_simplest_popup_get_block` → `wp_ajax_sppopups_get_block`
   - `wp_ajax_nopriv_simplest_popup_get_block` → `wp_ajax_nopriv_sppopups_get_block`

6. **Post Meta Keys**
   - `_simplest_popup_support` → `_sppopups_support`

### Testing Checklist

- [ ] AJAX requests work (modal content loads)
- [ ] Cache operations work
- [ ] Admin metabox saves correctly
- [ ] All filters can be hooked by other plugins
- [ ] Abilities API still works

### Phase 2 Completion Notes

All PHP functions, hooks, filters, nonces, AJAX actions, and post meta keys have been updated:
- ✅ All 12 filter hooks renamed from `simplest_popup_*` to `sppopups_*`
- ✅ All `add_filter` calls for `simplest_popup_the_content` updated to `sppopups_the_content`
- ✅ AJAX actions renamed: `wp_ajax_simplest_popup_get_block` → `wp_ajax_sppopups_get_block`
- ✅ Nonce names updated: `simplest_popup_ajax`, `simplest_popup_support_metabox`, `simplest_popup_support_nonce` → `sppopups_*`
- ✅ Post meta key updated: `_simplest_popup_support` → `_sppopups_support`
- ✅ All form field names updated in metabox

**Ready for testing!** Please test AJAX functionality, cache operations, and admin metabox before proceeding to Phase 3.

---

## Phase 3: Cache Keys and Transients ✅ COMPLETE

**Status:** ✅ Complete  
**Risk Level:** Low  
**Testing Required:** Cache functionality  
**Completed:** [Date will be updated when confirmed]

### Files to Modify

1. **`includes/class-sppopups-cache.php`**
   - Cache key prefix: `simplest_popup_block_` → `sppopups_block_`
   - Cache group: `simplest_popup` → `sppopups`
   - Pattern cache key: `simplest_popup_pattern_` → `sppopups_pattern_`
   - Cache group for patterns: `simplest_popup_patterns` → `sppopups_patterns`

2. **`includes/class-sppopups-pattern.php`**
   - Pattern cache key references

3. **`includes/class-sppopups-plugin.php`**
   - Pattern cache key references

4. **`includes/class-sppopups-abilities.php`**
   - Pattern cache key references

### Testing Checklist

- [ ] Cache still works after clearing
- [ ] Old cache entries don't interfere
- [ ] New cache entries use new keys

### Notes

- Old cache entries will naturally expire
- Consider adding a one-time migration if needed

### Phase 3 Completion Notes

All cache keys, cache groups, and transient keys have been updated:
- ✅ Cache key prefix: `simplest_popup_block_` → `sppopups_block_`
- ✅ Cache group: `simplest_popup` → `sppopups`
- ✅ Pattern cache key: `simplest_popup_pattern_` → `sppopups_pattern_`
- ✅ Pattern cache group: `simplest_popup_patterns` → `sppopups_patterns`
- ✅ Rate limit transient: `simplest_popup_rate_limit_` → `sppopups_rate_limit_`
- ✅ Transient pattern in clear_all: `_transient_simplest_popup_block_%` → `_transient_sppopups_block_%`

**Ready for testing!** Please test cache operations to ensure everything works correctly before proceeding to Phase 4.

---

## Phase 4: JavaScript Variables and Localization ✅ COMPLETE

**Status:** ✅ Complete  
**Risk Level:** Medium  
**Testing Required:** Frontend functionality  
**Completed:** [Date will be updated when confirmed]

### Files to Modify

1. **`assets/js/modal.js`**
   - Variable: `simplestPopup` → `sppopups`
   - All references to `simplestPopup.*`
   - Console error messages

2. **`assets/js/admin.js`**
   - Any references to old names

3. **`includes/class-sppopups-plugin.php`**
   - `wp_localize_script()` handle: `simplest-popup` → `sppopups`
   - Variable name: `simplestPopup` → `sppopups`

4. **`includes/class-sppopups-admin.php`**
   - `wp_localize_script()` handle: `simplest-popup-admin` → `sppopups-admin`
   - Variable name: `simplestPopupAdmin` → `sppopupsAdmin`

### Testing Checklist

- [ ] Modal opens when trigger clicked
- [ ] AJAX content loads
- [ ] Admin copy-to-clipboard works
- [ ] No JavaScript console errors

### Phase 4 Completion Notes

All JavaScript variables and localization have been updated:
- ✅ Main JavaScript variable: `simplestPopup` → `sppopups` in `modal.js`
- ✅ Admin JavaScript variable: `simplestPopupAdmin` → `sppopupsAdmin` in `admin.js`
- ✅ `wp_localize_script()` variable name: `simplestPopup` → `sppopups` in `plugin.php`
- ✅ `wp_localize_script()` variable name: `simplestPopupAdmin` → `sppopupsAdmin` in `admin.php`
- ✅ All references to `simplestPopup.*` updated to `sppopups.*`
- ✅ All references to `simplestPopupAdmin.*` updated to `sppopupsAdmin.*`
- ✅ Console error messages updated to "Synced Pattern Popups"
- ✅ JavaScript file header comments updated

**Note:** Script handles (`simplest-popup-modal`, `simplest-popup-admin`) remain unchanged as they are internal identifiers and will be addressed in Phase 5/6 if needed.

**Ready for testing!** Please test frontend functionality (modal opening, AJAX loading) and admin functionality (copy-to-clipboard) before proceeding to Phase 5.

---

## Phase 5: CSS Class Names ✅ COMPLETE

**Status:** ✅ Complete  
**Risk Level:** Medium  
**Testing Required:** Modal styling and layout  
**Completed:** [Date will be updated when confirmed]

### Files to Modify

1. **`assets/css/modal.css`**
   - `.simplest-popup-*` → `.sppopups-*`
   - All class selectors

2. **`assets/css/admin.css`**
   - `.simplest-popup-*` → `.sppopups-*`

3. **`includes/class-sppopups-plugin.php`**
   - HTML output: All `simplest-popup-*` classes → `sppopups-*`
   - ID attributes: `simplest-popup-*` → `sppopups-*`

### Class Name Mappings

- `.simplest-popup-modal` → `.sppopups-modal`
- `.simplest-popup-overlay` → `.sppopups-overlay`
- `.simplest-popup-container` → `.sppopups-container`
- `.simplest-popup-card` → `.sppopups-card`
- `.simplest-popup-content` → `.sppopups-content`
- `.simplest-popup-close` → `.sppopups-close`
- `.simplest-popup-close-footer` → `.sppopups-close-footer`
- `.simplest-popup-footer` → `.sppopups-footer`
- `.simplest-popup-loading` → `.sppopups-loading`
- `.simplest-popup-spinner` → `.sppopups-spinner`
- `.simplest-popup-sr-only` → `.sppopups-sr-only`
- `.simplest-popup-table-wrapper` → `.sppopups-table-wrapper`
- `.simplest-popup-usage-instructions` → `.sppopups-usage-instructions`
- `#simplest-popup-modal` → `#sppopups-modal`
- `#simplest-popup-title` → `#sppopups-title`
- `#simplest-popup-description` → `#sppopups-description`

### Testing Checklist

- [ ] Modal displays correctly
- [ ] All styling intact
- [ ] Responsive design works
- [ ] Admin table styling works

### Phase 5 Completion Notes

All CSS class names and IDs have been updated:
- ✅ Modal CSS classes: `.simplest-popup-*` → `.sppopups-*` in `modal.css`
- ✅ Admin CSS classes: `.simplest-popup-*` → `.sppopups-*` in `admin.css`
- ✅ Animation keyframes: `@keyframes simplest-popup-*` → `@keyframes sppopups-*`
- ✅ Modal HTML IDs: `#simplest-popup-*` → `#sppopups-*` in `plugin.php`
- ✅ Modal HTML classes: All `simplest-popup-*` → `sppopups-*` in `plugin.php`
- ✅ Admin HTML classes: All `simplest-popup-*` → `sppopups-*` in `admin.php`
- ✅ JavaScript class/ID references: All `simplest-popup-*` → `sppopups-*` in `modal.js`
- ✅ Body class: `simplest-popup-open` → `sppopups-open`
- ✅ CSS file header comments updated

**Note:** Script handles (`simplest-popup-modal`, `simplest-popup-admin`) and page slugs (`simplest-popup-patterns`) remain unchanged as they are internal WordPress identifiers and will be addressed in Phase 6 if needed.

**Ready for testing!** Please test modal display, styling, responsive design, and admin table styling before proceeding to Phase 6.

---

## Phase 6: File Renames ✅ COMPLETE

**Status:** ✅ Complete  
**Risk Level:** High  
**Testing Required:** Full plugin functionality  
**Completed:** [Date will be updated when confirmed]

### Files to Rename

1. **Main Plugin File**
   - `simplest-popup.php` → `sppopups.php`

2. **Class Files** (in `includes/`)
   - `class-simplest-popup-plugin.php` → `class-sppopups-plugin.php`
   - `class-simplest-popup-pattern.php` → `class-sppopups-pattern.php`
   - `class-simplest-popup-cache.php` → `class-sppopups-cache.php`
   - `class-simplest-popup-style-collector.php` → `class-sppopups-asset-collector.php`
   - `class-simplest-popup-ajax.php` → `class-sppopups-ajax.php`
   - `class-simplest-popup-admin.php` → `class-sppopups-admin.php`
   - `class-simplest-popup-abilities.php` → `class-sppopups-abilities.php`
   - `class-simplest-popup-trigger-parser.php` → `class-sppopups-trigger-parser.php`

3. **Asset Files** (optional - can keep names)
   - `assets/css/modal.css` → (keep or rename to `sppopups-modal.css`)
   - `assets/css/admin.css` → (keep or rename to `sppopups-admin.css`)
   - `assets/js/modal.js` → (keep or rename to `sppopups-modal.js`)
   - `assets/js/admin.js` → (keep or rename to `sppopups-admin.js`)

### Files to Update (require paths)

1. **`sppopups.php`** (main file)
   - Update all `require_once` paths to new file names

### Testing Checklist

- [ ] Plugin deactivates old version
- [ ] Plugin activates new version
- [ ] All includes load correctly
- [ ] No file not found errors
- [ ] Full functionality works

### Notes

- WordPress will treat this as a new plugin
- Users will need to reactivate
- Consider adding activation notice

### Phase 6 Completion Notes

All files have been renamed successfully:
- ✅ Main plugin file: `simplest-popup.php` → `sppopups.php`
- ✅ Class files renamed in `includes/` directory:
  - `class-simplest-popup-plugin.php` → `class-sppopups-plugin.php`
  - `class-simplest-popup-pattern.php` → `class-sppopups-pattern.php`
  - `class-simplest-popup-cache.php` → `class-sppopups-cache.php`
  - `class-simplest-popup-style-collector.php` → `class-sppopups-asset-collector.php`
  - `class-simplest-popup-ajax.php` → `class-sppopups-ajax.php`
  - `class-simplest-popup-admin.php` → `class-sppopups-admin.php`
  - `class-simplest-popup-abilities.php` → `class-sppopups-abilities.php`
  - `class-simplest-popup-trigger-parser.php` → `class-sppopups-trigger-parser.php`
- ✅ All `require_once` statements updated in `sppopups.php` to reference new file names

**Note:** Asset files (CSS/JS) were kept with their original names as they are referenced by handles, not file paths. The plugin directory name (`simplest-popup`) remains unchanged - this is optional and can be renamed manually if desired, but WordPress identifies plugins by the main file name, not the directory.

**Important:** WordPress will treat this as a new plugin. Users will need to:
1. Deactivate the old "Simplest Popup" plugin
2. Activate the new "Synced Pattern Popups" plugin
3. All settings and data should be preserved (same database tables/meta keys)

**Ready for testing!** Please test full plugin functionality including activation, deactivation, and all features before considering the renaming complete.

---

## Phase 7: Documentation and Headers

**Status:** ⏸️ Pending  
**Risk Level:** Low  
**Testing Required:** Documentation accuracy

### Files to Modify

1. **`README.md`**
   - Plugin name references
   - Installation path references
   - All code examples

2. **`readme.txt`**
   - Plugin name
   - All references

3. **All PHP Files**
   - Plugin headers
   - Package names in docblocks
   - Author information (if needed)

### Testing Checklist

- [ ] README is accurate
- [ ] WordPress.org readme.txt format correct
- [ ] All code examples work

---

## Phase 8: Directory Rename (Final)

**Status:** ⏸️ Pending  
**Risk Level:** High  
**Testing Required:** Full system test

### Steps

1. Deactivate plugin
2. Rename directory: `simplest-popup` → `sppopups`
3. Reactivate plugin
4. Verify all paths work

### Testing Checklist

- [ ] Plugin directory renamed
- [ ] Plugin activates from new location
- [ ] All asset URLs correct
- [ ] No broken image paths
- [ ] Screenshots still accessible

### Notes

- This is the final step
- May require server file system access
- Backup recommended before this phase

---

## Migration Considerations

### Breaking Changes

- **Filter/action hooks:** Any third-party code using old hook names will break
- **CSS classes:** Any custom CSS targeting old classes will break
- **JavaScript:** Any custom JS using `simplestPopup` variable will break
- **Cache:** Old cache entries will naturally expire (12 hours default)

### Backward Compatibility

Consider adding:
- Deprecated function wrappers (if needed)
- Filter aliases for common hooks (optional)
- Migration notice for users

---

## Testing Strategy

After each phase:
1. Test basic functionality
2. Check error logs
3. Test frontend modal
4. Test admin interface
5. Update this document with status

---

## Rollback Plan

If issues arise:
1. Git revert the phase
2. Restore from backup
3. Document the issue
4. Revise plan if needed

---

**Last Updated:** Phase 6 Complete - Ready for Testing  
**Current Phase:** Phase 6 Complete - Awaiting User Testing Confirmation

