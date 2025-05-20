<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Tests;

use PHPUnit\Framework\TestCase;
use Vasoft\VersionIncrement\Commits\CommitCollection;
use Vasoft\VersionIncrement\Commits\Section;
use Vasoft\VersionIncrement\Config;
use Vasoft\VersionIncrement\Contract\VcsExecutorInterface;
use Vasoft\VersionIncrement\Events\Event;
use Vasoft\VersionIncrement\Events\EventType;
use Vasoft\VersionIncrement\SectionRules\DefaultRule;
use Vasoft\VersionIncrement\SemanticVersionUpdater;
use Voral\BitrixModuleTool\Exception\ExtensionException;
use Voral\BitrixModuleTool\Exception\NotAccessibleException;
use Voral\BitrixModuleTool\Exception\NoVersionTagException;
use Voral\BitrixModuleTool\Exception\WrongVersionFileException;
use Voral\BitrixModuleTool\ModuleListener;
use Vasoft\VersionIncrement\Exceptions\ApplicationException;

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
        $event = self::createMock(Event::class);
        $event->expects(self::exactly(1))->method('getData')
            ->willReturn(null);

        $listener = new ModuleListener($config, 'vendor.test');
        self::expectException(NoVersionTagException::class);
        self::expectExceptionMessage('No version tag found.');
        self::expectExceptionCode(5103);
        $listener->handle($event);
    }

    /**
     * @throws ExtensionException
     * @throws ApplicationException
     *
     * @dataProvider provideWrongVersionFileContentCases
     */
    public function testWrongVersionFileContent(false|string $content): void
    {
        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => '/home/project'], '');
        $this->clearMockFileExists([
            '/home/project/last_version/install/version.php' => true,
        ]);
        $this->clearMockFileGetContents([
            '/home/project/last_version/install/version.php' => $content,
        ]);


        $config = new Config();
        //        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        //        $gitExecutor->expects(self::never())->method('addFile');
        //        $gitExecutor->expects(self::never())->method('getFilesSinceTag');

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(1))->method('getData')
            ->willReturnCallback(static function (string $key): string {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => $this->getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test');
        self::expectException(WrongVersionFileException::class);
        self::expectExceptionMessage('Wrong version file format.');
        self::expectExceptionCode(5104);
        $listener->handle($event);
    }

    public static function provideWrongVersionFileContentCases(): iterable
    {
        yield [false];
        yield [''];
        yield [
            <<<'PHP'
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                ];
                PHP,
        ];
        yield [
            <<<'PHP'
                $arModuleVersion = [
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ];
    }

    private function getCommitCollection(): CommitCollection
    {
        $config = new Config();
        $sections = [
            'feat' => new Section('feat', 'Feature', false, [new DefaultRule('feat')], false, false, $config),
        ];

        return new CommitCollection($sections, $sections['feat']);
    }
}
