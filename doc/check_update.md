# Как проверить обновление модуля

Для проверки обновления модуля без загрузки на маркетплейс необходимо выполнить следующие шаги.

1. Создать каталог с обновлениями /bitrix/upload/update_m1747424431/vendor.module\
где:
   * update_m1747424431 может быть иным, можно всегда использовать один и тот же
   * vendor.module - идентификатор вашего модуля
2. В это каталог распаковать архив обновления без каталога версии. т.е. у вас должна получиться примерно следующая структура 

    ```
    /bitrix/upload/update_m1747424431/vendor.module/
        install/version.php
        lib/SomeClass.php
        updater1.1.0.php
        description.ru
        description.en
        options.php
    ```

3. Создать консольный скрипт запуска обновления например в каталоге /local/cli-tools/ следующего содержания
    ```php
    declare(strict_types=1);

    $_SERVER['DOCUMENT_ROOT'] = realpath(dirname(__DIR__, 2));
    $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

    define('NOT_CHECK_PERMISSIONS', true);
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

    @set_time_limit(0);
    @ignore_user_abort(true);
    ini_set('implicit_flush', 1);
    ob_implicit_flush(true);
    ini_set('output_buffering', 'Off');
    ini_set('zlib.output_compression', 'Off');
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/update_client_partner.php';

    @set_time_limit(0);
    ini_set('track_errors', '1');
    ignore_user_abort(true);

    IncludeModuleLangFile(__FILE__);

    $errorMessage = '';

    CUpdateClientPartner::UpdateStepModules('update_m1747424431', $errorMessage, true);
    echo $errorMessage;

   ```
   первым параметром метода UpdateStepModules передаем тот каталог. который создали в каталоге /bitrix/updates/
4. Выполнить этот скрипт.