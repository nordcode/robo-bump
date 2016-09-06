<?php

namespace NordCode\RoboBump\Test;

use NordCode\RoboBump\Version;

class VersionTest extends \PHPUnit_Framework_TestCase
{

    public function testFromString()
    {
        $version = Version::fromString('1.2.3-beta4+567');

        $this->assertEquals(1, $version->major);
        $this->assertEquals(2, $version->minor);
        $this->assertEquals(3, $version->patch);
        $this->assertEquals('beta', $version->pre);
        $this->assertEquals(4, $version->preVersion);
        $this->assertEquals(567, $version->build);
        $this->assertNull($version->foo);
    }


    public function testSetter()
    {
        $version = new Version();

        $version->major = 1;
        $version->pre = 'beta1';
        $version->build = 1234;
        $version->preVersion = 2;
        $this->assertEquals('1.0.0-beta2+1234', $version->__toString());
    }

    public function testShorthands()
    {
        $this->assertEquals('1.0.0-beta1', (new Version(1, 0, 0, 'b'))->__toString());
        $this->assertEquals('1.0.0-alpha1', (new Version(1, 0, 0, 'a'))->__toString());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function fromStringFailsForInvalidVersions()
    {
        Version::fromString('dev-fff');
    }

    public function testModifiers()
    {
        $this->assertEquals('2.0.0', (new Version(1, 2))->major()->__toString());
        $this->assertEquals('2.1.0', (new Version(2, 0, 1))->minor()->__toString());
        $this->assertEquals('0.0.0', (new Version(1, 1, 1))->decreaseMajor()->__toString());
        $this->assertEquals('1.0.0-beta2', (new Version(1, 0, 0, 'beta'))->preVersion()->__toString());
        $this->assertEquals('1.0.0', (new Version(1, 0, 0))->decreasePatch()->__toString());
        $this->assertEquals('1.0.0-beta1', (new Version(1, 0, 0, 'beta1'))->decreasePreVersion()->__toString());
        $this->assertEquals('1.0.0+2', (new Version(1, 0, 0, null, 1))->build()->__toString());
    }

    /**
     * @test
     * @expectedException \BadMethodCallException
     */
    public function modifyingFailsForUnknownIdentifier()
    {
        (new Version())->foo();
    }

    public function testPreRelease()
    {
        $this->assertEquals('1.0.0-rc1', (new Version(1, 0, 0, 'beta2'))->rc()->__toString());
        $this->assertEquals('1.0.0-rc2', (new Version(1, 0, 0, 'rc'))->rc()->__toString());
        $this->assertEquals('1.0.0-beta4', (new Version(1, 0, 0))->beta(4)->__toString());
        $this->assertEquals('1.0.0', (new Version(1, 0, 0, 'beta2'))->stable()->__toString());
        $this->assertEquals('1.0.0-dev', (new Version(1, 0, 0))->dev()->__toString());
    }

    public function testAlwaysIncludePreReleaseVersion()
    {
        $version = new Version(1, 0, 0, 'rc');

        $version->alwaysIncludePreReleaseVersion(false);
        $this->assertEquals('1.0.0-rc', $version->__toString());
        $this->assertEquals('1.0.0-rc2', $version->rc()->__toString());
    }
}
