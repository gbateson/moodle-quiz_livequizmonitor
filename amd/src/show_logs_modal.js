// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Student log viewer modal module.
 *
 * @module      quiz_livequizmonitor/show_logs_modal
 * @copyright   2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author      Gordon Bateson <g.bateson@ucl.ac.uk>
 */

import Ajax from 'core/ajax';
import ModalCancel from 'core/modal_cancel';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Templates from 'core/templates';

/**
 * Load modal language strings.
 *
 * @param {string} studentName
 * @returns {Promise<object>}
 */
const loadStrings = async(studentName) => {
    const [
        modaltitle,
        showmore,
        close,
        errorload,
    ] = await Promise.all([
        getString('logs:modaltitle', 'quiz_livequizmonitor', studentName),
        getString('logs:showmore', 'quiz_livequizmonitor'),
        getString('closebuttontitle', 'core'),
        getString('logs:errorload', 'quiz_livequizmonitor'),
    ]);

    return {modaltitle, showmore, close, errorload};
};

/**
 * Fetch student log records from external API.
 *
 * @param {object} config
 * @returns {Promise<object>}
 */
const fetchLogs = async(config) => {
    return Ajax.call([{
        methodname: 'quiz_livequizmonitor_get_student_logs',
        args: {
            cmid: config.cmid,
            userid: config.userid,
            groupid: config.groupid ?? 0,
        },
    }])[0];
};

/**
 * Show student logs modal; opens complete report window on save action.
 *
 * @param {object} config
 * @param {number} config.cmid Course module id
 * @param {number} config.userid Target student user id
 * @param {number} [config.groupid] Group id filter
 * @param {string} [config.studentname] Student display name
 * @returns {Promise<boolean>}
 */
export const showLogsModal = async(config) => {
    const studentName = config.studentname ?? '';
    const strings = await loadStrings(studentName);

    let logData;
    try {
        logData = await fetchLogs(config);
    } catch (error) {
        await Notification.addNotification({
            message: strings.errorload,
            type: 'error',
        });
        Notification.exception(error);
        return false;
    }

    const bodyContext = {
        userid: config.userid,
        courseid: logData.courseid ?? config.courseid,
        studentname: studentName,
        haslogs: logData.haslogs,
        logs: logData.logs,
    };

    const {html, js} = await Templates.renderForPromise(
        'quiz_livequizmonitor/show_logs_modal_body',
        bodyContext
    );

    let modal = null;
    if (logData.haslogs) {
        modal = await ModalSaveCancel.create({
            title: strings.modaltitle,
            body: html,
        });
        modal.setSaveButtonText(strings.showmore);
    } else {
        modal = await ModalCancel.create({
            title: strings.modaltitle,
            body: html,
        });
        modal.setButtonText('cancel', strings.close);
    }

    await modal.show();
    if (js) {
        Templates.runTemplateJS(js);
    }

    return new Promise((resolve) => {
        let actionTriggered = false;

        if (logData.haslogs) {
            // "Show More" / Save action -> launch the core report log user page in a new window.
            modal.getRoot().on(ModalEvents.save, (event) => {
                event.preventDefault();

                // Filtered specifically to Course, User, AND Quiz Module Instance
                const params = new URLSearchParams({
                    chooselog: 1, // Required to show report immediately.
                    id: logData.courseid ?? config.courseid,
                    user: config.userid,
                    modid: config.cmid,
                    showusers: 0,
                    showcourses: 0,
                });
                const logUrl = `${M.cfg.wwwroot}/report/log/index.php?${params.toString()}`;
                window.open(logUrl, '_blank');

                actionTriggered = true;
                modal.destroy();
                resolve(true);
            });
        }

        // Cleanup on modal close/hide (handles backdrop click, escape, close button, or cancel button)
        modal.getRoot().on(ModalEvents.hidden, () => {
            if (!actionTriggered) {
                modal.destroy();
                resolve(logData.haslogs ? false : null);
            }
        });
    });
};

export default {showLogsModal};
