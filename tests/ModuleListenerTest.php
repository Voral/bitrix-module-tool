<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Tests;

use PHPUnit\Framework\TestCase;
use Vasoft\VersionIncrement\Config;
use Vasoft\VersionIncrement\Contract\VcsExecutorInterface;
use Vasoft\VersionIncrement\Events\Event;
use Vasoft\VersionIncrement\SemanticVersionUpdater;
use Voral\BitrixModuleTool\Exception\ExtensionException;
use Voral\BitrixModuleTool\Exception\NotAccessibleException;
use Voral\BitrixModuleTool\ModuleListener;

include_once __DIR__ . '/MockTrait.php';

/**
 * @internal
 *
 * @coversDefaultClass \Voral\BitrixModuleTool\ModuleListener
 */
final class ModuleListenerTest extends TestCase
{
    use MockTrait;

    protected function setUp(): void
    {
        ExtensionException::$errorCodeDelta = 0;
        $this->initMocks($this->fail(...), 'Voral\BitrixModuleTool');
    }

    /**
     * @throws ExtensionException
     *
     * @covers ::getProjectPath
     *
     * @dataProvider provideProjectPathNotAccessibleCases
     */
    public function testProjectPathNotAccessible(int $codeDelta): void
    {
        ExtensionException::$errorCodeDelta = $codeDelta;
        $this->clearMockGetEnv(['COMPOSER' => false]);
        $this->clearMockGetCwd([false]);
        $this->clearMockRealPath([], 'Should not run the command realpath');

        self::expectException(NotAccessibleException::class);
        self::expectExceptionMessage('Unable to determine project path: current working directory is not accessible.');
        self::expectExceptionCode(5101 + $codeDelta);
        new ModuleListener(new Config(), 'vendor.test');
    }

    public static function provideProjectPathNotAccessibleCases(): iterable
    {
        yield [0];
        yield [200];
    }

    /**
     * @throws ExtensionException
     *
     * @covers ::getProjectPath
     *
     * @dataProvider provideProjectPathNotAccessibleCases
     */
    public function testProjectInvalid(int $codeDelta): void
    {
        ExtensionException::$errorCodeDelta = $codeDelta;
        $this->clearMockGetEnv(['COMPOSER' => false]);
        $this->clearMockGetCwd(['/home/project']);
        $this->clearMockRealPath([], '');

        self::expectException(ExtensionException::class);
        self::expectExceptionMessage('Unable to determine project path: the resolved path "/home/project" is invalid.');
        self::expectExceptionCode(5102 + $codeDelta);
        new ModuleListener(new Config(), 'vendor.test');
    }

    public function testNotExistsTag(): void
    {
        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockFileExists([false]);
        $this->clearMockRealPath(['project' => '/home/project'], '');
        $config = new Config();
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::never())->method('addFile');
        $gitExecutor->expects(self::never())->method('getFilesSinceTag');

        $event = self::createMock(Event::class);
        $event->expects(self::exactly(1))->method('getData')
            ->willReturn(null);

        $listener = new ModuleListener($config, 'vendor.test');
        $listener->handle($event);
        self::assertSame(0, self::$mockFileExistsCount);
    }
}
