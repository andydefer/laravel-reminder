# Laravel Reminder

Une solution flexible et robuste pour gérer les rappels dans vos applications Laravel.

## Introduction

Laravel Reminder est un package qui vous permet d'ajouter facilement un système de rappels à vos modèles Eloquent. Que ce soit pour envoyer des notifications d'échéance, des rappels de rendez-vous, ou toute autre alerte temporelle, ce package vous offre une architecture propre et extensible, intégrée nativement avec le système de notification de Laravel.

```php
<?php

// Exemple simple : créer un rappel pour un rendez-vous
$appointment = Appointment::find(1);

$reminder = $appointment->scheduleReminder(
    scheduledAt: $appointment->date->subHours(24), // Le rappel sera envoyé 24h avant le rendez-vous
    metadata: ['type' => 'email', 'priority' => 'high'], // Données supplémentaires stockées avec le rappel
    channels: ['mail', 'sms'] // Canaux de notification personnalisés pour ce rappel
);
```

## Concept fondamental

Le package repose sur un principe simple mais puissant : **tout modèle qui doit recevoir des rappels est "rappelable"** (remindable). Ces rappels sont automatiquement traités selon une fenêtre de tolérance que vous définissez.

### Comment ça marche ?

1. **Planification** : Vous créez des rappels pour vos modèles à des dates spécifiques
2. **Traitement** : Un job planifié vérifie régulièrement les rappels à envoyer
3. **Fenêtre de tolérance** : Chaque modèle définit sa propre fenêtre d'acceptation (ex: 30 minutes)
4. **Notification** : Le modèle retourne une notification Laravel à envoyer
5. **Envoi automatique** : Le système utilise `notify()` pour envoyer la notification
6. **Suivi** : Le système garde une trace de chaque tentative (succès/échec)

## Installation

```bash
composer require andydefer/laravel-reminder
```

### Publication des ressources (optionnel)

```bash
# Publier la configuration
php artisan vendor:publish --provider="Andydefer\LaravelReminder\ReminderServiceProvider" --tag="reminder-config"

# Publier et exécuter les migrations
php artisan vendor:publish --provider="Andydefer\LaravelReminder\ReminderServiceProvider" --tag="reminder-migrations"
php artisan migrate
```

> **Note** : Les migrations sont automatiquement chargées par le package si vous ne les publiez pas.

## Configuration

Le fichier de configuration `config/reminder.php` vous offre un contrôle total sur le comportement du package :

```php
<?php

use Andydefer\LaravelReminder\Enums\ToleranceUnit;

return [
    /*
    |--------------------------------------------------------------------------
    | Tolérance par défaut
    |--------------------------------------------------------------------------
    |
    | Cette valeur définit la fenêtre de tolérance par défaut pour tous les modèles.
    | Chaque modèle peut surcharger cette valeur en implémentant getTolerance().
    |
    | Exemple: 30 minutes signifie que le rappel sera accepté 30 minutes avant
    |          ou après la date prévue.
    |
    */
    'default_tolerance' => [
        'value' => 30,
        'unit' => ToleranceUnit::MINUTE, // MINUTE, HOUR, DAY, WEEK, MONTH, YEAR
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombre maximum de tentatives
    |--------------------------------------------------------------------------
    |
    | Détermine combien de fois le système tentera d'envoyer un rappel
    | avant de le marquer comme échoué.
    |
    */
    'max_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Configuration de la file d'attente
    |--------------------------------------------------------------------------
    |
    | Configure comment les rappels sont traités via le système de queue.
    | Mettre enabled à false pour un traitement synchrone.
    |
    */
    'queue' => [
        'enabled' => env('REMINDER_QUEUE_ENABLED', true),
        'name' => env('REMINDER_QUEUE_NAME', 'default'),
        'connection' => env('REMINDER_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fréquence du scheduler
    |--------------------------------------------------------------------------
    |
    | Détermine à quelle fréquence le scheduler vérifie les rappels à envoyer.
    | Valeur en secondes. Valeurs courantes : 15, 30, 60.
    |
    */
    'schedule_frequency' => 15,

    /*
    |--------------------------------------------------------------------------
    | Configuration du nettoyage
    |--------------------------------------------------------------------------
    |
    | Nettoie automatiquement les anciens rappels pour garder la base propre.
    |
    */
    'cleanup' => [
        'enabled' => env('REMINDER_CLEANUP_ENABLED', false),
        'after_days' => env('REMINDER_CLEANUP_AFTER_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des logs
    |--------------------------------------------------------------------------
    |
    | Configure le comportement des logs pour le traitement des rappels.
    |
    */
    'logging' => [
        'enabled' => env('REMINDER_LOGGING_ENABLED', true),
        'channel' => env('REMINDER_LOG_CHANNEL', 'stack'),
    ],
];
```

## Utilisation de base

### 1. Rendre un modèle "rappelable"

Commencez par ajouter le trait `Remindable` à votre modèle :

```php
<?php

namespace App\Models;

use Andydefer\LaravelReminder\Traits\Remindable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use Remindable; // Donne accès à toutes les méthodes de rappel

    // Le reste de votre modèle...
}
```

### 2. Implémenter le contrat ShouldRemind

Pour qu'un modèle puisse recevoir des rappels, il doit implémenter l'interface `ShouldRemind` :

```php
<?php

namespace App\Models;

use Andydefer\LaravelReminder\Contracts\ShouldRemind; // Interface à implémenter
use Andydefer\LaravelReminder\Enums\ToleranceUnit; // Unité de temps pour la tolérance
use Andydefer\LaravelReminder\Models\Reminder; // Modèle Reminder
use Andydefer\LaravelReminder\Traits\Remindable; // Trait pour les rappels
use Andydefer\LaravelReminder\ValueObjects\Tolerance; // Value Object pour la tolérance
use App\Notifications\ArticleReminderNotification; // Votre notification
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable; // Nécessaire pour les notifications
use Illuminate\Notifications\Notification;

class Article extends Model implements ShouldRemind
{
    use Remindable, Notifiable; // Notifiable est requis pour recevoir des notifications

    /**
     * Retourne la notification à envoyer pour ce rappel
     *
     * @param Reminder $reminder Le rappel en cours de traitement
     * @return Notification
     */
    public function toRemind(Reminder $reminder): Notification
    {
        // Récupère les métadonnées stockées ou un tableau vide
        $metadata = $reminder->metadata ?? [];

        // Retourne une notification Laravel standard
        return new ArticleReminderNotification($this, $reminder, $metadata);
    }

    /**
     * Définit la fenêtre de tolérance pour ce modèle
     *
     * @return Tolerance
     */
    public function getTolerance(): Tolerance
    {
        // Exemple : tolérance de 2 heures
        return new Tolerance(
            value: 2,
            unit: ToleranceUnit::HOUR
        );

        // Autres possibilités :
        // return new Tolerance(30, ToleranceUnit::MINUTE); // 30 minutes
        // return new Tolerance(1, ToleranceUnit::DAY);    // 1 jour
        // return new Tolerance(1, ToleranceUnit::WEEK);   // 1 semaine
    }

    // Le reste de votre modèle...
}
```

### 3. Créer une notification Laravel

Créez une notification standard Laravel qui sera envoyée lors du rappel :

```php
<?php

namespace App\Notifications;

use App\Models\Article;
use Andydefer\LaravelReminder\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class ArticleReminderNotification extends Notification
{
    use Queueable; // Pour la mise en file d'attente

    /**
     * Constructeur de la notification
     *
     * @param Article $article Le modèle concerné
     * @param Reminder $reminder Le rappel en cours
     * @param array $metadata Métadonnées supplémentaires
     */
    public function __construct(
        protected Article $article,
        protected Reminder $reminder,
        protected array $metadata = []
    ) {}

    /**
     * Définit les canaux de notification pour ce rappel
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        // Utilise les canaux personnalisés du reminder
        // S'il n'y en a pas, utilise ['mail'] par défaut
        return $this->reminder->channelsForSending(['mail']);

        // Exemples de retour selon les canaux définis :
        // - ['mail'] → envoi par email uniquement
        // - ['mail', 'sms'] → envoi par email ET SMS
        // - ['database'] → stockage en base uniquement
    }

    /**
     * Notification par email
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Rappel: {$this->article->title}")
            ->line("N'oubliez pas de publier votre article !")
            ->line("Prévu le: {$this->reminder->scheduled_at->format('d/m/Y H:i')}")
            ->action('Voir l\'article', url("/articles/{$this->article->id}"));
    }

    /**
     * Notification en base de données
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'article_id' => $this->article->id,
            'article_title' => $this->article->title,
            'reminder_id' => $this->reminder->id,
            'scheduled_at' => $this->reminder->scheduled_at->toDateTimeString(),
            'metadata' => $this->metadata,
            'channels' => $this->reminder->channels(), // Canaux utilisés
        ];
    }

    /**
     * Notification par SMS (si configuré)
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toSms($notifiable): array
    {
        return [
            'message' => "Rappel: {$this->article->title} à publier le {$this->reminder->scheduled_at->format('d/m/Y H:i')}",
        ];
    }

    /**
     * Notification Slack (si configuré)
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\SlackMessage
     */
    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->content("Rappel: {$this->article->title}");
    }
}
```

### 4. Planifier des rappels

Une fois votre modèle configuré, vous pouvez planifier des rappels de plusieurs façons :

```php
<?php

// Récupérer un modèle
$article = Article::find(1);

// 1. Rappel simple avec date Carbon
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDays(7), // Date d'envoi : dans 7 jours
    metadata: ['type' => 'email'], // Données supplémentaires
    channels: ['mail', 'database'] // Canaux personnalisés
);

// 2. Rappel avec date en string
$reminder = $article->scheduleReminder(
    scheduledAt: '2025-12-25 09:00:00', // Date au format string
    channels: ['mail'] // Email uniquement
);

// 3. Rappel sans canaux spécifiques (utilisera le fallback)
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDays(3)
);

// 4. Vérifier le résultat après planification
echo $reminder->scheduled_at; // Date planifiée
print_r($reminder->channels()); // Canaux utilisés
echo $reminder->has_custom_channels ? 'Canaux personnalisés' : 'Canaux par défaut';
```

## Planification multiple

### Format 1 : Tableau indexé (mêmes canaux pour tous)

```php
<?php

// Tous les rappels utiliseront les mêmes canaux
$reminders = $article->scheduleMultipleReminders(
    scheduledTimes: [
        now()->addDays(7),  // Premier rappel : dans 7 jours
        now()->addDays(3),  // Deuxième rappel : dans 3 jours
        now()->addDay(),    // Troisième rappel : demain
    ],
    metadata: ['priority' => 'high'], // Métadonnées communes
    globalChannels: ['sms'] // Tous ces rappels utiliseront le canal SMS
);

// Résultat : 3 rappels avec channels = ['sms'] pour chacun
foreach ($reminders as $index => $reminder) {
    echo "Rappel " . ($index + 1) . " : " . $reminder->scheduled_at;
    echo " - Canaux : " . implode(', ', $reminder->channels());
}
```

### Format 2 : Tableau associatif (canaux différents par rappel)

```php
<?php

// Chaque rappel a ses propres canaux
$reminders = $article->scheduleMultipleReminders(
    scheduledTimes: [
        now()->addDays(7) => ['mail'],           // J-7 : email uniquement
        now()->addDays(3) => ['mail', 'sms'],    // J-3 : email + SMS
        now()->addDay()   => ['sms'],            // J-1 : SMS uniquement
    ],
    metadata: ['priority' => 'high'] // Métadonnées communes
);

// Vérifier les canaux de chaque rappel
echo "Premier rappel (J-7) : " . implode(', ', $reminders[0]->channels()); // mail
echo "Deuxième rappel (J-3) : " . implode(', ', $reminders[1]->channels()); // mail, sms
echo "Troisième rappel (J-1) : " . implode(', ', $reminders[2]->channels()); // sms
```

### Format 3 : Format mixte

```php
<?php

// Mélange de dates seules et de dates avec canaux
$reminders = $article->scheduleMultipleReminders(
    scheduledTimes: [
        now()->addDays(7),                    // Date seule → utilisera les canaux globaux
        now()->addDays(3) => ['mail', 'sms'], // Date avec canaux → utilise ses propres canaux
        now()->addDay(),                       // Date seule → utilisera les canaux globaux
    ],
    metadata: ['priority' => 'high'],
    channels: ['mail'] // Canaux globaux pour les dates seules
);

// Résultat :
// - Rappel 1 (J-7) : channels = ['mail'] (canaux globaux)
// - Rappel 2 (J-3) : channels = ['mail', 'sms'] (ses propres canaux)
// - Rappel 3 (J-1) : channels = ['mail'] (canaux globaux)
```

## Gestion des rappels existants

### Récupération des rappels

```php
<?php

// Tous les rappels du modèle (relation Eloquent)
$allReminders = $article->reminders; // Collection de tous les rappels

// Rappels en attente uniquement
$pendingReminders = $article->pendingReminders(); // Collection des rappels avec status = PENDING

// Prochain rappel à venir (le plus proche dans le futur)
$nextReminder = $article->nextReminder(); // Instance de Reminder ou null

// Vérifier s'il y a des rappels en attente
if ($article->hasPendingReminders()) {
    echo "Vous avez " . $article->pendingReminders()->count() . " rappel(s) en attente";
}
```

### Requêtes avancées avec le modèle Reminder

```php
<?php

use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Enums\ReminderStatus;

// Tous les rappels en attente (global)
$pending = Reminder::pending()->get();

// Rappels à envoyer maintenant (scheduled_at <= now, attempts < max)
$due = Reminder::due()->get();

// Rappels dans une fenêtre de tolérance (30 minutes)
$withinTolerance = Reminder::withinTolerance(30)->get();

// Rappels avec canaux personnalisés
$customChannels = Reminder::whereNotNull('channels')
    ->where('channels', '!=', json_encode([]))
    ->get();

// Rappels échoués
$failed = Reminder::where('status', ReminderStatus::FAILED)->get();

// Rappels envoyés aujourd'hui
$sentToday = Reminder::where('status', ReminderStatus::SENT)
    ->whereDate('sent_at', today())
    ->get();

// Compter les rappels par statut
$stats = [
    'pending' => Reminder::pending()->count(),
    'sent' => Reminder::where('status', ReminderStatus::SENT)->count(),
    'failed' => Reminder::where('status', ReminderStatus::FAILED)->count(),
    'cancelled' => Reminder::where('status', ReminderStatus::CANCELLED)->count(),
];
```

### Mise à jour manuelle des rappels

```php
<?php

$reminder = Reminder::find(1);

// Marquer comme envoyé avec succès
$reminder->markAsSent();
// Met à jour :
// - status = SENT
// - sent_at = maintenant
// - last_attempt_at = maintenant

// Marquer comme échoué
$reminder->markAsFailed('Connection timeout');
// Met à jour :
// - attempts = attempts + 1
// - last_attempt_at = maintenant
// - error_message = 'Connection timeout'
// - status = FAILED si attempts >= max_attempts, sinon reste PENDING

// Annuler le rappel
$reminder->cancel();
// Met à jour : status = CANCELLED
```

### Vérifications d'état

```php
<?php

if ($reminder->isPending()) {
    echo "Rappel en attente d'envoi";
}

if ($reminder->wasSent()) {
    echo "Rappel envoyé le " . $reminder->sent_at->format('d/m/Y H:i');
}

if ($reminder->hasFailed()) {
    echo "Échec après " . $reminder->attempts . " tentative(s)";
    echo "Erreur : " . $reminder->error_message;
}

// Vérifier le statut via l'énumération
if ($reminder->status === ReminderStatus::PENDING) {
    // ...
}

if ($reminder->status->isTerminal()) {
    echo "Statut final (ne changera plus)";
}
```

### Annulation de rappels

```php
<?php

// Annuler tous les rappels en attente d'un modèle
$cancelledCount = $article->cancelReminders();
// Retourne le nombre de rappels annulés
echo "$cancelledCount rappel(s) annulé(s)";

// Annuler un rappel spécifique
$reminder->cancel();

// Annuler avec conditions
$article->reminders()
    ->where('scheduled_at', '<', now()) // Rappels dans le passé
    ->pending() // En attente uniquement
    ->get()
    ->each->cancel(); // Annuler chacun
```

## Les channels de notification

### Définir des canaux personnalisés

```php
<?php

// Rappel avec canaux personnalisés
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDays(7),
    channels: ['mail', 'sms', 'database']
);

// Vérifier les canaux
$channels = $reminder->channels(); // Retourne ['mail', 'sms', 'database']
$hasCustom = $reminder->has_custom_channels; // true (car des canaux sont définis)

// Rappel sans canaux personnalisés
$reminder = $article->scheduleReminder(now()->addDays(3));
$channels = $reminder->channels(); // Retourne [] (tableau vide)
$hasCustom = $reminder->has_custom_channels; // false
```

### Utiliser channelsForSending()

Dans votre notification, utilisez cette méthode pour récupérer les canaux :

```php
<?php

public function via($notifiable): array
{
    // channelsForSending() retourne :
    // - les canaux personnalisés s'ils existent
    // - le fallback ['mail'] si pas de canaux personnalisés
    return $this->reminder->channelsForSending(['mail']);

    // Exemples :
    // Si reminder a ['mail', 'sms'] → retourne ['mail', 'sms']
    // Si reminder a [] → retourne ['mail']
    // Si reminder a null → retourne ['mail']
}
```

### Exemples concrets de combinaisons de canaux

```php
<?php

// 1. Email uniquement
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDays(7),
    channels: ['mail']
);

// 2. Email + SMS
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDays(3),
    channels: ['mail', 'sms']
);

// 3. SMS uniquement (pour les rappels urgents)
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addHours(2),
    channels: ['sms']
);

// 4. Tous les canaux disponibles
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDay(),
    channels: ['mail', 'sms', 'database', 'slack', 'push']
);

// 5. Stockage en base uniquement (pas de notification temps réel)
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDays(7),
    channels: ['database']
);
```

## Commandes Artisan

### Syntaxe et options

```bash
# Traitement synchrone (sans file d'attente)
# Utile pour : tests, debugging, ou quand on veut le résultat immédiat
php artisan reminders:send --sync

# Traitement avec file d'attente (par défaut)
# Dispatche un job qui sera traité par un worker
php artisan reminders:send

# Spécifier une file d'attente
# Permet de prioriser certains rappels
php artisan reminders:send --queue=emails
php artisan reminders:send --queue=high-priority
```

### Exemple de sortie

```bash
$ php artisan reminders:send --sync

Starting reminder processing...
Processing reminders synchronously...

+-----------+-------+
| Metric    | Count |
+-----------+-------+
| Total     | 10    |
| Processed | 8     |
| Failed    | 2     |
+-----------+-------+

Reminders processed successfully!
```

### Utilisation dans du code

```php
<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;

// Dans un script ou une commande
$exitCode = Artisan::call('reminders:send', ['--sync' => true]);

if ($exitCode === Command::SUCCESS) {
    echo "Traitement terminé avec succès";
    // Command::SUCCESS = 0
} else {
    echo "Erreur lors du traitement";
    // Command::FAILURE = 1
}

// Avec file d'attente
Artisan::call('reminders:send');
Artisan::call('reminders:send', ['--queue' => 'high-priority']);
```

## Scheduler et File d'attente

### Configuration dans le fichier .env

```env
# Activer/désactiver la file d'attente
REMINDER_QUEUE_ENABLED=true

# Connexion à utiliser (sync, database, redis, sqs, etc.)
REMINDER_QUEUE_CONNECTION=database

# Nom de la file d'attente
REMINDER_QUEUE_NAME=reminders
```

### Configuration du scheduler

```php
<?php
// config/reminder.php

'schedule_frequency' => 15, // secondes

// Options disponibles :
// - 15  → everyFifteenSeconds()  (toutes les 15 secondes)
// - 30  → everyThirtySeconds()   (toutes les 30 secondes)
// - 60  → everyMinute()          (toutes les minutes)
// - 120 → cron("*/120 * * * * *") (toutes les 2 minutes)
// - 300 → cron("*/300 * * * * *") (toutes les 5 minutes)
```

### Mise en place de la crontab

```bash
# Éditer la crontab
crontab -e

# Ajouter cette ligne (adapter le chemin vers votre projet)
* * * * * cd /home/user/projets/mon-app && php artisan schedule:run >> /dev/null 2>&1

# Vérifier que la crontab est bien configurée
crontab -l | grep "schedule:run"
```

### Vérification du bon fonctionnement

```bash
# Voir les rappels en attente
php artisan tinker
>>> Reminder::pending()->count()

# Vérifier les erreurs
>>> Reminder::whereNotNull('error_message')->get()

# Tester manuellement
php artisan reminders:send --sync

# Si vous utilisez la queue database :
php artisan queue:table
php artisan migrate
php artisan queue:work --queue=reminders
```

## Architecture détaillée

### Le modèle Reminder

```php
<?php

namespace Andydefer\LaravelReminder\Models;

use Andydefer\LaravelReminder\Casts\ChannelsCast;
use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    protected $table = 'reminders';

    protected $fillable = [
        'remindable_type', // Type du modèle associé (ex: App\Models\Article)
        'remindable_id',   // ID du modèle associé
        'scheduled_at',    // Date prévue pour l'envoi
        'sent_at',         // Date d'envoi effectif
        'status',          // Statut du rappel (pending, sent, failed, cancelled)
        'metadata',        // Données supplémentaires (JSON)
        'channels',        // Canaux de notification personnalisés (JSON)
        'attempts',        // Nombre de tentatives effectuées
        'last_attempt_at', // Date de la dernière tentative
        'error_message',   // Message d'erreur en cas d'échec
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'metadata' => 'array',
        'channels' => ChannelsCast::class, // Cast personnalisé pour les canaux
        'status' => ReminderStatus::class, // Cast vers l'énumération
        'attempts' => 'integer',
    ];

    protected $attributes = [
        'attempts' => 0,
        'status' => ReminderStatus::PENDING,
    ];

    /**
     * Relation polymorphique vers le modèle associé
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Retourne les canaux de notification
     *
     * @return array
     */
    public function channels(): array
    {
        return $this->channels ?? [];
    }

    /**
     * Indique si des canaux personnalisés sont définis
     *
     * @return bool
     */
    public function getHasCustomChannelsAttribute(): bool
    {
        return !empty($this->channels());
    }

    /**
     * Retourne les canaux à utiliser (personnalisés ou fallback)
     *
     * @param array $fallbackChannels Canaux par défaut
     * @return array
     */
    public function channelsForSending(array $fallbackChannels = ['mail']): array
    {
        return $this->has_custom_channels
            ? $this->channels()
            : $fallbackChannels;
    }

    /**
     * Marque le rappel comme envoyé
     */
    public function markAsSent(): self
    {
        $this->update([
            'status' => ReminderStatus::SENT,
            'sent_at' => now(),
            'last_attempt_at' => now(),
        ]);

        return $this;
    }

    /**
     * Marque le rappel comme échoué
     *
     * @param string $error Message d'erreur
     */
    public function markAsFailed(string $error): self
    {
        $maxAttempts = config('reminder.max_attempts', 3);
        $newStatus = $this->attempts + 1 >= $maxAttempts
            ? ReminderStatus::FAILED
            : ReminderStatus::PENDING;

        $this->update([
            'status' => $newStatus,
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
            'error_message' => $error,
        ]);

        return $this;
    }

    /**
     * Annule le rappel
     */
    public function cancel(): self
    {
        $this->update(['status' => ReminderStatus::CANCELLED]);

        return $this;
    }

    /**
     * Vérifie si le rappel est en attente
     */
    public function isPending(): bool
    {
        return $this->status === ReminderStatus::PENDING;
    }

    /**
     * Vérifie si le rappel a été envoyé
     */
    public function wasSent(): bool
    {
        return $this->status === ReminderStatus::SENT;
    }

    /**
     * Vérifie si le rappel a échoué
     */
    public function hasFailed(): bool
    {
        return $this->status === ReminderStatus::FAILED;
    }

    /**
     * Scope : rappels en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value);
    }

    /**
     * Scope : rappels à envoyer maintenant
     */
    public function scopeDue($query)
    {
        return $query->where('status', ReminderStatus::PENDING->value)
            ->where('scheduled_at', '<=', now())
            ->where('attempts', '<', config('reminder.max_attempts', 3));
    }

    /**
     * Scope : rappels dans une fenêtre de tolérance
     *
     * @param int $toleranceMinutes Tolérance en minutes
     */
    public function scopeWithinTolerance($query, int $toleranceMinutes)
    {
        return $query->whereBetween('scheduled_at', [
            now()->subMinutes($toleranceMinutes),
            now()->addMinutes($toleranceMinutes)
        ]);
    }
}
```

### Les énumérations

#### ReminderStatus

```php
<?php

namespace Andydefer\LaravelReminder\Enums;

enum ReminderStatus: string
{
    case PENDING = 'pending';   // En attente d'envoi
    case SENT = 'sent';         // Envoyé avec succès
    case FAILED = 'failed';     // Échoué après plusieurs tentatives
    case CANCELLED = 'cancelled'; // Annulé manuellement

    /**
     * Libellé affichable du statut
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::SENT => 'Envoyé',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
        };
    }

    /**
     * Vérifie si le statut est "en attente"
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Vérifie si le statut est terminal (ne changera plus)
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED, self::CANCELLED], true);
    }
}
```

#### ToleranceUnit

```php
<?php

namespace Andydefer\LaravelReminder\Enums;

enum ToleranceUnit: string
{
    case YEAR = 'year';
    case MONTH = 'month';
    case WEEK = 'week';
    case DAY = 'day';
    case HOUR = 'hour';
    case MINUTE = 'minute';

    /**
     * Convertit l'unité en minutes
     */
    public function toMinutes(): int
    {
        return match ($this) {
            self::YEAR => 525600,   // 365 jours
            self::MONTH => 43800,    // 30 jours
            self::WEEK => 10080,     // 7 jours
            self::DAY => 1440,       // 24 heures
            self::HOUR => 60,
            self::MINUTE => 1,
        };
    }

    /**
     * Convertit l'unité en secondes
     */
    public function toSeconds(): int
    {
        return $this->toMinutes() * 60;
    }

    /**
     * Libellé affichable de l'unité
     */
    public function label(): string
    {
        return match ($this) {
            self::YEAR => 'Année',
            self::MONTH => 'Mois',
            self::WEEK => 'Semaine',
            self::DAY => 'Jour',
            self::HOUR => 'Heure',
            self::MINUTE => 'Minute',
        };
    }
}
```

### Le Value Object Tolerance

```php
<?php

namespace Andydefer\LaravelReminder\ValueObjects;

use Andydefer\LaravelReminder\Enums\ToleranceUnit;

class Tolerance
{
    /**
     * @param int $value Valeur numérique
     * @param ToleranceUnit $unit Unité de temps
     */
    public function __construct(
        public readonly int $value,
        public readonly ToleranceUnit $unit
    ) {
        if ($value < 0) {
            throw new \InvalidArgumentException('La valeur de tolérance ne peut pas être négative');
        }
    }

    /**
     * Convertit la tolérance en minutes
     */
    public function toMinutes(): int
    {
        return $this->value * $this->unit->toMinutes();
    }

    /**
     * Convertit la tolérance en secondes
     */
    public function toSeconds(): int
    {
        return $this->value * $this->unit->toSeconds();
    }

    /**
     * Vérifie si une date est dans la fenêtre de tolérance
     *
     * @param \DateTimeInterface $scheduledAt Date prévue
     * @param \DateTimeInterface $now Date actuelle
     * @return bool
     */
    public function isWithinWindow(\DateTimeInterface $scheduledAt, \DateTimeInterface $now): bool
    {
        $diffInMinutes = abs($now->getTimestamp() - $scheduledAt->getTimestamp()) / 60;
        return $diffInMinutes <= $this->toMinutes();
    }

    /**
     * Représentation textuelle (ex: "2 Heures")
     */
    public function __toString(): string
    {
        $label = $this->unit->label();
        return $this->value . ' ' . $label . ($this->value > 1 ? 's' : '');
    }
}
```

### Le Cast ChannelsCast

```php
<?php

namespace Andydefer\LaravelReminder\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ChannelsCast implements CastsAttributes
{
    /**
     * Convertit le JSON en tableau
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return array
     */
    public function get($model, string $key, $value, array $attributes): array
    {
        // Si la valeur est null, vide, ou '[]', retourne un tableau vide
        if (is_null($value) || $value === '[]' || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Convertit le tableau en JSON pour le stockage
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return string
     */
    public function set($model, string $key, $value, array $attributes): string
    {
        // Si la valeur est null, retourne un tableau vide en JSON
        if (is_null($value)) {
            return json_encode([]);
        }

        // Si c'est un tableau, on le réindexe et on l'encode
        if (is_array($value)) {
            return json_encode(array_values($value));
        }

        // Dans tous les autres cas, retourne un tableau vide
        return json_encode([]);
    }
}
```

### Le trait Remindable

```php
<?php

namespace Andydefer\LaravelReminder\Traits;

use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Andydefer\LaravelReminder\Models\Reminder;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

trait Remindable
{
    /**
     * Relation : tous les rappels du modèle
     *
     * @return MorphMany
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Planifie un nouveau rappel
     *
     * @param DateTimeInterface|string $scheduledAt Date d'envoi
     * @param array $metadata Métadonnées supplémentaires
     * @param array|null $channels Canaux personnalisés
     * @return Reminder
     * @throws InvalidArgumentException
     */
    public function scheduleReminder(
        DateTimeInterface|string $scheduledAt,
        array $metadata = [],
        ?array $channels = []
    ): Reminder {
        $scheduledAt = $this->parseScheduledAt($scheduledAt);

        if ($scheduledAt->isPast()) {
            throw new InvalidArgumentException('Impossible de planifier un rappel dans le passé');
        }

        return $this->reminders()->create([
            'scheduled_at' => $scheduledAt,
            'metadata' => $metadata,
            'channels' => $channels ?? [],
            'status' => ReminderStatus::PENDING,
            'attempts' => 0,
        ]);
    }

    /**
     * Planifie plusieurs rappels
     *
     * @param array $scheduledTimes Tableau de dates ou tableau associatif [date => canaux]
     * @param array $metadata Métadonnées communes
     * @param array|null $globalChannels Canaux par défaut
     * @return array
     * @throws InvalidArgumentException
     */
    public function scheduleMultipleReminders(array $scheduledTimes, array $metadata = [], ?array $globalChannels = []): array
    {
        if (empty($scheduledTimes)) {
            throw new InvalidArgumentException('Le tableau des dates ne peut pas être vide');
        }

        $reminders = [];

        foreach ($scheduledTimes as $key => $value) {
            // Cas 1 : la valeur est un tableau → format [date => canaux]
            if (is_array($value)) {
                $scheduledAt = $key;
                $channels = $value;
            }
            // Cas 2 : la clé est un entier → format indexé [date, date]
            elseif (is_int($key)) {
                $scheduledAt = $value;
                $channels = $globalChannels;
            }
            // Cas 3 : la clé n'est pas un entier (donc c'est une date) et la valeur n'est pas un tableau
            // Format mixte avec date seule mais clé associative
            else {
                $scheduledAt = $key;
                $channels = $globalChannels;
            }

            $reminders[] = $this->scheduleReminder($scheduledAt, $metadata, $channels);
        }

        return $reminders;
    }

    /**
     * Annule tous les rappels en attente
     *
     * @return int Nombre de rappels annulés
     */
    public function cancelReminders(): int
    {
        return $this->reminders()
            ->where('status', ReminderStatus::PENDING->value)
            ->update(['status' => ReminderStatus::CANCELLED]);
    }

    /**
     * Récupère tous les rappels en attente
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function pendingReminders()
    {
        return $this->reminders()->pending()->get();
    }

    /**
     * Vérifie s'il y a des rappels en attente
     *
     * @return bool
     */
    public function hasPendingReminders(): bool
    {
        return $this->reminders()->pending()->exists();
    }

    /**
     * Récupère le prochain rappel à venir
     *
     * @return Reminder|null
     */
    public function nextReminder(): ?Reminder
    {
        return $this->reminders()
            ->pending()
            ->orderBy('scheduled_at')
            ->first();
    }

    /**
     * Parse la date d'envoi
     *
     * @param DateTimeInterface|string $scheduledAt
     * @return Carbon
     * @throws InvalidArgumentException
     */
    private function parseScheduledAt(DateTimeInterface|string $scheduledAt): Carbon
    {
        if ($scheduledAt instanceof DateTimeInterface) {
            return $scheduledAt instanceof Carbon
                ? $scheduledAt
                : Carbon::instance($scheduledAt);
        }

        try {
            return Carbon::parse($scheduledAt);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                'Format de date invalide pour scheduled_at : ' . $scheduledAt
            );
        }
    }
}
```

### Le service ReminderService

```php
<?php

namespace Andydefer\LaravelReminder\Services;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Notification;
use Throwable;

class ReminderService
{
    public function __construct(
        protected array $config,
        protected Dispatcher $events
    ) {}

    /**
     * Traite un rappel individuel
     *
     * @param Reminder $reminder
     * @return bool True si succès, False si échec
     */
    public function processReminder(Reminder $reminder): bool
    {
        // Dispatch événement de début de traitement
        $this->events->dispatch('reminder.processing', $reminder);

        try {
            // Récupérer le modèle associé
            $remindable = $reminder->remindable;

            // Vérifier que le modèle implémente ShouldRemind
            if (!$remindable instanceof ShouldRemind) {
                throw new \RuntimeException(
                    get_class($remindable) . ' n\'implémente pas l\'interface ShouldRemind'
                );
            }

            // Vérifier la fenêtre de tolérance
            $tolerance = $remindable->getTolerance();
            $now = Carbon::now();

            if (!$tolerance->isWithinWindow($reminder->scheduled_at, $now)) {
                $reminder->markAsFailed('Hors de la fenêtre de tolérance');
                $this->events->dispatch('reminder.outside_tolerance', $reminder);
                return false;
            }

            // Obtenir la notification
            $notification = $remindable->toRemind($reminder);

            if (!$notification instanceof Notification) {
                throw new \RuntimeException(
                    'toRemind() doit retourner une instance de Illuminate\Notifications\Notification'
                );
            }

            // Envoyer la notification
            $remindable->notify($notification);

            // Marquer comme envoyé
            $reminder->markAsSent();
            $this->events->dispatch('reminder.sent', $reminder);

            return true;
        } catch (Throwable $e) {
            // En cas d'erreur, marquer comme échoué
            $reminder->markAsFailed($e->getMessage());
            $this->events->dispatch('reminder.failed', [$reminder, $e]);

            return false;
        }
    }

    /**
     * Traite tous les rappels en attente
     *
     * @return array Statistiques [total, processed, failed]
     */
    public function processPendingReminders(): array
    {
        $reminders = Reminder::due()->get();

        $results = [
            'total' => $reminders->count(),
            'processed' => 0,
            'failed' => 0,
        ];

        foreach ($reminders as $reminder) {
            $success = $this->processReminder($reminder);

            if ($success) {
                $results['processed']++;
            } else {
                $results['failed']++;
            }
        }

        $this->events->dispatch('reminder.processed', $results);

        return $results;
    }

    /**
     * Retourne la configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
```

### Le job ProcessRemindersJob

```php
<?php

namespace Andydefer\LaravelReminder\Jobs;

use Andydefer\LaravelReminder\Services\ReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives maximum
     */
    public int $tries = 1;

    /**
     * Nombre maximum d'exceptions avant échec
     */
    public int $maxExceptions = 1;

    /**
     * Timeout en secondes
     */
    public int $timeout = 120;

    /**
     * Échouer si timeout
     */
    public bool $failOnTimeout = true;

    /**
     * Constructeur : configure la connexion et la file d'attente
     */
    public function __construct()
    {
        $this->onConnection(config('reminder.queue.connection', config('queue.default')));
        $this->onQueue(config('reminder.queue.name', 'default'));
    }

    /**
     * Exécute le job
     */
    public function handle(ReminderService $reminderService): void
    {
        Log::info('Traitement des rappels en attente');

        $startTime = microtime(true);
        $result = $reminderService->processPendingReminders();
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Rappels traités', array_merge($result, [
            'execution_time_ms' => $executionTime,
            'job_id' => $this->job?->getJobId(),
        ]));

        // Si beaucoup de rappels, replanifier
        if ($result['total'] >= 100 && $this->attempts() < 3) {
            $this->release(30); // Replanifier dans 30 secondes
        }
    }

    /**
     * Gère l'échec du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Échec du job ProcessRemindersJob', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
```

### La commande SendRemindersCommand

```php
<?php

namespace Andydefer\LaravelReminder\Console\Commands;

use Andydefer\LaravelReminder\Jobs\ProcessRemindersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRemindersCommand extends Command
{
    /**
     * Signature de la commande
     *
     * @var string
     */
    protected $signature = 'reminders:send
                            {--sync : Traiter les rappels de manière synchrone sans job}
                            {--queue= : File d\'attente à utiliser}';

    /**
     * Description de la commande
     *
     * @var string
     */
    protected $description = 'Envoyer les rappels en attente';

    /**
     * Exécute la commande
     */
    public function handle(): int
    {
        $this->info('Démarrage du traitement des rappels...');

        if ($this->option('sync')) {
            return $this->processSynchronously();
        }

        return $this->dispatchJob();
    }

    /**
     * Traitement synchrone
     */
    protected function processSynchronously(): int
    {
        $this->info('Traitement synchrone des rappels...');

        try {
            $service = app(\Andydefer\LaravelReminder\Services\ReminderService::class);
            $result = $service->processPendingReminders();

            $this->table(
                ['Métrique', 'Nombre'],
                [
                    ['Total', $result['total']],
                    ['Traités', $result['processed']],
                    ['Échoués', $result['failed']],
                ]
            );

            $this->info('Rappels traités avec succès !');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erreur lors du traitement : ' . $e->getMessage());
            Log::error('Échec du traitement synchrone', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Dispatch du job dans la file d'attente
     */
    protected function dispatchJob(): int
    {
        $job = new ProcessRemindersJob();

        if ($queue = $this->option('queue')) {
            $job->onQueue($queue);
        }

        dispatch($job);

        $this->info('Job de traitement des rappels dispatché avec succès.');

        return Command::SUCCESS;
    }
}
```

### Le facade Reminder

```php
<?php

namespace Andydefer\LaravelReminder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array processPendingReminders()
 * @method static bool processReminder(\Andydefer\LaravelReminder\Models\Reminder $reminder)
 * @method static \Andydefer\LaravelReminder\Services\ReminderService setEventDispatcher(\Illuminate\Contracts\Events\Dispatcher $events)
 *
 * @see \Andydefer\LaravelReminder\Services\ReminderService
 */
class Reminder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Andydefer\LaravelReminder\Services\ReminderService::class;
    }
}
```

Utilisation du facade :

```php
<?php

use Andydefer\LaravelReminder\Facades\Reminder;

// Traiter tous les rappels
$results = Reminder::processPendingReminders();

// Traiter un rappel spécifique
$success = Reminder::processReminder($reminder);
```

### Les événements

```php
<?php

// Dans votre AppServiceProvider.php
namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Écouter tous les événements de rappel
        Event::listen('reminder.*', function ($eventName, $payload) {
            Log::info("Événement reminder: {$eventName}", $payload);
        });

        // Écouter un événement spécifique
        Event::listen('reminder.sent', function ($reminder) {
            Log::info("Rappel envoyé avec succès", [
                'reminder_id' => $reminder->id,
                'channels' => $reminder->channels(),
            ]);
        });

        Event::listen('reminder.failed', function ($reminder, $exception) {
            Log::error("Échec d'envoi de rappel", [
                'reminder_id' => $reminder->id,
                'channels' => $reminder->channels(),
                'attempts' => $reminder->attempts,
                'error' => $exception->getMessage(),
            ]);
        });

        Event::listen('reminder.outside_tolerance', function ($reminder) {
            Log::warning("Rappel hors fenêtre de tolérance", [
                'reminder_id' => $reminder->id,
                'scheduled_at' => $reminder->scheduled_at,
            ]);
        });

        Event::listen('reminder.processing', function ($reminder) {
            Log::debug("Traitement du rappel", [
                'reminder_id' => $reminder->id,
            ]);
        });

        Event::listen('reminder.processed', function ($results) {
            Log::info("Traitement terminé", $results);
        });
    }
}
```

### Les exceptions

```php
<?php

namespace Andydefer\LaravelReminder\Exceptions;

use InvalidArgumentException;

class InvalidNotificationException extends InvalidArgumentException
{
    /**
     * Crée une exception pour un type de retour invalide
     *
     * @param mixed $actual Valeur retournée
     * @return self
     */
    public static function create(mixed $actual): self
    {
        $type = is_object($actual) ? get_class($actual) : gettype($actual);

        return new self(
            sprintf(
                'toRemind() doit retourner une instance de Illuminate\Notifications\Notification. Type reçu : %s',
                $type
            )
        );
    }
}
```

## Tests

Le package est fourni avec une suite de tests complète.

### Exécuter les tests

```bash
composer test
# ou
./vendor/bin/phpunit
```

### Exemple de test pour les channels

```php
<?php

namespace Tests\Feature;

use Andydefer\LaravelReminder\Facades\Reminder;
use App\Models\Article;
use App\Notifications\ArticleReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ArticleRemindersTest extends TestCase
{
    public function test_article_can_have_reminders_with_custom_channels()
    {
        // Créer un article
        $article = Article::factory()->create();

        // Planifier un rappel avec channels personnalisés
        $reminder = $article->scheduleReminder(
            scheduledAt: now()->addDays(3),
            metadata: ['reason' => 'publication'],
            channels: ['mail', 'sms']
        );

        // Vérifications en base de données
        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'remindable_id' => $article->id,
            'status' => 'pending',
        ]);

        // Vérifier les channels
        $this->assertEquals(['mail', 'sms'], $reminder->channels());
        $this->assertTrue($reminder->has_custom_channels);
    }

    public function test_reminder_sends_notification_on_specified_channels()
    {
        // Fake les notifications
        Notification::fake();

        // Simuler une date
        Carbon::setTestNow('2025-03-20 10:00:00');

        $article = Article::factory()->create();

        // Rappel avec channels spécifiques
        $article->scheduleReminder(
            scheduledAt: Carbon::now()->subMinutes(5),
            channels: ['mail', 'database']
        );

        // Traiter les rappels
        Reminder::processPendingReminders();

        // Vérifier que la notification a été envoyée
        Notification::assertSentTo(
            $article,
            ArticleReminderNotification::class,
            function ($notification) {
                // Vérifier que les channels sont correctement utilisés
                return $notification->reminder->channels() === ['mail', 'database'];
            }
        );
    }

    public function test_reminder_uses_fallback_channels_when_no_custom_channels()
    {
        Notification::fake();

        $article = Article::factory()->create();

        // Rappel sans channels spécifiques
        $article->scheduleReminder(now()->subMinutes(5));

        Reminder::processPendingReminders();

        // Vérifier que la notification a utilisé le fallback
        Notification::assertSentTo(
            $article,
            ArticleReminderNotification::class,
            function ($notification) {
                // Doit utiliser le fallback (par défaut ['mail'])
                return $notification->reminder->channels() === [];
            }
        );
    }
}
```

## Bonnes pratiques

### 1. Toujours inclure le trait Notifiable

```php
<?php

use Illuminate\Notifications\Notifiable;

class Article extends Model implements ShouldRemind
{
    use Remindable, Notifiable; // Notifiable est OBLIGATOIRE pour recevoir des notifications
}
```

### 2. Utiliser channelsForSending() dans vos notifications

```php
<?php

public function via($notifiable): array
{
    // Toujours utiliser un fallback explicite
    return $this->reminder->channelsForSending(['mail']);

    // Ne jamais faire ça :
    // return $this->reminder->channels(); // ❌ Peut retourner []
}
```

### 3. Nommer les métadonnées de façon cohérente

```php
<?php

// 👍 À faire : noms explicites et structurés
$reminder = $order->scheduleReminder(
    scheduledAt: now()->addDays(3),
    metadata: [
        'notification_type' => 'email',
        'template' => 'order.reminder',
        'locale' => app()->getLocale(),
        'user_id' => auth()->id(),
        'order_id' => $order->id,
    ],
    channels: ['mail']
);

// 👎 À éviter : métadonnées cryptiques
$reminder = $order->scheduleReminder(
    now()->addDays(3),
    ['abc' => 123, 'xyz' => true], // Qu'est-ce que ça veut dire ?
    ['abc'] // Channels invalides
);
```

### 4. Structurer les notifications selon le contexte

```php
<?php

public function toRemind(Reminder $reminder): Notification
{
    // Retourner différentes notifications selon le contexte
    if ($this->priority === 'high') {
        return new UrgentReminderNotification($this, $reminder);
    }

    if ($this->type === 'subscription') {
        return new SubscriptionReminderNotification($this, $reminder);
    }

    return new StandardReminderNotification($this, $reminder);
}
```

### 5. Valider les channels avant utilisation

```php
<?php

$channels = ['mail', 'sms', 'whatsapp'];

// Vérifier que les channels existent dans l'application
$availableChannels = ['mail', 'database', 'slack'];
$validChannels = array_intersect($channels, $availableChannels);

if (empty($validChannels)) {
    // Fallback sur mail si aucun channel valide
    $validChannels = ['mail'];
}

$article->scheduleReminder(
    scheduledAt: now()->addDays(7),
    channels: $validChannels
);
```

### 6. Gérer les erreurs gracieusement

```php
<?php

class Article implements ShouldRemind
{
    public function toRemind(Reminder $reminder): Notification
    {
        try {
            // Logique métier potentiellement instable
            $template = $this->getNotificationTemplate();
            return new DynamicReminderNotification($this, $reminder, $template);

        } catch (\Exception $e) {
            // Fallback en cas d'erreur
            Log::error('Erreur lors de la création de la notification', [
                'article' => $this->id,
                'reminder' => $reminder->id,
                'error' => $e->getMessage(),
            ]);

            // Retourner une notification de secours
            return new FallbackReminderNotification($this, $reminder);
        }
    }
}
```

### 7. Nettoyer les anciens rappels

```php
<?php
// config/reminder.php

'cleanup' => [
    'enabled' => true, // Activer le nettoyage automatique
    'after_days' => 30, // Supprimer les rappels de plus de 30 jours
],
```

### 8. Utiliser la file d'attente en production

```env
REMINDER_QUEUE_ENABLED=true
REMINDER_QUEUE_CONNECTION=database
REMINDER_QUEUE_NAME=reminders
```

```bash
# Lancer un worker dédié
php artisan queue:work --queue=reminders
```

## Cas d'usage avancés

### Rappels récurrents

```php
<?php

trait RecurringReminders
{
    /**
     * Planifie des rappels récurrents
     *
     * @param array $schedule Tableau associatif [intervalle => canaux]
     * @param array $metadata Métadonnées communes
     * @return array
     */
    public function scheduleRecurringReminders(array $schedule, array $metadata = []): array
    {
        $reminders = [];

        foreach ($schedule as $interval => $channels) {
            $nextDate = $this->calculateNextDate($interval);

            $reminders[] = $this->scheduleReminder(
                scheduledAt: $nextDate,
                metadata: array_merge($metadata, ['pattern' => $interval]),
                channels: $channels
            );
        }

        return $reminders;
    }

    /**
     * Calcule la prochaine date selon l'intervalle
     */
    private function calculateNextDate(string $interval): Carbon
    {
        return match ($interval) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'biweekly' => now()->addWeeks(2),
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            default => now()->addDay(),
        };
    }
}

// Utilisation
class Subscription extends Model implements ShouldRemind
{
    use Remindable, Notifiable, RecurringReminders;

    public function activate()
    {
        $this->scheduleRecurringReminders(
            schedule: [
                'daily' => ['mail'],           // Rappel quotidien par email
                'weekly' => ['mail', 'sms'],    // Rappel hebdomadaire email + SMS
                'monthly' => ['sms'],           // Rappel mensuel SMS uniquement
            ],
            metadata: ['subscription_id' => $this->id, 'type' => 'renewal']
        );
    }

    public function toRemind(Reminder $reminder): Notification
    {
        return new SubscriptionReminderNotification($this, $reminder);
    }

    public function getTolerance(): Tolerance
    {
        return new Tolerance(24, ToleranceUnit::HOUR);
    }
}
```

### Rappels avec conditions

```php
<?php

class Task extends Model implements ShouldRemind
{
    use Remindable, Notifiable;

    public function toRemind(Reminder $reminder): Notification
    {
        // Adapter la notification selon le contexte
        if ($this->priority === 'high') {
            return new UrgentTaskNotification($this, $reminder);
        }

        if ($this->assigned_to === auth()->id()) {
            return new AssignedTaskNotification($this, $reminder);
        }

        return new TaskReminderNotification($this, $reminder);
    }

    public function getTolerance(): Tolerance
    {
        // Tolérance variable selon la priorité
        return match ($this->priority) {
            'high' => new Tolerance(1, ToleranceUnit::HOUR),
            'medium' => new Tolerance(6, ToleranceUnit::HOUR),
            default => new Tolerance(24, ToleranceUnit::HOUR),
        };
    }

    public function scheduleTaskReminders(): void
    {
        // J-7 : email
        $this->scheduleReminder(
            scheduledAt: $this->due_date->subDays(7),
            channels: ['mail']
        );

        // J-1 : email + SMS
        $this->scheduleReminder(
            scheduledAt: $this->due_date->subDay(),
            channels: ['mail', 'sms']
        );

        // J-0 (urgent) : tous les canaux
        if ($this->priority === 'high') {
            $this->scheduleReminder(
                scheduledAt: $this->due_date,
                channels: ['mail', 'sms', 'database', 'slack']
            );
        }
    }
}
```

### Combinaison avec les préférences utilisateur

```php
<?php

class User extends Authenticatable implements ShouldRemind
{
    use Notifiable, Remindable;

    /**
     * Préférences de notification de l'utilisateur
     * Stockées en JSON dans la base
     */
    protected $casts = [
        'notification_preferences' => 'array',
    ];

    public function toRemind(Reminder $reminder): Notification
    {
        return new UserReminderNotification($this, $reminder);
    }

    public function getTolerance(): Tolerance
    {
        return new Tolerance(24, ToleranceUnit::HOUR);
    }

    /**
     * Planifie un rappel en respectant les préférences
     */
    public function schedulePersonalizedReminder(Carbon $date, array $metadata = []): Reminder
    {
        $channels = $this->notification_preferences['reminders'] ?? ['mail'];

        return $this->scheduleReminder(
            scheduledAt: $date,
            metadata: $metadata,
            channels: $channels
        );
    }
}

// Dans la notification
class UserReminderNotification extends Notification
{
    public function via($notifiable): array
    {
        // Priorité :
        // 1. Canaux du reminder (définis à la planification)
        // 2. Sinon, préférences de l'utilisateur
        // 3. Sinon, fallback ['mail']

        $channels = $this->reminder->channelsForSending([]);

        if (empty($channels)) {
            $channels = $notifiable->notification_preferences['reminders'] ?? ['mail'];
        }

        return $channels;
    }
}
```

### Statistiques et reporting

```php
<?php

namespace App\Console\Commands;

use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Enums\ReminderStatus;
use Illuminate\Console\Command;

class ReminderStatsCommand extends Command
{
    protected $signature = 'reminder:stats';

    public function handle()
    {
        $this->info('Statistiques des rappels');

        // Statistiques globales
        $stats = [
            'total' => Reminder::count(),
            'pending' => Reminder::pending()->count(),
            'sent' => Reminder::where('status', ReminderStatus::SENT)->count(),
            'failed' => Reminder::where('status', ReminderStatus::FAILED)->count(),
            'cancelled' => Reminder::where('status', ReminderStatus::CANCELLED)->count(),
        ];

        $this->table(
            ['Statut', 'Nombre'],
            collect($stats)->map(fn($count, $status) => [$status, $count])->values()->toArray()
        );

        // Rappels avec canaux personnalisés
        $customChannels = Reminder::whereNotNull('channels')
            ->where('channels', '!=', json_encode([]))
            ->count();

        $this->info("Rappels avec canaux personnalisés : $customChannels");

        // Taux de succès
        if ($stats['total'] > 0) {
            $successRate = round(($stats['sent'] / $stats['total']) * 100, 2);
            $this->info("Taux de succès : $successRate%");
        }
    }
}
```

## Dépannage

### Problème : Les rappels ne s'envoient pas

```bash
# 1. Vérifier que le scheduler est configuré
crontab -l | grep "schedule:run"

# 2. Vérifier les rappels en attente
php artisan tinker
>>> Reminder::pending()->count()

# 3. Voir les erreurs
>>> Reminder::whereNotNull('error_message')->get()

# 4. Tester manuellement
php artisan reminders:send --sync
```

### Problème : Les channels personnalisés ne sont pas utilisés

```php
<?php

// Vérifier votre méthode via()
public function via($notifiable): array
{
    // ✅ Bon : utilise channelsForSending()
    return $this->reminder->channelsForSending(['mail']);

    // ❌ Mauvais : ignore les channels personnalisés
    // return ['mail', 'database'];

    // ❌ Mauvais : risque de retourner []
    // return $this->reminder->channels();
}

// Vérifier que le reminder a bien des canaux
$reminder = Reminder::find(1);
dd($reminder->channels()); // Doit retourner un tableau
dd($reminder->has_custom_channels); // Doit être true si des canaux sont définis
```

### Problème : "toRemind() must return an instance of Notification"

```php
<?php

// ✅ Correct : retourne une instance de Notification
public function toRemind(Reminder $reminder): Notification
{
    return new MyNotification($this, $reminder);
}

// ❌ Incorrect : retourne un tableau
public function toRemind(Reminder $reminder): array
{
    return ['title' => 'test'];
}

// ❌ Incorrect : retourne une string
public function toRemind(Reminder $reminder): string
{
    return 'notification';
}
```

### Problème : Trop de tentatives échouées

```php
<?php
// config/reminder.php

// Augmenter le nombre de tentatives
'max_attempts' => 5,

// Ou désactiver la limite (non recommandé)
// 'max_attempts' => 999,
```

### Problème : "Call to undefined method channelsForSending()"

```bash
# Vérifier que la migration est à jour
php artisan tinker
>>> Schema::hasColumn('reminders', 'channels');

# Si false, publier et migrer
php artisan vendor:publish --provider="Andydefer\LaravelReminder\ReminderServiceProvider" --tag="reminder-migrations" --force
php artisan migrate
```

### Problème : Les dates ne sont pas reconnues

```php
<?php

try {
    $reminder = $article->scheduleReminder('2025-13-45'); // Date invalide
} catch (InvalidArgumentException $e) {
    echo "Format de date invalide : " . $e->getMessage();
}

// Formats supportés :
$reminder = $article->scheduleReminder('2025-12-25'); // Y-m-d
$reminder = $article->scheduleReminder('2025-12-25 09:00:00'); // Y-m-d H:i:s
$reminder = $article->scheduleReminder('tomorrow'); // Mots-clés Carbon
$reminder = $article->scheduleReminder('+7 days'); // Expressions Carbon
```

## Contribuer

Les contributions sont les bienvenues !

### Comment contribuer

1. Forkez le projet
2. Créez une branche (`git checkout -b feature/amazing-feature`)
3. Committez vos changements (`git commit -m 'Add amazing feature'`)
4. Pushez vers la branche (`git push origin feature/amazing-feature`)
5. Ouvrez une Pull Request

### Guide de style

- Suivez les conventions PSR-12
- Ajoutez des tests pour vos nouvelles fonctionnalités
- Mettez à jour la documentation si nécessaire
- Commentez votre code en français ou en anglais

### Tests

```bash
# Exécuter les tests
composer test

# Exécuter un test spécifique
./vendor/bin/phpunit --filter test_name

# Avec couverture de code
composer test-coverage
```

## Licence

Ce package est open-sourcé sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus d'informations.

---

Créé avec ❤️ par [Andy Kani](https://github.com/andydefer)