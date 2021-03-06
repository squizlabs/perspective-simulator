<?php
/**
 * Simulator Test class for Perspective Simulator.
 *
 * @package    Perspective
 * @subpackage Simulator
 * @author     Squiz Pty Ltd <products@squiz.net>
 * @copyright  2018 Squiz Pty Ltd (ABN 77 084 670 600)
 */

namespace PerspectiveSimulator\Tests;

use \PerspectiveSimulator\Bootstrap;

/**
 * SimulatorTest class
 */
abstract class SimulatorTest extends \PHPUnit\Framework\TestCase
{


    /**
     * Runs before the first test of the test case class is run.
     *
     * @return void
     */
    final public static function setUpBeforeClass()
    {
        $calledClass = get_called_class();
        $classParts  = explode('\\', $calledClass);

        if (empty($_SESSION) === true) {
            // "Start" the session for phpunit tests.
            $_SESSION = [];
        }

        Bootstrap::disableRead();
        Bootstrap::disableWrite();
        Bootstrap::disableNotifications();
        Bootstrap::load($classParts[0].'\\'.$classParts[1]);

    }//end setUpBeforeClass()


    /**
     * Gets the secret key for the project we are running tests on.
     *
     * @return string
     */
    final public function getSecretKey()
    {
        return \PerspectiveSimulator\Authentication::getSecretKey();

    }//end getSecretKey()


}//end class
