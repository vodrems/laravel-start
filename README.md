# PDF Storage

[Русский](#русский) · [English](#english)

---

## Русский

Веб-приложение для хранения PDF и DOCX файлов с ограниченным сроком хранения (24 часа).
Laravel 13 · PHP 8.4 · MySQL 8.4 · RabbitMQ 4 · Bootstrap 5 + jQuery · Docker Compose.

### Тестовое задание

**Описание задачи.** Необходимо разработать веб-приложение для хранения PDF и DOCX файлов с ограниченным сроком хранения.

**Функциональные требования**

1. Загрузка файлов
   - Реализовать асинхронный загрузчик файлов (PDF, DOCX) через веб-интерфейс.
   - Ограничение по размеру файла (например, 10MB).
   - Информация о загруженных файлах должна храниться в базе данных.
2. CRUD-страница
   - Отдельная страница для управления загруженными файлами.
   - Возможность просмотра списка загруженных файлов.
   - Возможность удаления файлов вручную.
3. Автоматическое удаление файлов
   - Файлы должны автоматически удаляться через 24 часа после загрузки.
   - После удаления файла через RabbitMQ должно отправляться email-уведомление на адрес, указанный в `.env`.
   - Реализовать отправку сообщения в RabbitMQ, но сам механизм отправки почты (SMTP и т.д.) реализовывать не обязательно.
   - Email-уведомление должно отправляться не только при автоматическом удалении через 24 часа, но и при ручном удалении файла.

**Технические требования**

- Стек технологий: Laravel + PHP 8, MySQL, RabbitMQ, Bootstrap + jQuery (для фронтенд-части).
- Дополнительно (будет плюсом): Docker / Docker Compose.

**Ожидаемый результат**

- Исходный код проекта должен быть размещён в репозитории (GitHub/GitLab/Bitbucket).
- Должна быть инструкция по запуску проекта в файле README.md.
- Приложение должно быть работоспособным и соответствовать описанным требованиям — с учётом локального Docker-окружения и базовых (упрощённых) принципов SOLID.

### Соответствие требованиям

| Требование | Реализация |
|---|---|
| Асинхронный загрузчик PDF/DOCX | `public/js/upload.js`: jQuery AJAX + `FormData`, drag & drop, прогресс по каждому файлу. Сервер: `FileController@store` |
| Лимит размера 10MB | `StoreFileRequest` (`max`, `mimes`, `extensions`), значение из `FILE_MAX_SIZE_KB`. Лимиты nginx/PHP чуть выше (12MB), чтобы ответ был понятной 422 |
| Информация о файлах в БД | Таблица `stored_files` (MySQL), модель `StoredFile` |
| Отдельная CRUD-страница | `/files`: список с пагинацией, скачивание, удаление (AJAX) |
| Удаление вручную | `DELETE /files/{id}` → `FileDeletionService` |
| Автоудаление через 24 часа | Команда `files:purge-expired`, контейнер `scheduler` запускает её каждую минуту |
| Уведомление через RabbitMQ | Queued-listener `SendFileDeletedNotification` публикуется в очередь `notifications` (соединение `rabbitmq`), контейнер `queue` читает её и отправляет письмо на `NOTIFICATION_EMAIL` |
| Уведомление и при ручном, и при автоудалении | Оба сценария идут через единый `FileDeletionService`, который бросает событие `StoredFileDeleted` |
| Docker / Docker Compose | `docker-compose.yml`: app, nginx, mysql, rabbitmq, mailpit, queue, scheduler |
| Упрощённый SOLID | См. раздел «Архитектура» |

### Быстрый старт

Нужны только Docker и Docker Compose v2 (и `make`, это необязательно).

```bash
git clone git@github.com:vodrems/laravel-start.git pdf-storage
cd pdf-storage
make init
```

`make init` создаёт `.env` из `.env.example`, собирает образ, поднимает контейнеры, выполняет `composer install`, генерирует `APP_KEY` и запускает миграции.

<details>
<summary>То же самое без make</summary>

```bash
cp .env.example .env
# Если ваш UID/GID не 1000, передайте их, чтобы права на storage/ совпадали с хостом:
HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose build
docker compose up -d mysql rabbitmq mailpit app
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose up -d
```

</details>

### Сервисы и адреса

| Сервис | Адрес | Доступ |
|---|---|---|
| Приложение — загрузка | http://localhost:8080 | — |
| Приложение — управление файлами | http://localhost:8080/files | — |
| RabbitMQ Management UI | http://localhost:15672 | `app` / `secret` |
| Mailpit (перехваченные письма) | http://localhost:8025 | — |
| MySQL | `localhost:3306` | `app` / `secret`, БД `pdf_storage` |

Порты меняются в `.env` (`APP_PORT`, `FORWARD_*`). Встроенный пользователь RabbitMQ `guest` может подключаться только с localhost, поэтому создаётся отдельный пользователь `app`.

### Настройки (`.env`)

| Переменная | По умолчанию | Назначение |
|---|---|---|
| `NOTIFICATION_EMAIL` | `admin@example.com` | Адрес для уведомлений об удалении |
| `FILE_MAX_SIZE_KB` | `10240` | Максимальный размер файла (КБ) |
| `FILE_TTL_HOURS` | `24` | Срок хранения файла (часы) |
| `FILE_STORAGE_DISK` | `local` | Диск Laravel (`storage/app/private`) |
| `QUEUE_CONNECTION`, `NOTIFICATION_QUEUE_CONNECTION` | `rabbitmq` | Соединение очереди для уведомлений |
| `NOTIFICATION_QUEUE` | `notifications` | Имя очереди в RabbitMQ |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` | `smtp` / `mailpit` / `1025` | Отправка почты. `MAIL_MAILER=log` пишет письма в `storage/logs/laravel.log` |

После изменения `.env` перезапустите воркеры: `make restart`.

### Как проверить

1. Откройте http://localhost:8080 и загрузите несколько PDF/DOCX (кнопкой или перетаскиванием). У каждого файла свой прогресс-бар. Файл `.txt`, файл больше 10MB или `.txt`, переименованный в `.pdf`, будут отклонены с понятной ошибкой.
2. Откройте http://localhost:8080/files: видны список, размер, время загрузки и обратный отсчёт до удаления. Скачивание отдаёт файл с исходным именем.
3. Удалите файл вручную. Строка исчезнет, а в Mailpit (http://localhost:8025) появится письмо «Файл … удалён (удалён вручную)».
4. **Автоудаление.** Чтобы не ждать 24 часа, «состарьте» файлы:
   ```bash
   docker compose exec app php artisan tinker --execute="App\Models\StoredFile::query()->update(['expires_at' => now()->subMinute()])"
   ```
   В течение минуты `scheduler` удалит их, и в Mailpit придут письма с причиной «истёк срок хранения». Можно запустить сразу: `make purge`. За процессом можно следить: `make logs`.
5. **Уведомление действительно идёт через RabbitMQ.** Остановите воркер (`docker compose stop queue`) и удалите файл. В RabbitMQ UI → Queues → `notifications` будет `Ready: 1`, письма нет. Запустите воркер (`docker compose start queue`): сообщение будет обработано, письмо появится в Mailpit.

### Тесты

```bash
make test        # или: docker compose exec app php artisan test
```

Feature-тесты (SQLite in-memory, `Storage::fake`, `Event/Queue/Mail::fake`) покрывают:
- загрузку и валидацию (тип по содержимому, расширение, размер, граница 10MB);
- список, скачивание и ручное удаление;
- автоудаление через 24 часа и расписание раз в минуту;
- защиту от двойного уведомления;
- постановку уведомления в очередь `notifications` соединения `rabbitmq`;
- содержимое письма.

### Архитектура

```
Загрузка (jQuery AJAX) → FileController@store → StoreFileRequest → FileUploader → диск + MySQL

Ручное удаление (AJAX DELETE) → FileController@destroy ─┐
Scheduler (раз в минуту) → files:purge-expired ─────────┴→ FileRemover::delete($file, DeletionReason)
    → удаление с диска и из БД (транзакция, lockForUpdate)
    → событие StoredFileDeleted (после коммита)
    → SendFileDeletedNotification (ShouldQueue) → RabbitMQ, очередь "notifications"
    → контейнер queue (queue:work rabbitmq) → Mail → Mailpit / SMTP → NOTIFICATION_EMAIL
```

Как это соотносится с SOLID:
- **S** — каждый класс делает одно дело. `StoreFileRequest` отвечает за валидацию, `FileUploadService` — за сохранение, `FileDeletionService` — за удаление, `PurgeExpiredFiles` — за выбор просроченных, `SendFileDeletedNotification` — за уведомление, `FileDeletedMail` — за содержимое письма. Контроллер тонкий.
- **O** — новая реакция на удаление (аудит, вебхук) добавляется новым listener'ом, сервис удаления при этом не меняется.
- **L / I** — маленькие интерфейсы `FileUploader` и `FileRemover` с одной операцией. Любая реализация взаимозаменяема.
- **D** — контроллер и команда зависят от интерфейсов из `app/Contracts`. Реализации и их настройки (диск, TTL) связываются в `AppServiceProvider`, значения берутся из `config/filestorage.php`.

Ключевые решения:
- **Единый путь удаления.** Ручное и автоматическое удаление вызывают один сервис, поэтому уведомление гарантировано в обоих случаях.
- **Scheduler, а не отложенный job на 24 часа.** Команда идемпотентна и после простоя сама удаляет всё просроченное. Файлу, удалённому вручную, не нужен «висящий» job. Точность — до минуты.
- **В событии DTO, а не модель.** Письмо отправляется асинхронно, когда записи в БД уже нет.
- **`ShouldDispatchAfterCommit` + `lockForUpdate`.** При откате транзакции уведомление не уходит. При одновременном ручном удалении и purge письмо отправляется ровно одно.
- **Файлы хранятся приватно** (`storage/app/private/uploads`), имена на диске — UUID. Скачивание идёт только через контроллер, под исходным именем.

### Структура проекта

```
app/
  Console/Commands/PurgeExpiredFiles.php   автоудаление
  Contracts/FileUploader.php, FileRemover.php
  DTO/DeletedFileData.php
  Enums/DeletionReason.php
  Events/StoredFileDeleted.php
  Http/Controllers/FileController.php
  Http/Requests/StoreFileRequest.php
  Http/Resources/StoredFileResource.php
  Listeners/SendFileDeletedNotification.php
  Mail/FileDeletedMail.php
  Models/StoredFile.php
  Services/FileStorage/FileUploadService.php, FileDeletionService.php
config/filestorage.php                      настройки хранилища
docker/                                     Dockerfile PHP, php.ini, конфиг nginx
public/js/                                  app.js, upload.js, files.js
resources/views/                            layout, страницы, шаблон письма
routes/web.php, routes/console.php          маршруты и расписание
tests/Feature/                              тесты
```

### Команды make

| Команда | Действие |
|---|---|
| `make init` | Первый запуск (сборка, зависимости, ключ, миграции) |
| `make up` / `make down` | Запустить / остановить контейнеры |
| `make restart` | Перезапустить воркер и scheduler (после изменения кода или `.env`) |
| `make logs` | Логи app, queue, scheduler |
| `make shell` | Shell в контейнере приложения |
| `make test` | Запустить тесты |
| `make purge` | Удалить просроченные файлы прямо сейчас |

---

## English

A web application for storing PDF and DOCX files with a limited retention period (24 hours).
Laravel 13 · PHP 8.4 · MySQL 8.4 · RabbitMQ 4 · Bootstrap 5 + jQuery · Docker Compose.

### Task

**Description.** Build a web application that stores PDF and DOCX files for a limited period of time.

**Functional requirements**

1. File upload
   - Implement an asynchronous file uploader (PDF, DOCX) in the web interface.
   - Limit the file size (e.g. 10MB).
   - Information about uploaded files must be stored in the database.
2. CRUD page
   - A separate page for managing uploaded files.
   - View the list of uploaded files.
   - Delete files manually.
3. Automatic deletion
   - Files must be deleted automatically 24 hours after upload.
   - After a file is deleted, an email notification must be sent via RabbitMQ to the address set in `.env`.
   - Publishing the message to RabbitMQ is required; the mail delivery itself (SMTP, etc.) is optional.
   - The email notification must be sent not only on automatic deletion after 24 hours, but also when a file is deleted manually.

**Technical requirements**

- Stack: Laravel + PHP 8, MySQL, RabbitMQ, Bootstrap + jQuery (frontend).
- Nice to have: Docker / Docker Compose.

**Expected result**

- Source code hosted in a repository (GitHub/GitLab/Bitbucket).
- Setup instructions in README.md.
- The application must work and meet the requirements above, run in a local Docker environment and follow basic (simplified) SOLID principles.

### Requirements coverage

| Requirement | Implementation |
|---|---|
| Asynchronous PDF/DOCX uploader | `public/js/upload.js`: jQuery AJAX + `FormData`, drag & drop, per-file progress bar. Server: `FileController@store` |
| 10MB size limit | `StoreFileRequest` (`max`, `mimes`, `extensions`), value from `FILE_MAX_SIZE_KB`. nginx/PHP limits are slightly higher (12MB) so the user gets a clear 422 |
| File info in the database | `stored_files` table (MySQL), `StoredFile` model |
| Separate CRUD page | `/files`: paginated list, download, delete (AJAX) |
| Manual deletion | `DELETE /files/{id}` → `FileDeletionService` |
| Automatic deletion after 24h | `files:purge-expired` command, run every minute by the `scheduler` container |
| Notification via RabbitMQ | Queued listener `SendFileDeletedNotification` is published to the `notifications` queue (`rabbitmq` connection). The `queue` container consumes it and emails `NOTIFICATION_EMAIL` |
| Notification on both manual and automatic deletion | Both paths go through the single `FileDeletionService`, which fires `StoredFileDeleted` |
| Docker / Docker Compose | `docker-compose.yml`: app, nginx, mysql, rabbitmq, mailpit, queue, scheduler |
| Simplified SOLID | See "Architecture" |

### Quick start

You only need Docker and Docker Compose v2 (`make` is optional).

```bash
git clone git@github.com:vodrems/laravel-start.git pdf-storage
cd pdf-storage
make init
```

`make init` creates `.env` from `.env.example`, builds the image, starts the containers, runs `composer install`, generates `APP_KEY` and runs the migrations.

<details>
<summary>Same steps without make</summary>

```bash
cp .env.example .env
# If your UID/GID is not 1000, pass them so storage/ permissions match the host:
HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose build
docker compose up -d mysql rabbitmq mailpit app
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose up -d
```

</details>

### Services and URLs

| Service | URL | Credentials |
|---|---|---|
| App — upload | http://localhost:8080 | — |
| App — file management | http://localhost:8080/files | — |
| RabbitMQ Management UI | http://localhost:15672 | `app` / `secret` |
| Mailpit (captured emails) | http://localhost:8025 | — |
| MySQL | `localhost:3306` | `app` / `secret`, database `pdf_storage` |

Ports can be changed in `.env` (`APP_PORT`, `FORWARD_*`). RabbitMQ's built-in `guest` user may only connect from localhost, so a dedicated `app` user is created.

### Configuration (`.env`)

| Variable | Default | Purpose |
|---|---|---|
| `NOTIFICATION_EMAIL` | `admin@example.com` | Recipient of deletion notifications |
| `FILE_MAX_SIZE_KB` | `10240` | Maximum file size (KB) |
| `FILE_TTL_HOURS` | `24` | Retention period (hours) |
| `FILE_STORAGE_DISK` | `local` | Laravel disk (`storage/app/private`) |
| `QUEUE_CONNECTION`, `NOTIFICATION_QUEUE_CONNECTION` | `rabbitmq` | Queue connection used for notifications |
| `NOTIFICATION_QUEUE` | `notifications` | RabbitMQ queue name |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` | `smtp` / `mailpit` / `1025` | Mail delivery. `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` instead |

After changing `.env`, restart the workers: `make restart`.

### How to verify

1. Open http://localhost:8080 and upload a few PDF/DOCX files (button or drag & drop). Each file gets its own progress bar. A `.txt`, a file over 10MB, or a `.txt` renamed to `.pdf` is rejected with a clear message.
2. Open http://localhost:8080/files. You see the list, sizes, upload time and a countdown to deletion. Download returns the file under its original name.
3. Delete a file manually. The row disappears, and an email "Файл … удалён (удалён вручную)" arrives in Mailpit (http://localhost:8025).
4. **Automatic deletion.** Instead of waiting 24 hours, make the files expire:
   ```bash
   docker compose exec app php artisan tinker --execute="App\Models\StoredFile::query()->update(['expires_at' => now()->subMinute()])"
   ```
   Within a minute the `scheduler` deletes them, and Mailpit receives emails with the reason "истёк срок хранения" (retention expired). To run it immediately: `make purge`. To watch the process: `make logs`.
5. **The notification really goes through RabbitMQ.** Stop the worker (`docker compose stop queue`) and delete a file. RabbitMQ UI → Queues → `notifications` shows `Ready: 1` and no email arrives. Start the worker (`docker compose start queue`): the message is consumed and the email appears in Mailpit.

### Tests

```bash
make test        # or: docker compose exec app php artisan test
```

Feature tests (in-memory SQLite, `Storage::fake`, `Event/Queue/Mail::fake`) cover:
- upload and validation (content type, extension, size, the 10MB boundary);
- the list, download and manual deletion;
- automatic deletion after 24 hours and the every-minute schedule;
- protection against duplicate notifications;
- queuing the notification on the `notifications` queue of the `rabbitmq` connection;
- the email content.

### Architecture

```
Upload (jQuery AJAX) → FileController@store → StoreFileRequest → FileUploader → disk + MySQL

Manual delete (AJAX DELETE) → FileController@destroy ─┐
Scheduler (every minute) → files:purge-expired ───────┴→ FileRemover::delete($file, DeletionReason)
    → remove from disk and DB (transaction, lockForUpdate)
    → StoredFileDeleted event (after commit)
    → SendFileDeletedNotification (ShouldQueue) → RabbitMQ, queue "notifications"
    → queue container (queue:work rabbitmq) → Mail → Mailpit / SMTP → NOTIFICATION_EMAIL
```

How it maps to SOLID:
- **S** — each class has one job. `StoreFileRequest` validates, `FileUploadService` stores, `FileDeletionService` deletes, `PurgeExpiredFiles` selects expired files, `SendFileDeletedNotification` notifies, `FileDeletedMail` defines the email content. The controller stays thin.
- **O** — a new reaction to deletion (audit, webhook) is a new listener; the deletion service does not change.
- **L / I** — small single-operation interfaces `FileUploader` and `FileRemover`. Any implementation can be swapped in.
- **D** — the controller and the command depend on interfaces from `app/Contracts`. Implementations and their settings (disk, TTL) are wired in `AppServiceProvider` from `config/filestorage.php`.

Key decisions:
- **One deletion path.** Manual and automatic deletion call the same service, so a notification is guaranteed in both cases.
- **Scheduler instead of a 24-hour delayed job.** The command is idempotent and catches up on everything expired after downtime. A manually deleted file leaves no pending job behind. Precision is one minute.
- **The event carries a DTO, not a model.** The email is sent asynchronously, after the database row is gone.
- **`ShouldDispatchAfterCommit` + `lockForUpdate`.** A rolled-back transaction sends nothing. A concurrent manual delete and purge produce exactly one email.
- **Files are private** (`storage/app/private/uploads`) and stored under UUID names. They are served only through the controller, under the original name.

### Project structure

```
app/
  Console/Commands/PurgeExpiredFiles.php   automatic deletion
  Contracts/FileUploader.php, FileRemover.php
  DTO/DeletedFileData.php
  Enums/DeletionReason.php
  Events/StoredFileDeleted.php
  Http/Controllers/FileController.php
  Http/Requests/StoreFileRequest.php
  Http/Resources/StoredFileResource.php
  Listeners/SendFileDeletedNotification.php
  Mail/FileDeletedMail.php
  Models/StoredFile.php
  Services/FileStorage/FileUploadService.php, FileDeletionService.php
config/filestorage.php                      storage settings
docker/                                     PHP Dockerfile, php.ini, nginx config
public/js/                                  app.js, upload.js, files.js
resources/views/                            layout, pages, email template
routes/web.php, routes/console.php          routes and schedule
tests/Feature/                              tests
```

### Make commands

| Command | Action |
|---|---|
| `make init` | First run (build, dependencies, key, migrations) |
| `make up` / `make down` | Start / stop containers |
| `make restart` | Restart the worker and scheduler (after code or `.env` changes) |
| `make logs` | Logs of app, queue, scheduler |
| `make shell` | Shell in the app container |
| `make test` | Run the tests |
| `make purge` | Delete expired files right now |
