{{-- Issue #226: the month-over-month badge used to hardcode color and arrow
     per tile (fa-arrow-down/bg-danger on Chờ duyệt, bg-success/fa-arrow-up on
     the rest), so a +100% pending surge rendered as a downward red arrow and
     a -50% rejected drop as an upward green one — the glyph contradicting
     the number beside it. Derive both from the delta's sign and the tile's
     semantics: goodWhenUp says whether growth is good news for that metric. --}}
@php
    $delta = (int) $delta;
    $movingUp = $delta > 0;
    if ($delta === 0) {
        // A number not moving is neither good nor bad: grey, no direction.
        $color = 'secondary';
        $icon = 'fa-minus';
    } else {
        $color = $movingUp === $goodWhenUp ? 'success' : 'danger';
        $icon = $movingUp ? 'fa-arrow-up' : 'fa-arrow-down';
    }
@endphp
<span class="badge bg-{{ $color }} bg-opacity-10 text-{{ $color }}">
    <i class="fas {{ $icon }} me-1"></i> {{ $delta }}%
</span>
