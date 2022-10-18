<?php

namespace V1nk0\LaravelFairsenden\Contracts;

interface ResourceContract
{
    public function rules(): array;

    public function values(): array;
}
