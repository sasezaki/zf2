#!/usr/bin/env php
<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

use Zend\Loader\StandardAutoloader;
use Zend\Console;
use Zend\Code\Scanner\DirectoryScanner;

/**
 * Generate class maps for use with autoloading.
 *
 * Usage:
 * --help|-h                    Get usage message
 * --library|-l [ <string> ]    Library to parse; if none provided, assumes
 *                              current directory
 * --output|-o [ <string> ]     Where to write autoload file; if not provided,
 *                              assumes "autoload_classmap.php" in library directory
 * --overwrite|-w               Whether or not to overwrite existing autoload
 *                              file
 */

$zfLibraryPath = getenv('LIB_PATH') ? getenv('LIB_PATH') : __DIR__ . '/../library';
if (is_dir($zfLibraryPath)) {
    // Try to load StandardAutoloader from library
    if (false === include($zfLibraryPath . '/Zend/Loader/StandardAutoloader.php')) {
        echo 'Unable to locate autoloader via library; aborting' . PHP_EOL;
        exit(2);
    }
} else {
    // Try to load StandardAutoloader from include_path
    if (false === include('Zend/Loader/StandardAutoloader.php')) {
        echo 'Unable to locate autoloader via include_path; aborting' . PHP_EOL;
        exit(2);
    }
}

$libraryPath = getcwd();

// Setup autoloading
$loader = new StandardAutoloader(array('autoregister_zf' => true));
$loader->register();

$rules = array(
    'help|h'      => 'Get usage message',
    //'output|o-s'  => '',
    'messageTemplatesDirs|m-s' => 'messageTemplates diretory - comma separated',
    'destination|d-s' => 'destination path for translate',
    'previous|p-s' => 'previous resources file to regenerate',
    'overwrite|w' => 'Whether or not to overwrite existing autoload file',
);

try {
    $opts = new Console\Getopt($rules);
    $opts->parse();
} catch (Console\Exception\RuntimeException $e) {
    echo $e->getUsageMessage();
    exit(2);
}

if ($opts->getOption('h')) {
    echo $opts->getUsageMessage();
    exit(0);
}

if ($opts->getOption('m')) {
    $messageTemplate_dirs = explode($opts->getOption('m'), ',');
} else {
    $messageTemplate_dirs = array(dirname(__DIR__).'/library/Zend/I18n', dirname(__DIR__).'/library/Zend/Validator');
}

if (count($messageTemplate_dirs) > 1) {
    $translateSet = array();
    foreach ($messageTemplate_dirs as $dir) {
        $translateSet = array_merge($translateSet, scan_messageTemplates($dir));
    }
} else {
    $translateSet = $messageTemplate_dirs;
}


ksort($translateSet);
$translateSet = replace($translateSet, include $opts->getOption('p'));

make_resourcefile($translateSet);

function scan_messageTemplates($dir) {
    $scanner = new DirectoryScanner($dir);
    $classes = $scanner->getClasses();

    $translateSet = array();
    foreach ($classes as $class) {
        foreach ($class->getProperties() as $property) {
            if ($property->getName() === 'messageTemplates') {
                $reflection = new ReflectionClass($class->getName());
                $defaults = $reflection->getDefaultProperties();

                $translateSet[str_replace('\\', '_', $class->getName())] = $defaults['messageTemplates'];
            }
        }
    }

    return $translateSet;
}

function replace($en, $target) {
    $translateSet = array();
    foreach ($en as $k => $m) {
        foreach ($m as $key => $message) {
            $translateSet[$k][$message] = (isset($target[$message]))? $target[$message] : $message;
        }
    }
    return $translateSet;
}

function make_resourcefile($translateSet, $destination = 'resources/Zend_Validate.php') {
    $translate = "\n";

    $alreadyKeyHas = array();
    foreach ($translateSet as $class_name => $messages) {
        $translate .= "    // $class_name"."\n";
        foreach ($messages as $key => $message) {
            if (in_array($key, $alreadyKeyHas)) {
                $translate .= <<<EOF
    // "$key" - same message already appeared above.

EOF;
                continue;
            }
            $alreadyKeyHas[]= $key;

            $translate .= <<<EOF
    "$key" => "$message",
EOF;
            $translate .= "\n";
        }
        $translate .= "\n";
    }

    file_put_contents($destination, "<?php \nreturn array($translate);");
}
