<?php
namespace PerspectiveSimulator;

class StorageFactory
{
    private static $stores = [];
    private static $props = [
        'page' => [],
        'data' => [],
        'user' => [],
    ];

    public static function createDataStore(string $code, string $project)
    {
        if (isset(self::$stores['data'][$code]) === false) {
            self::$stores['data'][$code] = new DataStore($code, $project);
        }
    }

    public static function createUserStore(string $code, string $project)
    {
        if (isset(self::$stores['user'][$code]) === false) {
            self::$stores['user'][$code] = new UserStore($code, $project);
        }
    }

    public static function createDataRecordProperty(string $code, string $type, $default=null)
    {
        self::$props['data'][$code] = [
            'type'    => $type,
            'default' => $default,
        ];
    }

    public static function createUserProperty(string $code, string $type, $default=null)
    {
        self::$props['user'][$code] = [
            'type'    => $type,
            'default' => $default,
        ];
    }

    public static function getDataRecordProperty(string $code)
    {
        return self::$props['data'][$code] ?? null;
    }

    public static function getUserProperty(string $code)
    {
        return self::$props['user'][$code] ?? null;
    }







    public static function getDataStore(string $code)
    {
        if (isset(self::$stores['data'][$code]) === false) {
            throw new \Exception("Data store \"$code\" does not exist");
        }

        return self::$stores['data'][$code];
    }

    public static function getUserStore(string $code)
    {
        if (isset(self::$stores['user'][$code]) === false) {
            throw new \Exception("User store \"$code\" does not exist");
        }

        return self::$stores['user'][$code];
    }
}
