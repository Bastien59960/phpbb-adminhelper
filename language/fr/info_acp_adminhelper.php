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
    'ADMINHELPER_SEARCH_BY_EMAIL'   => 'Ou rechercher par adresse e-mail',
    'ADMINHELPER_EMAIL_PLACEHOLDER' => 'ex. utilisateur@example.com',
    'ADMINHELPER_EMAIL_NOT_FOUND'   => 'Aucun utilisateur trouve avec l\'adresse : %s',
    'ADMINHELPER_HTML_MESSAGE_LABEL' => 'Votre message (HTML)',
    'ADMINHELPER_HTML_MESSAGE_EXPLAIN' => 'Collez uniquement le contenu visuel (ex: commencez par &lt;div&gt;). Les balises &lt;!DOCTYPE html&gt;, &lt;html&gt;, &lt;head&gt; et &lt;body&gt; sont gerees automatiquement.',
    'ADMINHELPER_USE_HTML_LABEL' => 'Envoyer la version HTML',
    'ADMINHELPER_USE_HTML_EXPLAIN' => 'Si active, le courriel sera envoye en multipart (texte brut + HTML).',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_LABEL' => 'Ajouter les instructions de desinscription',
    'ADMINHELPER_APPEND_UNSUBSCRIBE_EXPLAIN' => 'Ajoute un pied de mail avec un lien vers les preferences du profil (connexion requise) pour desactiver les emails d\'information admin.',
    'ADMINHELPER_ENABLE_ONE_CLICK_LABEL' => 'Activer la desinscription one-click (RFC8058)',
    'ADMINHELPER_ENABLE_ONE_CLICK_EXPLAIN' => 'Ajoute les en-tetes List-Unsubscribe et List-Unsubscribe-Post et envoie un destinataire par email pour une desinscription one-click conforme.',
    'ADMINHELPER_UNSUBSCRIBE_TEXT' => 'Pour ne plus recevoir les emails d\'information des administrateurs, connectez-vous puis desactivez l\'option correspondante dans les preferences de votre profil : %s',
    'ADMINHELPER_UNSUBSCRIBE_HTML' => '<hr><p><strong>Desinscription :</strong> Pour ne plus recevoir les emails d\'information des administrateurs, connectez-vous puis desactivez l\'option correspondante dans les preferences de votre profil : <a href="%1$s">%1$s</a></p>',
    'ADMINHELPER_NOTIFY_UNSUBSCRIBE_TEXT' => 'Pour ne plus recevoir les emails de notification du forum, utilisez la desinscription one-click : %1$s' . "\n" . 'et pour gérer plus finement vos notifications c\'est ici  : %2$s',
    'ADMINHELPER_UNSUB_INVALID_REQUEST' => 'Requete de desinscription invalide.',
    'ADMINHELPER_UNSUB_USER_NOT_FOUND' => 'Membre introuvable.',
    'ADMINHELPER_UNSUB_INVALID_SIGNATURE' => 'Signature de desinscription invalide.',
    'ADMINHELPER_UNSUB_EXPIRED' => 'Lien de desinscription expire.',
    'ADMINHELPER_UNSUB_DONE' => 'Desinscription prise en compte. Vous ne recevrez plus les emails de masse administrateur.',
    'ADMINHELPER_UNSUB_PAGE_TITLE' => 'Desinscription email',
    'ADMINHELPER_UNSUB_PAGE_HEADING' => 'Se desinscrire des emails de masse administrateur',
    'ADMINHELPER_UNSUB_PAGE_EXPLAIN' => 'Cette action desactive uniquement les emails de masse administrateur. Elle ne supprime pas votre compte forum.',
    'ADMINHELPER_UNSUB_PAGE_BUTTON' => 'Confirmer la desinscription',
    'ADMINHELPER_NOTIFY_UNSUB_DONE' => 'Desinscription prise en compte. Vous ne recevrez plus les emails de notification du forum.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_TITLE' => 'Desinscription notifications forum',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_HEADING' => 'Se desinscrire des emails de notification du forum',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_EXPLAIN' => 'Cette action desactive les notifications forum par email. Elle ne supprime pas votre compte forum.',
    'ADMINHELPER_NOTIFY_UNSUB_PAGE_BUTTON' => 'Confirmer la desinscription',
    'ADMINHELPER_MIME_MULTIPART_NOTICE' => 'Ceci est un message multi-parties au format MIME.',

    // Notes de modération
    'ADMINHELPER_MOD_NOTE_BTN'         => 'Inclure une note de modération',
    'ADMINHELPER_MOD_NOTE_SAVE'        => 'Enregistrer la note',
    'ADMINHELPER_MOD_NOTE_DELETE'      => 'Supprimer la note',
    'ADMINHELPER_MOD_NOTE_PLACEHOLDER' => 'Note interne (visible uniquement par les modérateurs)...',
    'ADMINHELPER_MOD_NOTE_BY'          => 'Note de %s le %s',
    'ADMINHELPER_MOD_NOTE_SAVED'       => 'Note enregistrée.',
    'ADMINHELPER_MOD_NOTE_DELETED'     => 'Note supprimée.',
    'ADMINHELPER_MOD_NOTE_DENIED'      => 'Action non autorisée.',
    'ADMINHELPER_MOD_NOTE_INVALID'     => 'Formulaire invalide ou expiré.',
    'ADMINHELPER_MOD_NOTES_TITLE'      => 'Posts à modérer',
    'ADMINHELPER_MOD_NOTES_EXPLAIN'    => 'Liste de tous les posts ayant une note de modération interne.',
    'ADMINHELPER_MOD_NOTES_EMPTY'      => 'Aucun post avec note de modération.',
    'ADMINHELPER_MOD_NOTES_QUICKLINK'  => 'Posts à modérer',
    'ADMINHELPER_MOD_NOTES_COL_DATE'   => 'Date note',
    'ADMINHELPER_MOD_NOTES_COL_BY'     => 'Posée par',
    'ADMINHELPER_MOD_NOTES_COL_POST'   => 'Post',
    'ADMINHELPER_MOD_NOTES_COL_AUTHOR' => 'Auteur post',
    'ADMINHELPER_MOD_NOTES_COL_FORUM'  => 'Forum',
    'ADMINHELPER_MOD_NOTES_COL_NOTE'   => 'Note',
    'ADMINHELPER_MOD_NOTES_COL_ACTION' => 'Action',
    'ADMINHELPER_MOD_NOTES_GO_POST'    => 'Voir le post',
]);
