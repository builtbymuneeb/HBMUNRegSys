<?php

/*
 * Only the keys set here override Livewire's defaults (merged one level deep),
 * so each block below must be complete.
 */
return [

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        // Livewire's default is 12 MB. Ambassador proof files allow 25 MB.
        'rules' => ['required', 'file', 'max:25600'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
