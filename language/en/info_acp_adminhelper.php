<?php
if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = [];
}

$lang = array_merge($lang, [
    'ADMINHELPER_SEARCH_BY_EMAIL'   => 'Or search by email address',
    'ADMINHELPER_EMAIL_PLACEHOLDER' => 'e.g. user@example.com',
    'ADMINHELPER_EMAIL_NOT_FOUND'   => 'No user found with this email address.',
]);
