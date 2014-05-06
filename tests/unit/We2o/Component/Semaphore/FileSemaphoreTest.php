<?php

namespace We2o\Component\Semaphore\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use org\bovigo\vfs\vfsStreamWrapper;
use Psr\Log\LoggerInterface;
use We2o\Component\Semaphore\FileSemaphore;

/**
 * The FileSemaphoreTest class.
 *
 * @author Sobit Akhmedov <sobit.akhmedov@gmail.com>
 * @package We2o\Component\Semaphore\Tests
 */
class FileSemaphoreTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('sample_dir'));
    }

    /**
     * @group unit
     */
    public function testConstructCreatesDirectory()
    {
        $semaphore = new FileSemaphore(vfsStream::url('sample_dir/semaphore'), 3600, $this->getMock(LoggerInterface::class));
        $this->assertTrue(vfsStreamWrapper::getRoot()->hasChild('semaphore'));
    }

    /**
     * @group unit
     * @expectedException \RuntimeException
     */
    public function testConstructWithNonWritableRootDirectoryThrowsException()
    {
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('non_writable_dir', 0444));
        $semaphore = new FileSemaphore(vfsStream::url('non_writable_dir/semaphore'), 3600, $this->getMock(LoggerInterface::class));
    }

    /**
     * @group unit
     * @expectedException \RuntimeException
     */
    public function testConstructWithNonWritableDirectoryThrowsException()
    {
        vfsStreamWrapper::getRoot()->addChild(new vfsStreamDirectory('semaphore', 0444));
        $semaphore = new FileSemaphore(vfsStream::url('sample_dir/semaphore'), 3600, $this->getMock(LoggerInterface::class));
    }

    /**
     * @group unit
     */
    public function testAcquire()
    {
        $semaphore = new FileSemaphore(vfsStream::url('sample_dir/semaphore'), 3600, $this->getMock(LoggerInterface::class));

        /** @var vfsStreamDirectory $dir */
        $dir = vfsStreamWrapper::getRoot()->getChild('semaphore');
        $this->assertFalse($dir->hasChildren());

        $result = $semaphore->acquire('test-key');
        $this->assertTrue($result);
        $this->assertTrue($dir->hasChildren());
    }

    /**
     * @group unit
     */
    public function testAcquireSecondTimeFails()
    {
        $semaphore = new FileSemaphore(vfsStream::url('sample_dir/semaphore'), 3600, $this->getMock(LoggerInterface::class));

        $result = $semaphore->acquire('test-key');
        $this->assertTrue($result);

        $result = $semaphore->acquire('test-key');
        $this->assertFalse($result);
    }

    /**
     * @group unit
     */
    public function testReleaseDeletesFile()
    {
        $semaphore = new FileSemaphore(vfsStream::url('sample_dir/semaphore'), 3600, $this->getMock(LoggerInterface::class));

        /** @var vfsStreamDirectory $dir */
        $dir = vfsStreamWrapper::getRoot()->getChild('semaphore');
        $this->assertFalse($dir->hasChildren());

        $semaphore->acquire('test-key');
        $this->assertTrue($dir->hasChildren());

        $semaphore->release('test-key');
        $this->assertFalse($dir->hasChildren());
    }

    /**
     * @group unit
     */
    public function testAcquireSecondTimeAfterTimeoutReleasesPrevious()
    {
        $semaphore = new FileSemaphore(vfsStream::url('sample_dir/semaphore'), 50, $this->getMock(LoggerInterface::class));

        $result = $semaphore->acquire('test-key');
        $this->assertTrue($result);

        /** @var vfsStreamDirectory $dir */
        $dir = vfsStreamWrapper::getRoot()->getChild('semaphore');

        $result = $semaphore->acquire('test-key');
        $this->assertFalse($result);

        /** @var vfsStreamFile $file */
        $file = $dir->getChildren()[0];
        $file->setContent($file->getContent() - 100);

        $result = $semaphore->acquire('test-key');
        $this->assertTrue($result);

        $files = $dir->getChildren();
        $this->assertCount(1, $files);
    }
}