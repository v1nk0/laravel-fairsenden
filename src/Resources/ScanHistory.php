<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;

class ScanHistory extends Resource
{
    public ?string $scanDescription = null;

    public ?Carbon $modificationDate = null;

    public ?string $fairsendenId = null;

    public ?string $depotId = null;
}
