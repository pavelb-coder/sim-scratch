# Scratch Code / sim-scratch

Локальный редактор блоков **только Scratch**: без Roblox, Arduino и Minecraft.
Внутри официальный `scratch-gui` 5.3.0 (AGPL-3.0-only) + React 16.14.

Порт по умолчанию: **8083**.

## Возможности

- блоки, спрайты, сцена, костюмы, звуки;
- стандартные расширения Scratch;
- открыть/сохранить `.sb3` через меню **Файл**;
- кнопка **Скачать .sb3**;
- автосохранение проекта в браузере (IndexedDB);
- русский интерфейс и темы Scratch;
- автосинхронизация исходников с GitHub: `pavelb-coder/sim-scratch`.

## Требования

- Node.js **18+**;
- Git — если нужна синхронизация с GitHub;
- для Windows: Git for Windows или GitHub CLI;
- для Ubuntu: `git` и при желании `gh`.

## Быстрый запуск

### Windows

Вариант 1 — двойной клик или из `cmd`:

```bat
start.cmd
```

Вариант 2 — PowerShell:

```powershell
./start.ps1
```

Вариант 3 — редактор + автосинхронизация:

```powershell
npm run dev
```

Открыть: <http://localhost:8083>

### Ubuntu

```bash
chmod +x start.sh
./start.sh
```

Редактор + автосинхронизация:

```bash
npm run dev
```

Открыть: <http://localhost:8083>

## Команды

| Команда | Что делает |
|---|---|
| `npm start` | запускает редактор на порту 8083 |
| `npm run dev` | запускает редактор и watcher автосинхронизации |
| `npm run sync` | один раз синхронизирует с GitHub |
| `npm run sync:watch` | только автосинхронизация |
| `npm run compress` | создаёт gzip-ассеты для быстрой загрузки |

Сменить порт:

```bash
PORT=9000 npm start
```

Windows PowerShell:

```powershell
$env:PORT = '9000'
npm start
```

## Автосинхронизация с GitHub

Репозиторий: <https://github.com/pavelb-coder/sim-scratch>

Режим `npm run dev` или `npm run sync:watch`:

1. отслеживает изменения в проекте;
2. делает `git add`/`commit` для изменений;
3. подтягивает изменения из `origin/main`;
4. отправляет локальные коммиты в GitHub.

При конфликте rebase автосинхронизация не делает force-push и требует ручного
разрешения конфликта.

Настройки по умолчанию: `sync.config.json`.
Локальные переопределения: `sync.local.json` — файл игнорируется Git и не должен
попадать в репозиторий.

### Авторизация на Windows

Обычно достаточно Git Credential Manager, который входит в Git for Windows:

```powershell
git push
```

При первом запросе войдите в GitHub. После этого учётные данные сохраняются в
системном хранилище Windows.

Альтернатива:

```powershell
gh auth login
```

### Авторизация на Ubuntu

Через GitHub CLI:

```bash
gh auth login
gh auth setup-git
```

Или используйте SSH-ключ, добавленный в GitHub, и SSH-remote.

### SSH-вариант для автосинхронизации

Создайте `sync.local.json` рядом с `package.json`:

```json
{
  "remote": "git@github.com:pavelb-coder/sim-scratch.git",
  "sshKey": "/home/user/.ssh/sim-scratch_deploy",
  "knownHosts": "/home/user/.ssh/known_hosts"
}
```

Для Windows пути могут быть такими:

```json
{
  "remote": "git@github.com:pavelb-coder/sim-scratch.git",
  "sshKey": "C:/Users/USER/.ssh/sim-scratch_deploy",
  "knownHosts": "C:/Users/USER/.ssh/known_hosts"
}
```

Приватные ключи и токены нельзя коммитить в репозиторий.

## Ubuntu: необязательный systemd-сервис

Примеры лежат в `deploy/`:

- `sim-scratch.service` — редактор;
- `sim-scratch-sync.service` — автосинхронизация.

Перед установкой замените `/opt/sim-scratch` и пользователя `simscratch` на свои.

```bash
sudo cp deploy/sim-scratch.service /etc/systemd/system/
sudo cp deploy/sim-scratch-sync.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now sim-scratch sim-scratch-sync
```

## Структура

- `server.js` — HTTP-сервер, порт 8083;
- `public/` — оболочка и Scratch GUI;
- `public/app.js` — автосохранение и скачивание `.sb3`;
- `scripts/sync.js` — синхронизация с GitHub;
- `scripts/dev.js` — совместный запуск редактора и синхронизации;
- `start.cmd`, `start.ps1`, `start.sh` — запуск на Windows/Ubuntu;
- `sync.config.json` — настройки синхронизации;
- `sync.local.json` — локальные секреты/переопределения, не в Git.

## Лицензии

- `scratch-gui` — AGPL-3.0-only; см. `public/vendor/scratch-gui/LICENSE`.
- React/ReactDOM — MIT.
- Товарный знак Scratch принадлежит Scratch Foundation; проект не аффилирован
  со Scratch Foundation.
