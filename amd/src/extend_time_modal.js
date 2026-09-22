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
 * Extend quiz time confirmation modal.
 *
 * The "+Nm" buttons and the custom-minutes input are not two competing
 * controls: the input is the single source of truth for the extension
 * length, and a preset button is just a quick way to write a value into it.
 * Typing a number by hand and clicking "+15m" therefore leave the modal in
 * exactly the same state. Keep this in sync with extend_time_manager's
 * MAX_CUSTOM_MINUTES on the PHP side (classes/local/manager/extend_time_manager.php).
 *
 * @module     quiz_livequizmonitor/extend_time_modal
 * @copyright  2026 SSYSTEMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Templates from 'core/templates';

/** @type {number[]} */
const PRESETS = [5, 10, 15, 30];

/** @type {number} */
const DEFAULT_MINUTES = 15;

/** @type {number} Upper bound for a custom extension. Mirrors extend_time_manager::MAX_CUSTOM_MINUTES. */
const MAX_CUSTOM_MINUTES = 180;

/**
 * Format a unix timestamp for display.
 *
 * @param {number} timestamp Unix seconds
 * @returns {string}
 */
const formatDeadline = (timestamp) => {
    if (!timestamp) {
        return '—';
    }
    return new Date(timestamp * 1000).toLocaleString();
};

/**
 * Parse a candidate minutes value from the custom input.
 *
 * Only a plain whole number in [1, MAX_CUSTOM_MINUTES] is valid. Anything
 * else, including an empty field, decimals, or a value out of range, is
 * treated as "not currently a usable value" rather than clamped or rounded,
 * so the person always sees exactly what they typed.
 *
 * @param {string} value Raw input value.
 * @returns {number|null} The parsed minutes, or null when not valid.
 */
const parseValidMinutes = (value) => {
    const trimmed = String(value ?? '').trim();
    if (!/^\d+$/.test(trimmed)) {
        return null;
    }
    const parsed = parseInt(trimmed, 10);
    if (parsed < 1 || parsed > MAX_CUSTOM_MINUTES) {
        return null;
    }
    return parsed;
};

/**
 * Load modal language strings.
 *
 * @returns {Promise<object>}
 */
const loadStrings = async() => {
    const keys = [
        {key: 'extend:modaltitle', component: 'quiz_livequizmonitor'},
        {key: 'extend:addtime', component: 'quiz_livequizmonitor'},
        {key: 'extend:customlabel', component: 'quiz_livequizmonitor'},
        {key: 'extend:custominvalid', component: 'quiz_livequizmonitor'},
        {key: 'extend:modalbodyindividual', component: 'quiz_livequizmonitor'},
        {key: 'extend:modalbodybulk', component: 'quiz_livequizmonitor'},
        {key: 'extend:newdeadlineindividual', component: 'quiz_livequizmonitor'},
        {key: 'extend:newdeadlinebulk', component: 'quiz_livequizmonitor'},
        {key: 'extend:confirm', component: 'quiz_livequizmonitor'},
        {key: 'extend:mineach', component: 'quiz_livequizmonitor'},
        {key: 'extend:successindividual', component: 'quiz_livequizmonitor'},
        {key: 'extend:successbulk', component: 'quiz_livequizmonitor'},
    ];
    const [
        modaltitle,
        addtime,
        customlabel,
        custominvalid,
        modalbodyindividual,
        modalbodybulk,
        newdeadlineindividual,
        newdeadlinebulk,
        confirm,
        mineach,
        successindividual,
        successbulk,
    ] = await Promise.all(keys.map(({key, component}) => getString(key, component)));

    return {
        modaltitle,
        addtime,
        customlabel,
        custominvalid,
        modalbodyindividual,
        modalbodybulk,
        newdeadlineindividual,
        newdeadlinebulk,
        confirm,
        mineach,
        successindividual,
        successbulk,
    };
};

/**
 * Build modal body template context.
 *
 * @param {object} config Modal configuration
 * @param {object} strings Loaded language strings
 * @param {number} minutes Currently selected minutes
 * @returns {Promise<object>}
 */
const buildBodyContext = async(config, strings, minutes) => {
    const isBulk = config.mode === 'bulk';
    const description = isBulk
        ? await getString('extend:modalbodybulk', 'quiz_livequizmonitor', {count: config.inprogresscount ?? 0})
        : await getString('extend:modalbodyindividual', 'quiz_livequizmonitor', {name: config.studentname ?? ''});

    let previewlabel;
    let previewvalue;
    if (isBulk) {
        previewlabel = await getString('extend:newdeadlinebulk', 'quiz_livequizmonitor', {count: config.inprogresscount ?? 0});
        previewvalue = await getString('extend:mineach', 'quiz_livequizmonitor', minutes);
    } else {
        previewlabel = strings.newdeadlineindividual;
        const base = parseInt(config.attemptendat, 10) || 0;
        previewvalue = base ? formatDeadline(base + minutes * 60) : '—';
    }

    return {
        description,
        addtimelabel: strings.addtime,
        previewlabel,
        previewvalue,
        presets: PRESETS.map((preset) => ({
            minutes: preset,
            active: preset === minutes,
        })),
        customlabel: strings.customlabel,
        custommax: MAX_CUSTOM_MINUTES,
        customvalue: minutes,
    };
};

/**
 * Refresh the preview text, confirm button label, and preset highlight for
 * the currently valid minutes value. Does nothing to the confirm button's
 * disabled state; that is handled separately since it also needs to react
 * to an invalid (not just changed) value.
 *
 * @param {HTMLElement} root Modal root element
 * @param {object} config Modal configuration
 * @param {object} strings Loaded language strings
 * @param {number} minutes Currently valid minutes
 * @param {object} modal Modal instance
 */
const refreshPreview = async(root, config, strings, minutes, modal) => {
    const context = await buildBodyContext(config, strings, minutes);
    const previewLabel = root.querySelector('[data-region="extend-preview-label"]');
    const previewValue = root.querySelector('[data-region="extend-preview"]');
    if (previewLabel) {
        previewLabel.textContent = context.previewlabel;
    }
    if (previewValue) {
        previewValue.textContent = context.previewvalue;
    }

    root.querySelectorAll('.livequizmonitor-extend-preset').forEach((button) => {
        const isActive = parseInt(button.dataset.minutes, 10) === minutes;
        button.classList.toggle('active', isActive);
    });

    const confirmLabel = await getString('extend:confirm', 'quiz_livequizmonitor', minutes);
    modal.setSaveButtonText(confirmLabel);
};

/**
 * Show extend time modal and return outcome on confirm.
 *
 * @param {object} config
 * @param {string} config.mode individual|bulk
 * @param {number} config.cmid Course module id
 * @param {number} config.groupid Group id
 * @param {number} [config.userid] Target user for individual mode
 * @param {string} [config.studentname] Student name for individual mode
 * @param {number} [config.attemptendat] Attempt deadline for individual preview
 * @param {number} [config.inprogresscount] In-progress count for bulk mode
 * @returns {Promise<object|null>} API response or null when cancelled
 */
export const showExtendModal = async(config) => {
    const strings = await loadStrings();
    let minutes = DEFAULT_MINUTES;
    const bodyContext = await buildBodyContext(config, strings, minutes);

    const {html, js} = await Templates.renderForPromise(
        'quiz_livequizmonitor/extend_time_modal_body',
        bodyContext
    );

    const modal = await ModalSaveCancel.create({
        title: strings.modaltitle,
        body: html,
    });

    await modal.show();

    const root = modal.getRoot()[0];
    if (js) {
        Templates.runTemplateJS(js);
    }

    modal.setSaveButtonText(await getString('extend:confirm', 'quiz_livequizmonitor', minutes));

    const customInput = root.querySelector('[data-region="extend-custom"]');
    const customError = root.querySelector('[data-region="extend-custom-error"]');
    const saveButton = modal.getRoot().find('[data-action="save"]');

    /**
     * Re-validate the custom input's current value, and bring the preview,
     * confirm button, and error text into line with it.
     *
     * @returns {Promise<void>}
     */
    const handleInputChange = async() => {
        const parsed = parseValidMinutes(customInput.value);
        const showError = customInput.value.trim() !== '' && parsed === null;

        customInput.classList.toggle('is-invalid', showError);
        if (customError) {
            customError.textContent = showError
                ? await getString('extend:custominvalid', 'quiz_livequizmonitor', MAX_CUSTOM_MINUTES)
                : '';
        }

        if (parsed === null) {
            saveButton.prop('disabled', true);
            return;
        }

        minutes = parsed;
        saveButton.prop('disabled', false);
        await refreshPreview(root, config, strings, minutes, modal);
    };

    customInput.addEventListener('input', handleInputChange);

    root.querySelectorAll('.livequizmonitor-extend-preset').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            customInput.value = button.dataset.minutes;
            customInput.dispatchEvent(new Event('input'));
        });
    });

    let finished = false;

    return new Promise((resolve) => {
        modal.getRoot().on(ModalEvents.save, async() => {
            // The confirm button is disabled whenever the input is invalid, but
            // guard here too in case save is somehow triggered while it is.
            const parsed = parseValidMinutes(customInput.value);
            if (parsed === null) {
                return;
            }
            minutes = parsed;

            saveButton.prop('disabled', true);
            try {
                const args = {
                    cmid: config.cmid,
                    groupid: config.groupid ?? 0,
                    minutes,
                    scope: config.mode === 'bulk' ? 'bulk' : 'individual',
                    userid: config.mode === 'bulk' ? 0 : (config.userid ?? 0),
                };
                const response = await Ajax.call([{
                    methodname: 'quiz_livequizmonitor_extend_quiz_time',
                    args,
                }])[0];

                if (config.mode === 'bulk') {
                    await Notification.addNotification({
                        message: await getString('extend:successbulk', 'quiz_livequizmonitor', {
                            minutes: response.minutes,
                            count: response.extendedcount,
                        }),
                        type: 'success',
                    });
                } else {
                    const name = config.studentname ?? (response.usernames[0] ?? '');
                    await Notification.addNotification({
                        message: await getString('extend:successindividual', 'quiz_livequizmonitor', {
                            minutes: response.minutes,
                            name,
                        }),
                        type: 'success',
                    });
                }

                if (response.warnings?.length) {
                    await Notification.addNotification({
                        message: response.warnings.join('\n'),
                        type: 'warning',
                    });
                }

                finished = true;
                modal.destroy();
                resolve(response);
            } catch (error) {
                saveButton.prop('disabled', false);
                Notification.exception(error);
            }
        });

        modal.getRoot().on(ModalEvents.hidden, () => {
            if (!finished) {
                resolve(null);
            }
        });
    });
};

export default {showExtendModal};
