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
    'ADMINHELPER_SEARCH_BY_EMAIL'   => 'O buscar por direccion de correo electronico',
    'ADMINHELPER_EMAIL_PLACEHOLDER' => 'p. ej. usuario@example.com',
    'ADMINHELPER_EMAIL_NOT_FOUND'   => 'No se encontro ningun usuario con la direccion: %s',
    'ADMINHELPER_HTML_MESSAGE_LABEL' => 'Tu mensaje (HTML)',
    'ADMINHELPER_HTML_MESSAGE_EXPLAIN' => 'Pega solo el contenido visual (p. ej. empieza con &lt;div&gt;). Las etiquetas &lt;!DOCTYPE html&gt;, &lt;html&gt;, &lt;head&gt; y &lt;body&gt; se gestionan automaticamente.',
    'ADMINHELPER_USE_HTML_LABEL' => 'Enviar version HTML',
    'ADMINHELPER_USE_HTML_EXPLAIN' => 'Si esta activado, el correo se envia como multipart (texto sin formato + HTML).',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_LABEL' => 'Anadir instrucciones de baja',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_EXPLAIN' => 'Anade un pie con enlace a las preferencias del perfil (requiere inicio de sesion) para desactivar correos masivos del administrador.',
    'ADMINHELPER_ENABLE_ONE_CLICK_LABEL' => 'Activar baja one-click (RFC8058)',
    'ADMINHELPER_ENABLE_ONE_CLICK_EXPLAIN' => 'Anade cabeceras List-Unsubscribe y List-Unsubscribe-Post y envia un destinatario por correo para una baja one-click compatible con RFC8058.',
    'ADMINHELPER_UNSUBSCRIBE_TEXT' => 'Para dejar de recibir correos masivos del administrador, inicia sesion y desactiva la opcion correspondiente en las preferencias de tu perfil: %s',
    'ADMINHELPER_UNSUBSCRIBE_HTML' => '<hr><p><strong>Baja:</strong> Para dejar de recibir correos masivos del administrador, inicia sesion y desactiva la opcion correspondiente en las preferencias de tu perfil: <a href="%1$s">%1$s</a></p>',
    'ADMINHELPER_NOTIFY_UNSUBSCRIBE_TEXT' => 'Para dejar de recibir emails de notificaciones del foro, usa la baja one-click: %1$s\nO inicia sesion y desactiva las notificaciones por email aqui: %2$s',
    'ADMINHELPER_UNSUB_INVALID_REQUEST' => 'Solicitud de baja invalida.',
    'ADMINHELPER_UNSUB_USER_NOT_FOUND' => 'Usuario no encontrado.',
    'ADMINHELPER_UNSUB_INVALID_SIGNATURE' => 'Firma de baja invalida.',
    'ADMINHELPER_UNSUB_EXPIRED' => 'El enlace de baja ha expirado.',
    'ADMINHELPER_UNSUB_DONE' => 'Baja procesada. Ya no recibiras correos masivos del administrador.',
    'ADMINHELPER_UNSUB_PAGE_TITLE' => 'Baja de email',
    'ADMINHELPER_UNSUB_PAGE_HEADING' => 'Darse de baja de correos masivos del administrador',
    'ADMINHELPER_UNSUB_PAGE_EXPLAIN' => 'Esta accion solo desactiva los correos masivos del administrador. No elimina tu cuenta del foro.',
    'ADMINHELPER_UNSUB_PAGE_BUTTON' => 'Confirmar baja',
    'ADMINHELPER_NOTIFY_UNSUB_DONE' => 'Baja procesada. Ya no recibiras emails de notificaciones del foro.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_TITLE' => 'Baja de notificaciones del foro',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_HEADING' => 'Darse de baja de emails de notificaciones del foro',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_EXPLAIN' => 'Esta accion desactiva las notificaciones del foro por email. No elimina tu cuenta del foro.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_BUTTON' => 'Confirmar baja',
    'ADMINHELPER_MIME_MULTIPART_NOTICE' => 'Este es un mensaje multiparte en formato MIME.',
]);
