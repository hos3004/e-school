<?php

declare(strict_types=1);

return [
    'routing_hint' => 'Un modèle est identifié par événement × canal × langue. Une organisation ne peut avoir qu’un modèle par combinaison.',
    'subject_hint' => 'Objet du message, utilisé pour l’e-mail et les notifications internes.',
    'body_hint' => 'Corps du message. Utilisez {{nom_parametre}} pour injecter une valeur déclarée.',
    'parameters_hint' => 'Noms des variables du corps, dans leur ordre d’apparition. Elles doivent exister dans l’événement.',
    'provider_template_hint' => 'WhatsApp uniquement : nom du modèle Meta approuvé.',
    'scope_global' => 'Valeur globale', 'scope_organization' => 'Organisation', 'scope_all' => 'Tous',
    'clone_action' => 'Créer une copie pour l’organisation',
    'clone_heading' => 'Personnaliser ce modèle pour votre organisation',
    'clone_description' => 'Crée une copie propre à l’organisation qui remplace le modèle global au moment de l’envoi.',
    'clone_done' => 'La copie du modèle a été créée.',
    'clone_conflict' => 'Une copie existe déjà pour le même événement, canal et langue.',
    'duplicate' => 'Un modèle existe déjà pour cette organisation avec le même événement, canal et langue.',
    'locale_ar' => 'Arabe', 'locale_en' => 'Anglais', 'locale_fr' => 'Français',
];
