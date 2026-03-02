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
    'ACP_ADMINHELPER_TITLE' => 'Admin Helper',
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS' => 'Unsubscribe logs',
    'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS_EXPLAIN' => 'Logs one-click unsubscribe events (RFC 8058) for administrator bulk emails and forum notification emails.',
    'ACP_ADMINHELPER_COUNTER_OVERVIEW' => 'Global email overview',
    'ACP_ADMINHELPER_COUNTER_TOTAL_MEMBERS' => 'Members counted',
    'ACP_ADMINHELPER_COUNTER_MASSMAIL' => 'Bulk emails',
    'ACP_ADMINHELPER_COUNTER_FORUM_NOTIFY' => 'Forum notifications (email)',
    'ACP_ADMINHELPER_COUNTER_SUBSCRIBED' => 'Subscribed',
    'ACP_ADMINHELPER_COUNTER_UNSUBSCRIBED' => 'Unsubscribed',
    'ACP_ADMINHELPER_LOG_TOTAL' => 'Total logged events',
    'ACP_ADMINHELPER_LOG_NO_ROWS' => 'No unsubscribe logs available yet.',
    'ACP_ADMINHELPER_LOG_TABLE_MISSING' => 'Unsubscribe log table is missing. Run the extension update migration.',
    'ACP_ADMINHELPER_LOG_COL_DATE' => 'Date',
    'ACP_ADMINHELPER_LOG_COL_TYPE' => 'Type',
    'ACP_ADMINHELPER_LOG_COL_STATUS' => 'Status',
    'ACP_ADMINHELPER_LOG_COL_USER' => 'User',
    'ACP_ADMINHELPER_LOG_COL_EMAIL' => 'Email',
    'ACP_ADMINHELPER_LOG_COL_HTTP' => 'HTTP',
    'ACP_ADMINHELPER_LOG_COL_METHOD' => 'Method',
    'ACP_ADMINHELPER_LOG_COL_IP' => 'IP',
    'ACP_ADMINHELPER_LOG_COL_TOKEN_EXP' => 'Token expiry',
    'ACP_ADMINHELPER_LOG_COL_UA' => 'User-Agent',
    'ACP_ADMINHELPER_LOG_COL_ACTIONS' => 'Actions',
    'ACP_ADMINHELPER_LOG_COL_SELECT' => 'Select',
    'ACP_ADMINHELPER_ACTION_RESTORE_NOTIFY' => 'Restore notifications',
    'ACP_ADMINHELPER_ACTION_DELETE_SELECTED' => 'Delete selected',
    'ACP_ADMINHELPER_ACTION_DELETE_ALL' => 'Delete all rows',
    'ACP_ADMINHELPER_ACTION_NONE' => '-',
    'ACP_ADMINHELPER_SELECT_ALL' => 'Select all',
    'ACP_ADMINHELPER_UNSELECT_ALL' => 'Unselect all',
    'ACP_ADMINHELPER_DELETE_SELECTED_CONFIRM' => 'Delete selected log rows?',
    'ACP_ADMINHELPER_DELETE_ALL_CONFIRM' => 'Delete all log rows?',
    'ACP_ADMINHELPER_DELETE_NONE_SELECTED' => 'No log row selected.',
    'ACP_ADMINHELPER_DELETE_SELECTED_SUCCESS' => '%d log row(s) deleted.',
    'ACP_ADMINHELPER_DELETE_ALL_SUCCESS' => 'Log cleared (%d row(s) deleted).',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_CONFIRM' => 'Restore forum notification emails for this user?',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_SUCCESS' => 'Forum notification emails have been restored for this user.',
    'ACP_ADMINHELPER_RESTORE_NOTIFY_INVALID_USER' => 'Selected user cannot be restored.',
    'ACP_ADMINHELPER_STATUS_INVALID_REQUEST' => 'Invalid request',
    'ACP_ADMINHELPER_STATUS_USER_NOT_FOUND' => 'User not found',
    'ACP_ADMINHELPER_STATUS_INVALID_SIGNATURE' => 'Invalid signature',
    'ACP_ADMINHELPER_STATUS_EXPIRED_TOKEN' => 'Expired token',
    'ACP_ADMINHELPER_STATUS_UNSUBSCRIBED' => 'Unsubscribed',
    'ACP_ADMINHELPER_STATUS_ALREADY_UNSUBSCRIBED' => 'Already unsubscribed',
    'ACP_ADMINHELPER_STATUS_CONFIRMATION_PAGE' => 'Confirmation page viewed',
    'ACP_ADMINHELPER_STATUS_ADMIN_RESTORED' => 'Admin restored notifications',
    'ACP_ADMINHELPER_TYPE_MASSMAIL' => 'Bulk emails',
    'ACP_ADMINHELPER_TYPE_FORUM_NOTIFY' => 'Forum notifications',
]);
