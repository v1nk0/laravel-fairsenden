<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use V1nk0\LaravelFairsenden\HttpMethod;

class Address extends Resource
{
    public ?string $street = null;

    public ?string $zip = null;

    public ?string $city = null;

    public string $countrycode = 'DE';

    public ?string $additional_information = null;

    public ?string $care_of = null;

    /** @return Collection<Address> */
    public function possibleAddresses(): Collection
    {
        $addresses = collect();

        try {
            $response = $this->client->request(HttpMethod::POST, 'addresses/', $this->values());

            $data = $response->json();

            if(!$data || !isset($data['possibleaddresses'])) {
                return $addresses;
            }

            foreach($data['possibleaddresses'] as $address) {
                $addresses->push(new Address($this->client, $address));
            }
        }
        catch(Exception $e) {
            //
        }

        return $addresses;
    }

    public function resolve(): bool
    {
        $addresses = $this->possibleAddresses();

        if($addresses->count() < 1) {
            return false;
        }

        $data = $addresses->first()->values();

        $data['care_of'] = $data['care_of'] ?? $this->getCareOf();
        $data['additional_information'] = $data['additional_information'] ?? $this->getAdditionalInformation();

        $this->createInstance($data, $this->client, $this);

        return true;
    }

    public function setStreet(string $street): self
    {
        $this->street = $street;
        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setZip(string|int $zip): self
    {
        $this->zip = (string)$zip;
        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setAdditionalInformation(string $additionalInformation): self
    {
        $this->additional_information = $additionalInformation;
        return $this;
    }

    public function getAdditionalInformation(): ?string
    {
        return $this->additional_information;
    }

    public function setCareOf(string $careOf): self
    {
        $this->care_of = $careOf;
        return $this;
    }

    public function getCareOf(): ?string
    {
        return $this->care_of;
    }

    public function rules(): array
    {
        return [
            'street' => 'required',
            'zip' => 'required',
            'city' => 'required',
            'countrycode' => 'required|min:2|max:2',
        ];
    }

    public function values(): array
    {
        $values = parent::values();

        if(empty($values['additional_information'])) {
            unset($values['additional_information']);
        }

        return $values;
    }

    public function earliestFixedDeliveryDate(): ?Carbon
    {
        try {
            $this->validate();
        }
        catch(Exception $e) {
            return null;
        }

        try {
            $response = $this->request(HttpMethod::POST, 'serviceareas/'.$this->zip.'/fixeddeliveryday', ['senderAdress' => $this->values()]);

            $data = $response->json();

            return ($data['earliestFixedDeliveryDay']) ? Carbon::parse($data['earliestFixedDeliveryDay'])->startOfDay() : null;
        }
        catch(Exception $e) {
            return null;
        }
    }
}
