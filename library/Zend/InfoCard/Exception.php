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
 * @package    Zend_InfoCard
 * @subpackage Zend_InfoCard
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Exception.php 24593 2012-01-05 20:35:02Z matthew $
 */

if (class_exists("Zend_Exception")) {
    #[AllowDynamicProperties]
    abstract class Zend_InfoCard_Exception_Abstract extends Zend_Exception
    {
    }
} else {
    #[AllowDynamicProperties]
    abstract class Zend_InfoCard_Exception_Abstract extends Exception
    {
    }
}

/**
 * Base Exception class for the InfoCard component
 *
 * @category   Zend
 * @package    Zend_InfoCard
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_InfoCard_Exception extends Zend_InfoCard_Exception_Abstract
{
}
