<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Tests;

use PHPUnit\Framework\TestCase;
use Vasoft\VersionIncrement\Commits\Commit;
use Vasoft\VersionIncrement\Commits\CommitCollection;
use Vasoft\VersionIncrement\Commits\FileModifyType;
use Vasoft\VersionIncrement\Commits\ModifiedFile;
use Vasoft\VersionIncrement\Commits\Section;
use Vasoft\VersionIncrement\Config;
use Vasoft\VersionIncrement\Contract\VcsExecutorInterface;
use Vasoft\VersionIncrement\Events\Event;
use Vasoft\VersionIncrement\Events\EventType;
use Vasoft\VersionIncrement\SectionRules\DefaultRule;
use Vasoft\VersionIncrement\SemanticVersionUpdater;
use Voral\BitrixModuleTool\Exception\ExtensionException;
use Voral\BitrixModuleTool\Exception\InvalidPathException;
use Voral\BitrixModuleTool\Exception\NotAccessibleException;
use Voral\BitrixModuleTool\Exception\NoVersionTagException;
use Voral\BitrixModuleTool\Exception\WrongPathException;
use Voral\BitrixModuleTool\Exception\WrongVersionFileException;
use Voral\BitrixModuleTool\ModuleListener;
use Vasoft\VersionIncrement\Exceptions\ApplicationException;
use PHPUnit\Framework\MockObject\Exception;

include_once __DIR__ . '/MockTrait.php';

/**
 * @internal
 *
 * @coversDefaultClass  \Voral\BitrixModuleTool\ModuleListener
 *
 * @covers \Voral\BitrixModuleTool\Exception\ExtensionException
 * @covers \Voral\BitrixModuleTool\Exception\NotAccessibleException
 * @covers \Voral\BitrixModuleTool\Exception\NoVersionTagException
 * @covers \Voral\BitrixModuleTool\Exception\WrongVersionFileException
 * @covers \Voral\BitrixModuleTool\ModuleListener
 */
final class ModuleListenerTest extends TestCase
{
    use MockTrait;

    private static string $proDir = __DIR__ . '/fake';
    private static string $dstDir = __DIR__ . '/fake/updates';
    private static string $srcDir = __DIR__ . '/fake/last_version';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $dir = self::$dstDir . '/1.1.0/install/';
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        if (!is_dir(self::$srcDir)) {
            mkdir(self::$srcDir, 0o775, true);
        }
        if (!is_dir(self::$dstDir . '_alt')) {
            mkdir(self::$dstDir . '_alt', 0o775, true);
        }
        if (!is_dir(self::$srcDir . '_alt')) {
            mkdir(self::$srcDir . '_alt', 0o775, true);
        }
    }

    private function createFakeFiles(): void
    {
        $dir = self::$dstDir . '/1.1.0/install/admin/';
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        if (!file_exists($dir . '/test1.php')) {
            file_put_contents($dir . '/test1.php', '<?php echo "test";');
        }
        if (!file_exists($dir . '/test2.php')) {
            file_put_contents($dir . '/test2.php', '<?php echo "test";');
        }

        $dir = self::$dstDir . '/1.1.0/install/components/vendor/test/';
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        if (!file_exists($dir . '/class.php')) {
            file_put_contents($dir . '/class.php', '<?php echo "test";');
        }
    }

    private function removeFakeFiles(): void
    {
        $dir = self::$dstDir . '/1.1.0/install/admin/';
        if (file_exists($dir . '/test1.php')) {
            unlink($dir . '/test1.php');
        }
        if (file_exists($dir . '/test2.php')) {
            unlink($dir . '/test2.php');
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }

        $dir = self::$dstDir . '/1.1.0/install/components/vendor/test/';
        if (file_exists($dir . '/class.php')) {
            unlink($dir . '/class.php');
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
        $dir = self::$dstDir . '/1.1.0/install/components/vendor/';
        if (is_dir($dir)) {
            rmdir($dir);
        }
        $dir = self::$dstDir . '/1.1.0/install/components/';
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    protected function setUp(): void
    {
        ExtensionException::$errorCodeDelta = 0;
        $this->initMocks($this->fail(...), 'Voral\BitrixModuleTool');
    }

    protected function tearDown(): void
    {
        $this->removeFakeFiles();
    }

    /**
     * @throws ExtensionException
     *
     * @covers ::getProjectPath
     * @covers ::handle
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

    /**
     * @throws ExtensionException
     *
     * @covers ::getProjectPath
     * @covers ::handle
     * @covers ::normalizePath
     * @covers       \Voral\BitrixModuleTool\Exception\WrongPathException
     *
     * @dataProvider providePathNotRelatedCases
     */
    public function testPathNotRelated(string $sourcePath, string $destinationPath, string $message): void
    {
        $this->clearMockGetEnv(['COMPOSER' => self::$proDir]);
        $this->clearMockFileExists([
            self::$proDir => true,
            self::$dstDir => true,
            self::$srcDir => true,
        ]);
        $this->clearMockGetCwd([false]);
        $this->clearMockRealPath([self::$proDir => self::$proDir], '');

        self::expectException(WrongPathException::class);
        self::expectExceptionMessage($message);
        self::expectExceptionCode(5105);
        new ModuleListener(new Config(), 'vendor.test', $sourcePath, $destinationPath);
    }

    public static function providePathNotRelatedCases(): iterable
    {
        yield [
            '/src',
            'last_updates',
            'The path sourcePath should be valid and relative to the project root directory',
        ];
        yield [
            'unknown',
            'last_updates',
            'The path sourcePath should be valid and relative to the project root directory',
        ];
        yield [
            'updates',
            '/dst',
            'The path destinationPath should be valid and relative to the project root directory',
        ];
        yield [
            'updates',
            'unknown',
            'The path destinationPath should be valid and relative to the project root directory',
        ];
    }

    /**
     * @throws ExtensionException
     *
     * @covers ::getProjectPath
     * @covers ::handle
     * @covers       \Voral\BitrixModuleTool\Exception\InvalidPathException
     *
     * @dataProvider provideProjectPathNotAccessibleCases
     */
    public function testProjectInvalid(int $codeDelta): void
    {
        ExtensionException::$errorCodeDelta = $codeDelta;
        $this->clearMockGetEnv(['COMPOSER' => false]);
        $this->clearMockGetCwd([self::$proDir]);
        $this->clearMockRealPath([], '');

        self::expectException(InvalidPathException::class);
        self::expectExceptionMessage(
            'Unable to determine project path: the resolved path "' . self::$proDir . '" is invalid.',
        );
        self::expectExceptionCode(5102 + $codeDelta);
        new ModuleListener(new Config(), 'vendor.test');
    }

    public static function provideProjectPathNotAccessibleCases(): iterable
    {
        yield [0];
        yield [200];
    }

    /**
     * @throws ApplicationException
     * @throws Exception
     * @throws ExtensionException
     *
     * @covers ::handle
     */
    public function testNotExistsTag(): void
    {
        $this->clearMockGetEnv(['COMPOSER' => 'fake']);
        $this->clearMockFileExists([self::$dstDir => true, self::$srcDir => true]);
        $this->clearMockRealPath(['fake' => self::$proDir], '');
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
     *
     * @covers ::handle
     * @covers ::updateModuleVersion
     */
    public function testWrongVersionFileContent(false|string $content): void
    {
        $this->clearMockGetEnv(['COMPOSER' => 'fake']);
        $this->clearMockRealPath(['fake' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            self::$srcDir => true,
            self::$dstDir => true,
        ]);
        $this->clearMockFileGetContents([
            self::$srcDir . '/install/version.php' => $content,
        ]);
        $config = new Config();
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

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::__construct
     * @covers ::controlPhpVersion
     * @covers ::copyFilesWithStructure
     * @covers ::getCopyDirectories
     * @covers ::getProjectPath
     * @covers ::getRemoveFiles
     * @covers ::handle
     * @covers ::updateModuleVersion
     * @covers ::writeDescription
     * @covers ::writeUpdater
     * @covers ::writeVersionControl
     */
    public function testSingleEmptyCommitList(): void
    {
        $this->clearMockCopy();
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            self::$srcDir => true,
            self::$dstDir => true,
        ]);
        $this->clearMockDirName([
            self::$dstDir . '/1.1.0/install/version.php' => self::$dstDir . '/1.1.0/install',
        ]);
        $this->clearMockIsDir([self::$dstDir . '/1.1.0/install' => true]);
        $this->clearMockIsFile([self::$srcDir . '/install/version.php' => true]);

        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileGetContents([
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(3))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);
        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test');
        $listener->handle($event);
        $date = date('Y-m-d');
        self::assertSame(
            <<<PHP
                <?php
                \$arModuleVersion = [
                    'VERSION' => '1.1.0',
                    'VERSION_DATE' => '{$date}',
                ];
                PHP,
            self::$mockFilePutContentsContent[self::$srcDir . '/install/version.php'],
            'Wrong version file content',
        );
        self::assertSame(
            [self::$srcDir . '/install/version.php'],
            array_keys(self::$mockCopyParam),
            'Wrong copy files',
        );

        self::assertSame([
            self::$dstDir . '/1.1.0/updater1.1.0.php',
            self::$dstDir . '/1.1.0/description.ru',
            self::$dstDir . '/1.1.0/description.en',
        ], $addedFiles, 'Wrong files added to git');
        self::assertSame(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                /**
                * @var CUpdater $updater
                */



                PHP,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/updater1.1.0.php'],
            'Wrong updater file content',
        );
        self::assertSame('', self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/description.ru']);
        self::assertSame('', self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/description.en']);
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::__construct
     * @covers ::updateModuleVersion
     */
    public function testSingleCreateVersionFile(): void
    {
        $this->clearMockDirName(
            [self::$srcDir . '/install/version.php' => self::$srcDir . 'install'],
        );
        $this->clearMockIsDir([self::$dstDir . '/1.1.0/install' => false]);
        $this->clearMockIsFile([self::$srcDir . '/install/version.php' => true]);

        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => false,
            self::$srcDir => true,
            self::$dstDir => true,
        ]);

        $this->clearMockFileGetContents([]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(4))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test');
        $listener->handle($event);
        $date = date('Y-m-d');
        self::assertSame(
            <<<PHP
                <?php

                declare(strict_types=1);

                \$arModuleVersion = [
                    'VERSION' => '1.1.0',
                    'VERSION_DATE' => '{$date}',
                ];

                PHP,
            self::$mockFilePutContentsContent[self::$srcDir . '/install/version.php'],
            'Wrong version file content',
        );
        self::assertSame([
            self::$srcDir . '/install/version.php',
            self::$dstDir . '/1.1.0/updater1.1.0.php',
            self::$dstDir . '/1.1.0/description.ru',
            self::$dstDir . '/1.1.0/description.en',
        ], $addedFiles, 'Wrong files added to git');
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::normalizePath
     */
    public function testSingleEmptyLang(): void
    {
        $sourcePath = self::$srcDir . '_alt';
        $destinationPath = self::$dstDir . '_alt';
        $this->clearMockDirName(
            [$destinationPath . '/install/version.php' => [$destinationPath . 'install']],
        );
        $this->clearMockIsDir([$destinationPath . 'install' => false]);
        $this->clearMockIsFile([$destinationPath . '/install/version.php' => true]);

        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            $sourcePath . '/install/version.php' => true,
            $sourcePath => true,
            $destinationPath => true,
        ]);
        $this->clearMockFileGetContents([
            $sourcePath . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::once())
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener(
            $config,
            'vendor.test',
            sourcePath: 'last_version_alt',
            destinationPath: 'updates_alt',
            lang: [],
        );
        $listener->handle($event);
        self::assertSame([
            $destinationPath . '/1.1.0/updater1.1.0.php',
        ], $addedFiles, 'Wrong files added to git');
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::normalizePath
     * @covers ::writeVersionControl
     */
    public function testVersionControl(): void
    {
        $this->clearMockDirName([self::$dstDir . '/install/version.php' => self::$dstDir . '/install']);
        $this->clearMockIsDir([self::$dstDir . '/install' => false]);
        $this->clearMockIsFile([self::$dstDir . '/install/version.php' => true]);
        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            self::$srcDir => true,
            self::$dstDir => true,
        ]);
        $this->clearMockFileGetContents([
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(2))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener(
            $config,
            'vendor.test',
            modulesVersion: ['main' => '20.0.0', 'sale' => '15.0.0'],
            lang: [],
        );
        $listener->handle($event);
        self::assertSame([
            self::$dstDir . '/1.1.0/updater1.1.0.php',
            self::$dstDir . '/1.1.0/version_control.txt',
        ], $addedFiles, 'Wrong files added to git');
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::writeDescription
     */
    public function testDescriptionAll(): void
    {
        $this->clearMockDirName(
            [self::$dstDir . '/install/version.php' => self::$dstDir . 'install'],
        );
        $this->clearMockIsDir([self::$dstDir . 'install' => false]);
        $this->clearMockIsFile([self::$dstDir . '/install/version.php' => true]);

        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            self::$srcDir => true,
            self::$dstDir => true,
        ]);
        $this->clearMockFileGetContents([
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(3))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();

        $commits = self::getCommitCollection();
        $commits->add(new Commit('feat: Update 1', 'feat', 'Update 1', false));
        $commits->add(new Commit('feat: Update 2', 'feat', 'Update 2', false));
        $commits->add(new Commit('fix: Fix', 'fix', 'Fix', false));

        $expectedDescription = <<<'TXT'
            - Update 1
            - Update 2
            - Fix

            TXT;


        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key) use ($commits): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => $commits,
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test');
        $listener->handle($event);
        self::assertSame([
            self::$dstDir . '/1.1.0/updater1.1.0.php',
            self::$dstDir . '/1.1.0/description.ru',
            self::$dstDir . '/1.1.0/description.en',
        ], $addedFiles, 'Wrong files added to git');
        self::assertSame(
            $expectedDescription,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/description.ru'],
        );
        self::assertSame(
            $expectedDescription,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/description.en'],
        );
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::writeDescription
     */
    public function testDescriptionFiltered(): void
    {
        $this->clearMockDirName([self::$dstDir . '/install/version.php' => self::$dstDir . '/install']);
        $this->clearMockIsDir([self::$dstDir . '/install' => false]);
        $this->clearMockIsFile([self::$dstDir . '/install/version.php' => true]);
        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            self::$dstDir => true,
            self::$srcDir => true,
        ]);
        $this->clearMockFileGetContents([
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(3))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();

        $commits = self::getCommitCollection();
        $commits->add(new Commit('feat: Update 1', 'feat', 'Update 1', false));
        $commits->add(new Commit('feat: Update 2', 'feat', 'Update 2', false));
        $commits->add(new Commit('fix: Fix', 'fix', 'Fix', false));

        $expectedDescription = <<<'TXT'
            - Fix

            TXT;


        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key) use ($commits): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => $commits,
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test', excludeCommitTypes: ['feat']);
        $listener->handle($event);
        self::assertSame([
            self::$dstDir . '/1.1.0/updater1.1.0.php',
            self::$dstDir . '/1.1.0/description.ru',
            self::$dstDir . '/1.1.0/description.en',
        ], $addedFiles, 'Wrong files added to git');
        self::assertSame(
            $expectedDescription,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/description.ru'],
        );
        self::assertSame(
            $expectedDescription,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/description.en'],
        );
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::__construct
     * @covers ::getCopyDirectories
     * @covers ::getIncludes
     * @covers ::getModuleRelated
     * @covers ::writeDescription
     */
    public function testIncludeFile(): void
    {
        $this->clearMockDirName([self::$srcDir . '/install/version.php' => self::$srcDir . 'install']);
        $this->clearMockIsDir([
            self::$srcDir . '/install' => true,
            self::$dstDir . '/1.1.0/install' => true,
        ]);
        $this->clearMockIsFile([self::$srcDir . '/install/version.php' => true]);

        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            'file.php' => true,
            self::$dstDir => true,
            self::$srcDir => true,
        ]);
        $this->clearMockFileGetContents([
            'file.php' => '<?php echo "123";',
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(1))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test', lang: [], includePhpFile: 'file.php');
        $listener->handle($event);
        self::assertSame(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                /**
                * @var CUpdater $updater
                */

                if(IsModuleInstalled('vendor.test')){
                    echo "123";
                }

                PHP,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/updater1.1.0.php'],
            'Wrong updater file content',
        );
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::__construct
     * @covers ::controlPhpVersion
     * @covers ::getCopyDirectories
     * @covers ::getIncludes
     * @covers ::getModuleRelated
     * @covers ::writeDescription
     */
    public function testPhpVersion(): void
    {
        $this->clearMockDirName([self::$srcDir . '/install/version.php' => self::$srcDir . '/install']);
        $this->clearMockIsDir([self::$dstDir . '/install/version.php' => self::$dstDir . '/install']);
        $this->clearMockIsFile([self::$srcDir . '/install/version.php' => true]);
        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            'file.php' => true,
            self::$dstDir => true,
            self::$srcDir => true,
        ]);
        $this->clearMockFileGetContents([
            'file.php' => '<?php echo "123";',
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(1))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test', lang: [], includePhpFile: 'file.php', phpVersion: '7.4');
        $listener->handle($event);
        self::assertSame(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                /**
                * @var CUpdater $updater
                */

                if (!version_compare(PHP_VERSION, '7.4', '>=')) {
                    $updater->errorMessage[] = 'Requires PHP version 7.4 and higher';
                    return;
                }
                if(IsModuleInstalled('vendor.test')){
                    echo "123";
                }

                PHP,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/updater1.1.0.php'],
            'Wrong updater file content',
        );
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::writeDescription
     * @covers ::writeVersionControl
     */
    public function testSingleCustomLang(): void
    {
        $this->clearMockDirName([self::$srcDir . '/install/version.php' => self::$srcDir . 'install']);
        $this->clearMockIsDir([]);
        $this->clearMockIsFile([self::$srcDir . '/install/version.php' => true]);

        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            self::$dstDir => true,
            self::$srcDir => true,
        ]);
        $this->clearMockFileGetContents([
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022-01-01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::exactly(4))
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn([]);
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener($config, 'vendor.test', lang: ['ru', 'en', 'fr']);
        $listener->handle($event);
        self::assertSame([
            self::$dstDir . '/1.1.0/updater1.1.0.php',
            self::$dstDir . '/1.1.0/description.ru',
            self::$dstDir . '/1.1.0/description.en',
            self::$dstDir . '/1.1.0/description.fr',
        ], $addedFiles, 'Wrong files added to git');
    }

    /**
     * @throws ApplicationException
     * @throws ExtensionException
     * @throws Exception
     *
     * @covers ::__construct
     * @covers ::controlPhpVersion
     * @covers ::getCopyDirectories
     * @covers ::getIncludes
     * @covers ::getModifiedFiles
     * @covers ::getModuleRelated
     * @covers ::normalizeFiles
     * @covers ::reduceFiles
     * @covers ::unsetExists
     * @covers ::writeDescription
     */
    public function testCopyFiles(): void
    {
        $this->createFakeFiles();
        $this->clearMockCopy();
        $this->clearMockDirName([
            self::$dstDir . '/1.1.0/install/version.php' => self::$dstDir . '/1.1.0/install',
            self::$dstDir . '/1.1.0/lib/test.php' => self::$dstDir . '/1.1.0/lib',
            self::$dstDir . '/1.1.0/lib/test-new.php' => self::$dstDir . '/1.1.0/lib',
            self::$dstDir . '/1.1.0/lib/test2.php' => self::$dstDir . '/1.1.0/lib',
            self::$dstDir . '/1.1.0/lib/a.php' => self::$dstDir . '/1.1.0/lib',
        ]);
        $this->clearMockIsDir([
            self::$dstDir . '/1.1.0/install' => true,
            self::$dstDir . '/1.1.0/lib' => true,
        ]);
        $this->clearMockIsFile([
            self::$srcDir . '/install/version.php' => true,
            self::$srcDir . '/lib/test.php' => true,
            self::$srcDir . '/lib/test-new.php' => true,
            self::$srcDir . '/lib/test2.php' => true,
            self::$srcDir . '/lib/a.php' => true,
        ]);

        $this->clearMockGetEnv(['COMPOSER' => 'project']);
        $this->clearMockRealPath(['project' => self::$proDir], '');
        $this->clearMockFileExists([
            self::$srcDir . '/install/version.php' => true,
            self::$srcDir => true,
            self::$dstDir => true,
        ]);
        $this->clearMockFileGetContents([
            self::$srcDir . '/install/version.php' => <<<'PHP'
                <?php
                $arModuleVersion = [
                    'VERSION' => '1.0.0',
                    'VERSION_DATE' => '2022 - 01 - 01',
                ];
                PHP,
        ]);
        $this->clearMockFilePutContents();
        $addedFiles = [];
        $gitExecutor = self::createMock(VcsExecutorInterface::class);
        $gitExecutor->expects(self::once())
            ->method('addFile')
            ->willReturnCallback(static function (string $fileName) use (&$addedFiles): void {
                $addedFiles[] = $fileName;
            });
        $gitExecutor->expects(self::once())->method('getFilesSinceTag')
            ->willReturn($this->getChangedFiles(self::$srcDir));
        $config = new Config();
        $config->setVcsExecutor($gitExecutor);

        $event = $this->getMockBuilder(Event::class)
            ->setConstructorArgs([EventType::BEFORE_VERSION_SET, '1.1.0'])
            ->getMock();
        $event->expects(self::exactly(2))->method('getData')
            ->willReturnCallback(static function (string $key): mixed {
                return match ($key) {
                    SemanticVersionUpdater::LAST_VERSION_TAG => 'v1.0.0',
                    SemanticVersionUpdater::COMMIT_LIST => self::getCommitCollection(),
                    default => '',
                };
            });

        $listener = new ModuleListener(
            $config,
            'vendor.test',
            lang: [],
        );
        $listener->handle($event);
        self::assertSame(
            [
                self::$srcDir . '/install/version.php' => self::$dstDir . '/1.1.0/install/version.php',
                self::$srcDir . '/lib/test.php' => self::$dstDir . '/1.1.0/lib/test.php',
                self::$srcDir . '/lib/test-new.php' => self::$dstDir . '/1.1.0/lib/test-new.php',
                self::$srcDir . '/lib/test2.php' => self::$dstDir . '/1.1.0/lib/test2.php',
                self::$srcDir . '/lib/a.php' => self::$dstDir . '/1.1.0/lib/a.php',
            ],
            self::$mockCopyParam,
        );
        self::assertSame(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                /**
                * @var CUpdater $updater
                */

                if($updater->CanUpdateKernel()) {
                // Отредактируйте пути в массиве удаляемых файлов
                    $filesForDelete = array (
                      0 => '/modules/vendor.test/install/test.php',
                      1 => '/modules/vendor.test/lib/test-old.php',
                    );
                    foreach ($filesForDelete as $file) {
                        CUpdateSystem::DeleteDirFilesEx($_SERVER["DOCUMENT_ROOT"] . $updater->kernelPath . "/" . $file);
                    }
                }
                if(IsModuleInstalled('vendor.test')){
                    $updater->CopyFiles('install/components', 'components');
                    $adminFiles = array (
                      0 => 'test1.php',
                      1 => 'test2.php',
                    );
                    foreach ($adminFiles as $file) {
                        file_put_contents(
                            $_SERVER["DOCUMENT_ROOT"] . $updater->kernelPath . '/admin/vendor_test_' . $file,
                            '<?php require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/vendor.test/install/admin/' . $file . '\');',
                        );
                    }

                }

                PHP,
            self::$mockFilePutContentsContent[self::$dstDir . '/1.1.0/updater1.1.0.php'],
            'Wrong updater file content',
        );
    }

    private function getChangedFiles(string $path): array
    {
        return [
            new ModifiedFile(FileModifyType::ADD, 'last_version/install/version.php', ''),
            new ModifiedFile(FileModifyType::DELETE, 'last_version/install/test.php', ''),
            new ModifiedFile(FileModifyType::MODIFY, 'last_version/lib/test.php', ''),
            new ModifiedFile(FileModifyType::RENAME, 'last_version/lib/test-old.php', 'last_version/lib/test-new.php'),
            new ModifiedFile(FileModifyType::COPY, 'last_version/lib/test1.php', 'last_version/lib/test2.php'),
            new ModifiedFile(FileModifyType::DELETE, 'last_version/lib/a.php', ''),
            new ModifiedFile(FileModifyType::ADD, 'last_version/lib/a.php', ''),
        ];
    }

    private static function getCommitCollection(): CommitCollection
    {
        $config = new Config();
        $sections = [
            'feat' => new Section('feat', 'Feature', false, [new DefaultRule('feat')], false, false, $config),
            'fix' => new Section('fix', 'Fix', false, [new DefaultRule('fix')], false, false, $config),
        ];

        return new CommitCollection($sections, $sections['feat']);
    }
}
