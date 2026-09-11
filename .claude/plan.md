# План: хранилище PDF/DOCX с TTL 24ч (Laravel + MySQL + RabbitMQ + Docker)

## Context
Тестовое задание: веб-приложение для загрузки PDF/DOCX (≤10MB) с асинхронным загрузчиком, CRUD-страницей, автоудалением через 24ч и email-уведомлением через RabbitMQ при **любом** удалении (ручном и автоматическом). Стек: Laravel + PHP 8, MySQL, RabbitMQ, Bootstrap + jQuery, Docker Compose. Репозиторий пустой (нет коммитов, remote `git@github.com:vodrems/laravel-start.git`), поэтому проект создаётся с нуля.

Принятые решения (согласованы):
- RabbitMQ — как драйвер очереди Laravel через `vladimir-yuldashev/laravel-queue-rabbitmq` v15 (поддерживает Laravel 13).
- Почта — Mailpit в Docker (письма видны в веб-UI).
- Без авторизации.
- Версии: Laravel 13.x, PHP 8.4 в контейнере, MySQL 8.4, RabbitMQ 4 management.

## Архитектура (упрощённый SOLID)

Ключевая идея: **единственный путь удаления** — `FileDeletionService`. Его вызывают и контроллер (вручную), и консольная команда (по TTL). Сервис бросает событие `StoredFileDeleted`, на него подписан queued-listener, который уходит в RabbitMQ и отправляет письмо. Поэтому уведомление гарантировано в обоих сценариях, и дублировать логику не нужно.

```
Upload (jQuery AJAX) → FileController@store → StoreFileRequest (валидация) → FileUploader → Storage + DB
Manual delete (AJAX DELETE) → FileController@destroy ─┐
Scheduler (каждую минуту) → files:purge-expired ───────┴→ FileRemover::delete($file, DeletionReason)
      → удаление с диска + из БД (транзакция) → event StoredFileDeleted (after commit)
      → SendFileDeletedNotification (ShouldQueue, connection=rabbitmq, queue=notifications)
      → [RabbitMQ] → queue worker → Mail::to(NOTIFICATION_EMAIL)->send(FileDeletedMail) → Mailpit
```

Как это ложится на SOLID:
- **SRP**: валидация в FormRequest, загрузка и удаление в отдельных сервисах, выборка просроченных в команде, уведомление в listener, текст письма в Mailable. Контроллер тонкий.
- **OCP**: чтобы добавить новую реакцию на удаление (аудит, вебхук), достаточно нового listener'а, сервис удаления не меняется.
- **DIP/ISP**: контроллер и команда зависят от маленьких интерфейсов `FileUploader` / `FileRemover`, которые биндятся в `AppServiceProvider`. Настройки (TTL, лимит, email, диск) берутся из `config/filestorage.php`, а не хардкодятся.

Почему автоудаление сделано через scheduler, а не через отложенный job на 24ч: команда идемпотентна и сама навёрстывает пропущенное после простоя (если воркер лежал). Для отложенных сообщений в RabbitMQ понадобились бы TTL+DLX или плагин, а удаление, отложенное на сутки, становится лишним, если файл уже удалили вручную. Точность: файл удаляется в течение ≤1 минуты после истечения 24ч.

## Файлы

### Docker / инфраструктура
- `docker-compose.yml`, сервисы:
  - `app`: php-fpm, собирается из `docker/php/Dockerfile`, код монтируется томом.
  - `nginx`: порт `${APP_PORT:-8080}`, конфиг `docker/nginx/default.conf` с `client_max_body_size 12M`.
  - `mysql`: `mysql:8.4`, healthcheck, том `mysql-data`.
  - `rabbitmq`: `rabbitmq:4-management`, порты 5672/15672, healthcheck.
  - `mailpit`: `axllent/mailpit`, порты 8025 (UI) и 1025 (SMTP).
  - `queue`: тот же образ, `php artisan queue:work rabbitmq --queue=notifications --tries=3 --backoff=10`, `restart: unless-stopped`, `depends_on` rabbitmq (healthy).
  - `scheduler`: тот же образ, `php artisan schedule:work`.
- `docker/php/Dockerfile`: `php:8.4-fpm`, расширения `pdo_mysql sockets bcmath pcntl zip opcache` (`sockets` нужен php-amqplib), composer из `composer:2`. ARG `UID/GID=1000`, чтобы права на `storage/` совпадали с хостом.
- `docker/php/php.ini`: `upload_max_filesize=12M`, `post_max_size=13M`. Лимиты чуть выше 10MB, чтобы превышение ловила валидация Laravel (понятный 422), а не nginx/PHP (413 или пустой запрос).
- `Makefile`: `init` (build, up, composer install, key:generate, migrate), `up`, `down`, `test`, `logs`, `shell`.

### Laravel
- Установка: `composer create-project laravel/laravel:^13` во временную папку внутри контейнера, затем перенос в корень репозитория (там уже есть `.git`). Потом `composer require vladimir-yuldashev/laravel-queue-rabbitmq:^15`. Дефолтный Vite/Tailwind-скаффолдинг удаляем, фронт подключаем через CDN.
- `config/filestorage.php`: `disk` (`local`, приватный), `max_size_kb` (env `FILE_MAX_SIZE_KB=10240`), `ttl_hours` (env `FILE_TTL_HOURS=24`), `allowed_extensions` `[pdf, docx]`, `notification_email` (env `NOTIFICATION_EMAIL`).
- `config/queue.php`: добавить соединение `rabbitmq` по документации пакета (`RABBITMQ_HOST/PORT/USER/PASSWORD/VHOST`, очередь по умолчанию `notifications`). В `.env.example` поставить `QUEUE_CONNECTION=rabbitmq`.
- Миграция `create_stored_files_table`: `id`, `original_name`, `path` (unique), `mime_type`, `size` (unsignedBigInteger), `expires_at` (index), timestamps.
- `app/Models/StoredFile.php`: casts (`expires_at` → datetime), `scopeExpired()` (`expires_at <= now()`).
- `app/Enums/DeletionReason.php`: backed enum `Manual`, `Expired` с методом `label()` (для письма).
- `app/DTO/DeletedFileData.php`: readonly-снимок удалённого файла (id, имя, размер, mime, uploadedAt, deletedAt, reason). В событие кладём DTO, а не модель: к моменту обработки job'а записи в БД уже нет, и `SerializesModels` упал бы.
- `app/Contracts/FileUploader.php`, `app/Contracts/FileRemover.php`: интерфейсы.
- `app/Services/FileStorage/FileUploadService.php` (реализует `FileUploader`): кладёт файл на диск под именем `uploads/{Y/m/d}/{uuid}.{ext}`, создаёт запись с `expires_at = now()->addHours(ttl)`. Если вставка в БД упала, удаляет уже сохранённый файл.
- `app/Services/FileStorage/FileDeletionService.php` (реализует `FileRemover`): в транзакции перечитывает запись с `lockForUpdate()` (защита от гонки ручного удаления и purge), удаляет файл с диска (если файла нет — warning в лог, продолжаем) и запись из БД, затем dispatch `StoredFileDeleted`.
- `app/Events/StoredFileDeleted.php`: `implements ShouldDispatchAfterCommit`, несёт `DeletedFileData`.
- `app/Listeners/SendFileDeletedNotification.php`: `implements ShouldQueue`, `$connection = 'rabbitmq'`, `$queue = 'notifications'`, `$tries = 3`. Отправляет `FileDeletedMail` на `config('filestorage.notification_email')`.
- `app/Mail/FileDeletedMail.php` + `resources/views/mail/file-deleted.blade.php` (markdown): имя, размер, дата загрузки и удаления, причина.
- `app/Console/Commands/PurgeExpiredFiles.php` (`files:purge-expired`): `StoredFile::expired()->chunkById(100, …)` → `FileRemover::delete($file, DeletionReason::Expired)`, выводит количество удалённых. В `routes/console.php`: `Schedule::command('files:purge-expired')->everyMinute()->withoutOverlapping()`.
- `app/Http/Requests/StoreFileRequest.php`: `required|file|max:{max_size_kb}|mimes:pdf,docx|extensions:pdf,docx`. Сообщения об ошибках на английском (уточнение после утверждения плана: весь UI и письма — на английском).
- `app/Http/Resources/StoredFileResource.php`: JSON для AJAX-ответа (id, name, human size, uploaded_at, expires_at, ссылки download/delete).
- `app/Http/Controllers/FileController.php`:
  - `create()`: страница загрузки;
  - `store(StoreFileRequest, FileUploader)`: 201 + resource;
  - `index()`: CRUD-страница, пагинация по 20;
  - `download(StoredFile)`: `Storage::download` с оригинальным именем;
  - `destroy(StoredFile, FileRemover)`: 204 для AJAX, redirect + flash без JS.
- `routes/web.php`: `GET /` → `files.create`, `POST /files`, `GET /files` → `files.index`, `GET /files/{file}/download`, `DELETE /files/{file}`.

### Фронтенд (Bootstrap 5 + jQuery через CDN с SRI)
- `resources/views/layouts/app.blade.php`: navbar со ссылками «Загрузка» и «Файлы», `<meta name="csrf-token">`, flash-сообщения.
- `resources/views/files/create.blade.php` + `public/js/upload.js`: выбор нескольких файлов (или drag&drop), клиентская предпроверка расширения и размера (дублирует серверную, для UX). Каждый файл уходит отдельным `$.ajax` с `FormData` и собственным Bootstrap progress bar (`xhr.upload.onprogress`). Ошибки 422 показываются под файлом, успех — со ссылкой на список.
- `resources/views/files/index.blade.php` + `public/js/files.js`: таблица (имя, размер, загружен, истекает через N ч M мин, «Скачать», «Удалить»). Удаление: `confirm` → AJAX `DELETE` с `X-CSRF-TOKEN` → строка плавно исчезает, toast. Пустое состояние со ссылкой на загрузку.

### Тесты (`tests/Feature`, sqlite in-memory из дефолтного phpunit.xml, `Storage::fake`)
- `FileUploadTest`: PDF и DOCX → 201, запись есть, файл на диске, `expires_at` ≈ now+24h; `.txt` → 422; >10MB (`UploadedFile::fake()->create(..., 10241)`) → 422.
- `FileDeletionTest`: ручное удаление убирает файл и запись, `Event::assertDispatched(StoredFileDeleted)` с reason `Manual`; удаление несуществующего → 404.
- `PurgeExpiredFilesTest`: `$this->travel(25)->hours()` → удаляются только просроченные, событие с reason `Expired` на каждый файл.
- `SendFileDeletedNotificationTest`: `Mail::fake()`, listener шлёт `FileDeletedMail` на адрес из конфига. Отдельно проверить, что listener попадает в очередь на соединение `rabbitmq` (`Queue::fake()` + `assertPushedOn('notifications', CallQueuedListener::class)`).

### Документация
- `README.md` — один двуязычный файл. Вверху переключатель `[Русский](#русский) · [English](#english)`, дальше полная русская часть и полная английская часть с одинаковой структурой. Все инструкции и информация (запуск, URL-ы, переменные, архитектура, проверка, тесты) есть на обоих языках. Текст ТЗ: в русской части оригинал, в английской перевод. Структура каждой части:
  1. **«Тестовое задание» / «Task»**: полный текст ТЗ, как прислал пользователь (описание, функциональные и технические требования, ожидаемый результат, пожелание про Docker-окружение и упрощённый SOLID). Для читаемости переформатировать в markdown: заголовки и вложенные списки вместо `◦`/`•`, смысл не менять.
  2. **«Соответствие требованиям»**: таблица «требование → где реализовано» (асинхронный загрузчик → `public/js/upload.js` + `FileController@store`; лимит 10MB → `StoreFileRequest` + `FILE_MAX_SIZE_KB`; БД → `stored_files`; CRUD → `/files`; автоудаление → `files:purge-expired` + scheduler; RabbitMQ → queued listener на соединении `rabbitmq`; уведомление при ручном и автоудалении → единый `FileDeletionService`; Docker → `docker-compose.yml`; SOLID → раздел «Архитектура»).
  3. Далее разделы, описанные ниже.
- Требования (Docker + Compose), быстрый старт (`cp .env.example .env && make init`, или те же шаги вручную через `docker compose`), URL-ы: приложение `http://localhost:8080`, RabbitMQ UI `http://localhost:15672` (guest/guest), Mailpit `http://localhost:8025`. Переменные `.env` (`NOTIFICATION_EMAIL`, `FILE_TTL_HOURS`, `FILE_MAX_SIZE_KB`). Как быстро проверить автоудаление: `FILE_TTL_HOURS` или `tinker` для сдвига `expires_at`, затем `php artisan files:purge-expired`. Схема потока и краткое описание архитектуры. Запуск тестов: `make test`.

## Порядок работы
0. Сохранить этот план в проект как `.claude/plan.md` (копия финального плана, коммитится вместе с кодом).
1. Docker-окружение и установка Laravel 13, подключение RabbitMQ-пакета; убедиться, что `docker compose up` поднимает все сервисы healthy.
2. Конфиг, миграция, модель, enum, DTO, контракты и сервисы.
3. Событие, listener, Mailable, команда, расписание.
4. Контроллер, request, resource, роуты, Blade и JS.
5. Тесты, README, Makefile.
6. Коммиты по логическим шагам в `master`. Push на GitHub — только после подтверждения.

## Проверка (end-to-end)
1. `cp .env.example .env && make init` → `docker compose ps`: все сервисы `running/healthy`.
2. `make test` → все тесты зелёные.
3. Браузер `http://localhost:8080`: загрузить PDF и DOCX (виден прогресс), попытаться загрузить .txt и файл >10MB → понятные ошибки.
4. `/files`: файлы в списке, скачивание отдаёт оригинальное имя. Удалить один вручную → строка исчезает, файла нет в `storage/app/private/uploads`.
5. RabbitMQ UI → очередь `notifications` существует, сообщения прошли (graph publish/ack). Mailpit → письмо «удалён вручную» на `NOTIFICATION_EMAIL`.
6. Автоудаление: `docker compose exec app php artisan tinker --execute="App\Models\StoredFile::query()->update(['expires_at'=>now()->subMinute()])"` → в течение минуты scheduler удаляет файлы, в Mailpit приходят письма «истёк срок хранения». Параллельно смотреть `docker compose logs -f scheduler queue`.
7. Остановить `queue` → удалить файл → сообщение копится в RabbitMQ (Ready=1) → запустить `queue` → письмо доставлено. Это доказывает, что уведомление действительно идёт через брокер.
