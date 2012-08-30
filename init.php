<?php defined('SYSPATH') or die('No direct script access.');

Route::set('crud', 'crud(/<action>(/<id>(/<pk>(/<submodel>))))')
    ->defaults(array(
    'controller' => 'crud',
    'action'     => 'index'
));

// Static file serving (CSS, JS, images)
Route::set('crud/media', 'crud/media(/<file>)', array('file' => '.+'))
    ->defaults(array(
    'controller' => 'crud',
    'action'     => 'media',
    'file'       => NULL,
));