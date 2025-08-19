# Сборка пакета обновления модуля Битрикс

Расширение для пакета [voral/version-increment](https://github.com/Voral/vs-version-incrementor) автоматизирующее пакетов обновления модулей для Битрикс-маркетплейс на основе анализа git коммитов и семантического обновления версии. А так же изменяющее номер версии в файле version.php

[![Latest Version on Packagist](https://img.shields.io/packagist/v/voral/bitrix-module-tool)](https://packagist.org/packages/voral/bitrix-module-tool )  

## Основные функции

При вычислении новой версии утилитой voral/version-increment перед коммитом выполняются следующие этапы по сборке обновления версии:

1. Изменение версии модуля в соответствии с обновлением в файле install/version.php. А так же даты версии модуля на текущую. Если это файла нет в модуле - он будет создан.
2. На основе анализа коммитов выполненных с предыдущей версии копируются новые и измененные файлы модуля в каталог пакета обновления. Кроме того, при необходимости, добавляется код удаления файлов, которые были удалены из модуля в скрипт обновления `updater.php`
    >  Обратите внимание, что функционал удаления упрощенный - перед окончательным оформлением пакета обновлений рекомендую проверить и скорректировать при необходимости
3. Если в обновление были добавлены каталоги `install/admin`, `install/components` и т.п в скрипт `updater.php` добавляется код по их копированию. При этом для каталога admin выполняется не копирование, а создание файлов которые подключают оригинальные.
4. Если произведена соответствующая настройка в скрипт `updater.php` добавляется проверка версии PHP
5. Если произведена соответствующая настройка в скрипт `updater.php` добавляется дополнительный кастомный PHP код
6. На основе коммитов git выполненных с предыдущей версии формируются файлы description.* пакета обновлений
7. Если произведена соответствующая настройка генерируется файл контроля версий модулей от которых ваш модуль зависит
8. Файлы `updater.php`, `description.*`, а так же `<путь_к_исходникам>/install/version.php` (если он был создан) добавляются в репозиторий git

## Требования

- PHP >= 8.1
- Git установлен и доступен в CLI
- Composer для управления зависимостями

## Подготовка

Для начала необходимо установить пакет

```bash
composer require -dev voral/bitrix-module-tool
```

Если у вас до этого не был установлен пакет [voral/version-increment](https://github.com/Voral/vs-version-incrementor)  - он будет установлен и необходимо будет произвести настройку согласно документации. А так же подключить данный пакет в файле `.vs-version-increment.php`

```php
use Vasoft\VersionIncrement\Config;
use Vasoft\VersionIncrement\Events\EventType;
use Voral\BitrixModuleTool\ModuleListener;

$config = new Config();

$eventBus = $config->getEventBus();
$listener = new ModuleListener(
    $config,
    'vendor.module',
    includePhpFile: __DIR__ . '/updates/update_include.php',
);
$eventBus->addListener(EventType::BEFORE_VERSION_SET, $listener);
// прочие настройки

return $config;

```

Т.к., как правило, есть необходимость доработать пакет обновлений перед загрузкой на маркетплейс (например перевести языковые description.*), рекомендую команду обновления версии выполнять с флагом отключающим коммит

```bash
php ./vendor/bin/vs-version-increment --no-commit
```

Удобно записать этот скрипт в composer.json. Так же необходимо добавить скрипты создания пакетов модуля (обновления и полного) для загрузки на маркетплейс

```json
{
   "scripts": {
      "vi:auto": "php ./vendor/bin/vs-version-increment --no-commit",
      "pack:last": "sh scripts/pack-last.sh",
      "pack:ver": "sh scripts/pack-version.sh",
      "pack": [
         "@pack:ver",
         "@pack:last"
      ]
   },
   "scripts-descriptions": {
      "vi:auto": "Increment module version and generate update package",
      "pack:last": "Pack the full package of the module's latest version",
      "pack:ver": "Pack the update package for a specified module version",
      "pack": "Pack the full package of the latest version and the update package for a specified version"
   }
}
```

## Применение

1. После выполнения изменений в модуле выполните расчет версии. Ппо окончанию этого скрипта в консоль будут выведены рекомендуемые команды для выполнения коммита и установки тега. Они будут содержать новую версию пакета.
   ```bash
   composer vi:auto
   ```

2. При необходимости внесите изменения в CHANGELOG.md, в так же сгенерированные файлы описания обновления /updates/<версия_пакета>/description.*

3. Выполните сборку пакета обновлений для полученной версии (например для версии 1.3.0)
   ```bash
   composer pack:last 1.3.0
   ```
4. Выполните проверку пакета обновлений как описано в [статье](doc/check_update.md)
5. После всех проверок выполните сборку пакетов обновления и полного пакета модуля
   ```bash
   composer pack:pack 1.3.0
   ```
6. Выполните коммит релиза и установите так как рекомендовано на шаге 1
7. Загрузить пакет на Битрикс маркетплейс

## Конфигурация

Настройка выполняется при помощи конструктора, который имеет следующие параметры:

**$config** - обязательный. Конфигурация version-increment

**$moduleId** - обязательный. Идентификатор вашего модуля

**$sourcePath** - необязательный. Каталог с исходниками модуля, относительно корня проекта. По умолчанию `src`

**$destinationPath** - необязательный. Каталог для пакетов обновлений, относительно корня проекта. По умолчанию `updates`

**$phpVersion** - необязательный. Версия PHP если необходим контроль в скрипте обновления

**$modulesVersion** - необязательный. Требующиеся модули и их версии

**$excludeCommitTypes** - необязательный. Типы коммитов, сообщения которых необходимо пропускать при формировании файлов description.*

**$lang** - необязательный. Символьные коды языков для создания описания обновлений (файлов description.*)

**$includePhpFile** - необязательный. Путь у php файлу, код которого необходимо включить в скрипт обновления update.php

Пример конфигурации
```php
use Vasoft\VersionIncrement\Config;
use Vasoft\VersionIncrement\Events\EventType;
use Voral\BitrixModuleTool\ModuleListener;

$config = new Config();

$eventBus = $config->getEventBus();
$listener = new ModuleListener(
    $config,
    'vendor.module',
    'last_version',
    'marketplace',
    '8.3.0',
    [
        'main' => '24.100.100',
        'sale' => '24.0.100',
    ],
    ['build','docs','test'],
    ['ru','en','fr']
    __DIR__ . '/updates/update_include.php',
);
$eventBus->addListener(EventType::BEFORE_VERSION_SET, $listener);
// прочие настройки

return $config;

```

## Исключения генерируемые расширением

| Код  | Описание                                                     |
|------|--------------------------------------------------------------|
| 5101 | Нет доступа к каталогу проекта                               |
| 5102 | Не корректный путь к проекту                                 |
| 5103 | Не найден git тег версии                                     |
| 5104 | Отсутствует или не верный формат файла version.php           |
| 5105 | Не корректно задан путь к каталогу исходников или обновлений |

Чтобы избежать конфликты с прочими расширениями пакета voral/vs-version-incrementor можно изменять коды ошибок. Для этого необходимо задать дельту. В приведенном ниже примере коды ошибок будут увеличены на 100

```php
use Vasoft\VersionIncrement\Config;
use Vasoft\VersionIncrement\Events\EventType;
use Voral\BitrixModuleTool\ModuleListener;
use Voral\BitrixModuleTool\Exception\ExtensionException;

ExtensionException::$errorCodeDelta = 100;
$config = new Config();

$eventBus = $config->getEventBus();
$listener = new ModuleListener($config, 'vendor.module');
$eventBus->addListener(EventType::BEFORE_VERSION_SET, $listener);
// прочие настройки

return $config;
```

## Пример файла install/version.php

```php
<?php
$arModuleVersion = [
    'VERSION' => '1.0.0',
    'VERSION_DATE' => '2023-01-01'
];
```

## Лицензия

MIT License. Подробности в файле [LICENSE](LICENSE.md).