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
 * @package    Zend_Validate
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @see Zend_Validate_Abstract
 */
require_once 'Zend/Validate/Abstract.php';

/**
 * @category   Zend
 * @package    Zend_Validate
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Validate_PasswordStrength extends Zend_Validate_Abstract
{
    const ALL_WHITESPACE = 'allWhitespace';
    const NOT_LONG = 'notLong';
    const NO_NUMERIC = 'noNumeric';
    const NO_ALPHA = 'noAlpha';
    const NO_CAPITAL = 'noCapital';

    /**
     * @var array
     */
    protected $_messageTemplates = array(
        self::ALL_WHITESPACE => 'Password cannot consist of all whitespace.',
        self::NOT_LONG => 'Password must be at least %min% characters in length.',
        self::NO_NUMERIC => 'Password must contain at least 1 numeric character.',
        self::NO_ALPHA => 'Password must contain at least one alphabetic character.',
        self::NO_CAPITAL => 'Password must contain at least one capital letter.',
    );

    /**
     * @var array
     */
    protected $_messageVariables = array(
        'min' => '_minPasswordLength'
    );

    /**
     * Minimum password length
     *
     * @var integer
     */
    protected $_minPasswordLength = 8;

    /**
     * Require numeric character
     *
     * @var boolean
     */
    protected $_requireNumeric = true;

    /**
     * Require alpha character
     *
     * @var boolean
     */
    protected $_requireAlpha = true;

    /**
     * Require capital letter
     *
     * @var boolean
     */
    protected $_requireCapital = false;

    /**
     * Constructor
     *
     * @param array|Zend_Config $options
     */
    public function __construct($options = array())
    {
        if ($options instanceof Zend_Config) {
            $options = $options->toArray();
        }

        if (array_key_exists('minPasswordLength', $options)) {
            $this->setMinPasswordLength($options['minPasswordLength']);
        }

        if (array_key_exists('requireNumeric', $options)) {
            $this->setRequireNumeric($options['requireNumeric']);
        }

        if (array_key_exists('requireAlpha', $options)) {
            $this->setRequireAlpha($options['requireAlpha']);
        }

        if (array_key_exists('requireCapital', $options)) {
            $this->setRequireCapital($options['requireCapital']);
        }
    }

    /**
     * Returns the minPasswordLength option
     *
     * @return integer
     */
    public function getMinPasswordLength()
    {
        return $this->_minPasswordLength;
    }

    /**
     * Sets the minPasswordLength option
     *
     * @param integer $length
     * @return Zend_Validate_PasswordStrength Provides a fluent interface
     */
    public function setMinPasswordLength($length)
    {
        $this->_minPasswordLength = max(4, (integer) $length);
        return $this;
    }

    /**
     * Returns the requireNumeric option
     *
     * @return boolean
     */
    public function getRequireNumeric()
    {
        return $this->_requireNumeric;
    }

    /**
     * Sets the requireNumeric option
     *
     * @param boolean $flag
     * @return Zend_Validate_PasswordStrength Provides a fluent interface
     */
    public function setRequireNumeric($flag)
    {
        $this->_requireNumeric = (boolean) $flag;
        return $this;
    }

    /**
     * Returns the requireAlpha option
     *
     * @return boolean
     */
    public function getRequireAlpha()
    {
        return $this->_requireAlpha;
    }

    /**
     * Sets the requireAlpha option
     *
     * @param boolean $flag
     * @return Zend_Validate_PasswordStrength Provides a fluent interface
     */
    public function setRequireAlpha($flag)
    {
        $this->_requireAlpha = (boolean) $flag;
        return $this;
    }

    /**
     * Returns the requireCapital option
     *
     * @return boolean
     */
    public function getRequireCapital()
    {
        return $this->_requireCapital;
    }

    /**
     * Sets the requireCapital option
     *
     * @param boolean $flag
     * @return Zend_Validate_PasswordStrength Provides a fluent interface
     */
    public function setRequireCapital($flag)
    {
        $this->_requireCapital = (boolean) $flag;
        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $value meets all configured password strength rules.
     *
     * @param string $value
     * @param mixed  $context
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        if (! is_string($value)) {
            $value = (string) $value;
        }

        $this->_setValue($value);

        if (trim($value) === '') {
            $this->_error(self::ALL_WHITESPACE);
            return false;
        }

        if (strlen($value) < $this->_minPasswordLength) {
            $this->_error(self::NOT_LONG);
        }

        if ($this->_requireNumeric && ! preg_match('/\d/', $value)) {
            $this->_error(self::NO_NUMERIC);
        }

        if ($this->_requireAlpha && ! preg_match('/[a-z]/i', $value)) {
            $this->_error(self::NO_ALPHA);
        }

        if ($this->_requireCapital && ! preg_match('/[A-Z]/', $value)) {
            $this->_error(self::NO_CAPITAL);
        }

        return count($this->_messages) === 0;
    }

    /**
     * Return a string explaining the current password requirements
     *
     * @return string
     */
    public function getRequirementString()
    {
        $parts = array(
            "Passwords must be at least {$this->_minPasswordLength} characters long"
        );

        if ($this->_requireNumeric) {
            $parts[] = 'contain one digit';
        }
        if ($this->_requireAlpha) {
            $parts[] = 'contain one alpha character';
        }
        if ($this->_requireCapital) {
            $parts[] = 'have at least one uppercase letter';
        }

        $count = count($parts);
        if ($count === 1) {
            return $parts[0].'.';
        }

        if ($count === 2) {
            return $parts[0].' and '.$parts[1].'.';
        }

        $last = array_pop($parts);
        return implode(', ', $parts).' and '.$last.'.';
    }
}
