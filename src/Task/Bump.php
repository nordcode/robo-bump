<?php

namespace NordCode\RoboBump\Task;

use NordCode\RoboBump\Version;
use Robo\Common\DynamicParams;
use Robo\Task\BaseTask;
use Robo\Result;

/**
 * @method $this files(array|string $files)
 * @method $this context(array|string $contexts)
 * @method $this to(string $version)
 * @method $this major()
 * @method $this decreaseMajor()
 * @method $this minor()
 * @method $this decreaseMinor()
 * @method $this patch()
 * @method $this decreasePatch()
 * @method $this preVersion()
 * @method $this decreasePreVersion()
 * @method $this build()
 * @method $this decreaseBuild()
 * @method $this rc($preVersion = null)
 * @method $this beta($preVersion = null)
 * @method $this alpha($preVersion = null)
 */
class Bump extends BaseTask
{
    use DynamicParams {
        __call as dynamicParamAssign;
    }

    const CONTEXT_BLOCK_COMMENT = '/\/\*\*(.+?)(?<!\\\\)\*\//s';
    const CONTEXT_LINE_COMMENT = '/^\/(?<!\\\\)\/ *([^\n]+)$/m';
    const CONTEXT_PROPERTY = '/([\'|"]?version[\'|"]? *(?::|=>) *)([\'|"]?)(\d+\.\d+\.\d+?)[A-a|.|-]*\2/i';

    /**
     * Allowed methods by underlying Version class
     *
     * @see Version::__call()
     * @var array
     */
    const POSSIBLE_CALLS = [
        'major',
        'decreaseMajor',
        'minor',
        'decreaseMinor',
        'patch',
        'decreasePatch',
        'preVersion',
        'decreasePreVersion',
        'build',
        'decreaseBuild',
        'rc',
        'beta',
        'alpha'
    ];

    /**
     * List of files that should be respected
     *
     * @var array
     */
    protected $files = [];

    /**
     * List of contexts where versions should be replaced
     * Each item should be a RegExp matching the given context
     *
     * @var array
     */
    protected $context = [];

    /**
     * Array of methods that will be passed to the version instance
     *
     * @var array
     */
    protected $calls = [];

    /**
     * Optional fixed version string to bump to
     *
     * @var string
     */
    protected $to;

    /**
     * @param array|string $files
     */
    public function __construct($files)
    {
        $this->files($files);
    }

    /**
     * @param string $property
     * @param array  $args
     * @return $this
     */
    public function __call($property, $args)
    {
        // check if it's one of Version's methods. Otherwise pass the call to __call() of DynamicParams
        if (in_array($property, self::POSSIBLE_CALLS)) {
            $this->calls[] = [$property, $args];
            return $this;
        }

        return $this->dynamicParamAssign($property, $args);
    }

    /**
     * @return \Robo\Result
     */
    public function run()
    {
        // make sure we bump each file only once
        $files = array_unique($this->files);

        foreach ($files as $file) {
            $result = $this->bumpVersionInFile($file);
            if ($result instanceof Result) {
                return $result;
            }
        }
        return Result::success($this);
    }

    /**
     * @param string $file
     * @return bool
     */
    protected function bumpVersionInFile($file)
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return Result::error($this, 'Cannot open file ' . $file);
        }

        // fall back to some default contexts if nothing was set
        $contexts = $this->context;
        if (empty($contexts)) {
            $contexts = [self::CONTEXT_BLOCK_COMMENT, self::CONTEXT_LINE_COMMENT, self::CONTEXT_PROPERTY];
        }

        // loop over each context, find the context in the file and replace the version in the context
        // with the new version
        foreach ($contexts as $context) {
            $content = preg_replace_callback($context, function ($matches) {
                return $this->bumpVersionInString($matches[0]);
            }, $content);
        }


        return file_put_contents($file, $content) !== false;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function bumpVersionInString($string)
    {
        return preg_replace_callback(Version::VERSION_REGEX, function ($matches) {
            return $this->bump($matches[0]);
        }, $string);
    }

    /**
     * Modify the version string with the passed in version calls or the fixed set version
     *
     * @param string $versionString
     * @return string
     */
    protected function bump($versionString)
    {
        if ($this->to) {
            return $this->to;
        }

        $version = Version::fromString($versionString);
        foreach ($this->calls as list($method, $args)) {
            // modifiers will return a new version so we need to override it on each call
            $version = call_user_func_array([$version, $method], $args);
        }
        return $version->__toString();
    }
}