# Laravel Reminder

Une solution flexible et robuste pour gérer les rappels dans vos applications Laravel.

## Introduction

Laravel Reminder est un package qui vous permet d'ajouter facilement un système de rappels à vos modèles Eloquent. Que ce soit pour envoyer des notifications d'échéance, des rappels de rendez-vous, ou toute autre alerte temporelle, ce package vous offre une architecture propre et extensible.

```php
// Exemple simple : créer un rappel pour un rendez-vous
$rendezVous = RendezVous::find(1);

$rappel = $rendezVous->scheduleReminder(
    scheduledAt: $rendezVous->date->subHours(24),
    metadata: ['type' => 'email', 'priority' => 'haute']
);
```

## Concept fondamental

Le package repose sur un principe simple mais puissant : **tout modèle qui doit recevoir des rappels est "rappelable"** (remindable). Ces rappels sont automatiquement traités selon une fenêtre de tolérance que vous définissez.

### Comment ça marche ?

1. **Planification** : Vous créez des rappels pour vos modèles à des dates spécifiques
2. **Traitement** : Un job planifié vérifie régulièrement les rappels à envoyer
3. **Fenêtre de tolérance** : Chaque modèle définit sa propre fenêtre d'acceptation
4. **Notification** : Le modèle génère les données de notification adaptées
5. **Suivi** : Le système garde une trace de chaque tentative (succès/échec)

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
    // Tolérance par défaut pour tous les modèles
    'default_tolerance' => [
        'value' => 30,
        'unit' => ToleranceUnit::MINUTE, // MINUTE, HOUR, DAY, WEEK, MONTH, YEAR
    ],

    // Tentatives maximales avant de marquer comme échoué
    'max_attempts' => 3,

    // Configuration de la file d'attente
    'queue' => [
        'enabled' => env('REMINDER_QUEUE_ENABLED', true),
        'name' => env('REMINDER_QUEUE_NAME', 'default'),
        'connection' => env('REMINDER_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    ],

    // Fréquence de vérification (en secondes)
    'schedule_frequency' => 15,
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
use Illuminate\Database\Eloquent\Model;

class Article extends Model implements ShouldRemind // Important : implémenter l'interface
{
    use Remindable;

    /**
     * Définit les données de notification pour le rappel
     */
    public function toRemind(Reminder $reminder): array
    {
        // Vous avez accès au rappel et à ses métadonnées
        $metadata = $reminder->metadata ?? [];

        return [
            'title' => "Rappel : {$this->title}",
            'body' => "N'oubliez pas de publier votre article !",
            'type' => $metadata['type'] ?? 'notification',
            'data' => [
                'article_id' => $this->id,
                'custom_data' => $metadata,
            ],
            'imageUrl' => $this->featured_image, // Optionnel
        ];
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

### 3. Planifier des rappels

Une fois votre modèle configuré, vous pouvez planifier des rappels de plusieurs façons :

```php
// Récupérer un modèle
$article = Article::find(1);

// 1. Planifier un rappel simple
$rappel = $article->scheduleReminder(
    scheduledAt: now()->addDays(7), // Date d'envoi
    metadata: ['type' => 'email'] // Données supplémentaires
);

// 2. Planifier plusieurs rappels à la fois
$rappels = $article->scheduleMultipleReminders(
    scheduledTimes: [
        now()->addDays(7),
        now()->addDays(3),
        now()->addDay(),
    ],
    metadata: ['priority' => 'high']
);

// 3. Planifier avec une date en string
$rappel = $article->scheduleReminder('2025-12-25 09:00:00');
```

### 4. Gérer les rappels existants

Le trait `Remindable` met à disposition plusieurs méthodes pour gérer vos rappels :

```php
// Récupérer tous les rappels d'un modèle
$tousLesRappels = $article->reminders;

// Récupérer uniquement les rappels en attente
$enAttente = $article->pendingReminders();

// Vérifier s'il y a des rappels en attente
if ($article->hasPendingReminders()) {
    // Faire quelque chose...
}

// Obtenir le prochain rappel à venir
$prochainRappel = $article->nextReminder();

// Annuler tous les rappels en attente
$nombreAnnule = $article->cancelReminders();
```

### 5. Traitement manuel des rappels

Si vous préférez traiter les rappels manuellement (sans la file d'attente) :

```bash
# Traitement synchrone
php artisan reminders:send --sync

# Affiche un tableau comme ceci :
# +-----------+-------+
# | Metric    | Count |
# +-----------+-------+
# | Total     | 10    |
# | Processed | 8     |
# | Failed    | 2     |
# +-----------+-------+
```

Ou via la façade dans votre code :

```php
use Andydefer\LaravelReminder\Facades\Reminder;

$resultats = Reminder::processPendingReminders();

// $resultats = [
//     'processed' => 8,
//     'failed' => 2,
//     'total' => 10,
// ];
```

## Architecture détaillée

### Le modèle Reminder

Le cœur du package est le modèle `Reminder` qui stocke toutes les informations nécessaires :

```php
// Création manuelle (si nécessaire)
$reminder = new Reminder([
    'remindable_type' => Article::class,
    'remindable_id' => $article->id,
    'scheduled_at' => now()->addDays(3),
    'metadata' => ['type' => 'whatsapp'],
    'status' => ReminderStatus::PENDING,
    'attempts' => 0,
]);
$reminder->save();

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
$rappelsEnRetard = Reminder::due()->get();
$rappelsRecents = Reminder::withinTolerance(30)->get();
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
$rappelsEnAttente = Reminder::where('status', ReminderStatus::PENDING)->get();

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
$secondes = ToleranceUnit::HOUR->toSeconds(); // 3600
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
$estDansFenetre = $tolerance->isWithinWindow(
    scheduledAt: $scheduledAt,
    now: now() // 2025-03-20 10:00:00
); // true (car 1h < 2h)

// Obtenir la représentation en minutes/secondes
$minutes = $tolerance->toMinutes(); // 120
$secondes = $tolerance->toSeconds(); // 7200

// Affichage
echo (string) $tolerance; // "2 Hours"
```

## Intégration avec le système de notifications Laravel

Le package est conçu pour s'intégrer parfaitement avec le système de notifications de Laravel. Voici un exemple complet :

```php
<?php

namespace App\Models;

use Andydefer\LaravelReminder\Contracts\ShouldRemind;
use Andydefer\LaravelReminder\Models\Reminder;
use Andydefer\LaravelReminder\Traits\Remindable;
use App\Notifications\ArticleReminderNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements ShouldRemind
{
    use Notifiable, Remindable;

    public function toRemind(Reminder $reminder): array
    {
        // Les données retournées seront utilisées pour créer une notification
        return [
            'title' => 'Rappel important',
            'body' => 'Votre abonnement expire bientôt',
            'type' => 'subscription',
            'data' => $reminder->metadata,
        ];
    }

    public function getTolerance(): Tolerance
    {
        // Grande flexibilité pour les utilisateurs
        return new Tolerance(24, ToleranceUnit::HOUR);
    }
}

// Dans un contrôleur ou une commande
class ProcessRemindersController
{
    public function __invoke(ReminderService $service)
    {
        // Récupérer les rappels à traiter
        $reminders = Reminder::due()->with('remindable')->get();

        foreach ($reminders as $reminder) {
            $user = $reminder->remindable;

            // Créer une notification Laravel à partir des données du rappel
            $notificationData = $user->toRemind($reminder);

            $user->notify(new ArticleReminderNotification(
                title: $notificationData['title'],
                body: $notificationData['body'],
                data: $notificationData['data'] ?? []
            ));

            $reminder->markAsSent();
        }
    }
}
```

## Événements

Le package dispatch des événements à chaque étape importante, vous permettant d'ajouter votre propre logique :

```php
// Dans votre AppServiceProvider.php
public function boot(): void
{
    // Écouter tous les événements de rappel
    Event::listen('reminder.*', function ($eventName, $payload) {
        Log::info("Événement rappel : {$eventName}", $payload);
    });

    // Écouter un événement spécifique
    Event::listen('reminder.sent', function ($reminder) {
        // Envoyer une confirmation à l'administrateur
        Log::info("Rappel envoyé avec succès : {$reminder->id}");
    });

    Event::listen('reminder.failed', function ($reminder, $exception) {
        // Notifier l'équipe technique
        Log::error("Échec d'envoi de rappel", [
            'reminder_id' => $reminder->id,
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
| `reminder.sending` | `[$reminder, $notificationData]` | Avant l'envoi |
| `reminder.processed` | `$results` | Fin du traitement global |

## Tests

Le package est fourni avec une suite de tests complète. Voici comment les exécuter :

```bash
composer test
# ou
./vendor/bin/phpunit
```

### Exemple de test personnalisé

```php
<?php

namespace Tests\Feature;

use Andydefer\LaravelReminder\Facades\Reminder;
use App\Models\Article;
use Carbon\Carbon;
use Tests\TestCase;

class ArticleRemindersTest extends TestCase
{
    public function test_article_can_have_reminders()
    {
        // Créer un article
        $article = Article::factory()->create();

        // Planifier un rappel
        $rappel = $article->scheduleReminder(
            scheduledAt: now()->addDays(3),
            metadata: ['reason' => 'publication']
        );

        // Vérifications
        $this->assertDatabaseHas('reminders', [
            'id' => $rappel->id,
            'remindable_id' => $article->id,
            'status' => 'pending',
        ]);

        $this->assertCount(1, $article->reminders);
    }

    public function test_reminder_is_sent_within_tolerance()
    {
        // Simuler une date
        Carbon::setTestNow('2025-03-20 10:00:00');

        $article = Article::factory()->create();

        // Rappel prévu il y a 25 minutes (dans la tolérance de 30 min)
        $article->scheduleReminder(
            scheduledAt: Carbon::now()->subMinutes(25)
        );

        // Traiter les rappels
        $resultats = Reminder::processPendingReminders();

        // Vérifier que le rappel a été traité
        $this->assertEquals(1, $resultats['processed']);
    }
}
```

## Bonnes pratiques

### 1. Nommez vos métadonnées de façon cohérente

```php
// 👍 À faire
$rappel = $commande->scheduleReminder(
    scheduledAt: now()->addDays(3),
    metadata: [
        'notification_type' => 'email',
        'template' => 'order.reminder',
        'locale' => app()->getLocale(),
        'user_id' => auth()->id(),
    ]
);

// 👎 À éviter
$rappel = $commande->scheduleReminder(
    now()->addDays(3),
    ['abc' => 123, 'xyz' => true] // Métadonnées non explicites
);
```

### 2. Utilisez la file d'attente en production

```env
# .env
REMINDER_QUEUE_ENABLED=true
REMINDER_QUEUE_CONNECTION=redis
REMINDER_QUEUE_NAME=high
```

```bash
# Lancez un worker dédié
php artisan queue:work redis --queue=high
```

### 3. Nettoyez les anciens rappels

Activez le nettoyage automatique dans votre configuration :

```php
// config/reminder.php
'cleanup' => [
    'enabled' => true,
    'after_days' => 30, // Supprime les rappels de plus de 30 jours
],
```

### 4. Gérez les erreurs gracieusement

```php
class Article implements ShouldRemind
{
    public function toRemind(Reminder $reminder): array
    {
        try {
            // Logique métier potentiellement instable
            $titre = $this->getDynamicTitle();
        } catch (\Exception $e) {
            // Fallback en cas d'erreur
            Log::warning('Erreur lors de la génération du rappel', [
                'article' => $this->id,
                'error' => $e->getMessage(),
            ]);

            $titre = 'Rappel: ' . $this->title;
        }

        return [
            'title' => $titre,
            'body' => 'Contenu par défaut',
        ];
    }
}
```

## Cas d'usage avancés

### Rappels récurrents

```php
trait RecurringReminders
{
    public function scheduleRecurringReminders(array $schedule, array $metadata = []): array
    {
        $reminders = [];

        foreach ($schedule as $interval) {
            // $interval peut être 'daily', 'weekly', 'monthly', etc.
            $nextDate = $this->calculateNextDate($interval);

            $reminders[] = $this->scheduleReminder(
                scheduledAt: $nextDate,
                metadata: array_merge($metadata, ['pattern' => $interval])
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
    use Remindable, RecurringReminders;

    public function activate()
    {
        // Planifier des rappels récurrents
        $this->scheduleRecurringReminders(
            schedule: ['daily', 'weekly', 'monthly'],
            metadata: ['subscription_id' => $this->id, 'type' => 'renewal']
        );
    }
}
```

### Rappels avec conditions

```php
class Task extends Model implements ShouldRemind
{
    use Remindable;

    public function toRemind(Reminder $reminder): array
    {
        $data = [
            'title' => "Tâche : {$this->title}",
            'body' => "Cette tâche est due dans 24h",
        ];

        // Adapter la notification selon le contexte
        if ($this->priority === 'high') {
            $data['type'] = 'urgent';
            $data['imageUrl'] = config('app.url') . '/images/urgent.png';
        }

        // Ajouter des données supplémentaires
        if ($this->assigned_to) {
            $data['data']['assignee'] = $this->assigned_to;
        }

        return $data;
    }

    public function getTolerance(): Tolerance
    {
        // Plus de flexibilité pour les tâches urgentes
        if ($this->priority === 'high') {
            return new Tolerance(1, ToleranceUnit::HOUR);
        }

        return new Tolerance(24, ToleranceUnit::HOUR);
    }
}
```

## Dépannage

### Problème : Les rappels ne s'envoient pas

Vérifiez les points suivants :

```bash
# 1. La file d'attente est-elle en cours d'exécution ?
php artisan queue:status

# 2. Les rappels sont-ils bien en attente ?
php artisan tinker
>>> Reminder::pending()->count()

# 3. Y a-t-il des erreurs dans les logs ?
tail -f storage/logs/laravel.log | grep "reminder"
```

### Problème : Trop de tentatives échouées

Ajustez la configuration :

```php
// config/reminder.php
'max_attempts' => 5, // Augmenter le nombre de tentatives
'queue' => [
    'enabled' => true,
    'connection' => 'redis', // Passer à Redis pour meilleure performance
],
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