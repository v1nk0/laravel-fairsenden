<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use V1nk0\LaravelFairsenden\Exceptions\FixedDeliveryDayNotAvailableException;
use V1nk0\LaravelFairsenden\Exceptions\ForeignKeyMissingException;
use V1nk0\LaravelFairsenden\Exceptions\InvalidAddressException;
use V1nk0\LaravelFairsenden\Exceptions\PrimaryKeyMissingException;
use V1nk0\LaravelFairsenden\Exceptions\RequestUnsuccesfulException;
use V1nk0\LaravelFairsenden\Exceptions\ResourceNotFoundException;
use V1nk0\LaravelFairsenden\Exceptions\TokenMissingException;
use V1nk0\LaravelFairsenden\Exceptions\ZipNotCoveredByServiceAreaException;
use V1nk0\LaravelFairsenden\HttpMethod;

class Shipment extends Resource
{
    public ?string $shipmentId = null;

    public ?Sender $sender = null;

    public ?Recipient $recipient = null;

    public ?State $deliveryState = null;

    /** @var ShipmentHistory[] */
    public array $history = [];

    public ?string $timeslotUrl = null;

    public ?string $trackUrl = null;

    public ?Timeslot $selectedTimeslot = null;

    public ?Timeslot $selectedReturnTimeslot = null;

    public ?string $customerReferenceId = null;

    public bool $oversized = false;

    public ?Carbon $fixedDeliveryday = null;

    public bool $returnable = false;

    public ?string $deliveryType = null;

    public ?string $accessToken = null;

    public ?DeliveryOptions $deliveryOptions = null;

    public ?string $merchantId = null;

    /** @var ScanHistory[] */
    public array $scanHistory = [];

    /** @var Delivery[] */
    public array $deliveries = [];

    public int $totalWeight = 0;

    public int $totalVolume = 0;

    /** @var Parcel[] */
    public array $parcels = [];

    protected static string $PRIMARY_KEY = 'shipmentId';

    protected static array $PATHS = [
        'read' => 'shipments/{id}',
        'create' => 'shipments',
        'update' => 'shipments/{id}'
    ];

    protected static array $HAS_ONE = [
        'sender' => Sender::class,
        'recipient' => Recipient::class,
        'deliveryState' => State::class,
        'selectedTimeslot' => Timeslot::class,
        'selectedReturnTimeslot' => Timeslot::class,
        'deliveryOptions' => DeliveryOptions::class,
    ];

    protected static array $HAS_MANY = [
        'history' => ShipmentHistory::class,
        'scanHistory' => ScanHistory::class,
        'deliveries' => Delivery::class,
        'parcels' => Parcel::class,
    ];

    public function rules(): array
    {
        return [
            'sender' => 'required',
            'recipient' => 'required',
        ];
    }

    public function find(string $id): ?self
    {
        if(!$id) {
            return null;
        }

        try {
            $response = $this->client->request(HttpMethod::GET, 'shipments/'.$id);

            if(!$response->successful()) {
                return null;
            }
        }
        catch(Exception $e) {
            return null;
        }

        return self::createInstance($response->json(), $this->client);
    }

    /** @throws ConnectionException|ResourceNotFoundException|Exception|InvalidAddressException|ZipNotCoveredByServiceAreaException|FixedDeliveryDayNotAvailableException */
    public function save(bool $updateObject = false): ?self
    {
        $this->validate();

        if(!$this->recipient->address->resolve()) {
            throw new InvalidAddressException('Invalid recipient address');
        }

        $serviceArea = ServiceArea::createInstance([], $this->client);
        if(!$serviceArea->coversZip($this->recipient->address->zip)) {
            throw new ZipNotCoveredByServiceAreaException();
        }

        if($this->fixedDeliveryday) {
            $earliestFixedDeliveryDay = $this->recipient->address->earliestFixedDeliveryDate();
            if(!$earliestFixedDeliveryDay || $earliestFixedDeliveryDay->isAfter($this->fixedDeliveryday->startOfDay())) {
                throw new FixedDeliveryDayNotAvailableException();
            }
        }

        $method = ($this->hasPrimaryKey()) ? HttpMethod::PUT : HttpMethod::POST;
        $path = ($this->hasPrimaryKey()) ? static::$PATHS['update'] : static::$PATHS['create'];
        $path = str_replace('{id}', $this->primaryKey(), $path);

        $response = $this->request($method, $path, $this->values());

        if($updateObject) {
            $data = $response->json();
            self::createInstance($data, $this->client, $this);
        }

        return new self($this->client, $response->json());
    }

    /** @throws ConnectionException|PrimaryKeyMissingException|RequestUnsuccesfulException|TokenMissingException|ResourceNotFoundException */
    public function confirm(): void
    {
        if(!$this->primaryKey()) {
            throw new PrimaryKeyMissingException();
        }

        $this->request(HttpMethod::PUT, 'shipments/'.$this->primaryKey().'/status', 'CUSTOMER_CONFIRMED');
    }

    /** @throws ConnectionException|PrimaryKeyMissingException|RequestUnsuccesfulException|TokenMissingException|ResourceNotFoundException */
    public function delete(): void
    {
        if(!$this->primaryKey()) {
            throw new PrimaryKeyMissingException();
        }

        $this->request(HttpMethod::DELETE, 'shipments/'.$this->primaryKey());
    }

    public function values(): array
    {
        $values = parent::values();

        if($values['fixedDeliveryday'] instanceof Carbon) {
            $values['fixedDeliveryday'] = $values['fixedDeliveryday']->format('Y-m-d');
        }

        return $values;
    }

    public function hasParcel(string $parcelId): bool
    {
        if(!$this->parcels) {
            return false;
        }

        foreach($this->parcels as $parcel) {
            if($parcel->parcelId === $parcelId) {
                return true;
            }
        }

        return false;
    }

    /** @throws ConnectionException|PrimaryKeyMissingException|RequestUnsuccesfulException|TokenMissingException|ResourceNotFoundException */
    public function saveParcel(Parcel $parcel)
    {
        if(!$this->primaryKey()) {
            throw new PrimaryKeyMissingException();
        }

        $method = ($parcel->primaryKey()) ? HttpMethod::PUT : HttpMethod::POST;
        $path = ($parcel->primaryKey()) ? 'shipments/'.$this->primaryKey().'/parcels/'.$parcel->parcelId : 'shipments/'.$this->primaryKey().'/parcels';

        $this->request($method, $path, $parcel->values());
    }

    /** @throws ConnectionException|PrimaryKeyMissingException|RequestUnsuccesfulException|TokenMissingException|ResourceNotFoundException|ForeignKeyMissingException */
    public function deleteParcel(Parcel|string $parcel)
    {
        if(!$this->primaryKey()) {
            throw new PrimaryKeyMissingException();
        }

        $foreignId = ($parcel instanceOf Parcel) ? $parcel->primaryKey() : $parcel;

        if(!$foreignId) {
            throw new ForeignKeyMissingException();
        }

        $this->request(HttpMethod::DELETE, 'shipments/'.$this->primaryKey().'/parcels/'.$foreignId);
    }
}
