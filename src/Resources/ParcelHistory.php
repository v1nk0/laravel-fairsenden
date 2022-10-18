<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;

class ParcelHistory extends Resource
{
    public ?State $newState = null;

    public ?Carbon $modificationDate = null;

    protected static array $HAS_ONE = [
        'newState' => State::class,
    ];
}
