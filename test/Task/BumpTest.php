<?php

namespace NordCode\RoboBump\Test\Task;

use League\Container\Container;
use NordCode\RoboBump\Task\Bump;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use Robo\Log\ResultPrinter;

class BumpTest extends \PHPUnit_Framework_TestCase
{
    const FILE_NAME = 'test.php';

    /**
     * @var Bump
     */
    private $fixture;

    /**
     * @var vfsStreamFile
     */
    private $file;

    /**
     * @var vfsStreamDirectory
     */
    private $dir;

    public function setUp()
    {
        $container = new Container();
        \Robo\Robo::configureContainer($container);
        \Robo\Robo::setContainer($container);
        $resultPrinter = $this->getMockBuilder(ResultPrinter::class)->disableProxyingToOriginalMethods()->getMock();
        \Robo\Robo::getContainer()->share('resultPrinter', $resultPrinter);
        $this->dir = vfsStream::setup();
        $this->file = new vfsStreamFile(self::FILE_NAME);
        $this->dir->addChild($this->file);
        $this->fixture = new Bump($this->file->url());
    }

    /**
     * @test
     */
    public function bumpsToFixedVersion()
    {
        $this->file->setContent(<<<FILE
/**
 * Library v1.0.0
 * Copyright 2011-2016 Acme Inc 
 */
FILE
        );
        $this->fixture->to('1.2.3')->run();

        $this->assertEquals(<<<FILE
/**
 * Library v1.2.3
 * Copyright 2011-2016 Acme Inc 
 */
FILE
            , $this->file->getContent());
    }

    /**
     * @test
     */
    public function bumpsToRelativeVersion()
    {
        $this->file->setContent(<<<FILE
/**
 * Library v1.0.0
 * Copyright 2011-2016 Acme Inc 
 */
FILE
        );
        $this->fixture->major()->minor()->patch()->rc(2)->run();

        $this->assertEquals(<<<FILE
/**
 * Library v2.1.1-rc2
 * Copyright 2011-2016 Acme Inc 
 */
FILE
            , $this->file->getContent());
    }

    /**
     * @test
     */
    public function bumpsInBlockCommentContext()
    {
        $this->file->setContent(<<<FILE
/**
 * Library v1.0.0
 * Copyright 2011-2016 Acme Inc 
 */

return [
    'version' => '1.0.0'
];
FILE
        );
        $this->fixture->context(Bump::CONTEXT_BLOCK_COMMENT)->to('1.2.3')->run();

        $this->assertEquals(<<<FILE
/**
 * Library v1.2.3
 * Copyright 2011-2016 Acme Inc 
 */

return [
    'version' => '1.0.0'
];
FILE
            , $this->file->getContent());
    }

    /**
     * @test
     */
    public function bumpsInLineCommentContext()
    {
        $this->file->setContent(<<<FILE
/**
 * Library v1.0.0
 * Copyright 2011-2016 Acme Inc 
 */
// v1.0.0
return [
    'version' => '1.0.0'
];
FILE
        );
        $this->fixture->context(Bump::CONTEXT_LINE_COMMENT)->to('1.2.3')->run();

        $this->assertEquals(<<<FILE
/**
 * Library v1.0.0
 * Copyright 2011-2016 Acme Inc 
 */
// v1.2.3
return [
    'version' => '1.0.0'
];
FILE
            , $this->file->getContent());
    }

    /**
     * @test
     */
    public function bumpsInPropertyContext()
    {
        $this->file->setContent(<<<FILE
/**
 * Library v1.0.0
 * Copyright 2011-2016 Acme Inc 
 */

return [
    'version' => '1.0.0',
    "version" => "1.0.0"
];
FILE
        );
        $this->fixture->context(Bump::CONTEXT_PROPERTY)->to('1.2.3')->run();

        $this->assertEquals(<<<FILE
/**
 * Library v1.0.0
 * Copyright 2011-2016 Acme Inc 
 */

return [
    'version' => '1.2.3',
    "version" => "1.2.3"
];
FILE
            , $this->file->getContent());
    }

    /**
     * @test
     */
    public function bumpFailsForNonExistentFiles()
    {
        $this->dir->removeChild(self::FILE_NAME);
        $result = $this->fixture->run();
        $this->assertFalse($result->wasSuccessful());
    }
}
