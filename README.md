# Scratch Code — только Scratch

Локальный аналог RobX Code, но **без Roblox и Arduino**. Внутри официальный
`scratch-gui` 5.3.0 (AGPL-3.0-only) + React 16.14. Редактор работает без
регистрации: блоки, спрайты, сцена, костюмы, звуки, расширения Scratch.

## Что есть

- Только режим Scratch: нет переключателя режимов, нет Roblox/Arduino/Minecraft.
- Открыть/сохранить `.sb3` через меню **Файл** и кнопку **Скачать .sb3**.
- Автосохранение в браузере (IndexedDB) и восстановление после перезагрузки.
- Русский/английский и другие локали Scratch, темы Scratch.
- Локальные статические ассеты редактора; костюмы/звуки/фоны библиотеки
  проксируются с assets.scratch.mit.edu и кэшируются сервером.
- Минимальная same-origin заглушка `/projects` и `/assets` нужна только чтобы
  `scratch-gui` не ругался на «создание проекта». Это не облако аккаунта:
  рабочее сохранение — `.sb3` на компьютер и IndexedDB в браузере.

## Запуск

```bash
node server.js
# или
npm start
```

Открыть http://localhost:5173

## Сжатие ассетов

```bash
npm run compress
```

Скрипт создаёт `.gz` рядом с JS/CSS/HTML/JSON/SVG; сервер отдаёт их при
`Accept-Encoding: gzip`.

## Структура

- `public/index.html` — оболочка.
- `public/app.js` — обёртка scratch-gui, автосохранение, скачивание .sb3.
- `public/styles.css` — шапка и layout.
- `public/vendor/scratch-gui/` — npm-пакет scratch-gui (dist + static).
- `server.js` — статический сервер, gzip, same-origin proxy для Scratch assets.

## Проверено

- Редактор загружается, стандартный проект Scratch появляется.
- В интерфейсе нет Roblox и Arduino.
- Кнопка «Скачать .sb3» отдаёт валидный ZIP-проект Scratch.
- Переименование проекта автосохраняется и восстанавливается после перезагрузки.
- Критических ошибок страницы и 404 в гостевом тесте нет.

## Лицензии

- `scratch-gui` — AGPL-3.0-only; см. `public/vendor/scratch-gui/LICENSE`.
- React/ReactDOM — MIT.
- Товарный знак Scratch принадлежит Scratch Foundation; проект не аффилирован
  со Scratch Foundation.
