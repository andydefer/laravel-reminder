Tu as raison, je m'excuse ! J'ai été trop zélé et j'ai supprimé des sections importantes. Voici le README complet avec TOUTES les sections conservées et la nouvelle fonctionnalité de channels intégrée naturellement :

---

# Laravel Reminder

Une solution flexible et robuste pour gérer les rappels dans vos applications Laravel.

## Introduction

Laravel Reminder est un package qui vous permet d'ajouter facilement un système de rappels à vos modèles Eloquent. Que ce soit pour envoyer des notifications d'échéance, des rappels de rendez-vous, ou toute autre alerte temporelle, ce package vous offre une architecture propre et extensible, intégrée nativement avec le système de notification de Laravel.

```php
// Exemple simple : créer un rappel pour un rendez-vous
$appointment = Appointment::find(1);

$reminder = $appointment->scheduleReminder(
    scheduledAt: $appointment->date->subHours(24),
    metadata: ['type' => 'email', 'priority' => 'high'],
    channels: ['mail', 'sms'] // Channels personnalisés pour ce rappel
);
```

## Concept fondamental

Le package repose sur un principe simple mais puissant : **tout modèle qui doit recevoir des rappels est "rappelable"** (remindable). Ces rappels sont automatiquement traités selon une fenêtre de tolérance que vous définissez.

### Comment ça marche ?

1. **Planification** : Vous créez des rappels pour vos modèles à des dates spécifiques, avec la possibilité de définir des canaux de notification personnalisés
2. **Traitement** : Un job planifié vérifie régulièrement les rappels à envoyer
3. **Fenêtre de tolérance** : Chaque modèle définit sa propre fenêtre d'acceptation
4. **Notification** : Le modèle retourne une notification Laravel à envoyer
5. **Envoi automatique** : Le système utilise `notify()` pour envoyer la notification via les canaux définis
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
return [
    /*
    |--------------------------------------------------------------------------
    | Default Tolerance
    |--------------------------------------------------------------------------
    |
    | This value defines the default tolerance window for all remindable models.
    | Each model can override this value by implementing the getTolerance() method.
    |
    */
    'default_tolerance' => [
        'value' => 30,
        'unit' => ToleranceUnit::MINUTE, // MINUTE, HOUR, DAY, WEEK, MONTH, YEAR
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Attempts
    |--------------------------------------------------------------------------
    |
    | This value determines how many times the system will attempt to send a
    | reminder before marking it as failed.
    |
    */
    'max_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how reminders are processed through Laravel's queue system.
    | Set enabled to false to process reminders synchronously.
    |
    */
    'queue' => [
        'enabled' => env('REMINDER_QUEUE_ENABLED', true),
        'name' => env('REMINDER_QUEUE_NAME', 'default'),
        'connection' => env('REMINDER_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule Frequency
    |--------------------------------------------------------------------------
    |
    | This value determines how often the scheduler checks for due reminders.
    | Value is in seconds. Common values: 15, 30, 60.
    |
    */
    'schedule_frequency' => 15,

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Automatically clean up old reminders to keep your database clean.
    |
    */
    'cleanup' => [
        'enabled' => env('REMINDER_CLEANUP_ENABLED', false),
        'after_days' => env('REMINDER_CLEANUP_AFTER_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for reminder processing.
    |
    */
    'logging' => [
        'enabled' => env('REMINDER_LOGGING_ENABLED', true),
        'channel' => env('REMINDER_LOG_CHANNEL', 'stack'),
    ],
];
```

## Utilisation

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

    // Votre modèle existant...
}
```

### 2. Implémenter le contrat ShouldRemind

Pour qu'un modèle puisse recevoir des rappels, il doit implémenter l'interface `ShouldRemind` :

```php
<?php

namespace App\Models;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Enums\ToleranceUnit;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Traits\Remindable;
use Andydefer\LaravelReminder\ValueObjects\Tolerance;
use App\Notifications\ArticleReminderNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class Article extends Model implements ShouldRemind
{
    use Remindable, Notifiable; // Notifiable est requis pour recevoir des notifications

    /**
     * Retourne la notification à envoyer pour ce rappel
     */
    public function toRemind(Reminder $reminder): Notification
    {
        $metadata = $reminder->metadata ?? [];

        return new ArticleReminderNotification($this, $reminder, $metadata);
    }

    /**
     * Définit la fenêtre de tolérance pour ce modèle
     */
    public function getTolerance(): Tolerance
    {
        // Exemple : tolérance de 2 heures
        return new Tolerance(
            value: 2,
            unit: ToleranceUnit::HOUR
        );

        // Autres possibilités :
        // return new Tolerance(30, ToleranceUnit::MINUTE);
        // return new Tolerance(1, ToleranceUnit::DAY);
        // return new Tolerance(1, ToleranceUnit::WEEK);
    }

    // Le reste de votre modèle...
}
```

### 3. Créer une notification Laravel avec channels dynamiques

Le package stocke les canaux de notification directement dans le reminder. Utilisez la méthode `channelsForSending()` dans votre notification pour les récupérer :

```php
<?php

namespace App\Notifications;

use App\Models\Article;
use Andydefer\LaravelReminder\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ArticleReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Article $article,
        protected Reminder $reminder,
        protected array $metadata = []
    ) {}

    /**
     * Définit les canaux de notification pour ce rappel
     */
    public function via($notifiable): array
    {
        // Utilise les canaux personnalisés du reminder, sinon ['mail', 'database'] par défaut
        return $this->reminder->channelsForSending(['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Reminder: {$this->article->title}")
            ->line("Don't forget to publish your article!")
            ->line("Scheduled for: {$this->reminder->scheduled_at->format('Y-m-d H:i')}")
            ->action('View Article', url("/articles/{$this->article->id}"));
    }

    public function toArray($notifiable): array
    {
        return [
            'article_id' => $this->article->id,
            'article_title' => $this->article->title,
            'reminder_id' => $this->reminder->id,
            'scheduled_at' => $this->reminder->scheduled_at->toDateTimeString(),
            'metadata' => $this->metadata,
        ];
    }
}
```

### 4. Planifier des rappels

Une fois votre modèle configuré, vous pouvez planifier des rappels de plusieurs façons :

```php
// Récupérer un modèle
$article = Article::find(1);

// 1. Planifier un rappel simple
$reminder = $article->scheduleReminder(
    scheduledAt: now()->addDays(7), // Date d'envoi
    metadata: ['type' => 'email'], // Données supplémentaires
    channels: ['mail', 'database'] // Channels personnalisés
);

// 2. Planifier plusieurs rappels à la fois
$reminders = $article->scheduleMultipleReminders(
    scheduledTimes: [
        now()->addDays(7),
        now()->addDays(3),
        now()->addDay(),
    ],
    metadata: ['priority' => 'high'],
    channels: ['sms'] // Tous ces rappels utiliseront SMS
);

// 3. Planifier avec une date en string
$reminder = $article->scheduleReminder(
    scheduledAt: '2025-12-25 09:00:00',
    channels: ['mail']
);

// 4. Planifier avec des channels différents par rappel
$reminders = $article->scheduleMultipleReminders(
    scheduledTimes: [
        now()->addDays(7) => ['mail'],
        now()->addDays(3) => ['mail', 'sms'],
        now()->addDay() => ['sms'],
    ],
    metadata: ['priority' => 'high']
);
```

### 5. Gérer les rappels existants

Le trait `Remindable` met à disposition plusieurs méthodes pour gérer vos rappels :

```php
// Récupérer tous les rappels d'un modèle
$allReminders = $article->reminders;

// Récupérer uniquement les rappels en attente
$pendingReminders = $article->pendingReminders();

// Vérifier les canaux d'un reminder spécifique
foreach ($pendingReminders as $reminder) {
    $channels = $reminder->channels(); // ['mail', 'sms'] ou null
    $hasCustomChannels = $reminder->has_custom_channels; // true ou false
}

// Vérifier s'il y a des rappels en attente
if ($article->hasPendingReminders()) {
    // Faire quelque chose...
}

// Obtenir le prochain rappel à venir
$nextReminder = $article->nextReminder();

// Annuler tous les rappels en attente
$cancelledCount = $article->cancelReminders();
```

## Commandes et Scheduler

Le package fournit plusieurs façons de traiter vos rappels, que ce soit via des commandes artisan ou le scheduler Laravel.

### Commande Artisan

Une commande dédiée vous permet de traiter les rappels manuellement :

```bash
# Traiter les rappels de manière synchrone (sans file d'attente)
php artisan reminders:send --sync

# Traiter les rappels en dispatchant un job (par défaut)
php artisan reminders:send

# Spécifier une file d'attente particulière
php artisan reminders:send --queue=emails
```

Exemple de sortie de la commande :
```
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

### Scheduler Automatique

Le package enregistre automatiquement un job planifié dans le scheduler Laravel. Pour l'activer, ajoutez simplement ceci dans votre `crontab` :

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Le scheduler exécutera alors automatiquement le job de traitement des rappels à la fréquence définie dans votre configuration (par défaut : toutes les 15 secondes).

### Configuration du Scheduler

La fréquence d'exécution est configurable dans `config/reminder.php` :

```php
// Fréquence en secondes (15, 30, 60, ou valeur personnalisée)
'schedule_frequency' => 15,
```

Le scheduler s'adapte automatiquement à la valeur configurée :
- `15` → exécution toutes les 15 secondes
- `30` → exécution toutes les 30 secondes
- `60` → exécution toutes les minutes
- Autre valeur → exécution selon un cron personnalisé

### Vérification du Bon Fonctionnement

Pour vérifier que tout fonctionne correctement :

```bash
# 1. Vérifier que le scheduler est configuré dans votre crontab
crontab -l | grep "schedule:run"

# 2. Voir les rappels en attente
php artisan tinker
>>> Reminder::pending()->count()

# 3. Tester manuellement le traitement
php artisan reminders:send --sync
```

## Architecture détaillée

### Le modèle Reminder

Le cœur du package est le modèle `Reminder` qui stocke toutes les informations nécessaires, y compris les canaux de notification :

```php
// Création manuelle (si nécessaire)
$reminder = new Reminder([
    'remindable_type' => Article::class,
    'remindable_id' => $article->id,
    'scheduled_at' => now()->addDays(3),
    'metadata' => ['type' => 'whatsapp'],
    'channels' => ['mail', 'whatsapp'], // Canaux personnalisés
    'status' => ReminderStatus::PENDING,
    'attempts' => 0,
]);
$reminder->save();

// Méthodes utilitaires pour les channels
$channels = $reminder->channels(); // Retourne les channels bruts ou null
$channels = $reminder->channelsForSending(['mail']); // Channels ou fallback

if ($reminder->has_custom_channels) {
    // Des channels spécifiques ont été définis
}

// Mise à jour du statut
$reminder->markAsSent();               // ✅ Marquer comme envoyé
$reminder->markAsFailed('Timeout');     // ❌ Marquer comme échoué
$reminder->cancel();                    // 🚫 Annuler le rappel

// Vérifications
if ($reminder->isPending()) {
    // Encore en attente
}

if ($reminder->wasSent()) {
    // Déjà envoyé
}

// Requêtes courantes
$dueReminders = Reminder::due()->get();
$recentReminders = Reminder::withinTolerance(30)->get();
```

### Les statuts possibles

Le package utilise une énumération `ReminderStatus` pour suivre l'état des rappels :

- `PENDING` : En attente d'envoi
- `SENT` : Envoyé avec succès
- `FAILED` : Échec après plusieurs tentatives
- `CANCELLED` : Annulé manuellement

```php
use Andydefer\LaravelReminder\Enums\ReminderStatus;

// Utilisation dans vos requêtes
$pendingReminders = Reminder::where('status', ReminderStatus::PENDING)->get();

// Vérifier si un statut est terminal (ne changera plus)
if ($reminder->status->isTerminal()) {
    // SENT, FAILED ou CANCELLED
}
```

### Les unités de tolérance

La fenêtre de tolérance peut être définie avec différentes unités via l'énumération `ToleranceUnit` :

```php
use Andydefer\LaravelReminder\Enums\ToleranceUnit;

ToleranceUnit::MINUTE->toMinutes();    // 1
ToleranceUnit::HOUR->toMinutes();      // 60
ToleranceUnit::DAY->toMinutes();       // 1440
ToleranceUnit::WEEK->toMinutes();      // 10080
ToleranceUnit::MONTH->toMinutes();     // 43800 (30 jours)
ToleranceUnit::YEAR->toMinutes();      // 525600 (365 jours)

// Conversion en secondes
$seconds = ToleranceUnit::HOUR->toSeconds(); // 3600
```

### Le Value Object Tolerance

L'objet `Tolerance` encapsule la logique de la fenêtre de tolérance :

```php
use Andydefer\LaravelReminder\ValueObjects\Tolerance;
use Andydefer\LaravelReminder\Enums\ToleranceUnit;

// Créer une tolérance de 2 heures
$tolerance = new Tolerance(2, ToleranceUnit::HOUR);

// Vérifier si une date est dans la fenêtre
$scheduledAt = now()->subHours(1); // Rappel prévu il y a 1h
$isWithinWindow = $tolerance->isWithinWindow(
    scheduledAt: $scheduledAt,
    now: now() // 2025-03-20 10:00:00
); // true (car 1h < 2h)

// Obtenir la représentation en minutes/secondes
$minutes = $tolerance->toMinutes(); // 120
$seconds = $tolerance->toSeconds(); // 7200

// Affichage
echo (string) $tolerance; // "2 Hours"
```

### Le système de channels

Le package offre une gestion flexible des canaux de notification :

```php
// Dans le modèle Reminder
public function channels(): array|null
{
    return $this->channels; // via le cast ChannelsCast
}

public function channelsForSending(array $fallbackChannels = ['mail']): array
{
    return $this->has_custom_channels
        ? $this->channels
        : $fallbackChannels;
}

// Attribut dynamique pour vérifier la présence de channels personnalisés
public function getHasCustomChannelsAttribute(): bool
{
    $channels = $this->channels;
    return !empty($channels);
}
```

## Intégration native avec le système de notifications Laravel

Le package est conçu pour s'intégrer parfaitement avec le système de notifications de Laravel. Voici un exemple complet :

```php
<?php

namespace App\Models;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Enums\ToleranceUnit;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Traits\Remindable;
use Andydefer\LaravelReminder\ValueObjects\Tolerance;
use App\Notifications\SubscriptionReminderNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class User extends Authenticatable implements ShouldRemind
{
    use Notifiable, Remindable;

    public function toRemind(Reminder $reminder): Notification
    {
        // Retournez directement une notification Laravel
        return new SubscriptionReminderNotification($this, $reminder);
    }

    public function getTolerance(): Tolerance
    {
        return new Tolerance(24, ToleranceUnit::HOUR);
    }
}

// La notification sera automatiquement envoyée par le package
// via $user->notify($notification) sur les canaux définis
```

## Événements

Le package dispatch des événements à chaque étape importante, vous permettant d'ajouter votre propre logique :

```php
// Dans votre AppServiceProvider.php
public function boot(): void
{
    // Écouter tous les événements de rappel
    Event::listen('reminder.*', function ($eventName, $payload) {
        Log::info("Reminder event: {$eventName}", $payload);
    });

    // Écouter un événement spécifique
    Event::listen('reminder.sent', function ($reminder) {
        Log::info("Reminder sent successfully via channels: " . implode(', ', $reminder->channels() ?? ['default']));
    });

    Event::listen('reminder.failed', function ($reminder, $exception) {
        Log::error("Failed to send reminder", [
            'reminder_id' => $reminder->id,
            'channels' => $reminder->channels(),
            'error' => $exception->getMessage(),
        ]);
    });
}
```

Événements disponibles :

| Événement | Payload | Description |
|-----------|---------|-------------|
| `reminder.processing` | `$reminder` | Début du traitement d'un rappel |
| `reminder.sent` | `$reminder` | Rappel envoyé avec succès |
| `reminder.failed` | `[$reminder, $exception]` | Échec d'envoi |
| `reminder.outside_tolerance` | `$reminder` | Rappel hors fenêtre de tolérance |
| `reminder.processed` | `$results` | Fin du traitement global |

## Tests

Le package est fourni avec une suite de tests complète. Voici comment les exécuter :

```bash
composer test
# ou
./vendor/bin/phpunit
```

### Exemple de test personnalisé avec channels

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

        // Vérifications
        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'remindable_id' => $article->id,
            'status' => 'pending',
        ]);

        $this->assertEquals(['mail', 'sms'], $reminder->channels());
        $this->assertTrue($reminder->has_custom_channels);
    }

    public function test_reminder_sends_notification_on_specified_channels()
    {
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
}
```

## Bonnes pratiques

### 1. Utilisez le trait Notifiable

```php
use Illuminate\Notifications\Notifiable;

class Article extends Model implements ShouldRemind
{
    use Remindable, Notifiable; // Toujours inclure Notifiable
}
```

### 2. Utilisez channelsForSending() dans vos notifications

```php
public function via($notifiable): array
{
    // Toujours utiliser un fallback explicite
    return $this->reminder->channelsForSending(['mail']);
}
```

### 3. Structurez vos notifications

```php
public function toRemind(Reminder $reminder): Notification
{
    // Vous pouvez retourner différentes notifications selon le contexte
    if ($this->priority === 'high') {
        return new UrgentReminderNotification($this, $reminder);
    }

    return new StandardReminderNotification($this, $reminder);
}
```

### 4. Nommez vos métadonnées de façon cohérente

```php
// 👍 À faire
$reminder = $order->scheduleReminder(
    scheduledAt: now()->addDays(3),
    metadata: [
        'notification_type' => 'email',
        'template' => 'order.reminder',
        'locale' => app()->getLocale(),
        'user_id' => auth()->id(),
    ],
    channels: ['mail']
);

// 👎 À éviter
$reminder = $order->scheduleReminder(
    now()->addDays(3),
    ['abc' => 123, 'xyz' => true], // Métadonnées non explicites
    ['abc'] // Channels invalides
);
```

### 5. Utilisez la file d'attente en production

```env
# .env
REMINDER_QUEUE_ENABLED=true
REMINDER_QUEUE_CONNECTION=database
REMINDER_QUEUE_NAME=default
```

```bash
# Lancez un worker (adaptez selon votre configuration)
php artisan queue:work
```

### 6. Nettoyez les anciens rappels

Activez le nettoyage automatique dans votre configuration :

```php
// config/reminder.php
'cleanup' => [
    'enabled' => true,
    'after_days' => 30, // Supprime les rappels de plus de 30 jours
],
```

### 7. Gérez les erreurs gracieusement

```php
class Article implements ShouldRemind
{
    public function toRemind(Reminder $reminder): Notification
    {
        try {
            // Logique métier potentiellement instable
            return new DynamicReminderNotification($this, $reminder);
        } catch (\Exception $e) {
            // Fallback en cas d'erreur
            Log::error('Error creating reminder notification', [
                'article' => $this->id,
                'channels' => $reminder->channels(),
                'error' => $e->getMessage(),
            ]);

            return new FallbackReminderNotification($this, $reminder);
        }
    }
}
```

### 8. Validez les channels au moment de la planification

```php
$article->scheduleReminder(
    scheduledAt: now()->addDay(),
    channels: ['mail', 'sms'] // Assurez-vous que ces channels existent dans votre application
);
```

### 9. Combinez avec les préférences utilisateur

```php
public function via($notifiable): array
{
    $channels = $this->reminder->channelsForSending([]);

    if (empty($channels)) {
        // Fallback sur les préférences de l'utilisateur
        return $notifiable->notification_preferences ?? ['mail'];
    }

    return $channels;
}
```

## Cas d'usage avancés

### Rappels récurrents avec channels

```php
trait RecurringReminders
{
    public function scheduleRecurringReminders(array $schedule, array $metadata = [], array $defaultChannels = ['mail']): array
    {
        $reminders = [];

        foreach ($schedule as $interval => $channels) {
            $nextDate = $this->calculateNextDate(is_string($interval) ? $interval : 'daily');

            $reminders[] = $this->scheduleReminder(
                scheduledAt: $nextDate,
                metadata: array_merge($metadata, ['pattern' => $interval]),
                channels: is_array($channels) ? $channels : $defaultChannels
            );
        }

        return $reminders;
    }

    private function calculateNextDate(string $interval): Carbon
    {
        return match($interval) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
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
                'weekly' => ['mail', 'sms'],    // Rappel hebdomadaire par email + SMS
                'monthly' => ['sms'],           // Rappel mensuel par SMS uniquement
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

### Rappels avec conditions et channels adaptatifs

```php
class Task extends Model implements ShouldRemind
{
    use Remindable, Notifiable;

    public function toRemind(Reminder $reminder): Notification
    {
        // Adapter la notification selon le contexte
        if ($this->priority === 'high') {
            return new UrgentTaskNotification($this, $reminder);
        }

        return new TaskReminderNotification($this, $reminder);
    }

    public function getTolerance(): Tolerance
    {
        // Plus de flexibilité pour les tâches urgentes
        if ($this->priority === 'high') {
            return new Tolerance(1, ToleranceUnit::HOUR);
        }

        return new Tolerance(24, ToleranceUnit::HOUR);
    }

    public function scheduleTaskReminders(): void
    {
        // Rappel J-7 : email uniquement
        $this->scheduleReminder(
            scheduledAt: $this->due_date->subDays(7),
            channels: ['mail']
        );

        // Rappel J-1 : email + SMS
        $this->scheduleReminder(
            scheduledAt: $this->due_date->subDay(),
            channels: ['mail', 'sms']
        );

        // Rappel J-0 (urgent) : tous les canaux
        if ($this->priority === 'high') {
            $this->scheduleReminder(
                scheduledAt: $this->due_date,
                channels: ['mail', 'sms', 'database', 'slack']
            );
        }
    }
}
```

## Dépannage

### Problème : Les rappels ne s'envoient pas

Vérifiez les points suivants :

```bash
# 1. Le scheduler est-il configuré dans votre crontab ?
crontab -l | grep "schedule:run"

# 2. Les rappels sont-ils bien en attente ?
php artisan tinker
>>> Reminder::pending()->count()

# 3. Vérifiez les erreurs dans les rappels
>>> Reminder::whereNotNull('error_message')->get()

# 4. Testez manuellement le traitement
php artisan reminders:send --sync
```

### Problème : Les channels personnalisés ne sont pas utilisés

Vérifiez que vous utilisez bien `channelsForSending()` dans votre notification :

```php
// ✅ Correct
public function via($notifiable): array
{
    return $this->reminder->channelsForSending(['mail']);
}

// ❌ Incorrect (ignore les channels personnalisés)
public function via($notifiable): array
{
    return ['mail', 'database'];
}
```

### Problème : "toRemind() must return an instance of Notification"

Assurez-vous que votre méthode `toRemind()` retourne bien une instance de `Illuminate\Notifications\Notification` :

```php
// ✅ Correct
public function toRemind(Reminder $reminder): Notification
{
    return new MyNotification($this, $reminder);
}

// ❌ Incorrect (retourne un tableau)
public function toRemind(Reminder $reminder): array
{
    return ['title' => 'test'];
}
```

### Problème : Trop de tentatives échouées

Ajustez la configuration :

```php
// config/reminder.php
'max_attempts' => 5, // Augmenter le nombre de tentatives
```

### Problème : "Call to undefined method channelsForSending()"

Assurez-vous que votre migration est à jour et que le champ `channels` existe dans votre table `reminders` :

```bash
# Vérifier si le champ channels existe
php artisan tinker
>>> Schema::hasColumn('reminders', 'channels');

# Si non, publier et exécuter les migrations à jour
php artisan vendor:publish --provider="Andydefer\LaravelReminder\ReminderServiceProvider" --tag="reminder-migrations" --force
php artisan migrate
```

## Contribuer

Les contributions sont les bienvenues ! Voici comment procéder :

1. Forkez le projet
2. Créez une branche (`git checkout -b feature/amazing-feature`)
3. Committez vos changements (`git commit -m 'Add amazing feature'`)
4. Pushez vers la branche (`git push origin feature/amazing-feature`)
5. Ouvrez une Pull Request

### Guide de style

- Suivez les conventions PSR-12
- Ajoutez des tests pour vos nouvelles fonctionnalités
- Mettez à jour la documentation si nécessaire

## Licence

Ce package est open-sourcé sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus d'informations.

---

Créé avec ❤️ par [Andy Kani](https://github.com/andydefer)