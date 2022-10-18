<?php

namespace V1nk0\LaravelFairsenden\Resources;

class Parcel extends Resource
{
    public int $weight = 0;

    public int $volume = 0;

    public ?string $parcelId = null;

    public ?string $parcelCustomerReferenceId = null;

    public ?State $deliveryState = null;

    /** @var ParcelHistory[] */
    public array $history = [];

    protected static string $PRIMARY_KEY = 'parcelId';

    protected static array $HAS_ONE = [
        'deliveryState' => State::class,
    ];

    protected static array $HAS_MANY = [
        'history' => ParcelHistory::class,
    ];
}
