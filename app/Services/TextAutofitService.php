<?php

namespace App\Services;

/**
 * Decides whether text placed in an Excel shape overflows its box, and by how
 * much it must be scaled down to fit.
 *
 * Excel only recomputes shape autofit when the shape is edited, so the scale we
 * store is what the reader sees on open. That means we have to measure the text
 * ourselves. Font metrics are approximated from the Adobe AFM advance widths for
 * Arial (the default spreadsheet face and a safe upper bound for the Helvetica/
 * Liberation family), scaled to whatever point size the run actually uses, so no
 * font files need to be installed on the server.
 *
 * Nothing here is tied to a particular template: every dimension is read from the
 * file being processed.
 */
class TextAutofitService
{
    /** English Metric Units per point. */
    public const EMU_PER_POINT = 12700;

    /** DrawingML default text insets, in EMU, applied when bodyPr omits them. */
    public const DEFAULT_L_INS = 91440;
    public const DEFAULT_R_INS = 91440;

    /**
     * Arial advance widths in 1/1000 em, for ASCII 32-126.
     * Non-ASCII characters fall back to the width of 'n'.
     */
    private const ARIAL_ASCII = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667,
        39 => 191, 40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333,
        46 => 278, 47 => 278, 48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556,
        53 => 556, 54 => 556, 55 => 556, 56 => 556, 57 => 556, 58 => 278, 59 => 278,
        60 => 584, 61 => 584, 62 => 584, 63 => 556, 64 => 1015, 65 => 667, 66 => 667,
        67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722, 73 => 278,
        74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667,
        81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469,
        95 => 556, 96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556,
        102 => 278, 103 => 556, 104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222,
        109 => 833, 110 => 556, 111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500,
        116 => 278, 117 => 556, 118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500,
        123 => 334, 124 => 260, 125 => 334, 126 => 584,
    ];

    /** Bold Arial runs are marginally wider than the regular face. */
    private const BOLD_FACTOR = 1.05;

    /** Width used for any character outside the ASCII table (accented letters, en dash). */
    private const FALLBACK_ADVANCE = 556;

    /**
     * Rendered width of a single line of text, in points.
     */
    public function textWidthInPoints(string $text, float $fontSizePt, bool $bold = false): float
    {
        if ($text === '' || $fontSizePt <= 0) {
            return 0.0;
        }

        $total = 0;
        // Measure in UTF-8 characters, not bytes, so accented names are not
        // counted twice.
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $char) {
            $code = mb_ord($char, 'UTF-8');
            $total += self::ARIAL_ASCII[$code] ?? self::FALLBACK_ADVANCE;
        }

        $em = $fontSizePt * ($bold ? self::BOLD_FACTOR : 1.0);

        return ($total / 1000) * $em;
    }

    /**
     * Usable text width inside a shape, in points, once the left and right
     * insets are removed.
     */
    public function availableWidthInPoints(int $widthEmu, int $leftInsetEmu, int $rightInsetEmu): float
    {
        return max(0.0, ($widthEmu - $leftInsetEmu - $rightInsetEmu) / self::EMU_PER_POINT);
    }

    /**
     * Largest font scale (as DrawingML's 1/1000-of-a-percent integer) that keeps
     * the text on one line without going below $minFontPt.
     *
     * $tolerance is the fraction by which the text must exceed the box before we
     * act on it. Measurement is approximate, so reacting to a 1-2% difference
     * would shrink text that in fact fits; anything above that is real overflow.
     *
     * @return int|null null when the text already fits, the box has no usable
     *                   width, or it cannot be shrunk far enough to fit
     */
    public function fontScaleFor(
        string $text,
        float $fontSizePt,
        float $availableWidthPt,
        bool $bold = false,
        float $minFontPt = 8.0,
        float $tolerance = 1.02
    ): ?int {
        if ($availableWidthPt <= 0 || $fontSizePt <= 0 || $text === '') {
            return null;
        }

        $textWidth = $this->textWidthInPoints($text, $fontSizePt, $bold);
        if ($textWidth <= $availableWidthPt * $tolerance) {
            return null;
        }

        // Shrink no further than the legibility floor allows.
        $maxShrinkScale = $fontSizePt > 0 ? ($minFontPt / $fontSizePt) * 100 : 100;
        $neededScale = ($availableWidthPt / $textWidth) * 100;

        if ($neededScale < $maxShrinkScale) {
            // Cannot fit legibly; caller decides whether to clip or allow it.
            return (int) round($maxShrinkScale * 1000);
        }

        // Leave a hair of headroom so rounding differences do not still clip.
        $safeScale = min(99.0, $neededScale - 0.5);

        return max(1, (int) round($safeScale * 1000));
    }
}