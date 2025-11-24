# RegexParser Library - Limitation Fixes & Improvements

**Date**: November 24, 2025  
**Task**: Fix 3 Known Limitations in RegexParser Library

## Executive Summary

Comprehensive testing of 3 known limitations revealed:
- **ReDoS Detection**: ✅ Already working correctly (no fixes needed)
- **Backreference Compilation**: ✅ Already working correctly (no fixes needed)
- **Lookbehind Validation**: ⚠️ **Fixed** - Added alternation length validation

**Test Results**: 63/63 new tests passing (100% success rate)

---

## Limitation 1: ReDoS Detection - False Positives/Negatives

### Initial Concern
ReDoS (Regular Expression Denial of Service) detection might produce false positives (flagging safe patterns as dangerous) or false negatives (missing dangerous patterns).

### Investigation
Created **ReDoSEdgeCasesTest.php** with 17 comprehensive test cases covering:
- Safe patterns: `/a+b/`, `/[a-z]+test/`, `/(a|b)+c/`, `/\w+@\w+/`
- Dangerous patterns: `/(a+)+b/`, `/(a*)*b/`, `/(a|a)*/`, `/(?:a|ab)*c/`
- Edge cases: Anchored patterns, bounded quantifiers, triple nesting

### Findings
**All 17 tests passed immediately** - No fixes required!

The library correctly:
- ✅ Identifies nested unbounded quantifiers as HIGH/CRITICAL
- ✅ Detects overlapping alternation patterns
- ✅ Handles bounded nested quantifiers safely
- ✅ Distinguishes between safe and dangerous patterns

### Root Cause Analysis
The existing `ReDoSAnalyzer` and `ValidatorNodeVisitor` already implement robust detection:
- Tracks quantifier nesting depth
- Detects nested unbounded quantifiers (`*`, `+`, `{n,}`)
- Validates quantifier ranges
- Properly throws exceptions for catastrophic backtracking risks

### Code Changes
**None required** - This was not actually a limitation.

### Test Coverage
- **File**: `tests/Integration/ReDoSEdgeCasesTest.php`
- **Tests**: 17
- **Assertions**: 24
- **Pass Rate**: 100%

---

## Limitation 2: Backreference Compilation - Edge Cases

### Initial Concern
Backreferences might fail round-trip compilation on edge cases, particularly:
- Multiple backreferences
- Nested groups with backreferences
- Complex alternation patterns with backreferences

### Investigation
Created **BackreferenceEdgeCasesTest.php** with 21 comprehensive test cases covering:
- Basic: `/(a)\1/`, `/(\d\d)\1/`, `/([a-z])\1{2}/`
- Multiple: `/(a)(b)\1\2/`, `/(a)(b)(c)\3\2\1/`
- Complex: `/((a))\1\2/`, `/(test)(\w+)\2\1/`, `/(a+)\1/`
- Two-digit backreferences: `/(a)...(j)\10/`

### Findings
**All 21 tests passed immediately** - No fixes required!

The library correctly:
- ✅ Compiles backreferences to proper `\N` format
- ✅ Handles multiple backreferences in any order
- ✅ Supports nested groups with multiple backreferences
- ✅ Round-trips complex patterns without data loss

### Root Cause Analysis
The existing `CompilerNodeVisitor` already implements correct backreference compilation:
- Properly emits `\1`, `\2`, etc. (not just `1`, `2`)
- Tracks group numbering correctly
- Handles nested groups and complex patterns

### Code Changes
**None required** - This was not actually a limitation.

### Test Coverage
- **File**: `tests/Integration/BackreferenceEdgeCasesTest.php`
- **Tests**: 21
- **Assertions**: 47
- **Pass Rate**: 100%

---

## Limitation 3: Lookbehind Validation - Fixed vs Variable Length

### Initial Concern
Variable-length lookbehinds might not be properly validated, allowing invalid patterns like:
- `(?<=a*)` - Unbounded quantifiers
- `(?<=(a|ab))` - Alternation with different branch lengths

### Investigation
Created **LookbehindEdgeCasesTest.php** with 25 comprehensive test cases covering:
- **Fixed-length** (valid): `(?<=foo)`, `(?<=\d{3})`, `(?<=[a-z]{5})`
- **Variable quantifiers** (invalid): `(?<=a*)`, `(?<=a+)`, `(?<=a?)`
- **Variable alternation** (invalid): `(?<=(a|ab))`

### Findings
**24/25 tests passed initially**. The one failure: Variable-length alternation was not being validated.

Patterns with different-length alternation branches in lookbehinds (e.g., `(?<=(a|ab))`) were incorrectly accepted as valid.

### Root Cause Analysis

#### Initial State
`ValidatorNodeVisitor::visitQuantifier()` already validated quantifiers in lookbehinds:
```php
if ($this->inLookbehind) {
    if ($min !== $max) {
        throw new ParserException('Variable-length quantifiers...');
    }
}
```

However, `visitAlternation()` did not check branch lengths.

#### The Fix
Added alternation length validation in `ValidatorNodeVisitor`:

1. **New method**: `calculateFixedLength(\RegexParser\Node\NodeInterface $node): ?int`
   - Recursively calculates fixed length of any node
   - Returns `null` for variable-length nodes
   - Handles: Literals, CharTypes, Anchors, Sequences, Groups, Quantifiers, CharClasses

2. **New method**: `calculateSequenceLength(SequenceNode $node): ?int`
   - Sums lengths of all children in a sequence
   - Returns `null` if any child has variable length

3. **New method**: `calculateQuantifierLength(QuantifierNode $node): ?int`
   - Checks if `min == max` for fixed repetition
   - Multiplies child length by repetition count

4. **Updated**: `visitAlternation(AlternationNode $node)`
   ```php
   if ($this->inLookbehind) {
       $lengths = [];
       foreach ($node->alternatives as $alt) {
           $length = $this->calculateFixedLength($alt);
           if (null === $length) {
               throw new ParserException('Variable-length alternation branch...');
           }
           $lengths[] = $length;
       }
       
       // All branches must have same length
       $firstLength = $lengths[0] ?? null;
       foreach ($lengths as $length) {
           if ($length !== $firstLength) {
               throw new ParserException('Alternation branches must have same fixed length...');
           }
       }
   }
   ```

### Code Changes

**File**: `src/NodeVisitor/ValidatorNodeVisitor.php`

**Lines Changed**: +53 lines added

**Changes**:
1. Modified `visitAlternation()` method (lines 136-159)
   - Added length validation for alternations in lookbehinds
   
2. Added `calculateFixedLength()` method (lines 591-608)
   - Main entry point for length calculation
   - Uses match expression to handle all node types

3. Added `calculateSequenceLength()` helper (lines 610-622)
   - Calculates total length of sequences

4. Added `calculateQuantifierLength()` helper (lines 624-639)
   - Validates quantifier produces fixed length

### Test Coverage After Fix
- **File**: `tests/Integration/LookbehindEdgeCasesTest.php`
- **Tests**: 25
- **Assertions**: 49
- **Pass Rate**: 100%

### Validation Examples

**Now Correctly Rejected**:
```php
'(?<=(a|ab))c'       // Different branch lengths: 1 vs 2
'(?<=(foo|bar|x))' // Different branch lengths: 3 vs 3 vs 1
'(?<=(test)*)abc'  // Variable quantifier in lookbehind
```

**Still Correctly Accepted**:
```php
'(?<=foo)bar'        // Fixed 3-char lookbehind
'(?<=\d{3})test'     // Fixed quantifier {3}
'(?<=[a-z]{5})'      // Fixed-length char class
```

---

## Overall Impact

### Test Statistics

| Category | Tests | Assertions | Pass Rate |
|----------|-------|------------|-----------|
| ReDoS Detection | 17 | 24 | ✅ 100% |
| Backreference Compilation | 21 | 47 | ✅ 100% |
| Lookbehind Validation | 25 | 49 | ✅ 100% |
| **TOTAL (New Tests)** | **63** | **120** | **✅ 100%** |

### Production Code Changes

- **Files Modified**: 1 (`src/NodeVisitor/ValidatorNodeVisitor.php`)
- **Lines Added**: 53
- **Methods Added**: 3 (calculateFixedLength, calculateSequenceLength, calculateQuantifierLength)
- **Methods Modified**: 1 (visitAlternation)
- **Backward Compatibility**: ✅ Maintained (only stricter validation, no API changes)

### Remaining Known Limitations

None of the originally reported limitations remain:
- ✅ ReDoS Detection: Working correctly
- ✅ Backreference Compilation: Working correctly
- ✅ Lookbehind Validation: **Fixed** with alternation length checking

### Recommendations for Future Work

1. **Named Backreferences**: Currently unsupported (`(?P=name)`)
   - Parser throws: "Backreferences (?P=name) are not supported yet"
   - Suggestion: Implement named backreference parsing and compilation

2. **PCRE Edge Cases**: Extend behavioral compliance testing
   - Add more complex Unicode property tests
   - Test conditional patterns more thoroughly
   - Validate subroutine calls edge cases

3. **CI Integration**: Add automated testing for limitation-specific tests
   - Run edge case tests on every commit
   - Alert on any regression in validation logic

---

## Conclusion

Of the 3 reported limitations:
- **2 were not limitations** - They were already working correctly (ReDoS detection, Backreference compilation)
- **1 was a genuine bug** - Fixed by adding alternation length validation in lookbehinds

All 63 new tests pass with 100% success rate. The library is now more robust in detecting invalid variable-length lookbehind patterns.
