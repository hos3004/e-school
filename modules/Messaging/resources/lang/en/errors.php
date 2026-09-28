<?php

declare(strict_types=1);

/*
| Error messages of the Messaging module.
| Consumed via __('messaging::errors.key') — keys describe meaning, not wording.
*/

return [
    'invalid_participant_scope' => 'Every participant must be an active account in the same organization.',
    'class_access_denied' => 'Only active class members and assigned teachers can access this class conversation.',
    'not_participant' => 'You cannot send a message in a conversation you are not part of.',
    'too_many_participants' => 'The number of participants exceeds the allowed limit (:max).',
    'direct_exceeds_two' => 'A direct conversation accepts only two parties.',
    'not_message_author' => 'You cannot edit a message you did not write.',
    'message_flagged_locked' => 'A flagged message cannot be edited.',
    'message_already_edited' => 'This message has already been edited and cannot be edited again.',
    'edit_window_expired' => 'The message editing window of :minutes minutes has expired.',
    'message_already_flagged' => 'This message is already flagged.',
    'wall_comment_too_long' => 'The comment exceeds the maximum length of :max characters.',
    'whatsapp_duplicate_message' => 'This WhatsApp message has already been recorded.',
    'whatsapp_already_handled' => 'This message has already been handled.',
    'invalid_recipient' => 'The recipient is unavailable or outside your organization.',
    'supervision_unavailable' => 'The supervision channel is not available right now.',
    'recipient_not_reachable' => 'You can only message your own students.',

    // WhatsApp campaigns
    'campaign_delay_range_invalid' => 'The shortest gap cannot exceed the longest gap.',
    'campaign_recipients_empty' => 'The list holds no usable number.',
    'campaign_recipients_exceeded' => 'The list exceeds the limit for one campaign (:max).',
    'campaign_media_store_failed' => 'The attachment could not be stored on the server.',
    'campaign_not_startable' => 'The campaign cannot be started from its current state.',
    'campaign_not_stoppable' => 'The campaign cannot be stopped from its current state.',
    'campaign_channel_disabled' => 'The WhatsApp channel is off — switch it on before starting a campaign.',
];
