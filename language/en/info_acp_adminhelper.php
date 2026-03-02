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
    'ADMINHELPER_EMAIL_NOT_FOUND'   => 'No user found with the address: %s',
    'ADMINHELPER_HTML_MESSAGE_LABEL' => 'Your message (HTML)',
    'ADMINHELPER_HTML_MESSAGE_EXPLAIN' => 'Paste only visual content (e.g. start with &lt;div&gt;). &lt;!DOCTYPE html&gt;, &lt;html&gt;, &lt;head&gt; and &lt;body&gt; are handled automatically.',
    'ADMINHELPER_USE_HTML_LABEL' => 'Send HTML version',
    'ADMINHELPER_USE_HTML_EXPLAIN' => 'If enabled, the email is sent as multipart (plain text + HTML).',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_LABEL' => 'Add unsubscribe instructions',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_EXPLAIN' => 'Adds a footer with a link to user profile preferences (login required) to disable admin bulk emails.',
    'ADMINHELPER_ENABLE_ONE_CLICK_LABEL' => 'Enable one-click unsubscribe (RFC8058)',
    'ADMINHELPER_ENABLE_ONE_CLICK_EXPLAIN' => 'Adds List-Unsubscribe and List-Unsubscribe-Post headers and sends one recipient per email for compliant one-click unsubscribe.',
    'ADMINHELPER_UNSUBSCRIBE_TEXT' => 'To stop receiving administrator bulk emails, log in and disable the corresponding option in your profile preferences: %s',
    'ADMINHELPER_UNSUBSCRIBE_HTML' => '<hr><p><strong>Unsubscribe:</strong> To stop receiving administrator bulk emails, log in and disable the corresponding option in your profile preferences: <a href="%1$s">%1$s</a></p>',
    'ADMINHELPER_NOTIFY_UNSUBSCRIBE_TEXT' => 'To stop receiving forum notification emails, use one-click unsubscribe: %1$s\nOr log in and disable notification email options here: %2$s',
    'ADMINHELPER_UNSUB_INVALID_REQUEST' => 'Invalid unsubscribe request.',
    'ADMINHELPER_UNSUB_USER_NOT_FOUND' => 'User not found.',
    'ADMINHELPER_UNSUB_INVALID_SIGNATURE' => 'Invalid unsubscribe signature.',
    'ADMINHELPER_UNSUB_EXPIRED' => 'Unsubscribe link expired.',
    'ADMINHELPER_UNSUB_DONE' => 'Unsubscribe processed. You will no longer receive administrator bulk emails.',
    'ADMINHELPER_UNSUB_PAGE_TITLE' => 'Email unsubscribe',
    'ADMINHELPER_UNSUB_PAGE_HEADING' => 'Unsubscribe from administrator bulk emails',
    'ADMINHELPER_UNSUB_PAGE_EXPLAIN' => 'This action only disables administrator bulk emails. It does not delete your forum account.',
    'ADMINHELPER_UNSUB_PAGE_BUTTON' => 'Confirm unsubscribe',
    'ADMINHELPER_NOTIFY_UNSUB_DONE' => 'Unsubscribe processed. You will no longer receive forum notification emails.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_TITLE' => 'Forum notification unsubscribe',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_HEADING' => 'Unsubscribe from forum notification emails',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_EXPLAIN' => 'This action disables forum notification emails. It does not delete your forum account.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_BUTTON' => 'Confirm unsubscribe',
    'ADMINHELPER_MIME_MULTIPART_NOTICE' => 'This is a multi-part message in MIME format.',
]);
