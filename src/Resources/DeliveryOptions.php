<?php

namespace V1nk0\LaravelFairsenden\Resources;

class DeliveryOptions extends Resource
{
    public ?string $pin = null;

    public bool $neighbourAllowed = false;

    public bool $storageLocationAllowed = false;

    public bool $signatureRequired = false;
}
