<?php

declare(strict_types = 1);

if (!function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param DateTimeZone|string|null $tz
     *
     * @return Carbon\Carbon
     */
    function now($tz = null)
    {
        return Carbon\Carbon::now($tz);
    }
}
