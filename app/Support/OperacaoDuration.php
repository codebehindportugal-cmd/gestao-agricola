<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class OperacaoDuration
{
    public const MAX_HOURS_PER_DAY = 8;

    public static function calculate(?CarbonInterface $start, ?CarbonInterface $end): ?float
    {
        if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
            return null;
        }

        $cursor = $start->copy();
        $total = 0.0;

        while ($cursor->lessThan($end)) {
            $dayEnd = $cursor->copy()->endOfDay();
            $segmentEnd = $end->lessThan($dayEnd) ? $end->copy() : $dayEnd;

            $hours = ($segmentEnd->getTimestamp() - $cursor->getTimestamp()) / 3600;

            $total += min($hours, self::MAX_HOURS_PER_DAY);
            $cursor = $segmentEnd->isSameDay($cursor) && $segmentEnd->isSameDay($dayEnd)
                ? $cursor->copy()->addDay()->startOfDay()
                : $segmentEnd->copy();
        }

        return round($total, 2);
    }

    public static function calculateFromStrings(?string $start, ?string $end): ?float
    {
        if (! $start || ! $end) {
            return null;
        }

        return self::calculate(Carbon::parse($start), Carbon::parse($end));
    }
}
