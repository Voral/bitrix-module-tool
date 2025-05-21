<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool\Tests;

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
trait MockTrait
{
    use PHPMock;

    private bool $initialized = false;
    protected static array $mockFileExistsParam = [];
    protected static array $mockFileExistsResult = [];
    protected static int $mockFileExistsCount = 0;
    protected static int $mockFilePutContentsCount = 0;
    protected static array $mockFilePutContentsParam = [];
    protected static array $mockFilePutContentsContent = [];

    protected static array $mockRealPathParam = [];
    protected static int $mockRealPathCount = 0;
    protected static string $mockRealPathMessage = '';
    protected static array $mockRealPathResult = [];
    protected static int $mockGetEnvCount = 0;
    protected static array $mockGetEnvResult = [];
    protected static int $mockGetCwdCount = 0;
    protected static array $mockGetCwdResult = [];
    protected static string $mockGetCwdFailMessage = '';

    protected static int $mockFileGetContentsCount = 0;
    protected static array $mockFileGetContentsResult = [];
    protected static int $mockDirNameCount = 0;
    protected static array $mockDirNameResult = [];
    protected static int $mockIsDirCount = 0;
    protected static array $mockIsDirResult = [];
    protected static int $mockIsFileCount = 0;
    protected static array $mockIsFileResult = [];
    private static $failFunction;
    private static int $mockMkdirCount = 0;
    private static array $mockMkdirParam = [];
    private static array $mockMkdirResult = [];
    private static int $mockCopyCount = 0;
    private static array $mockCopyParam = [];

    protected function initMocks(callable $fail, string $namespace = __NAMESPACE__): void
    {
        if (!$this->initialized) {
            self::$failFunction = $fail;
            $mockCopy = $this->getFunctionMock($namespace, 'copy');
            $mockCopy->expects(TestCase::any())->willReturnCallback(static function ($from, $to): bool {
                ++self::$mockCopyCount;
                self::$mockCopyParam[$from] = $to;

                return true;
            });

            $mockMkdir = $this->getFunctionMock($namespace, 'mkdir');
            $mockMkdir->expects(TestCase::any())->willReturnCallback(static function ($path, $perms, $recursive): bool {
                ++self::$mockMkdirCount;
                self::$mockMkdirParam[$path] = [$perms, $recursive];

                return self::$mockMkdirResult[$path] ?? false;
            });

            $mockIsFile = $this->getFunctionMock($namespace, 'is_file');
            $mockIsFile->expects(TestCase::any())->willReturnCallback(static function ($path): bool {
                ++self::$mockIsFileCount;

                return self::$mockIsFileResult[$path] ?? false;
            });

            $mockIsDir = $this->getFunctionMock($namespace, 'is_dir');
            $mockIsDir->expects(TestCase::any())->willReturnCallback(static function ($path): bool {
                ++self::$mockIsDirCount;

                return self::$mockIsDirResult[$path] ?? false;
            });

            $mockDirName = $this->getFunctionMock($namespace, 'dirname');
            $mockDirName->expects(TestCase::any())->willReturnCallback(static function ($path): string {
                ++self::$mockDirNameCount;

                return self::$mockDirNameResult[$path] ?? '';
            });


            $mockGetCwd = $this->getFunctionMock($namespace, 'getcwd');
            $mockGetCwd->expects(TestCase::any())->willReturnCallback(static function (): bool|string {
                if ('' !== self::$mockGetCwdFailMessage) {
                    (self::$failFunction)(self::$mockGetCwdFailMessage);
                }
                $index = self::$mockGetCwdCount;
                ++self::$mockGetCwdCount;

                return self::$mockGetCwdResult[$index] ?? false;
            });
            $mockGetEnv = $this->getFunctionMock($namespace, 'getenv');
            $mockGetEnv->expects(TestCase::any())->willReturnCallback(static function ($key): false|string {
                ++self::$mockGetEnvCount;

                return self::$mockGetEnvResult[$key] ?? '';
            });
            $mockFileExists = $this->getFunctionMock($namespace, 'file_exists');
            $mockFileExists->expects(TestCase::any())->willReturnCallback(static function ($file): bool {
                self::$mockFileExistsParam[] = $file;
                ++self::$mockFileExistsCount;

                return self::$mockFileExistsResult[$file] ?? false;
            });
            $mockFilePutContents = $this->getFunctionMock($namespace, 'file_put_contents');
            $mockFilePutContents->expects(TestCase::any())->willReturnCallback(
                static function ($file, string $content): int {
                    ++self::$mockFilePutContentsCount;
                    self::$mockFilePutContentsParam[] = $file;
                    self::$mockFilePutContentsContent[$file] = $content;

                    return strlen($content);
                },
            );
            $mockFileGetContents = $this->getFunctionMock($namespace, 'file_get_contents');
            $mockFileGetContents->expects(TestCase::any())->willReturnCallback(
                static function ($file): false|string {
                    ++self::$mockFileGetContentsCount;

                    return self::$mockFileGetContentsResult[$file] ?? false;
                },
            );

            $mockRealPath = $this->getFunctionMock($namespace, 'realpath');
            $mockRealPath->expects(TestCase::any())->willReturnCallback(static function ($path): false|string {
                if ('' !== self::$mockRealPathMessage) {
                    (self::$failFunction)(self::$mockRealPathMessage);
                }
                self::$mockRealPathParam[] = $path;
                ++self::$mockRealPathCount;

                return self::$mockRealPathResult[$path] ?? false;
            });
            $this->initialized = true;
        }
    }

    protected function clearMockFileGetContents(array $result): void
    {
        self::$mockFileGetContentsCount = 0;
        self::$mockFileGetContentsResult = $result;
    }

    protected function clearMockGetCwd(array $result, string $failMessage = ''): void
    {
        self::$mockGetCwdFailMessage = $failMessage;
        self::$mockGetCwdCount = 0;
        self::$mockGetCwdResult = $result;
    }

    protected function clearMockGetEnv(array $result): void
    {
        self::$mockGetEnvCount = 0;
        self::$mockGetEnvResult = $result;
    }

    protected function clearMockFileExists(array $result): void
    {
        self::$mockFileExistsCount = 0;
        self::$mockFileExistsResult = $result;
        self::$mockFileExistsParam = [];
    }

    protected function clearMockFilePutContents(): void
    {
        self::$mockFilePutContentsCount = 0;
        self::$mockFilePutContentsParam = [];
        self::$mockFilePutContentsContent = [];
    }

    protected function clearMockRealPath(array $result, string $failMessage): void
    {
        self::$mockRealPathMessage = $failMessage;
        self::$mockRealPathParam = [];
        self::$mockRealPathCount = 0;
        self::$mockRealPathResult = $result;
    }

    protected function clearMockDirName(array $result): void
    {
        self::$mockDirNameCount = 0;
        self::$mockDirNameResult = $result;
    }

    protected function clearMockIsDir(array $result): void
    {
        self::$mockIsDirCount = 0;
        self::$mockIsDirResult = $result;
    }

    protected function clearMockIsFile(array $result): void
    {
        self::$mockIsFileCount = 0;
        self::$mockIsFileResult = $result;
    }

    protected function clearMockMkdir(array $result): void
    {
        self::$mockMkdirCount = 0;
        self::$mockMkdirParam = [];
        self::$mockMkdirResult = $result;
    }

    protected function clearMockCopy(): void
    {
        self::$mockCopyCount = 0;
        self::$mockCopyParam = [];
    }
}
