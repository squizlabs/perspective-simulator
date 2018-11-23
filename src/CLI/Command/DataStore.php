<?php
/**
 * Data Store class for Perspective Simulator CLI.
 *
 * @package    Perspective
 * @subpackage Simulator
 * @author     Squiz Pty Ltd <products@squiz.net>
 * @copyright  2018 Squiz Pty Ltd (ABN 77 084 670 600)
 */

namespace PerspectiveSimulator\CLI\Command;

require_once dirname(__FILE__).'/StoreTrait.inc';

use \PerspectiveSimulator\Libs;

/**
 * DataStore Class
 */
class DataStore
{
    use StoreTrait;


    /**
     * Constructor function.
     *
     * @param string $action The action we are going to perfom.
     * @param array  $args   An array of arguments to be used.
     *
     * @return void
     */
    public function __construct(string $action, array $args)
    {
        $projectDir         = Libs\FileSystem::getProjectDir();
        $this->storeDir     = $projectDir.'/Stores/Data/';
        $this->readableType = 'Data Store';
        $this->type         = 'DataStore';

        $this->setArgs($action, $args);

    }//end __construct()


}//end class