<?php

namespace V1nk0\LaravelFairsenden\Resources;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Validator;
use Reflection;
use ReflectionObject;
use ReflectionProperty;
use V1nk0\LaravelFairsenden\Client;
use V1nk0\LaravelFairsenden\Contracts\ResourceContract;
use V1nk0\LaravelFairsenden\Exceptions\RequestUnsuccesfulException;
use V1nk0\LaravelFairsenden\Exceptions\ResourceNotFoundException;
use V1nk0\LaravelFairsenden\Exceptions\ResourceValidationException;
use V1nk0\LaravelFairsenden\Exceptions\TokenMissingException;
use V1nk0\LaravelFairsenden\HttpMethod;

abstract class Resource implements ResourceContract
{
    protected Client $client;

    /** @var ResourceContract[] */
    protected static array $HAS_ONE = [];

    /** @var ResourceContract[] */
    protected static array $HAS_MANY = [];

    protected static string $PRIMARY_KEY = 'id';

    private array $originalState = [];

    public function __construct(Client $client, array $attributes = [])
    {
        $this->client = $client;

        self::setInstanceData($this, $attributes);
    }

    public function values(): array
    {
        return $this->getPublicProperties();
    }

    public function getPublicProperties(): array
    {
        $properties = (new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC);

        $values = [];

        foreach($properties as $property) {
            $modifiers = Reflection::getModifierNames($property->getModifiers());

            if(in_array('static', $modifiers)) {
                continue;
            }

            $propertyName = $property->getName();

            if(array_key_exists($propertyName, static::$HAS_ONE)) {
                $values[$propertyName] = ($this->{$propertyName}) ? $this->{$propertyName}->values() : null;
                continue;
            }

            if(array_key_exists($propertyName, static::$HAS_MANY)) {
                foreach($this->{$propertyName} as $item) {
                    $values[$propertyName][] = $item->values();
                }

                continue;
            }

            $values[$property->getName()] = $this->{$property->getName()};
        }

        return $values;
    }

    public function jsonSerialize(): string
    {
        return json_encode($this->toArray());
    }

    public function rules(): array
    {
        return [];
    }

    protected function hasProperty(string $key): bool
    {
        return property_exists($this, $key);
    }

    private function _getPropertyType(string $key): ?string
    {
        $rp = new ReflectionProperty($this, $key);

        return $rp->getType()->getName();
    }

    private function _valueByPropertyType(mixed $value, string $propertyType = 'string')
    {
        return match ($propertyType) {
            'string' => (string)$value,
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => (bool)$value,
            'Carbon\Carbon', 'Illuminate\Support\Carbon' => Carbon::parse($value),
            default => null,
        };
    }

    public function primaryKey(): mixed
    {
        return $this->{static::$PRIMARY_KEY} ?? null;
    }

    public function hasPrimaryKey(): bool
    {
        return (bool)($this->primaryKey());
    }

    /** @throws ResourceValidationException */
    public function validate(): void
    {
        $validator = Validator::make($this->values(), $this->rules());

        if($validator->fails()) {
            throw new ResourceValidationException($validator->errors()->first());
        }

        foreach(array_keys(static::$HAS_ONE) as $field) {
            $this->{$field}?->validate();
        }

        foreach(array_keys(static::$HAS_MANY) as $field) {
            foreach($this->{$field} as $relation) {
                $relation->validate();
            }
        }
    }

    /** @return static */
    public static function createInstance(array $data, Client $client, &$instance = null)
    {
        $instance = $instance ?: new static($client, $data);

        if(!empty($data)) {
            self::setInstanceData($instance, $data);
        }

        return $instance;
    }

    public static function setInstanceData(self &$instance, array $data): void
    {
        $instance->originalState = [];

        foreach($data as $key => $value) {
            if(array_key_exists($key, static::$HAS_ONE)) {
                if($value && !empty($value)) {
                    $instance->{$key} = static::$HAS_ONE[$key]::createInstance($value, $instance->client);
                }
                continue;
            }

            if(array_key_exists($key, static::$HAS_MANY)) {
                $attrList = [];
                if(!empty($value)) {
                    foreach($value as $item) {
                        array_push(
                            $attrList,
                            static::$HAS_MANY[$key]::createInstance($item, $instance->client)
                        );
                    }
                }

                $instance->{$key} = $attrList;
                continue;
            }

            if($instance->hasProperty($key)) {
                $instance->{$key} = $instance->_valueByPropertyType($value, $instance->_getPropertyType($key));
                $instance->originalState[$key] = $value;
            }
        }
    }

    /** @throws ConnectionException|ResourceNotFoundException|TokenMissingException|RequestUnsuccesfulException */
    public function request(HttpMethod $method, string $path = '', array|string $data = ''): Response
    {
        $response = $this->client->request($method, $path, $data);

        if(!$response->successful()) {
            throw new RequestUnsuccesfulException($response->reason());
        }

        return $response;
    }
}
