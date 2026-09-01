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
];
