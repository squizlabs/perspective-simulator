<?php
/**
 * Data Record Property class for Perspective Simulator CLI.
 *
 * @package    Perspective
 * @subpackage Simulator
 * @author     Squiz Pty Ltd <products@squiz.net>
 * @copyright  2018 Squiz Pty Ltd (ABN 77 084 670 600)
 */

namespace PerspectiveSimulator\CLI\Command;

require_once dirname(__FILE__).'/PropertyTrait.inc';

use \PerspectiveSimulator\Libs;

/**
 * DataRecordProperty Class
 */
class DataRecordProperty
{
    use PropertyTrait;


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
        $this->storeDir     = $projectDir.'/Properties/Data/';
        $this->type         = 'datarecord';
        $this->readableType = 'Data Record';
        $this->setArgs($action, $args);

        if (is_dir($this->storeDir) === false) {
            Libs\FileSystem::mkdir($this->storeDir, true);
        }

    }//end __construct()


}//end class
