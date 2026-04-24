<?php

namespace Tests\Unit;

use App\Support\OperacaoDuration;
use PHPUnit\Framework\TestCase;

class OperacaoDurationTest extends TestCase
{
    public function test_it_calculates_same_day_duration_from_start_and_end(): void
    {
        $duration = OperacaoDuration::calculateFromStrings('2026-04-24 08:30', '2026-04-24 12:00');

        $this->assertSame(3.5, $duration);
    }

    public function test_it_caps_each_day_at_eight_hours(): void
    {
        $duration = OperacaoDuration::calculateFromStrings('2026-04-24 06:00', '2026-04-26 20:00');

        $this->assertSame(24.0, $duration);
    }

    public function test_it_counts_only_the_hours_that_fall_on_each_day_when_crossing_midnight(): void
    {
        $duration = OperacaoDuration::calculateFromStrings('2026-04-24 20:00', '2026-04-25 10:00');

        $this->assertSame(12.0, $duration);
    }
}
