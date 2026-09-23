<?php

declare(strict_types=1);

return [
    'ids_required' => 'The organization id and the admin account id (--actor) are required, 26 characters each.',
    'reason_required' => 'Give a reason with --reason (at least three characters).',
    'key_required' => 'No key was read.',
    'key_prompt' => 'Provider key (hidden while typing)',
    'verify_failed' => 'The provider rejected the key or could not be reached: :reason. Nothing was saved.',
    'saved' => 'The key was saved encrypted. The bot is still off; turn it on from the AI assistant section with a written reason.',
    'prune_disabled' => 'Retention is zero: the archive is kept indefinitely and nothing was deleted.',
    'pruned' => ':count conversations past the retention period were deleted.',
];
