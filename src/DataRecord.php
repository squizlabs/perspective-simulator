<?php
namespace PerspectiveSimulator;

class DataRecord
{

    private $id = '';
    private $store = '';
    private $project = '';
    private $properties = [];
    private $references = [];


    final public function __construct(DataStore $store, string $id, string $project) {
        $this->store = $store;
        $this->id = $id;
        $this->project = $project;

        if ($this->load() === false) {
            $this->save();
        }
    }


    final public function getId()
    {
        return $this->id;
    }

    final public function getStorage()
    {
        return $this->store;
    }


    final public function getValue(string $propertyCode)
    {
        $prop = StorageFactory::getDataRecordProperty($propertyCode);
        if ($prop === null) {
            throw new \Exception("Property \"$propertyCode\" does not exist");
        }

        if (isset($this->properties[$propertyCode]) === true) {
            return $this->properties[$propertyCode];
        }

        return $prop['default'];
    }


    final public function setValue(string $propertyCode, $value)
    {
        $prop = StorageFactory::getDataRecordProperty($propertyCode);
        if ($prop === null) {
            throw new \Exception("Property \"$propertyCode\" does not exist");
        }

        if ($prop['type'] === 'unique') {
            $current = $this->store->getUniqueDataRecord($propertyCode, $value);
            if ($current !== null) {
                throw new \Exception("Unique value \"$value\" is already in use");
            }

            $this->store->setUniqueDataRecord($propertyCode, $value, $this);
        }

        $this->properties[$propertyCode] = $value;

        $this->save();
    }

    final public function getChildren($depth=null)
    {
        return $this->store->getChildren($this->id, $depth);

    }


    final public function getReference(string $code)
    {
        // TODO: check reference is valid
        if (isset($this->references[$code]) === false) {
            return null;
        }

        $ids = array_keys($this->references[$code]);

        if (count($ids) === 1) {
            return $ids[0];
        } else {
            return $ids;
        }
    }

    final public function addReference(string $code, $objects)
    {
        // TODO: check reference is valid
        // TODO: set reference on other side
        // TODO: save to cache
        if (is_array($objects) === false) {
            $objects = [$objects];
        }

        if (isset($this->references[$code]) === false) {
            $this->references[$code] = [];
        }

        foreach ($objects as $object) {
            $id = $object->getId();
            $this->references[$code][$id] = true;
        }
    }

    final public function setReference(string $code, $objects)
    {
        // TODO: check reference is valid
        // TODO: set reference on other side
        // TODO: save to cache
        if (isset($this->references[$code]) === false) {
            $this->references[$code] = [];
        }

        $this->addReference($code, $objects);
    }


    final public function save()
    {
        if (Bootstrap::isWriteEnabled() === false) {
            return false;
        }

        $record = [
            'id' => $this->id,
            'type' => get_class($this),
            'properties' => $this->properties,
        ];

        $storeCode = $this->store->getCode();
        $filePath = dirname(dirname(dirname(dirname(__DIR__)))).'/simulator/'.$this->project.'/storage/'.$storeCode.'/'.$this->id.'.json';

        file_put_contents($filePath, json_encode($record));
        return true;
    }

    final public function load()
    {
        if (Bootstrap::isReadEnabled() === false) {
            return false;
        }

        $storeCode = $this->store->getCode();
        $filePath  = dirname(dirname(dirname(dirname(__DIR__)))).'/simulator/'.$this->project.'/storage/'.$storeCode.'/'.$this->id.'.json';
        if (is_file($filePath) === false) {
            return false;
        }

        $data = json_decode(file_get_contents($filePath), true);
        $this->properties = $data['properties'];
        return true;

    }//end load()


}//end class
