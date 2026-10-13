<?php

declare(strict_types = 1);

if (!function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param DateTimeZone|string|null $tz
     * @return DateTime
     * @throws Exception
     */
    function now($tz = null)
    {
        $timezone = $tz ? new DateTimeZone($tz) : null;

        return new DateTime('now', $timezone);
    }
}
