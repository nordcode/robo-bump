<?php

namespace NordCode\RoboBump;

/**
 * The class represents a semver compatible version string parsed into its individual components
 * Support for pre-release versions and especially builds is limited because of the various implementations
 * Pre-release stages rc, beta and alpha are supported
 *
 * @link http://semver.org/
 *
 * @property int    $major
 * @property int    $minor
 * @property int    $patch
 * @property string $pre
 * @property int    $preVersion
 * @property string $build
 * @method Version major()
 * @method Version decreaseMajor()
 * @method Version minor()
 * @method Version decreaseMinor()
 * @method Version patch()
 * @method Version decreasePatch()
 * @method Version preVersion()
 * @method Version decreasePreVersion()
 * @method Version build()
 * @method Version decreaseBuild()
 * @method Version rc($preVersion = null)
 * @method Version beta($preVersion = null)
 * @method Version alpha($preVersion = null)
 */
class Version
{
    /**
     * RegEx to capture:
     * 1: Major version
     * 2: Minor version
     * 3: Patch version
     * 4: Pre and pre-version (optional)
     * 5: Build version (optional)
     *
     * @var string
     */
    const VERSION_REGEX = '/(\d+)\.(\d+)\.(\d+)(?:-((?:dev|beta|b|alpha|a|rc)(?:\.?\d+)?))?(?:\+(.+))?/i';

    /**
     * List of supported pre-release stages
     * Please keep the order from stable to early (left to right)
     * Stage "dev" is no pre-release version
     *
     * @var array
     */
    protected $preStages = ['rc', 'beta', 'alpha'];

    /**
     * @var int
     */
    protected $major;

    /**
     * @var int
     */
    protected $minor;

    /**
     * @var int
     */
    protected $patch;

    /**
     * @var string
     */
    protected $pre;

    /**
     * @var int
     */
    protected $preVersion;

    /**
     * @var int
     */
    protected $build;

    /**
     * Indicates if the pre-release version should be included, even if we are at first pre-release version
     * Enabled this will be 1.0.0-rc1
     * Disabled it is 1.0.0-rc
     *
     * @var bool
     */
    protected $alwaysIncludePreReleaseVersion = true;

    /**
     * @param int    $major The major version number
     * @param int    $minor The minor version number
     * @param int    $patch The patch version number
     * @param string $pre   The pre-release identifier
     * @param int    $build The build number
     */
    public function __construct($major = 0, $minor = 0, $patch = 0, $pre = null, $build = 0)
    {
        $this->major = (int)$major;
        $this->minor = (int)$minor;
        $this->patch = (int)$patch;
        $this->importPre($pre);
        $this->build = (int)$build;
    }

    /**
     * @param string $version
     *
     * @return static
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString($version)
    {
        if (!preg_match(self::VERSION_REGEX, $version, $matches)) {
            throw new \InvalidArgumentException('Unsupported version string given ' . $version);
        }

        $matches += ['', 0, 0, 0, null, 0];

        return new static($matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
    }

    public function __toString()
    {
        $version = sprintf("%d.%d.%d", $this->major, $this->minor, $this->patch);

        if ($this->pre) {
            $version .= '-' . $this->pre;

            if ($this->preVersion > 1 || $this->alwaysIncludePreReleaseVersion) {
                $version .= $this->preVersion;
            }
        }

        if ($this->build) {
            $version .= '+' . $this->build;
        }

        return $version;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'major':
            case 'minor':
            case 'patch':
            case 'pre':
            case 'preVersion':
            case 'build':
                return $this->{$name};
        }

        return null;
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'major':
            case 'minor':
            case 'patch':
            case 'preVersion':
            case 'build':
                $this->{$name} = (int)$value;
                break;
            case 'pre':
                $this->importPre($value);
                break;
        }
    }

    /**
     * Will capture increase*() and rc(), alpha(), beta() calls
     *
     * @see stable()
     * @see dev()
     *
     * @param string $name
     * @param array  $arguments
     * @return Version New version with modified properties
     */
    public function __call($name, $arguments)
    {
        $modify = clone $this;
        // if rc() or alpha() is called we will set $pre to it
        // if pre is already the same state, we will increase the preVersion
        // if an argument is given, we will assume it's the preVersion number
        if (in_array($name, $this->preStages)) {
            if ($name === $this->pre) {
                $preVersion = isset($arguments[0]) ? (int)$arguments[0] : (int)$this->preVersion + 1;
                $modify->preVersion = $preVersion;
            } else {
                $modify->pre = $name;
                $modify->preVersion = isset($arguments[0]) ? (int)$arguments[0] : 1;
            }

            return $modify;
        }

        // switch between increment and decrement
        if (substr($name, 0, 8) === 'decrease') {
            $target = lcfirst(substr($name, 8));
            $add = -1;
        } else {
            $target = $name;
            $add = 1;
        }

        if (!in_array($target, ['major', 'minor', 'patch', 'preVersion', 'build'])) {
            throw new \BadMethodCallException(
                'you can only increase/decrease major, minor, patch, preVersion, build, rc, beta and alpha'
            );
        }

        $modify->{$target} += $add;

        switch ($target) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'major':
                $modify->minor = 0;
            // intended fallthrough to reset patch also when major is modified
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'minor':
                $modify->patch = 0;
            // intended fallthrough to reset pre-release states on major, minor AND patch modification
            case 'patch':
                $modify->pre = null;
                $modify->preVersion = null;
        }

        // catch cases where the target is set to lower than 0 (or 
        if ($add < 0) {
            if ($target === 'preVersion' && $this->preVersion <= 1) {
                $modify->preVersion = 1;
            } elseif ($this->{$target} <= 0) {
                $modify->{$target} = 0;
            }
        }

        return $modify;
    }

    public function stable()
    {
        $modify = clone $this;
        $modify->pre = null;
        $modify->preVersion = null;
        return $modify;
    }

    public function dev()
    {
        $modify = clone $this;
        $modify->pre = 'dev';
        $modify->preVersion = null;
        return $modify;
    }

    /**
     * @see $alwaysIncludePreReleaseVersion
     * @param bool $yep
     * @return $this
     */
    public function alwaysIncludePreReleaseVersion($yep = true)
    {
        $this->alwaysIncludePreReleaseVersion = !!$yep;
        return $this;
    }

    /**
     * Parse pre-version identifiers like -rc.1 or -beta1 into $pre and $preVersion
     *
     * @param string $pre
     */
    protected function importPre($pre)
    {
        if (!$pre) {
            return;
        }

        $pre = strtolower($pre);

        // check if we get something like 1.0.0-rc1 or 2.1.0-alpha.1
        // in this case we can tell the pre-version
        if (preg_match('/([a-z]+)\.?(\d+)/', $pre, $matches) === 1) {
            $pre = $matches[1];
            $this->preVersion = (int)$matches[2];
        } else {
            $this->preVersion = 1;
        }

        $this->pre = $this->expandPreShorthand($pre);
    }

    protected function expandPreShorthand($pre)
    {
        $pre = strtolower($pre);
        switch ($pre) {
            case 'a':
                return 'alpha';
            case 'b':
                return 'beta';
        }

        return $pre;
    }
}
