<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;

class Delivery extends Resource
{
    public ?string $deliveryEvent = null;

    public ?Carbon $deliveryTime = null;

    public ?string $pictureName = null;

    public ?string $signatureName = null;

    public ?string $reason = null;

    public ?Coordinates $geocoordinate = null;
}
