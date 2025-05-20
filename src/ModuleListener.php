<?php

declare(strict_types=1);

namespace Voral\BitrixModuleTool;

use Vasoft\VersionIncrement\Commits\CommitCollection;
use Vasoft\VersionIncrement\Commits\FileModifyType;
use Vasoft\VersionIncrement\Commits\ModifiedFile;
use Vasoft\VersionIncrement\Commits\Section;
use Vasoft\VersionIncrement\Config;
use Vasoft\VersionIncrement\Contract\EventListenerInterface;
use Vasoft\VersionIncrement\Events\Event;
use Vasoft\VersionIncrement\Exceptions\GitCommandException;
use Vasoft\VersionIncrement\SemanticVersionUpdater;
use Voral\BitrixModuleTool\Exception\ExtensionException;
use Voral\BitrixModuleTool\Exception\InvalidPathException;
use Voral\BitrixModuleTool\Exception\NotAccessibleException;
use Voral\BitrixModuleTool\Exception\NoVersionTagException;

class ModuleListener implements EventListenerInterface
{
    private readonly string $projectPath;
    private readonly string $sourcePath;
    private string $destinationPath;

    /**
     * @param Config               $config             Конфигурация version-increment
     * @param string               $moduleId           Идентификатор модуля
     * @param string               $sourcePath         Каталог с исходниками модуля
     * @param string               $destinationPath    Путь для файлов обновлений
     * @param string               $phpVersion         Версия PHP если необходим контроль в скрипте обновления
     * @param array<string,string> $modulesVersion     Требующиеся модули и их версии
     * @param array<string>        $excludeCommitTypes Типы коммитов не включаемые в файлы описания обновлений
     * @param array<string>        $lang               Символьные коды языков для создания описания обновлений
     * @param string               $includePhpFile     Путь у php файлу, код которого необходимо включить в скрипт обновления
     *
     * @throws ExtensionException
     */
    public function __construct(
        private readonly Config $config,
        private readonly string $moduleId,
        string $sourcePath = 'last_version',
        string $destinationPath = 'updates',
        private readonly string $phpVersion = '',
        private readonly array $modulesVersion = [],
        private readonly array $excludeCommitTypes = [],
        private readonly array $lang = ['ru', 'en'],
        private readonly string $includePhpFile = '',
    ) {
        $this->projectPath = $this->getProjectPath();
        $this->sourcePath = $this->normalizePath($sourcePath);
        $this->destinationPath = $this->normalizePath($destinationPath);
    }

    /**
     * @throws ExtensionException
     */
    private function getProjectPath(): string
    {
        $composer = getenv('COMPOSER');
        if (false !== $composer) {
            $path = $composer;
        } else {
            $path = getcwd();
            if (false === $path) {
                throw new NotAccessibleException();
            }
        }
        $pathReal = realpath($path);
        if (false === $pathReal) {
            throw new InvalidPathException($path);
        }

        return $pathReal . \DIRECTORY_SEPARATOR;
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim($path, " \t\n\r\0\x0B\\/");
        if (!str_starts_with($path, \DIRECTORY_SEPARATOR)) {
            $path = $this->projectPath . $path;
        }

        return $path . \DIRECTORY_SEPARATOR;
    }

    private function updateModuleVersion(string $version): void
    {
        $fileName = $this->sourcePath . '/install/version.php';
        $date = date('Y-m-d');
        if (file_exists($fileName)) {
            $content = file_get_contents($fileName);
            if ($content) {
                $content = (string) preg_replace(
                    '/[\'"]VERSION[\'"]\s*=>\s*[\'"](.*?)[\'"]/',
                    '\'VERSION\' => \'' . $version . '\'',
                    $content,
                );
                $content = (string) preg_replace(
                    '/[\'"]VERSION_DATE[\'"]\s*=>\s*[\'"](.*?)[\'"]/',
                    '\'VERSION_DATE\' => \'' . $date . '\'',
                    $content,
                );
            }
        } else {
            $content = <<<PHP
                <?php

                declare(strict_types=1);

                \$arModuleVersion = [
                    'VERSION' => '{$version}',
                    'VERSION_DATE' => '{$date}',
                ];

                PHP;
        }
        file_put_contents($fileName, $content);
    }

    public function handle(Event $event): void
    {
        /** @var string $lastTag */
        $lastTag = $event->getData(SemanticVersionUpdater::LAST_VERSION_TAG) ?? '';
        $lastTag = trim($lastTag);

        if (empty($lastTag)) {
            throw new NoVersionTagException();
        }
        $this->updateModuleVersion($event->version);
        $this->destinationPath .= $event->version . \DIRECTORY_SEPARATOR;
        [$copy, $remove] = $this->getModifiedFiles($lastTag);
        $copy[$this->destinationPath . 'install/version.php'] = $this->sourcePath . 'install/version.php';
        $this->copyFilesWithStructure($copy);
        $this->writeUpdater($remove, $event->version);
        $this->writeVersionControl();

        $commitCollection = $event->getData(SemanticVersionUpdater::COMMIT_LIST);
        if ($commitCollection instanceof CommitCollection) {
            $this->writeDescription($commitCollection);
        }
    }

    private function writeDescription(CommitCollection $commitCollection): void
    {
        if (empty($this->lang)) {
            return;
        }
        $content = '';
        /** @var Section $section */
        foreach ($commitCollection as $section) {
            foreach ($section->getCommits() as $commit) {
                if (in_array($commit->type, $this->excludeCommitTypes, true)) {
                    continue;
                }
                $content .= '- ' . $commit->comment . PHP_EOL;
            }
        }
        foreach ($this->lang as $lang) {
            $fileName = $this->destinationPath . 'description.' . $lang;
            file_put_contents($fileName, $content);
            $this->config->getVcsExecutor()->addFile($fileName);
        }
    }

    private function writeVersionControl(): void
    {
        if (empty($this->modulesVersion)) {
            return;
        }
        $rows = [];
        foreach ($this->modulesVersion as $moduleId => $version) {
            $rows[] = "{$moduleId},{$version}";
        }
        $fileName = $this->destinationPath . 'version_control.txt';
        file_put_contents($fileName, implode(PHP_EOL, $rows));
        $this->config->getVcsExecutor()->addFile($fileName);
    }

    /**
     * @param array<string> $remove
     *
     * @throws GitCommandException
     */
    private function writeUpdater(array $remove, string $version): void
    {
        $delete = $this->getRemoveFiles($remove);
        $copyDirectories = $this->getCopyDirectories();
        $versionPhp = $this->controlPhpVersion();

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            /**
            * @var CUpdater \$updater
            */

            {$versionPhp}
            {$delete}
            {$copyDirectories}

            PHP;
        $fileName = $this->destinationPath . 'updater' . $version . '.php';
        file_put_contents($fileName, $content);
        $this->config->getVcsExecutor()->addFile($fileName);
    }

    private function controlPhpVersion(): string
    {
        if ('' === $this->phpVersion) {
            return '';
        }

        return <<<PHP

            if (!version_compare(PHP_VERSION, '{$this->phpVersion}', '>=')) {
                \$updater->errorMessage[] = 'Requires PHP version {$this->phpVersion} and higher';
                return;
            }

            PHP;
    }

    private function getCopyDirectories(): string
    {
        $directory = $this->sourcePath . 'install';
        if (!is_dir($directory)) {
            return '';
        }

        $iterator = new \DirectoryIterator($directory);
        $commands = [];
        $adminExists = false;
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                if ('admin' === $fileInfo->getFilename()) {
                    $adminExists = true;

                    continue;
                }
                $commands[] = "    \$updater->CopyFiles('install/{$fileInfo->getFilename()}', '{$fileInfo->getFilename()}');";
            }
        }
        $contents = implode("\n", $commands);
        if ($adminExists) {
            $contents .= $this->getCopyAdminPages();
        }
        if ('' !== $this->includePhpFile && file_exists($this->includePhpFile)) {
            $code = file_get_contents($this->includePhpFile);
            if ($code) {
                $code = trim((string) preg_replace('/<\?(php)?/', '', $code));
                $contents .= PHP_EOL . PHP_EOL . $code;
            }
        }

        return <<<PHP

            if(IsModuleInstalled('{$this->moduleId}')){
            {$contents}
            }

            PHP;
    }

    private function formatVarExport(mixed $value): string
    {
        $export = var_export($value, true);

        return (string) preg_replace('/(?:\r\n|\r|\n)/', '$0    ', $export);
    }

    private function getCopyAdminPages(): string
    {
        $directory = $this->sourcePath . 'install/admin/';
        $iterator = new \DirectoryIterator($directory);
        $files = [];
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getFilename();
            }
        }
        $fileList = $this->formatVarExport($files);
        $modulePrefix = str_replace('.', '_', $this->moduleId) . '_';
        $path = "/bitrix/modules/{$this->moduleId}/install/admin/";

        return <<<PHP

                \$adminFiles = {$fileList};
                foreach (\$adminFiles as \$file) {
                    file_put_contents(
                        \$_SERVER["DOCUMENT_ROOT"].\$updater->kernelPath . '/admin/{$modulePrefix}' . \$file,
                        '<?php require(\$_SERVER["DOCUMENT_ROOT"].\\'{$path}'.\$file.'\\');',
                    );
                }
            PHP;
    }

    /**
     * @param array<string> $remove
     */
    private function getRemoveFiles(array $remove): string
    {
        if (empty($remove)) {
            return '';
        }
        foreach ($remove as &$file) {
            $file = str_replace($this->sourcePath, '/modules/' . $this->moduleId . '/', $file);
        }
        $delete = $this->formatVarExport($remove);

        return <<<PHP
            if(\$updater->CanUpdateKernel()) {
            // Отредактируйте пути в массиве удаляемых файлов
                \$filesForDelete = {$delete};
                foreach (\$filesForDelete as \$file) {
                    CUpdateSystem::DeleteDirFilesEx(\$_SERVER["DOCUMENT_ROOT"] . \$updater->kernelPath . "/" . \$file);
                }
            }

            PHP;
    }

    /**
     * @param array<string> $files
     */
    private function copyFilesWithStructure(array $files): void
    {
        foreach ($files as $file) {
            $relativePath = str_replace($this->sourcePath, '', $file);
            $destination = $this->destinationPath . $relativePath;
            $directory = dirname($destination);
            if (!is_dir($directory)) {
                mkdir($directory, 0o775, true);
            }
            if (is_file($file)) {
                copy($file, $destination);
            }
        }
    }

    /**
     * @return array<array<string>>
     *
     * @throws GitCommandException
     */
    private function getModifiedFiles(string $lastTag): array
    {
        $files = $this->config->getVcsExecutor()->getFilesSinceTag($lastTag, $this->sourcePath);
        $result = ['copy' => [], 'remove' => []];
        $result = array_reduce($files, [$this, 'reduceFiles'], $result);
        $copy = array_keys($result['copy']);
        $remove = array_keys($result['remove']);

        return [$this->normalizeFiles($copy), $this->normalizeFiles($remove)];
    }

    /**
     * @param array<string,array<string,true>> $result
     *
     * @return array<string,array<string,true>>
     */
    private function reduceFiles(array $result, ModifiedFile $file): array
    {
        switch ($file->type) {
            case FileModifyType::ADD:
            case FileModifyType::MODIFY:
                $this->unsetExists($file->path, $result['remove']);
                $result['copy'][$file->path] = true;
                break;
            case FileModifyType::DELETE:
                $this->unsetExists($file->path, $result['copy']);
                $result['remove'][$file->path] = true;
                break;
            case FileModifyType::RENAME:
                $this->unsetExists($file->destination, $result['remove']);
                $result['copy'][$file->destination] = true;
                $this->unsetExists($file->path, $result['copy']);
                $result['remove'][$file->path] = true;
                break;
            case FileModifyType::COPY:
                $this->unsetExists($file->destination, $result['remove']);
                $result['copy'][$file->destination] = true;
                break;
        }

        return $result;
    }

    /**
     * @param string             $path   Путь файлу
     * @param array<string,true> $result Агрегатор измененных файлов
     */
    private function unsetExists(string $path, array &$result): void
    {
        if (isset($result[$path])) {
            unset($result[$path]);
        }
    }

    /**
     * @param array<string> $files Массив файлов
     *
     * @return array<string>
     */
    private function normalizeFiles(array $files): array
    {
        $files = array_unique($files);
        foreach ($files as &$file) {
            $file = $this->projectPath . $file;
        }

        return $files;
    }
}
