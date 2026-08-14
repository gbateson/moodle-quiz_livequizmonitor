/**
 * Student quiz attempts viewer modal module.
 *
 * @module      quiz_livequizmonitor/show_attempts_modal
 * @copyright   2026 SSYSTEMS
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        getString('attempts:modaltitle', 'quiz_livequizmonitor', studentName),
        getString('attempts:showmore', 'quiz_livequizmonitor'),
        getString('closebuttontitle', 'core'),
        getString('attempts:errorload', 'quiz_livequizmonitor'),
    ]);

    return {modaltitle, showmore, close, errorload};
};

/**
 * Fetch student quiz attempts from external API.
 *
 * @param {object} config
 * @returns {Promise<object>}
 */
const fetchAttempts = async(config) => {
    return Ajax.call([{
        methodname: 'quiz_livequizmonitor_get_student_attempts',
        args: {
            cmid: config.cmid,
            userid: config.userid,
            groupid: config.groupid ?? 0,
        },
    }])[0];
};

/**
 * Show student quiz attempts modal.
 *
 * @param {object} config
 * @param {number} config.cmid Course module id
 * @param {number} config.userid Target student user id
 * @param {number} [config.groupid] Group id filter
 * @param {string} [config.studentname] Student display name
 * @returns {Promise<boolean>}
 */
export const showAttemptsModal = async(config) => {
    const studentName = config.studentname ?? '';
    const strings = await loadStrings(studentName);

    let attemptData;
    try {
        attemptData = await fetchAttempts(config);
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
        courseid: attemptData.courseid ?? config.courseid,
        studentname: studentName,
        hasattempts: attemptData.hasattempts,
        attempts: attemptData.attempts,
    };

    const {html, js} = await Templates.renderForPromise(
        'quiz_livequizmonitor/show_attempts_modal_body',
        bodyContext
    );

    let modal = null;
    if (attemptData.hasattempts) {
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

        if (attemptData.hasattempts) {
            // "Show more" opens the complete quiz attempts report.
            modal.getRoot().on(ModalEvents.save, (event) => {
                event.preventDefault();

                const params = new URLSearchParams({
                    id: config.cmid,
                    userid: config.userid,
                });

                const attemptsUrl =
                    `${M.cfg.wwwroot}/mod/quiz/report.php?${params.toString()}`;

                window.open(attemptsUrl, '_blank');

                actionTriggered = true;
                modal.destroy();
                resolve(true);
            });
        }

        // Cleanup on modal close/hide.
        modal.getRoot().on(ModalEvents.hidden, () => {
            if (!actionTriggered) {
                modal.destroy();
                resolve(attemptData.hasattempts ? false : null);
            }
        });
    });
};

export default {showAttemptsModal};