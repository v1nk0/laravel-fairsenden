<?php

namespace V1nk0\LaravelFairsenden\Resources;

class Sender extends Resource
{
    public ?string $salutation = null;

    public ?string $title = null;

    public ?string $company = null;

    public ?string $email = null;

    public Address $address;

    public ?string $phone = null;

    public ?string $first_name = null;

    public ?string $last_name = null;

    protected static array $HAS_ONE = [
        'address' => Address::class,
    ];

    public function rules(): array
    {
        return [];
    }
}
