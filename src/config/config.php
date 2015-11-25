<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    |
    | The index name. Change it to the name of your application or something
    | else meaningful.
    |
    */
    'index' => getenv('SEARCH_INDEX'),

    /*
    |--------------------------------------------------------------------------
    | Auto Index
    |--------------------------------------------------------------------------
    |
    | When enabled, indexes will be set automatically on create, save or delete.
    | Disable it to have manual control over indexes.
    |
    */
    'auto_index' => false,
];