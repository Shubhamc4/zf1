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
 * @subpackage Zend_InfoCard_Cipher
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Abstract.php 24593 2012-01-05 20:35:02Z matthew $
 */

/**
 * Zend_InfoCard_Cipher_Symmetric_Interface
 */
require_once 'Zend/InfoCard/Cipher/Symmetric/Interface.php';

/**
 * @category   Zend
 * @package    Zend_InfoCard
 * @subpackage Zend_InfoCard_Cipher
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
#[AllowDynamicProperties]
abstract class Zend_InfoCard_Cipher_Symmetric_Adapter_Abstract
    implements Zend_InfoCard_Cipher_Symmetric_Interface
{
}
