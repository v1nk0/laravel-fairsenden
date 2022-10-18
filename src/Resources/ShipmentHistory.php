<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;

class ShipmentHistory extends Resource
{
    public ?Carbon $modificationDate = null;

    public ?State $new_state = null;

    protected static array $HAS_ONE = [
        'new_state' => State::class,
    ];
}
