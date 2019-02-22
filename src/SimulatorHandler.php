<?php
/**
 * Simulator Handler class for Perspective Simulator.
 *
 * @package    Perspective
 * @subpackage Simulator
 * @author     Squiz Pty Ltd <products@squiz.net>
 * @copyright  2018 Squiz Pty Ltd (ABN 77 084 670 600)
 */
namespace PerspectiveSimulator;

use \PerspectiveSimulator\Libs;

/**
 * SimulatorHandler Class
 */
class SimulatorHandler
{

    /**
     * File path for the save file.
     *
     * @var string
     */
    private $saveFile = '';

    /**
     * Instance of simulator filesystem storage.
     *
     * @var object.
     */
    private static $simulator = null;

    /**
     * Sequence of data record ids.
     *
     * @var integer
     */
    private $dataRecordSequence = 0;

    /**
     * Sequence of user ids.
     *
     * @var integer
     */
    private $userSequence = 0;

    /**
     * Sequence of user group ids.
     *
     * @var integer
     */
    private $userGroupSequence = 0;

    /**
     * Local cache of loaded properties.
     *
     * @var array
     */
    private $properties = [
        'data'    => [],
        'user'    => [],
        'project' => [],
    ];

    /**
     * Local cache of loaded stores.
     *
     * @var array
     */
    private $stores = [
        'data'    => [],
        'user'    => [],
        'project' => [],
    ];

    /**
     * Local cache of the loaded references.
     *
     * @var array
     */
    private $referneces = [];


    /**
     * Constructor function for simulator handler.
     */
    public function __construct()
    {
        $this->saveFile = Libs\FileSystem::getStorageDir().'/saved.json';
        if (Bootstrap::isReadEnabled() === true && file_exists($this->saveFile) === true) {
            $savedData = Libs\Util::jsonDecode(file_get_contents($this->saveFile));

            // Reload the sequneces.
            $this->dataRecordSequence = ($savedData['dataRecordSequence'] ?? 0);
            $this->userSequence       = ($savedData['userSequence'] ?? 0);
            $this->userGroupSequence  = ($savedData['userGroupSequence'] ?? 0);

            if (isset($savedData['stores']) === true) {
                foreach ($savedData['stores'] as $type => $projects) {
                    foreach ($projects as $projectid => $stores) {
                        if (isset($this->stores[$type][$projectid]) === false) {
                            $this->stores[$type][$projectid] = [];
                        }

                        foreach ($stores as $storeCode => $storeData) {
                            if (isset($this->stores[$type][$projectid][$storeCode]) === false) {
                                $this->stores[$type][$projectid][$storeCode] = $storeData;
                            }
                        }//end foreach
                    }//end foreach
                }//end foreach
            }//end if
        }//end if

    }//end __construct()


    /**
     * Returns or instantiates a singleton instance of this console object.
     *
     * @return object
     */
    public static function getSimulator()
    {
        if (isset(self::$simulator) === false) {
            self::$simulator = new SimulatorHandler();
        }

        return self::$simulator;

    }//end getSimulator()


    /**
     * Loads the data from the filesystem
     *
     * @return void
     */
    public function load()
    {
        $prefix     = Bootstrap::generatePrefix($GLOBALS['projectNamespace']);
        $projectDir = Libs\FileSystem::getProjectDir();

        $this->loadProperties($prefix, $projectDir);
        $this->loadStores($prefix, $projectDir);

        // Add default user properties.
        $this->properties['user'][$prefix]['__first-name__'] = [
            'type'    => 'text',
            'default' => null,
        ];

        $this->properties['user'][$prefix]['__last-name__'] = [
            'type'    => 'text',
            'default' => null,
        ];

        $path     = substr(Libs\FileSystem::getProjectDir(), 0, -4);
        $composer = $path.'/composer.json';
        if (file_exists($composer) === true) {
            $requirements     = [];
            $composerContents = Libs\Util::jsonDecode(file_get_contents($composer));
            if (isset($composerContents['require']) === true) {
                $requirements = array_merge($requirements, $composerContents['require']);
            }

            if (isset($composerContents['require-dev']) === true) {
                $requirements = array_merge($requirements, $composerContents['require-dev']);
            }

            if (empty($requirements) === false) {
                foreach ($requirements as $requirement => $version) {
                    $project    = str_replace('/', '\\', $requirement);
                    $projectDir = $path.'/vendor/'.str_replace('\\', '/', $requirement).'/src';
                    $prefix     = Bootstrap::generatePrefix($project);

                    $this->loadProperties($prefix, $projectDir);
                    $this->loadStores($prefix, $projectDir);

                    $perspectiveAPIClassAliases = [
                        'PerspectiveAPI\Objects\Types\DataRecord' => $project.'\CustomTypes\Data\DataRecord',
                        'PerspectiveAPI\Objects\Types\User'       => $project.'\CustomTypes\User\User',
                        'PerspectiveAPI\Objects\Types\Group'      => $project.'\CustomTypes\User\Group',
                        'PerspectiveSimulator\View\ViewBase'      => $project.'\Web\Views\View',
                    ];

                    if (class_exists($project.'\CustomTypes\Data\DataRecord') === false) {
                        foreach ($perspectiveAPIClassAliases as $orignalClass => $aliasClass) {
                            class_alias($orignalClass, $aliasClass);
                        }
                    }

                    $perspectiveAPIClassAliases = [
                        'PerspectiveAPI\Authentication'                => 'Authentication',
                        'PerspectiveAPI\Email'                         => 'Email',
                        'PerspectiveAPI\Request'                       => 'Request',
                        'PerspectiveAPI\Queue'                         => 'Queue',
                        'PerspectiveAPI\Storage\StorageFactory'        => 'StorageFactory',
                        'PerspectiveAPI\Objects\Types\ProjectInstance' => 'ProjectInstance',
                    ];

                    if (class_exists($project.'\Framework\Authentication') === false) {
                        foreach ($perspectiveAPIClassAliases as $orignalClass => $aliasClass) {
                            eval('namespace '.$project.'\\Framework; class '.$aliasClass.' extends \\'.$orignalClass.' {}');
                        }
                    }
                }//end foreach
            }//end if
        }//end if

    }//end load()


    /**
     * Loads the properties.
     *
     * @param string $prefix     The projects prefix.
     * @param string $projectDir The projects directory path.
     *
     * @return void
     */
    private function loadProperties(string $prefix, string $projectDir)
    {
        $namespace = str_replace('-', '/', $prefix);
        // Add data record properties.
        if (is_dir($projectDir.'/Properties/Data') === true) {
            $files = scandir($projectDir.'/Properties/Data');
            foreach ($files as $file) {
                if ($file[0] === '.'
                    || substr($file, -5) !== '.json'
                ) {
                    continue;
                }

                $propName = $namespace.'/'.strtolower(substr($file, 0, -5));
                $propInfo = Libs\Util::jsonDecode(file_get_contents($projectDir.'/Properties/Data/'.$file));

                $this->properties['data'][$prefix][$propName] = [
                    'type'    => $propInfo['type'],
                    'default' => ($propInfo['default'] ?? null),
                ];
            }
        }

        // Add project properties.
        if (is_dir($projectDir.'/Properties/Project') === true) {
            $files = scandir($projectDir.'/Properties/Project');
            foreach ($files as $file) {
                if ($file[0] === '.'
                    || substr($file, -5) !== '.json'
                ) {
                    continue;
                }

                $propName = $namespace.'/'.strtolower(substr($file, 0, -5));
                $propInfo = Libs\Util::jsonDecode(file_get_contents($projectDir.'/Properties/Project/'.$file));

                $this->properties['project'][$prefix][$propName] = [
                    'type'    => $propInfo['type'],
                    'default' => ($propInfo['default'] ?? null),
                ];
            }
        }

        // Add user properties.
        if (is_dir($projectDir.'/Properties/User') === true) {
            $files = scandir($projectDir.'/Properties/User');
            foreach ($files as $file) {
                if ($file[0] === '.'
                    || substr($file, -5) !== '.json'
                ) {
                    continue;
                }

                $propName = $namespace.'/'.strtolower(substr($file, 0, -5));
                $propInfo = Libs\Util::jsonDecode(file_get_contents($projectDir.'/Properties/User/'.$file));

                $this->properties['user'][$prefix][$propName] = [
                    'type'    => $propInfo['type'],
                    'default' => ($propInfo['default'] ?? null),
                ];
            }
        }

    }//end loadProperties()


    /**
     * Loads the stores.
     *
     * @param string $prefix     The projects prefix.
     * @param string $projectDir The projects directory path.
     *
     * @return void
     */
    private function loadStores(string $prefix, string $projectDir)
    {
        $namespace = str_replace('-', '/', $prefix);
        // Add data stores.
        $dirs = glob($projectDir.'/Stores/Data/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $storeName = $namespace.'/'.strtolower(basename($dir));
            if (isset($this->stores['data'][$prefix]) === false) {
                $this->stores['data'][$prefix] = [];
            }

            if (isset($this->stores['data'][$prefix][$storeName]) === false) {
                $this->stores['data'][$prefix][$storeName] = [
                    'records'   => [],
                    'uniqueMap' => [],
                ];
            }

            // Loads the stores references.
            $refs = glob($dir.'/*.json');
            foreach ($refs as $ref) {
                $refContent    = Libs\Util::jsonDecode(file_get_contents($ref));
                $referenceCode = $namespace.'/'.str_replace('.json', '', basename($ref));
                if (isset($this->references[$referenceCode]) === false) {
                    $this->references[$referenceCode] = $refContent;
                }
            }
        }//end foreach

        // Add user stores.
        $dirs = glob($projectDir.'/Stores/User/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $storeName = $namespace.'/'.strtolower(basename($dir));
            if (isset($this->stores['user'][$prefix]) === false) {
                $this->stores['user'][$prefix] = [];
            }

            if (isset($this->stores['user'][$prefix][$storeName]) === false) {
                $this->stores['user'][$prefix][$storeName] = [
                    'records'     => [],
                    'uniqueMap'   => [],
                    'usernameMap' => [],
                    'groups'      => [],
                ];
            }

            // Loads the stores references.
            $refs = glob($dir.'/*.json');
            foreach ($refs as $ref) {
                $refContent    = Libs\Util::jsonDecode(file_get_contents($ref));
                $referenceCode = $namespace.'/'.str_replace('.json', '', basename($ref));
                if (isset($this->references[$referenceCode]) === false) {
                    $this->references[$referenceCode] = $refContent;
                }
            }
        }

    }//end loadStores()


    /**
     * Saves data to file system.
     *
     * @return void
     */
    public function save()
    {
        if (Bootstrap::isWriteEnabled() === true) {
            $saveData = [
                'dataRecordSequence' => $this->dataRecordSequence,
                'userSequence'       => $this->userSequence,
                'userGroupSequence'  => $this->userGroupSequence,
                'stores'             => $this->stores,
            ];
            file_put_contents($this->saveFile, Libs\Util::jsonEncode($saveData));
        }

    }//end save()


    /**
     * Gets the value of a reference.
     *
     * @param string $objectType    Type of the object.
     * @param string $storeCode     The store the object belongs to.
     * @param string $id            The id of the record.
     * @param string $referenceCode The reference code.
     *
     * @return mixed
     */
    public function getReference(string $objectType, string $storeCode, string $id, string $referenceCode)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores[$objectType][$project][$storeCode]['records'][$id]['references']) === true) {
            if (isset($this->stores[$objectType][$project][$storeCode]['records'][$id]['references'][$referenceCode]) === true) {
                $ids       = array_keys($this->stores[$objectType][$project][$storeCode]['records'][$id]['references'][$referenceCode]);
                $reference = $this->getReferenceDefinition($objectType, $storeCode, $referenceCode);

                if (empty($reference) === true) {
                    return null;
                }

                $sourceCode = $reference['sourceCode'];
                if ($sourceCode === null) {
                    foreach ($this->stores['user'][$project] as $storeid => $store) {
                        if (isset($store['records'][$ids[0]]) === true) {
                            $sourceCode          = $storeid;
                            $referenceObjectType = 'user';
                            break;
                        }
                    }

                    if ($sourceCode === null) {
                        // Must not be a user
                        foreach ($this->stores['data'][$project] as $storeid => $store) {
                            if (isset($store['records'][$ids[0]]) === true) {
                                $sourceCode          = $storeid;
                                $referenceObjectType = 'data';
                                break;
                            }
                        }
                    }
                }

                if (count($ids) === 1) {
                    if ($referenceObjectType === 'user') {
                        $user = $this->getUser($sourceCode, $ids[0]);
                        return [
                            'objectType' => $referenceObjectType,
                            'storeCode'  => $sourceCode,
                            'id'         => $ids[0],
                            'typeClass'  => $user['typeClass'],
                            'username'   => $user['username'],
                            'firstName'  => $user['firstName'],
                            'lastName'   => $user['lastName'],
                        ];
                    } else {
                        $dataRecord = $this->getDataRecord($sourceCode, $ids[0]);
                        return [
                            'objectType' => $referenceObjectType,
                            'storeCode'  => $sourceCode,
                            'id'         => $ids[0],
                            'typeClass'  => $dataRecord['typeClass'],
                        ];
                    }
                } else {
                    $references = [];
                    foreach ($ids as $id) {
                        if ($referenceObjectType === 'user') {
                            $user         = $this->getUser($sourceCode, $id);
                            $references[] = [
                                'objectType' => $referenceObjectType,
                                'storeCode'  => $sourceCode,
                                'id'         => $id,
                                'typeClass'  => $user['typeClass'],
                                'username'   => $user['username'],
                                'firstName'  => $user['firstName'],
                                'lastName'   => $user['lastName'],
                            ];
                        } else {
                            $dataRecord   = $this->getDataRecord($sourceCode, $id);
                            $references[] = [
                                'objectType' => $referenceObjectType,
                                'storeCode'  => $sourceCode,
                                'id'         => $id,
                                'typeClass'  => $dataRecord['typeClass'],
                            ];
                        }
                    }//end foreach

                    return $references;
                }//end if
            }//end if
        }//end if

        return null;

    }//end getReference()


    /**
     * Adds/Sets the value of a reference.
     *
     * @param string $objectType    Type of the object.
     * @param string $storeCode     The store the object belongs to.
     * @param string $id            The id of the record.
     * @param string $referenceCode The reference code.
     * @param mixed  $objects       The objects to store as reference.
     *
     * @return void
     */
    public function addReference(string $objectType, string $storeCode, string $id, string $referenceCode, $objects)
    {
        if (is_array($objects) === false) {
            $objects = [$objects];
        }

        $project = Bootstrap::getProjectPrefix($storeCode);
        if ($this->validateReference($objectType, $storeCode, $id, $referenceCode, $objects) === false) {
            return;
        }

        $reference = $this->getReferenceDefinition($objectType, $storeCode, $referenceCode);
        if (empty($reference) === false) {
            $sourceValue   = [];
            $targetValue   = [];
            $referenceSide = $this->getReferenceSide($reference, $objectType, $storeCode);
            if ($referenceSide === 'source') {
                $sourceValue[] = $id;
            } else if ($referenceSide === 'target') {
                $targetValue[] = $id;
            }

            if ($reference['cardinality'] === '1:1') {
                if (count($sourceValue) === 1 || count($targetValue) === 1) {
                    unset($this->stores[$objectType][$project][$storeCode]['records'][$id]['references'][$referenceCode]);
                }
            } else if ($reference['cardinality'] === '1:M') {
                if (count($sourceValue) !== 1) {
                    unset($this->stores[$objectType][$project][$storeCode]['records'][$id]['references'][$referenceCode]);
                }
            }
        }//end if

        if (isset($this->stores[$objectType][$project][$storeCode]['records'][$id][$referenceCode]) === false) {
            $this->stores[$objectType][$project][$storeCode]['records'][$id][$referenceCode] = [];
        }

        foreach ($objects as $object) {
            $objectid = $object->getId();
            $this->stores[$objectType][$project][$storeCode]['records'][$id]['references'][$referenceCode][$objectid] = true;

            if ($object->getReference(basename($referenceCode)) === null) {
                $storeCodeParts = explode('/', $storeCode);
                $namespace      = '\\'.ucfirst($storeCodeParts[0]).'\\'.ucfirst($storeCodeParts[1]).'\\Framework\\StorageFactory';
                if ($objectType === 'user') {
                    $store = $namespace::getUserStore(basename($storeCode));
                } else {
                    $store = $namespace::getDataStore(basename($storeCode));
                }

                $object->addReference(
                    basename($referenceCode),
                    [new $this->stores[$objectType][$project][$storeCode]['records'][$id]['typeClass']($store, $id)]
                );
            }
        }//end foreach

    }//end addReference()


    /**
     * Deletes the value of a reference.
     *
     * @param string $objectType    Type of the object.
     * @param string $storeCode     The store the object belongs to.
     * @param string $id            The id of the record.
     * @param string $referenceCode The reference code.
     * @param mixed  $objects       The objects to store as reference.
     *
     * @return void
     */
    public function deleteReference(string $objectType, string $storeCode, string $id, string $referenceCode, $objects)
    {
        if (is_array($objects) === false) {
            $objects = [$objects];
        }

        $project = Bootstrap::getProjectPrefix($storeCode);

        foreach ($objects as $object) {
            $id = $object->getId();
            unset($this->stores[$objectType][$project][$storeCode]['records'][$id]['references'][$referenceCode][$id]);

            if ($objectType === 'user') {
                $store = $namespace::getUserStore(basename($storeCode));
            } else {
                $store = $namespace::getDataStore(basename($storeCode));
            }

            if ($object->getReference(basename($referenceCode)) === null) {
                $object->deleteReference(
                    basename($referenceCode),
                    [new $this->stores[$objectType][$project][$storeCode]['records'][$id]['typeClass']($store, $id)]
                );
            }
        }

    }//end deleteReference()


    /**
     * Gets the reference definition.
     *
     * @param string $objectType  The object type we are using to reference.
     * @param string $storeCode   The code of the store.
     * @param string $referenceid The id of the reference we getting.
     *
     * @return array
     */
    private function getReferenceDefinition(string $objectType, string $storeCode, string $referenceid)
    {
        $reference = [];
        if (isset($this->references[$referenceid]) === true) {
            $reference = $this->references[$referenceid];
            if ($reference['cardinality'] === 'M:1') {
                $reference['cardinality'] = '1:M';
                $sourceTypeArg            = $reference['sourceType'];
                $sourceCodeArg            = $reference['sourceCode'];
                $reference['sourceType']  = $reference['targetType'];
                $reference['sourceCode']  = $reference['targetCode'];
                $reference['targetType']  = $sourceTypeArg;
                $reference['targetCode']  = $sourceCodeArg;
            }
        }

        return $reference;

    }//end getReferenceDefinition()


    /**
     * Validates if the reference can be set.
     *
     * @param object $objectType  The object type we are using to reference.
     * @param string $storageCode The code of the store.
     * @param string $id          The id of the data record/user object.
     * @param string $referenceid The id of the reference we are trying to set.
     * @param array  $objects     The objects we are setting the reference against, used if we are setting the reference
     *                             for the other side.
     *
     * @return boolean.
     * @throws \Exception When reference is invalid.
     */
    private function validateReference(string $objectType, string $storageCode, string $id, string $referenceid, array $objects=[])
    {
        $valid     = false;
        $reference = $this->getReferenceDefinition($objectType, $storageCode, $referenceid);
        if (empty($reference) === false) {
            $sourceValue = [];
            $targetValue = [];

            // Categorise the given objects into source and target values depending on their side in relationship.
            foreach ($objects as $object) {
                $type = $objectType;
                if ($object instanceof \PerspectiveAPI\Objects\Types\User) {
                    $type = 'user';
                } else if ($object instanceof \PerspectiveAPI\Objects\Types\DataRecord) {
                    $type = 'data';
                }

                $referenceSide = $this->getReferenceSide($reference, $type, $object->getStorage()->getCode());
                if ($referenceSide === 'source') {
                    $sourceValue[] = $object->getId();
                } else if ($referenceSide === 'target') {
                    $targetValue[] = $object->getId();
                }
            }

            $referenceSide = $this->getReferenceSide($reference, $objectType, $storageCode);
            if ($referenceSide === 'source') {
                $sourceValue[] = $id;
            } else if ($referenceSide === 'target') {
                $targetValue[] = $id;
            }

            $errorMsg = 'Expecting single %s value in %s cardinality, but %s given';
            if ($reference['cardinality'] === '1:1') {
                if (count($sourceValue) !== 1) {
                    throw new \Exception(
                        sprintf($errorMsg, 'source', $reference['cardinality'], implode(',', $sourceValue))
                    );
                }

                if (count($targetValue) !== 1) {
                    throw new \Exception(
                        sprintf($errorMsg, 'target', $reference['cardinality'], implode(',', $targetValue))
                    );
                }
            } else if ($reference['cardinality'] === '1:M') {
                if (count($sourceValue) !== 1) {
                    throw new \Exception(
                        sprintf($errorMsg, 'source', $reference['cardinality'], implode(',', $sourceValue))
                    );
                }
            }

            if ($referenceSide === 'source') {
                if ($sourceValue[0] !== $id) {
                    throw new \Exception('The target must be the object itself');
                }

                $valid = true;
            } else {
                if ($targetValue[0] !== $id) {
                    throw new \Exception('The source must be the object itself');
                }

                if ($reference['cardinality'] === 'M:M' && count($sourceValue) > 1) {
                    $valid = true;
                } else {
                    $valid = true;
                }
            }//end if
        } else {
            $valid = true;
        }//end if

        return $valid;

    }//end validateReference()


    /**
     * Gets the reference side (source or target) to validate the reference.
     *
     * @param array  $reference   The reference data.
     * @param object $objectType  The object type we are using to reference.
     * @param string $storageCode The code of the store.
     *
     * @return string
     * @throws \Exception When invalid reference.
     */
    private function getReferenceSide(array $reference, string $objectType, string $storageCode)
    {
        if (ucfirst($objectType) === 'User') {
            $storageClass = 'UserStore';
        } else if (ucfirst($objectType) === 'Data') {
            $storageClass = 'DataStore';
        } else {
            throw new \Exception(
                sprintf('Invalid referenced object: invalid object type: %s'),
                $objectType
            );
        }

        $parts = explode('/', $storageCode);

        if ($reference['sourceCode'] !== null) {
            $reference['sourceCode'] = $parts[0].'/'.$parts[1].'/'.$reference['sourceCode'];
        }

        if ($reference['targetCode'] !== null) {
            $reference['targetCode'] = $parts[0].'/'.$parts[1].'/'.$reference['targetCode'];
        }

        if ($reference['sourceType'] === $storageClass && ($reference['sourceCode'] === $storageCode || $reference['sourceCode'] === null)) {
            return 'source';
        } else if ($reference['targetType'] === $storageClass && ($reference['targetCode'] === $storageCode || $reference['targetCode'] === null)) {
            return 'target';
        } else {
            throw new \Exception(sprintf('Invalid referenced object: %s', $objectType));
        }

    }//end getReferenceSide()


    /**
     * Gets the users from a group.
     *
     * @param string $storeCode The store the users and group belongs to.
     * @param string $groupid   The groupid.
     *
     * @return array
     */
    public function getGroupMembers(string $storeCode, string $groupid)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        $users   = $this->stores['user'][$project][$storeCode]['records'];
        $members = array_filter(
            $users,
            function ($record) use ($groupid) {
                $groups = array_keys($record['groups']);
                return in_array($groupid, $groups);
            },
            ARRAY_FILTER_USE_BOTH
        );

        return $members;

    }//end getGroupMembers()


    /**
     * Sets the group name.
     *
     * @param string $storeCode The store the group belongs to.
     * @param string $id        The id of the group.
     * @param string $name      The name of the group.
     */
    public function setGroupName(string $storeCode, string $id, string $name)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['groups'][$id]) === true) {
            $this->stores['user'][$project][$storeCode]['groups'][$id] = $name;
        }

    }//end setGroupName()


    /**
     * Set username.
     *
     * @param string $storeCode The store the user belongs to.
     * @param string $id        The id of the user.
     * @param string $username  The username.
     *
     * @return void
     */
    public static function setUsername(string $id, string $storeCode, string $username)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['records'][$id]) === true) {
            $this->stores['user'][$project][$storeCode]['records'][$id]['username'] = $username;
        }

    }//end setUsername()


    /**
     * Set first name
     *
     * @param string $storeCode The store the user belongs to.
     * @param string $id        The id of the user.
     * @param string $firstName The first name of the user.
     *
     * @return void
     */
    public static function setUserFirstName(string $id, string $storeCode, string $firstName)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['records'][$id]) === true) {
            $this->stores['user'][$project][$storeCode]['records'][$id]['properties']['__first-name__'] = $firstName;
        }

    }//end setUserFirstName()


    /**
     * Set last name
     *
     * @param string $storeCode The store the user belongs to.
     * @param string $id        The id of the user.
     * @param string $lastName  The last name of the user.
     *
     * @return void
     */
    public static function setUserLastName(string $id, string $storeCode, string $lastName)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['records'][$id]) === true) {
            $this->stores['user'][$project][$storeCode]['records'][$id]['properties']['__last-name__'] = $lastName;
        }

    }//end setUserLastName()


    /**
     * Gets the users groups.
     *
     * @param string $storeCode The store the user belongs to.
     * @param string $id        The id of the user.
     *
     * @return mixed
     */
    public function getUserGroups(string $storeCode, string $id)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['records'][$id]) === true) {
            return array_keys($this->stores['user'][$project][$storeCode]['records'][$id]['groups']);
        }

        return false;

    }//end getUserGroups()


    /**
     * Assign an user to parent groups.
     *
     * @param string $storeCode The store the user belongs to.
     * @param string $id        The id of the user.
     * @param mixed  $groupid   Parent user groups to assign the user to.
     *
     * @return void
     */
    public static function addUserToGroup(string $id, string $storeCode, string $groupid)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['records'][$id]) === true) {
            $this->stores['user'][$project][$storeCode]['records'][$id]['groups'][$groupid] = true;
            return true;
        }

        return false;

    }//end addUserToGroup()


    /**
     * Remove an user from specified parent groups.
     *
     * @param mixed $groupid Parent user groups to remove the user from.
     *
     * @return void
     */
    public static function removeUserFromGroup(string $id, string $storeCode, string $groupid)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['records'][$id]) === true) {
            unset($this->stores['user'][$project][$storeCode]['records'][$id]['groups'][$groupid]);
            return true;
        }

        return false;

    }//end removeUserFromGroup()


    /**
     * Gets a property value.
     *
     * @param string $objectType   The object type eg, data, user.
     * @param string $storeCode    The store code.
     * @param string $id           The id of the data record.
     * @param string $propertyCode The property we want the value of.
     *
     * @return mixed
     */
    public function getPropertyValue(string $objectType, string $storeCode, string $id, string $propertyCode)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->properties[$objectType][$project][$propertyCode]) === false) {
            throw new \Exception('Property '.$propertyCode.' does not exist');
        }

        if ($objectType === 'project'
            && isset($this->stores[$objectType][$project][$propertyCode]) === true
        ) {
            return $this->stores[$objectType][$project][$propertyCode];
        } else if (isset($this->stores[$objectType][$project][$storeCode]['records'][$id][$propertyCode]) === true) {
            return $this->stores[$objectType][$project][$storeCode]['records'][$id][$propertyCode];
        }

        $property = $this->properties[$objectType][$project][$propertyCode];
        if ($property['type'] === 'image' || $property['type'] === 'file') {
            $propDir        = Libs\FileSystem::getProjectDir().'/Properties/'.ucfirst($objectType);
            $unprefixedCode = explode('-', $propertyCode);
            $prefix         = array_shift($unprefixedCode);
            $prefix        .= '/'.array_shift($unprefixedCode);
            if ($prefix === strtolower($GLOBALS['project'])) {
                $unprefixedCode = implode('-', $unprefixedCode);
            } else {
                $prefix         = str_replace('\\', '/', $prefix);
                $unprefixedCode = implode('-', $unprefixedCode);
                $propDir        = substr(Libs\FileSystem::getProjectDir(), 0, -4);
                $propDir       .= '/vendor/'.$prefix.'/src/Properties/'.ucfirst($objectType);
            }

            $propFiles = Libs\FileSystem::listDirectory(
                $propDir,
                [],
                false,
                false
            );

            foreach ($propFiles as $file) {
                if ($file[0] === '.'
                    || substr($file, -5) === '.json'
                    || strpos($file, $unprefixedCode) !== 0
                ) {
                    continue;
                }

                $property['default'] = $file;
            }

            $prefix = str_replace('/', '-', str_replace('\\', '-', $prefix));
            return '/property/'.$GLOBALS['projectPath'].'/'.ucfirst($objectType).'/'.strtolower($prefix).'-'.$property['default'];
        }//end if

        return $property['default'];

    }//end getPropertyValue()


    /**
     * Sets a property value.
     *
     * @param string $objectType   The object type eg, data, user.
     * @param string $storeCode    The store code.
     * @param string $id           The id of the data record.
     * @param string $propertyCode The property we are setting.
     * @param mixed  $value        The value of the property.
     *
     * @return void
     */
    public function setPropertyValue(string $objectType, string $storeCode, string $id, string $propertyCode, $value)
    {
        if ($value === null) {
            throw new \Exception('Property value violates not-null constraint');
        }

        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->properties[$objectType][$project][$propertyCode]) === false) {
            throw new \Exception('Property '.$propertyCode.'does not exist');
        }

        $property = $this->properties[$objectType][$project][$propertyCode];

        if ($property['type'] === 'unique') {
            $current = ($this->stores[$objectType][$project][$storeCode]['uniqueMap'][$propertyCode][$value] ?? null);
            if ($current !== null) {
                throw new \Exception('Unique value "'.$value.'" is already in use');
            }

            if ($objectType === 'project') {
                $this->stores[$objectType][$project][$propertyCode] = $id;
            } else {
                $this->stores[$objectType][$project][$storeCode]['uniqueMap'][$propertyCode][$value] = $id;
            }
        } else if ($property['type'] === 'image' || $property['type'] === 'file') {
            $value = $this->prepareFileImagePropertyValue($value, ucfirst($objectType), $propertyCode);
        }

        if ($objectType === 'project') {
            $this->stores[$objectType][$project][$propertyCode] = $value;
        } else {
            $this->stores[$objectType][$project][$storeCode]['records'][$id][$propertyCode] = $value;
        }

    }//end setPropertyValue()


    /**
     * Deletes a property value.
     *
     * @param string $objectType   The object type eg, data, user.
     * @param string $storeCode    The store code.
     * @param string $id           The id of the data record.
     * @param string $propertyCode The property we are setting.
     *
     * @return void
     */
    public function deletePropertyValue(string $objectType, string $storeCode, string $id, string $propertyCode)
    {
        $project  = Bootstrap::getProjectPrefix($storeCode);
        $property = $this->properties[$objectType][$project][$propertyCode];

        if ($objectType === 'project'
            && isset($this->stores[$objectType][$project][$propertyCode]) === true
        ) {
            unset($this->stores[$objectType][$project][$propertyCode]);
        } else if (isset($this->stores[$objectType][$project][$storeCode]['records'][$id][$propertyCode]) === true) {
            unset($this->stores[$objectType][$project][$storeCode]['records'][$id][$propertyCode]);
        }

    }//end deletePropertyValue()


    /**
     * Prepares the value of a file and image property.
     *
     * @param array  $value        The value of the property to validate and prepare.
     * @param string $propertyType The type of the property.
     *
     * @return array
     * @throws \Exception Thrown when the value array is invalid.
     */
    private function prepareFileImagePropertyValue($value, $propertyType, $propertyCode)
    {
        if (is_array($value) === true) {
            // Expecting the structure of file upload array.
            $requiredFields = [
                'name',
                'type',
                'tmp_name',
                'error',
                'size',
            ];
            foreach ($requiredFields as $field) {
                if (array_key_exists($field, $value) === false) {
                    $errMsg = sprintf(
                        'Expecting \'%s\' field but not found in the value.',
                        $field
                    );
                    throw new \Exception($errMsg);
                }
            }

            $unprefixedCode = explode('-', $propertyCode);
            $prefix         = array_shift($unprefixedCode);
            $prefix        .= '/'.array_shift($unprefixedCode);
            if ($prefix === strtolower($GLOBALS['project'])) {
                $unprefixedCode = implode('-', $unprefixedCode);
            } else {
                $prefix         = str_replace('\\', '/', $prefix);
                $unprefixedCode = implode('-', $unprefixedCode);
                $propDir        = substr(\PerspectiveSimulator\Libs\FileSystem::getProjectDir(), 0, -4);
                $propDir       .= '/vendor/'.$prefix.'/src/Properties/'.$typeName;
            }

            $uploadedFilepath = Libs\FileSystem::getStorageDir().'/properties/'.$propertyType;
            if (is_dir($uploadedFilepath) === false) {
                Libs\FileSystem::mkdir($uploadedFilepath, true);
            }

            $ext            = Libs\FileSystem::getExtension($value['name']);
            $targetFilepath = $uploadedFilepath.'/'.$propertyCode.'.'.$ext;
            if (move_uploaded_file($value['tmp_name'], $targetFilepath) === false) {
                throw new \Exception('Failed to get the upload file.');
            }

            return '/property/'.$GLOBALS['projectPath'].'/'.$propertyType.'/'.$propertyCode.'.'.$ext;
        } else if (is_string($value) === true) {
            // Expecting the base64 string.
            if (preg_match('#^data:[a-z]+/([a-z]+);base64,[\w=+/]++#', $value) !== 1) {
                throw new \Exception('The string value for File/Image property should be a valid base64 string.');
            }

            return $value;
        }//end if

    }//end prepareFileImagePropertyValue()


    /**
     * Gets the children for an object.
     *
     * @param string  $objectType The object type.
     * @param string  $storeCode  The store the object belongs to.
     * @param string  $id         The id of the record.
     * @param integer $depth      The depth to get.
     *
     * @return array
     */
    public function getChildren(string $objectType, string $storeCode, string $id, int $depth=null)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores[$objectType][$project][$storeCode]['records'][$id]) === false) {
            return [];
        }

        if ($depth !== null) {
            if ($depth === 0) {
                return [];
            }

            $depth--;
        }

        $children = [];
        foreach ($this->stores[$objectType][$project][$storeCode]['records'][$id]['children'] as $childid => $child) {
            $children[$childid] = [
                'depth'    => $this->stores[$objectType][$project][$storeCode]['records'][$childid]['depth'],
                'children' => [],
            ];

            if ($depth !== 0) {
                $children[$childid]['children'] = $this->getChildren($objectType, $storeCode, $childid, $depth);
            }
        }

        return $children;

    }//end getChildren()


    /**
     * Gets the parents for an object.
     *
     * @param string  $objectType The object type.
     * @param string  $storeCode  The store the object belongs to.
     * @param string  $id         The id of the record.
     * @param integer $depth      The depth to get.
     *
     * @return array
     */
    public function getParents(string $objectType, string $storeCode, string $id, int $depth=null)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores[$objectType][$project][$storeCode]['records'][$id]['parent']) === false) {
            return [];
        }

        if ($depth !== null) {
            if ($depth === 0) {
                return [];
            }

            $depth--;
        }

        $parents = [];
        if ($this->stores[$objectType][$project][$storeCode]['records'][$id]['parent'] !== null) {
            $parentid           = $this->stores[$objectType][$project][$storeCode]['records'][$id]['parent'];
            $parents[$parentid] = [
                'depth'   => $this->stores[$objectType][$project][$storeCode]['records'][$id]['depth'],
                'parents' => [],
            ];

            if ($depth !== 0) {
                $parents[$parentid]['parents'] = $this->getParents($objectType, $storeCode, $parentid, $depth);
            }
        }

        return $parents;

    }//end getParents()


    /**
     * Creates a Data Record.
     *
     * @param string $storeCode  The store the data record will belong to.
     * @param string $customType The type of the data record.
     * @param string $parent     The parent of the data record.
     *
     * @return mixed
     */
    public function createDataRecord(string $storeCode, string $customType, string $parent=null)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if ($customType === null) {
            $customType = '\PerspectiveAPI\Objects\Types\DataRecord';
        } else {
            if (strpos($storeCode, strtolower($GLOBALS['project'])) === 0) {
                $customType = '\\'.$GLOBALS['projectNamespace'].'\CustomTypes\Data\\'.basename($customType);
            } else {
                $parts      = explode('/', $storeCode);
                $customType = '\\'.ucfirst($parts[0]).'\\'.ucfirst($parts[1]).'\CustomTypes\Data\\'.basename($customType);
            }
        }

        if ($parent !== null && isset($this->stores['data'][$project][$storeCode]['records'][$parent]) === false) {
            return null;
        }

        $this->dataRecordSequence++;
        $recordid = $this->dataRecordSequence.'.1';

        $this->stores['data'][$project][$storeCode]['records'][$recordid] = [
            'id'        => $recordid,
            'typeClass' => $customType,
            'depth'     => 1,
            'children'  => [],
            'parent'    => $parent,
        ];

        if ($parent !== null) {
            $this->stores['data'][$project][$storeCode]['records'][$parent]['children'][$recordid] = $this->stores['data'][$project][$storeCode]['records'][$recordid];
            $this->stores['data'][$project][$storeCode]['records'][$recordid]['depth']            += $this->stores['data'][$project][$storeCode]['records'][$parent]['depth'];
        }

        return $this->stores['data'][$project][$storeCode]['records'][$recordid];

    }//end createDataRecord()


    /**
     * Gets a data record.
     *
     * @param string $storeCode The store code the data record belongs to.
     * @param string $id        The id of the data record.
     *
     * @return mixed
     */
    public function getDataRecord(string $storeCode, string $id)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['data'][$project][$storeCode]['records'][$id]) === true) {
            return $this->stores['data'][$project][$storeCode]['records'][$id];
        }

        return null;

    }//end getDataRecord()


    /**
     * Gets unique data record value.
     *
     * @param string $storeCode  The store we are looking in.
     * @param string $propertyid The unique property code.
     * @param string $value      The value.
     *
     * @return mixed.
     */
    public function getDataRecordByValue(string $storeCode, string $propertyid, string $value)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        $id      = ($this->stores['data'][$project][$storeCode]['uniqueMap'][$propertyid][$value] ?? null);
        if ($id === null) {
            return null;
        }

        return $this->getDataRecord($storeCode, $id);

    }//end getDataRecordByValue()


    /**
     * Creates a User.
     *
     * @param string $storeCode  The store the data record will belong to.
     * @param string $customType The type of the data record.
     * @param string $parent     The parent of the data record.
     *
     * @return mixed
     */
    public function createUser(string $storeCode, string $username, string $firstName, string $lastName, string $type=null, array $groups=[])
    {
        $this->userSequence++;

        $project  = Bootstrap::getProjectPrefix($storeCode);
        $recordid = $this->userSequence.'.1';
        $this->stores['user'][$project][$storeCode]['records'][$recordid] = [
            'id'        => $recordid,
            'username'  => $username,
            'typeClass' => '\PerspectiveAPI\Objects\Types\User',
            'groups'    => $groups,
            'firstName' => $firstName,
            'lastName'  => $lastName,
        ];

        $this->stores['user'][$project][$storeCode]['usernameMap'][$username] = $recordid;

        return $this->stores['user'][$project][$storeCode]['records'][$recordid];

    }//end createUser()


    /**
     * Creates a user group.
     *
     * @param string $storeCode The store the group will belong to.
     * @param string $groupName The name of the group.
     * @param string $type      User type code.
     *                          TODO: this is a palceholder until user types are implemented.
     * @param array  $groups    Optional. Parent user groups to assign the new user to. If left empty, user will be
     *                          created under root user group.
     *
     * @return array
     */
    public function createGroup(string $storeCode, string $groupName, string $type, array $groups=[])
    {
        $this->userGroupSequence++;

        $project  = Bootstrap::getProjectPrefix($storeCode);
        $recordid = $this->userGroupSequence.'.1';

        $this->stores['user'][$project][$storeCode]['groups'][$recordid] = [
            'groupid'   => $recordid,
            'groupName' => $groupName,
            'type'      => null,
            'groups'    => $groups,
        ];

        return $this->getGroup($storeCode, $recordid);

    }//end createGroup()


    /**
     * Gets a user group.
     *
     * @param string $storeCode The store the group belongs to.
     * @param string $groupid   The groupid of the group.
     *
     * @return mixed
     */
    public function getGroup(string $storeCode, string $groupid)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['groups'][$groupid]) === false) {
            return null;
        }

        return $this->stores['user'][$project][$storeCode]['groups'][$groupid];

    }//end getGroup()


    /**
     * Gets a user by username.
     *
     * @param string $storeCode The store the user belongs to.
     * @param string $username  The username to search for.
     *
     * @return mixed
     */
    public function getUserByUsername(string $storeCode, string $username)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['usernameMap'][$username]) === false) {
            return null;
        }

        return $this->getUser($storeCode, $this->stores['user'][$project][$storeCode]['usernameMap'][$username]);

    }//end getUserByUsername()


    /**
     * Gets a user.
     *
     * @param string $storeCode The store the user belongs to.
     * @param string $userid    The userid to search for.
     *
     * @return mixed
     */
    public function getUser(string $storeCode, string $userid)
    {
        $project = Bootstrap::getProjectPrefix($storeCode);
        if (isset($this->stores['user'][$project][$storeCode]['records'][$userid]) === false) {
            return null;
        }

        return $this->stores['user'][$project][$storeCode]['records'][$userid];

    }//end getUser()


}//end class
