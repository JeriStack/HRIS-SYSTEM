<?php

/**
 * Philippine Leave Management Configuration
 * Supporting document storage limits, allowed formats, and policy defaults.
 */

$maxDocumentBytes = getenv('LEAVE_DOCUMENT_MAX_BYTES');

return [
    'document_max_bytes'     => ($maxDocumentBytes !== false && (int)$maxDocumentBytes > 0)
        ? (int)$maxDocumentBytes
        : 5 * 1024 * 1024, // 5 MB default
    'document_allowed_mimes' => [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ],
    'confidential_codes' => [
        'VAWC',
        'SLW',
    ],
];
