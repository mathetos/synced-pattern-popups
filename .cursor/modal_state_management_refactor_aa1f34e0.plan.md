---
name: Modal State Management Refactor
overview: Refactor scattered modal state variables into a centralized state object with getters/setters, implemented in phases with full testing between each step to ensure no breakage.
todos: []
---

# Modal State Management Refactor Plan

## Overview

Refactor 8+ scattered global state variables in `modal.js` into a centralized `modalState` object with getters/setters, validation, and better organization. This is a **large effort** with **medium risk**, implemented in **6 phases** with validation and testing between each step.

## Current State Variables

Located in [`wp-content/plugins/sppopups/assets/js/modal.js`](wp-content/plugins/sppopups/assets/js/modal.js) (lines 33-52):

- `currentMaxWidth` - Modal width setting (used in 5 places)
- `lastActiveElement` - Focus restoration (used in 3 places)
- `savedScrollPosition` - Scroll restoration (used in 3 places)
- `hiddenBackgroundElements` - Accessibility state (used in 3 places)
- `loadedStyles` - Asset tracking Set (used in 4 places)
- `loadedScripts` - Asset tracking Set (used in 5 places)
- `titleElement` - DOM cache (used in 2 places)
- `modalContainer` - DOM cache (used in 1 place, passed to gallery)

**Total: 39 references** across the file that need updating.

## Phase 1: Create State Object (Non-Breaking)

**Goal:** Create `modalState` object alongside existing variables. Both systems work in parallel.

### Implementation

1. **Create state object** after line 52 in `modal.js`:
   ```javascript
   // Modal state object (new centralized state management)
   var modalState = {
     // Core state
     isOpen: false,
     mode: 'pattern', // 'pattern', 'gallery', 'tldr'
     
     // Dimensions
     maxWidth: null,
     
     // Focus and scroll
     lastActiveElement: null,
     savedScrollPosition: 0,
     
     // Accessibility
     hiddenBackgroundElements: [],
     
     // Asset tracking
     loadedStyles: new Set(),
     loadedScripts: new Set(),
     
     // DOM caches
     titleElement: null,
     modalContainer: null,
     
     // Getters
     getMaxWidth: function() { return this.maxWidth; },
     isGalleryMode: function() { return this.mode === 'gallery'; },
     
     // Setters (with validation)
     setMaxWidth: function(width) {
       if (width !== null && (typeof width !== 'number' || width < 100)) {
         console.warn('Synced Pattern Popups: Invalid maxWidth:', width);
         return false;
       }
       this.maxWidth = width;
       return true;
     },
     
     // State snapshot for debugging
     snapshot: function() {
       return {
         isOpen: this.isOpen,
         mode: this.mode,
         maxWidth: this.maxWidth,
         savedScrollPosition: this.savedScrollPosition,
         hiddenBackgroundElementsCount: this.hiddenBackgroundElements.length,
         loadedStylesCount: this.loadedStyles.size,
         loadedScriptsCount: this.loadedScripts.size
       };
     },
     
     // Reset all state
     reset: function() {
       this.isOpen = false;
       this.mode = 'pattern';
       this.maxWidth = null;
       this.lastActiveElement = null;
       this.savedScrollPosition = 0;
       this.hiddenBackgroundElements = [];
       // Note: loadedStyles and loadedScripts are NOT reset (persist across modals)
       // Note: titleElement and modalContainer are NOT reset (cached DOM elements)
     }
   };
   ```

2. **Add sync functions** to keep state object in sync with existing variables:
   ```javascript
   // Sync functions to keep state object in sync with existing variables
   function syncStateToObject() {
     modalState.maxWidth = currentMaxWidth;
     modalState.lastActiveElement = lastActiveElement;
     modalState.savedScrollPosition = savedScrollPosition;
     modalState.hiddenBackgroundElements = hiddenBackgroundElements;
     // Note: loadedStyles/loadedScripts Sets are shared references
   }
   
   function syncStateFromObject() {
     currentMaxWidth = modalState.maxWidth;
     lastActiveElement = modalState.lastActiveElement;
     savedScrollPosition = modalState.savedScrollPosition;
     hiddenBackgroundElements = modalState.hiddenBackgroundElements;
   }
   ```

3. **Add debug helper** (optional, for validation):
   ```javascript
   // Debug helper to compare state object vs variables
   function validateStateSync() {
     var mismatches = [];
     if (modalState.maxWidth !== currentMaxWidth) mismatches.push('maxWidth');
     if (modalState.lastActiveElement !== lastActiveElement) mismatches.push('lastActiveElement');
     if (modalState.savedScrollPosition !== savedScrollPosition) mismatches.push('savedScrollPosition');
     if (modalState.hiddenBackgroundElements !== hiddenBackgroundElements) mismatches.push('hiddenBackgroundElements');
     if (mismatches.length > 0) {
       console.warn('Synced Pattern Popups: State sync mismatch:', mismatches);
       return false;
     }
     return true;
   }
   ```


### Validation

- **Manual Testing:**

  1. Open browser console
  2. Run: `window.sppopupsModalState.snapshot()` (if exposed for testing)
  3. Verify state object exists and has correct structure
  4. Test that existing functionality still works (no changes to behavior)

- **Functional Testing Checklist:**
  - [ ] Regular pattern popup opens/closes
  - [ ] Gallery popup opens/closes
  - [ ] TLDR modal opens/closes
  - [ ] Focus restoration works
  - [ ] Scroll restoration works
  - [ ] Modal resize works
  - [ ] No console errors

### Success Criteria

- State object created and accessible
- All existing functionality works unchanged
- No console errors
- State object structure matches design

---

## Phase 2: Migrate Simple State Variables (Read-Only First)

**Goal:** Migrate `titleElement` and `modalContainer` (simplest, read-only DOM caches).

### Implementation

1. **Update `getTitleElement()` function** (line 603):
   ```javascript
   function getTitleElement() {
     if (!modalState.titleElement || !modal.contains(modalState.titleElement)) {
       modalState.titleElement = modal.querySelector('#sppopups-title');
     }
     return modalState.titleElement;
   }
   ```


Remove: `titleElement` variable (line 51)

2. **Update gallery module initialization** (line 1164):
   ```javascript
   modalContainer: modalState.modalContainer // Will be cached by gallery module
   ```


Remove: `modalContainer` variable (line 52)

3. **Update gallery module** to use `modalState.modalContainer` if it accesses it directly (check `gallery.js`).

### Validation

- **Manual Testing:**

  1. Open modal, check console: `modalState.titleElement` should be set
  2. Open gallery modal, check: `modalState.modalContainer` should be set

- **Functional Testing Checklist:**
  - [ ] Regular pattern popup opens/closes
  - [ ] Gallery popup opens/closes
  - [ ] Modal title displays correctly
  - [ ] Gallery modal container works
  - [ ] No console errors

### Success Criteria

- `titleElement` and `modalContainer` migrated to state object
- Old variables removed
- All functionality works
- No console errors

---

## Phase 3: Migrate Asset Tracking (Sets)

**Goal:** Migrate `loadedStyles` and `loadedScripts` Sets to state object.

### Implementation

1. **Update `injectStyles()` function** (lines 250, 260, 304, 696, 705):

   - Replace `loadedStyles.has()` with `modalState.loadedStyles.has()`
   - Replace `loadedStyles.add()` with `modalState.loadedStyles.add()`
   - Remove: `loadedStyles` variable (line 45)

2. **Update `injectScripts()` function** (lines 357, 367, 414, 441):

   - Replace `loadedScripts.has()` with `modalState.loadedScripts.has()`
   - Replace `loadedScripts.add()` with `modalState.loadedScripts.add()`
   - Remove: `loadedScripts` variable (line 48)

### Validation

- **Manual Testing:**

  1. Open multiple modals with same pattern
  2. Check console: `modalState.loadedStyles.size` and `modalState.loadedScripts.size` should increment
  3. Verify assets are not duplicated (check Network tab)

- **Functional Testing Checklist:**
  - [ ] Open pattern popup - assets load
  - [ ] Open same pattern again - assets NOT reloaded (cached)
  - [ ] Open different pattern - new assets load
  - [ ] Gallery modal - assets load correctly
  - [ ] No duplicate asset loading
  - [ ] No console errors

### Success Criteria

- Asset tracking Sets migrated to state object
- Old variables removed
- Asset caching still works (no duplicates)
- All functionality works

---

## Phase 4: Migrate Core Modal State (MaxWidth, Focus, Scroll)

**Goal:** Migrate `currentMaxWidth`, `lastActiveElement`, and `savedScrollPosition`.

### Implementation

1. **Update `setupModalState()` function** (lines 552-596):
   ```javascript
   function setupModalState(maxWidth, loadingContent) {
     // Save scroll position
     modalState.savedScrollPosition = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
     
     // Store active element
     modalState.lastActiveElement = document.activeElement;
     
     // Store max-width (with validation)
     modalState.setMaxWidth(maxWidth);
     
     // Update isOpen and mode
     modalState.isOpen = true;
     modalState.mode = maxWidth ? 'gallery' : 'pattern'; // Simple heuristic, can refine
     
     // Calculate and apply max-width
     if (maxWidth !== null) {
       var calculatedWidth = calculateMaxWidth(maxWidth);
       container.style.maxWidth = calculatedWidth + 'px';
     } else {
       container.style.maxWidth = '';
     }
     
     // Use savedScrollPosition from state
     body.style.top = '-' + modalState.savedScrollPosition + 'px';
     
     // ... rest of function unchanged ...
   }
   ```


Remove direct assignments to old variables.

2. **Update `closeModal()` function** (lines 761-829):
   ```javascript
   function closeModal() {
     // ... existing code ...
     
     // Reset max-width
     container.style.maxWidth = '';
     modalState.setMaxWidth(null);
     
     // ... existing code ...
     
     // Restore scroll position from state
     if (modalState.savedScrollPosition > 0) {
       window.scrollTo(0, modalState.savedScrollPosition);
     }
     
     // ... existing code ...
     
     // Restore focus from state
     if (modalState.lastActiveElement && document.body.contains(modalState.lastActiveElement)) {
       focusWithoutScroll(modalState.lastActiveElement);
     }
     
     // Reset state
     modalState.reset();
     
     // Clear old variables (temporary, for validation)
     currentMaxWidth = null;
     lastActiveElement = null;
     savedScrollPosition = 0;
   }
   ```

3. **Update resize handler** (line 1138):
   ```javascript
   if (modal.classList.contains('active') && modalState.getMaxWidth() !== null) {
     var calculatedWidth = calculateMaxWidth(modalState.getMaxWidth());
     // ... rest unchanged
   }
   ```

4. **Update gallery module dependency** (line 1161):
   ```javascript
   setMaxWidth: function(width) {
     modalState.setMaxWidth(width);
   },
   ```

5. **Remove old variables** (lines 33, 36, 42):

   - Remove: `var currentMaxWidth = null;`
   - Remove: `var lastActiveElement = null;`
   - Remove: `var savedScrollPosition = 0;`

### Validation

- **Manual Testing:**

  1. Open modal, check: `modalState.snapshot()` shows correct values
  2. Resize window, verify modal resizes correctly
  3. Close modal, verify focus and scroll restore

- **Functional Testing Checklist:**
  - [ ] Regular pattern popup opens/closes
  - [ ] Focus restoration works (returns to trigger element)
  - [ ] Scroll restoration works (returns to scroll position)
  - [ ] Gallery modal opens with correct width
  - [ ] Window resize handler works
  - [ ] Modal closes correctly
  - [ ] No console errors

### Success Criteria

- Core state variables migrated
- Old variables removed
- Focus and scroll restoration work
- Modal resize works
- All functionality works

---

## Phase 5: Migrate Accessibility State

**Goal:** Migrate `hiddenBackgroundElements` array.

### Implementation

1. **Update `hideBackgroundFromAT()` function** (lines 502-529):
   ```javascript
   function hideBackgroundFromAT() {
     // Clear any previously hidden elements
     modalState.hiddenBackgroundElements = [];
     
     // ... existing iteration code ...
     
     // Push to state object
     modalState.hiddenBackgroundElements.push(element);
   }
   ```

2. **Update `showBackgroundToAT()` function** (lines 534-544):
   ```javascript
   function showBackgroundToAT() {
     // Use state object
     modalState.hiddenBackgroundElements.forEach(function(element) {
       // ... existing code ...
     });
     
     modalState.hiddenBackgroundElements = [];
   }
   ```

3. **Remove old variable** (line 39):

   - Remove: `var hiddenBackgroundElements = [];`

### Validation

- **Manual Testing:**

  1. Open modal, check: `modalState.hiddenBackgroundElements.length` > 0
  2. Close modal, check: `modalState.hiddenBackgroundElements.length` === 0
  3. Test with screen reader (if available)

- **Functional Testing Checklist:**
  - [ ] Modal opens - background elements hidden from AT
  - [ ] Modal closes - background elements restored to AT
  - [ ] Multiple modals - state resets correctly
  - [ ] No console errors
  - [ ] Accessibility attributes work correctly

### Success Criteria

- `hiddenBackgroundElements` migrated to state object
- Old variable removed
- Accessibility features work
- All functionality works

---

## Phase 6: Final Cleanup and Enhancement

**Goal:** Remove sync functions, add state validation, enhance getters/setters.

### Implementation

1. **Remove sync functions** (from Phase 1):

   - Remove `syncStateToObject()`
   - Remove `syncStateFromObject()`
   - Remove `validateStateSync()` (or keep as optional debug tool)

2. **Enhance state object** with better mode detection:
   ```javascript
   // Update setupModalState to set mode correctly
   function setupModalState(maxWidth, loadingContent) {
     // ... existing code ...
     
     // Set mode based on context (can be enhanced)
     modalState.mode = maxWidth ? 'gallery' : 'pattern';
     modalState.isOpen = true;
     
     // ... rest unchanged ...
   }
   ```

3. **Add state validation** to setters:
   ```javascript
   setMaxWidth: function(width) {
     if (width !== null && (typeof width !== 'number' || width < 100 || width > 5000)) {
       console.warn('Synced Pattern Popups: Invalid maxWidth:', width, '(must be 100-5000)');
       return false;
     }
     this.maxWidth = width;
     return true;
   },
   ```

4. **Add JSDoc comments** to state object methods.

5. **Optional: Expose state for debugging** (development only):
   ```javascript
   // Expose for debugging (remove in production or guard with flag)
   if (typeof window !== 'undefined' && window.console) {
     window.sppopupsModalState = modalState;
   }
   ```


### Validation

- **Manual Testing:**

  1. Check console: no sync function references
  2. Test state validation: try invalid maxWidth, verify warning
  3. Test state snapshot: `modalState.snapshot()` works

- **Functional Testing Checklist:**
  - [ ] All previous functionality still works
  - [ ] State validation works (invalid values rejected)
  - [ ] State snapshot works for debugging
  - [ ] No console errors
  - [ ] Code is cleaner and more maintainable

### Success Criteria

- All old variables removed
- Sync functions removed
- State validation working
- Code is cleaner and more maintainable
- All functionality works perfectly

---

## Regression Testing Checklist (After Each Phase)

After **each phase**, test all plugin features:

### Core Modal Features

- [ ] Regular pattern popup opens via link click
- [ ] Modal closes via X button
- [ ] Modal closes via footer Close button
- [ ] Modal closes via overlay click
- [ ] Modal closes via Escape key
- [ ] Focus restoration works (returns to trigger element)
- [ ] Scroll restoration works (returns to scroll position)
- [ ] Modal displays content correctly
- [ ] Loading spinner shows during AJAX

### Gallery Features

- [ ] Gallery popup opens via image click
- [ ] Gallery navigation (prev/next) works
- [ ] Gallery modal respects modal size setting
- [ ] Gallery captions display correctly
- [ ] Gallery close buttons work (icon/button/both)
- [ ] Gallery image navigation works (on image/in footer/both)
- [ ] Gallery transitions are smooth
- [ ] Gallery keyboard navigation works

### Advanced Features

- [ ] TLDR modal opens/closes
- [ ] Multiple modals in sequence work correctly
- [ ] Window resize handler works
- [ ] Asset caching works (no duplicate loads)
- [ ] Accessibility features work (ARIA, focus trap)
- [ ] No console errors or warnings

### Performance

- [ ] No memory leaks (open/close multiple modals)
- [ ] State resets correctly between modals
- [ ] Asset tracking prevents duplicates

---

## Rollback Plan

If any phase causes issues:

1. **Immediate:** Revert the phase's changes via git
2. **Investigation:** Check console for errors, test specific feature
3. **Fix:** Address issue in that phase before proceeding
4. **Re-test:** Full regression test before continuing

Each phase is designed to be **independently revertible** without affecting other phases.

---

## Success Metrics

- **Code Quality:** State management is centralized and organized
- **Maintainability:** Easier to add new state variables
- **Debugging:** State snapshots available for troubleshooting
- **Validation:** Invalid state prevented
- **Testing:** Easier to test state transitions
- **Future:** Foundation for state history/undo features

---

## Estimated Timeline

- **Phase 1:** 30 minutes (create object, no behavior change)
- **Phase 2:** 15 minutes (simple DOM caches)
- **Phase 3:** 20 minutes (asset tracking)
- **Phase 4:** 45 minutes (core state, most complex)
- **Phase 5:** 20 minutes (accessibility state)
- **Phase 6:** 30 minutes (cleanup and enhancement)

**Total:** ~2.5 hours + testing time between phases

---

## Notes

- Each phase maintains **backward compatibility** until old variables are removed
- State object uses **shared references** for Sets (loadedStyles/loadedScripts) to avoid breaking existing code
- `reset()` method does **NOT** reset asset tracking Sets (they persist across modals by design)
- Mode detection can be enhanced later (currently simple heuristic)
- State validation can be expanded as needed
- Debug exposure is optional and can be removed/guarded for production