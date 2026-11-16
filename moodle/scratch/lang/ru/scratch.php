<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Russian strings for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Проект Scratch';
$string['modulename'] = 'Проект Scratch';
$string['modulenameplural'] = 'Проекты Scratch';
$string['scratch:addinstance'] = 'Добавлять задание Scratch';
$string['scratch:view'] = 'Просматривать задание Scratch';
$string['scratch:submit'] = 'Сдавать проекты Scratch';
$string['scratch:grade'] = 'Оценивать проекты Scratch';
$string['scratch:manage'] = 'Управлять проектами Scratch';

$string['settings'] = 'Настройки Scratch';
$string['duedate'] = 'Срок сдачи';
$string['duedate_help'] = 'Рекомендуемая дата сдачи проекта Scratch. После этой даты работы считаются просроченными, но могут приниматься до конечной даты.';
$string['cutoffdate'] = 'Конечная дата';
$string['cutoffdate_help'] = 'После этой даты ученики больше не могут редактировать или сдавать проект.';
$string['cutoffdatebeforeduedate'] = 'Конечная дата не может быть раньше срока сдачи.';
$string['maxbytes'] = 'Максимальный размер проекта';
$string['maxbytes_help'] = 'Максимальный размер загружаемого файла проекта .sb3.';
$string['allowresubmit'] = 'Разрешить повторную сдачу';
$string['allowresubmit_help'] = 'Если включено, ученики могут редактировать и сдавать проект повторно после сдачи, до конечной даты.';

$string['status'] = 'Статус';
$string['statusnotsubmitted'] = 'Не сдано';
$string['statussubmitted'] = 'Сдано';
$string['statusdraft'] = 'Черновик';
$string['openeditor'] = 'Открыть редактор Scratch';
$string['viewproject'] = 'Просмотреть проект';
$string['downloadproject'] = 'Скачать .sb3';
$string['submissions'] = 'Сданные работы';
$string['nosubmissions'] = 'Пока нет сданных работ.';
$string['nosubmissionyet'] = 'Ученик ещё не загрузил проект.';
$string['gradeverb'] = 'Оценить';
$string['gradesaved'] = 'Оценка сохранена.';
$string['gradingdisabled'] = 'Оценивание отключено для этого задания (максимальный балл — 0).';
$string['feedback'] = 'Комментарий';
$string['savedraft'] = 'Сохранить черновик';
$string['submitproject'] = 'Сдать проект';
$string['confirmsubmit'] = 'Сдать этот проект преподавателю?';
$string['backtoactivity'] = 'Вернуться к заданию';
$string['readonlyproject'] = 'Просмотр без сохранения';
$string['loading'] = 'Загрузка Scratch…';
$string['saving'] = 'Сохранение…';
$string['saved'] = 'Сохранено в Moodle';
$string['saveerror'] = 'Ошибка сохранения';
$string['noinstances'] = 'В этом курсе нет заданий Scratch.';

$string['errorinvalidfile'] = 'Файл не является корректным проектом Scratch .sb3.';
$string['errorfiletoolarge'] = 'Файл проекта превышает допустимый размер.';
$string['errornotallowed'] = 'Вам нельзя редактировать или сдавать этот проект.';
$string['invalidaction'] = 'Недопустимое действие.';

$string['eventsubmissionsubmitted'] = 'Проект Scratch сдан';
$string['eventsubmissiongraded'] = 'Проект Scratch оценён';

$string['privacy:metadata:scratch_submissions'] = 'Сданные проекты Scratch, включая файлы проектов, статус, оценки и комментарии.';
$string['privacy:metadata:scratch_submissions:userid'] = 'ID пользователя, сдавшего проект.';
$string['privacy:metadata:scratch_submissions:status'] = 'Статус сдачи (черновик или сдано).';
$string['privacy:metadata:scratch_submissions:title'] = 'Название проекта Scratch.';
$string['privacy:metadata:scratch_submissions:filename'] = 'Имя файла сданного проекта .sb3.';
$string['privacy:metadata:scratch_submissions:filesize'] = 'Размер файла сданного проекта.';
$string['privacy:metadata:scratch_submissions:timemodified'] = 'Время последнего изменения работы.';
$string['privacy:metadata:scratch_submissions:timesubmitted'] = 'Время сдачи проекта.';
$string['privacy:metadata:scratch_submissions:grade'] = 'Оценка за проект.';
$string['privacy:metadata:scratch_submissions:feedback'] = 'Комментарий преподавателя.';
$string['privacy:metadata:scratch_submissions:grader'] = 'ID пользователя, оценившего проект.';
$string['privacy:metadata:scratch_submissions:timegraded'] = 'Время оценивания проекта.';
$string['privacy:metadata:files:submission'] = 'Сданные файлы проектов Scratch .sb3.';
$string['hardwaretitle'] = 'Подключение LEGO';
$string['hardwarehelp'] = 'Редактор подключается к LEGO WeDo 2.0 и LEGO MINDSTORMS EV3 через бесплатную программу Scratch Link на вашем компьютере. Связь локальная: данные об устройствах не передаются в интернет.';
$string['hardwarestep1'] = 'Установите Scratch Link и запустите её — она должна работать всё время, пока вы используете блоки.';
$string['hardwarestep2'] = 'Включите Bluetooth и включите ваш контроллер LEGO.';
$string['hardwarestep3'] = 'В редакторе откройте «Добавить расширение», выберите LEGO Education WeDo 2.0 или LEGO MINDSTORMS EV3 и нажмите «Начать поиск».';
$string['hardwareinstalllink'] = 'Скачать Scratch Link';
$string['hardwareclose'] = 'Закрыть';
$string['hardwarenoedit'] = 'Блоки для работы с оборудованием доступны только в проекте, который можно редактировать. Откройте задание, чтобы работать с LEGO.';
$string['privacy:metadata:external:scratchlink'] = 'Редактор Scratch может подключаться к программе Scratch Link, установленной на компьютере пользователя, чтобы управлять оборудованием LEGO WeDo 2.0 и LEGO MINDSTORMS EV3. Соединение устанавливается напрямую из браузера к локальному компьютеру (WebSocket на 127.0.0.1); данные об устройствах не передаются ни на сервер Moodle, ни третьим лицам.';
$string['continuetitle'] = 'У вас есть сохранённый проект. Продолжить работу над ним?';
$string['continueyes'] = 'Продолжить проект';
$string['continuefresh'] = 'Начать новый проект';
$string['newproject'] = 'Новый проект';
