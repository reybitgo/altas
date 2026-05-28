# Reactivation Terms Checkbox Reference
## Phase 4 UI Enhancement

---

## Feature
Submit button on reactivation page is **disabled by default** and only becomes clickable when the user checks the terms checkbox.

## File Modified
`views/member/reactivate.php`

## Implementation

### HTML Changes
```html
<!-- Checkbox (already existed, added required attribute) -->
<div class="form-check">
  <input class="form-check-input" type="checkbox" id="termsCheck" required>
  <label class="form-check-label" for="termsCheck" style="font-size:.8rem;">
    I understand that reactivation resets my lifetime earnings counter 
    to zero and starts a new cycle. Previous earnings are retained but 
    do not count toward the new cap.
  </label>
</div>

<!-- Button: starts disabled, id added for JS targeting -->
<button type="submit" class="btn btn-primary w-100" id="reactivateBtn" disabled>
  🔄 Reactivate Account — ₱10,000.00
</button>
```

### JavaScript (inline, immediately invoked)
```javascript
(function () {
  const terms = document.getElementById('termsCheck');
  const btn   = document.getElementById('reactivateBtn');
  if (!terms || !btn) return;
  
  terms.addEventListener('change', function () {
    btn.disabled = !this.checked;
  });
})();
```

## Behavior
| Checkbox State | Button State |
|----------------|--------------|
| Unchecked (default) | Disabled (`disabled` attribute present) |
| Checked | Enabled (`disabled` attribute removed) |

## Reference Copy
- **Current implementation**: `temp/lifetime_capping/phase4/reactivate_v4.php`
- **Original before Phase 4**: `temp/lifetime_capping/phase4/reactivate_current.php`
