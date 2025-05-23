<?php
/**
 * PHP 8.2 Compatibility Verification Script
 * 
 * This script tests the compatibility of Zend Framework 1 with PHP 8.2
 * focusing particularly on interface implementations and deprecated features.
 */

// Set up error reporting to catch all issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set include path to include the Zend Framework
set_include_path(implode(PATH_SEPARATOR, [
    __DIR__.'/library',
    get_include_path(),
]));

// Function to display test results
function test_result($component, $test, $passed, $message = '')
{
    echo str_pad("[$component] $test", 60).": ";
    if ($passed) {
        echo "\033[32mPASS\033[0m";
    } else {
        echo "\033[31mFAIL\033[0m";
    }
    if ($message) {
        echo " - $message";
    }
    echo PHP_EOL;

    return $passed;
}

// Track overall test results
$allPassed = true;

echo "PHP 8.2 Compatibility Verification Test\n";
echo "======================================\n\n";
echo "Testing with PHP ".PHP_VERSION."\n\n";

// Test 1: Zend_Config
try {
    require_once 'Zend/Config.php';
    $config = new Zend_Config(['test' => 'value']);

    // Test Countable
    $testPassed = $config->count() === 1;
    $allPassed &= test_result('Zend_Config', 'Countable::count()', $testPassed);

    // Test Iterator
    $config->rewind();
    $testPassed = $config->valid() === true;
    $allPassed &= test_result('Zend_Config', 'Iterator::valid()', $testPassed);

    $testPassed = $config->current() === 'value';
    $allPassed &= test_result('Zend_Config', 'Iterator::current()', $testPassed);

    $testPassed = $config->key() === 'test';
    $allPassed &= test_result('Zend_Config', 'Iterator::key()', $testPassed);

    $config->next();
    $testPassed = $config->valid() === false;
    $allPassed &= test_result('Zend_Config', 'Iterator::next()', $testPassed);

} catch (Throwable $e) {
    echo "Error in Zend_Config tests: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
    $allPassed = false;
}

// Test 2: Zend_Registry
try {
    require_once 'Zend/Registry.php';
    $registry = new Zend_Registry();

    $testPassed = ($registry->offsetExists('test') === false);
    $allPassed &= test_result('Zend_Registry', 'ArrayAccess::offsetExists()', $testPassed);

} catch (Throwable $e) {
    echo "Error in Zend_Registry tests: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
    $allPassed = false;
}

// Test 3: Zend_Form
try {
    require_once 'Zend/Form.php';
    $form = new Zend_Form();

    $testPassed = ($form->count() === 0);
    $allPassed &= test_result('Zend_Form', 'Countable::count()', $testPassed);

    $form->setElementsBelongTo('test');
    $testPassed = ($form->getElementsBelongTo() === 'test');
    $allPassed &= test_result('Zend_Form', 'ElementsBelongTo', $testPassed);

} catch (Throwable $e) {
    echo "Error in Zend_Form tests: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
    $allPassed = false;
}

// Test 4: Zend_Controller_Action_HelperBroker_PriorityStack
try {
    require_once 'Zend/Controller/Action/HelperBroker/PriorityStack.php';
    $stack = new Zend_Controller_Action_HelperBroker_PriorityStack();

    $testPassed = $stack->count() === 0;
    $allPassed &= test_result('PriorityStack', 'Countable::count()', $testPassed);

    $iterator = $stack->getIterator();
    $testPassed = $iterator instanceof ArrayObject;
    $allPassed &= test_result('PriorityStack', 'IteratorAggregate::getIterator()', $testPassed);

    $testPassed = $stack->offsetExists('test') === false;
    $allPassed &= test_result('PriorityStack', 'ArrayAccess::offsetExists()', $testPassed);

} catch (Throwable $e) {
    echo "Error in PriorityStack tests: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
    $allPassed = false;
}

// Test 5: Zend_Uri
try {
    require_once 'Zend/Uri.php';
    $testPassed = class_exists('Zend_Uri');
    $allPassed &= test_result('Zend_Uri', 'Class loading', $testPassed);

    // Test static methods
    $testPassed = (Zend_Uri::check('http://example.com') === true);
    $allPassed &= test_result('Zend_Uri', 'Static method check()', $testPassed);

} catch (Throwable $e) {
    echo "Error in Zend_Uri tests: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
    $allPassed = false;
}

// Test 6: Zend_Session_Namespace
try {
    require_once 'Zend/Session/Namespace.php';
    $testPassed = class_exists('Zend_Session_Namespace');
    $allPassed &= test_result('Zend_Session_Namespace', 'Class loading', $testPassed);

    // We don't instantiate since it tries to start a session

} catch (Throwable $e) {
    echo "Error in Zend_Session_Namespace tests: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
    $allPassed = false;
}

echo "\n======================================\n";
if ($allPassed) {
    echo "\033[32mALL TESTS PASSED\033[0m\n";
} else {
    echo "\033[31mSOME TESTS FAILED\033[0m\n";
}
echo "======================================\n";