import axios from 'axios';

let config = {};

export const initApi = (cfg) => {
    config = cfg;
};

export const moodleCall = async (methodname, args) => {
    const url = `${config.wwwroot}/lib/ajax/service.php?sesskey=${config.sesskey}`;
    const payload = [{
        index: 0,
        methodname,
        args
    }];
    const response = await axios.post(url, payload);
    const data = response.data[0];
    if (data.error) {
        console.error(data);

        const message =
            data.exception?.message ??
            data.message ??
            JSON.stringify(data);

        throw new Error(message);
    }

    return data.data;
};

export const getCardsByBox = (boxnumber) => {
    return moodleCall('mod_adaptivereview_get_cards_by_box', {
        instanceid: config.instanceid,
        boxnumber
    });
};

export const getBoxCounts = async () => {
    const counts = await moodleCall('mod_adaptivereview_get_box_counts', {
        instanceid: config.instanceid
    });

    const result = { 0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
    for (const item of counts) {
        result[item.box_number] = item.count;
    }
    return result;
};

export const getStudentSummary = () => {
    return moodleCall('mod_adaptivereview_get_student_summary', {
        instanceid: config.instanceid,
    });
};

export const startReviewSession = async (mode) => {
    const result = await moodleCall('mod_adaptivereview_start_review_session', {
        instanceid: config.instanceid,
        mode
    });
    return result.sessionkey;
};

export const completeReviewSession = (sessionkey) => {
    return moodleCall('mod_adaptivereview_complete_review_session', {
        sessionkey
    });
};

export const submitAnswer = (cardid, response, sessionkey) => {
    return moodleCall('mod_adaptivereview_submit_answer', {
        cardid,
        response,
        sessionkey
    });
};

export const getLogoUrl = () => {
    return `${config.wwwroot}/mod/adaptivereview/pix/logo.png`;
};

export const resetProgress = () => {
    return moodleCall('mod_adaptivereview_reset_progress', {
        instanceid: config.instanceid
    });
};
