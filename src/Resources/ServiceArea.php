<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use V1nk0\LaravelFairsenden\Exceptions\RequestUnsuccesfulException;
use V1nk0\LaravelFairsenden\Exceptions\ResourceNotFoundException;
use V1nk0\LaravelFairsenden\HttpMethod;

class ServiceArea extends Resource
{
    /** @throws ConnectionException|Exception */
    public function coversZip(string|int $zip): bool
    {
        try {
            $response = $this->request(HttpMethod::GET, 'serviceareas/'.$zip);
        }
        catch(ResourceNotFoundException|RequestUnsuccesfulException) {
            return false;
        }

        $data = $response->json();

        return $data['active'] ?? false;
    }

    /** @throws ConnectionException|Exception */
    public function coversAddress(Address $address)
    {
        try {
            $response = $this->request(HttpMethod::GET, 'serviceareas/'.$address->zip, $address->values());
        }
        catch(ResourceNotFoundException|RequestUnsuccesfulException) {
            return false;
        }

        $data = $response->json();

        return $data['active'] ?? false;
    }
}
