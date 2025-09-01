<?php

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 *
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 *
 * @version    $Id$
 */

/**
 * The Stomp client interacts with a Stomp server.
 *
 * @category   Zend
 *
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Queue_Stomp_Client
{
    /**
     * Array of $client Zend_Queue_Stomp_Client_Interface
     *
     * @var array
     */
    protected $_connection;

    /**
     * Add a server to connections
     *
     * @param string scheme
     * @param string host
     * @param int port
     */
    public function __construct(
        $scheme = null, $host = null, $port = null,
        $connectionClass = 'Zend_Queue_Stomp_Client_Connection',
        $frameClass = 'Zend_Queue_Stomp_Frame'
    ) {
        if (($scheme !== null)
            && ($host !== null)
            && ($port !== null)
        ) {
            $this->addConnection($scheme, $host, $port, $connectionClass);
            $this->getConnection()->setFrameClass($frameClass);
        }
    }

    /**
     * Shutdown
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->getConnection()) {
            $this->getConnection()->close(true);
        }
    }

    /**
     * Add a connection to this client.
     *
     * Attempts to add this class to the client.  Returns a boolean value
     * indicating success of operation.
     *
     * You cannot add more than 1 connection to the client at this time.
     *
     * @param  string  $scheme  ['tcp', 'udp']
     * @param string  host
     * @param int port
     * @param string  class - create a connection with this class; class must support Zend_Queue_Stomp_Client_ConnectionInterface
     * @return bool
     */
    public function addConnection($scheme, $host, $port, $class = 'Zend_Queue_Stomp_Client_Connection')
    {
        if (! class_exists($class)) {
            require_once 'Zend/Loader.php';
            Zend_Loader::loadClass($class);
        }

        $connection = new $class;

        if ($connection->open($scheme, $host, $port)) {
            $this->setConnection($connection);

            return true;
        }

        $connection->close();

        return false;
    }

    /**
     * Set client connection
     *
     * @return void
     */
    public function setConnection(Zend_Queue_Stomp_Client_ConnectionInterface $connection)
    {
        $this->_connection = $connection;

        return $this;
    }

    /**
     * Get client connection
     *
     * @return Zend_Queue_Stomp_Client_ConnectionInterface|null
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * Send a stomp frame
     *
     * Returns true if the frame was successfully sent.
     *
     * @return bool
     */
    public function send(Zend_Queue_Stomp_FrameInterface $frame)
    {
        $this->getConnection()->write($frame);

        return $this;
    }

    /**
     * Receive a frame
     *
     * Returns a frame or false if none were to be read.
     *
     * @return Zend_Queue_Stomp_FrameInterface|bool
     */
    public function receive()
    {
        return $this->getConnection()->read();
    }

    /**
     * canRead()
     *
     * @return bool
     */
    public function canRead()
    {
        return $this->getConnection()->canRead();
    }

    /**
     * creates a frame class
     *
     * @return Zend_Queue_Stomp_FrameInterface
     */
    public function createFrame()
    {
        return $this->getConnection()->createFrame();
    }
}
