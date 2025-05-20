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
    private static $failFunction;

    protected function initMocks(callable $fail, string $namespace = __NAMESPACE__): void
    {
        if (!$this->initialized) {
            self::$failFunction = $fail;
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

                return self::$mockFileExistsResult[$file];
            });
            $mockFilePutContents = $this->getFunctionMock($namespace, 'file_put_contents');
            $mockFilePutContents->expects(TestCase::any())->willReturnCallback(
                static function ($file, string $content): int {
                    ++self::$mockFilePutContentsCount;
                    self::$mockFilePutContentsParam[] = $file;
                    self::$mockFilePutContentsContent[] = $content;

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
}
