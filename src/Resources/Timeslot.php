<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;

class Timeslot extends Resource
{
    public ?Carbon $start = null;

    public ?Carbon $end = null;

    public bool $is_available = false;
}
