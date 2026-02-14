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
    'ADMINHELPER_SEARCH_BY_EMAIL'   => 'Ou rechercher par adresse email',
    'ADMINHELPER_EMAIL_PLACEHOLDER' => 'ex: utilisateur@example.com',
    'ADMINHELPER_EMAIL_NOT_FOUND'   => 'Aucun utilisateur trouvé avec cette adresse email.',
]);
