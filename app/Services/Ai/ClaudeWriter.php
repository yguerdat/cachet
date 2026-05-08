<?php

namespace App\Services\Ai;

use App\Contracts\IncidentWriter;
use App\Exceptions\AiAssistantException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeWriter implements IncidentWriter
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly string $version,
        private readonly int $timeout,
        private readonly int $maxTokens,
    ) {}

    public function suggestIncident(string $rawNote): array
    {
        $tool = [
            'name' => 'provide_incident_suggestion',
            'description' => 'Fournit un titre court et une description Markdown pour un nouvel incident.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => 'Titre factuel, 70 caractères max, sans emojis ni tournures sensationnalistes.',
                        'maxLength' => 70,
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Description Markdown, 2 à 3 paragraphes courts. Décrit ce qui est observé, l\'impact utilisateur si pertinent, et l\'étape en cours.',
                    ],
                ],
                'required' => ['title', 'description'],
            ],
        ];

        $userMessage = "Note brute saisie par l'opérateur :\n\n".trim($rawNote);

        $args = $this->callTool($this->incidentSystemPrompt(), $tool, $userMessage);

        return [
            'title' => (string) ($args['title'] ?? ''),
            'description' => (string) ($args['description'] ?? ''),
        ];
    }

    public function suggestIncidentUpdate(string $rawNote, array $incidentContext): string
    {
        $tool = [
            'name' => 'provide_incident_update',
            'description' => 'Rédige le texte d\'une mise à jour de statut pour un incident en cours.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'description' => [
                        'type' => 'string',
                        'description' => 'Description Markdown courte (1 à 2 paragraphes) de la mise à jour, factuelle.',
                    ],
                ],
                'required' => ['description'],
            ],
        ];

        $contextLines = [];
        if (! empty($incidentContext['title'])) {
            $contextLines[] = 'Titre actuel : '.$incidentContext['title'];
        }
        if (! empty($incidentContext['status'])) {
            $contextLines[] = 'Statut courant : '.$incidentContext['status'];
        }
        if (! empty($incidentContext['previous_updates']) && is_array($incidentContext['previous_updates'])) {
            $contextLines[] = "Mises à jour précédentes :\n- ".implode("\n- ", $incidentContext['previous_updates']);
        }

        $userMessage = (! empty($contextLines) ? implode("\n", $contextLines)."\n\n" : '')
            ."Note brute pour la nouvelle mise à jour :\n".trim($rawNote);

        $args = $this->callTool($this->incidentUpdateSystemPrompt(), $tool, $userMessage);

        return (string) ($args['description'] ?? '');
    }

    public function suggestMaintenance(string $rawNote, array $context = []): array
    {
        $tool = [
            'name' => 'provide_maintenance_suggestion',
            'description' => 'Fournit un titre et une description Markdown pour une maintenance planifiée.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => 'Titre factuel commençant idéalement par "Maintenance planifiée — ", 70 caractères max.',
                        'maxLength' => 70,
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Description Markdown, 2 à 3 paragraphes courts. Mentionne l\'objectif, l\'impact attendu, et la fenêtre horaire.',
                    ],
                ],
                'required' => ['title', 'description'],
            ],
        ];

        $contextLines = [];
        if (! empty($context['scheduled_at'])) {
            $contextLines[] = 'Début prévu : '.$context['scheduled_at'];
        }
        if (! empty($context['completed_at'])) {
            $contextLines[] = 'Fin prévue : '.$context['completed_at'];
        }

        $userMessage = (! empty($contextLines) ? implode("\n", $contextLines)."\n\n" : '')
            ."Note brute saisie par l'opérateur :\n".trim($rawNote);

        $args = $this->callTool($this->maintenanceSystemPrompt(), $tool, $userMessage);

        return [
            'title' => (string) ($args['title'] ?? ''),
            'description' => (string) ($args['description'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    private function callTool(string $systemPrompt, array $tool, string $userMessage): array
    {
        if ($this->apiKey === '') {
            throw new AiAssistantException('Anthropic API key is not configured.');
        }

        $response = $this->client()->post('/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => [[
                'type' => 'text',
                'text' => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'tools' => [$tool + ['cache_control' => ['type' => 'ephemeral']]],
            'tool_choice' => ['type' => 'tool', 'name' => $tool['name']],
            'messages' => [[
                'role' => 'user',
                'content' => $userMessage,
            ]],
        ]);

        if ($response->failed()) {
            Log::warning('Anthropic API call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiAssistantException('Le service IA est momentanément indisponible.');
        }

        $payload = $response->json();
        $content = $payload['content'] ?? [];

        foreach ($content as $block) {
            if (($block['type'] ?? null) === 'tool_use' && isset($block['input']) && is_array($block['input'])) {
                return $block['input'];
            }
        }

        Log::warning('Anthropic returned no tool_use block', ['payload' => $payload]);

        throw new AiAssistantException('Réponse inattendue du service IA.');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->version,
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->asJson();
    }

    private function incidentSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es l'assistant rédactionnel de la page de status `status.eseances.app`, opérée par Artionet pour ses clients (Suisse, FR-CH).

Ta mission : transformer une note brute (souvent télégraphique, pleine d'abréviations techniques, parfois en franglais) en un texte d'incident clair, factuel, sobre, à destination de :
- les clients finaux (utilisateurs des plateformes eSeances, parfois non-techniques) ;
- les équipes des clients (cadres, IT internes).

Règles non négociables :

1. **Langue** : français de Suisse romande. JAMAIS d'anglais sauf pour des noms propres techniques (« API », « token », « webhook », noms de services). Pas d'anglicismes inutiles.
2. **Titre** :
   - 70 caractères maximum (compte serré).
   - Factuel, descriptif. Décrit *ce qui est cassé*, pas *l'émotion*.
   - Pas d'emoji, pas de point d'exclamation, pas de majuscules de début de chaque mot, pas de "URGENT".
   - Forme nominale ou impersonnelle ("Lenteurs sur le portail Conseil", "Indisponibilité partielle de l'API webhook").
3. **Description** :
   - Markdown, 2 à 3 paragraphes courts (max ~80 mots au total).
   - Paragraphe 1 : ce qu'on observe (symptômes, périmètre, depuis quand si fourni).
   - Paragraphe 2 : impact utilisateur (concret) si pertinent.
   - Paragraphe 3 (optionnel) : ce qu'on est en train de faire / prochaine étape.
   - Pas d'engagement temporel ("rétabli dans 30 minutes") sauf si explicitement fourni.
4. **Ton** : professionnel, posé, sobre. On reconnaît la gêne occasionnée mais sans surjouer ni se faire pardonner. Pas de "nous sommes désolés".
5. **Vérité** : N'INVENTE JAMAIS la cause racine, ni un nombre d'utilisateurs touchés, ni un service tiers fautif si ce n'est pas explicitement dans la note. En cas de doute, reste descriptif (« nous investiguons l'origine »).
6. **Confidentialité** : ne mentionne pas de noms de clients individuels, d'IPs internes, de credentials, de noms de serveurs internes (`ov-cab125`, `mysql-01`…). Si la note en contient, retire-les.
7. **Format de sortie** : tu DOIS appeler l'outil fourni, jamais répondre directement.
PROMPT;
    }

    private function incidentUpdateSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es l'assistant rédactionnel de la page de status `status.eseances.app`. Tu rédiges une **mise à jour** d'un incident déjà publié.

Une mise à jour ≠ un nouveau titre. Tu dois rédiger UNIQUEMENT la description de l'update, en supposant que les lecteurs ont déjà vu les updates précédents.

Règles :

1. **Langue** : français de Suisse romande. Pas d'anglicismes inutiles.
2. **Format** : Markdown, 1 à 2 paragraphes courts (max ~60 mots au total).
3. **Contenu** : décrit ce qui a changé depuis le dernier update : nouveau symptôme, hypothèse de cause, mitigation appliquée, retour à la normale partiel ou total.
4. **Ton** : professionnel, sobre, factuel.
5. **Vérité** : n'invente rien. Si la note dit « DB toujours lente », ne dis pas « problème en cours d'investigation par le fournisseur cloud » (sauf si écrit).
6. **Continuité** : pas de redite du titre. Pas de répétition des updates précédents.
7. **Confidentialité** : pas de noms internes, pas de noms de clients, pas d'IPs.
8. **Format de sortie** : tu DOIS appeler l'outil fourni.
PROMPT;
    }

    private function maintenanceSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es l'assistant rédactionnel de la page de status `status.eseances.app`. Tu rédiges l'annonce d'une **maintenance planifiée**.

Une maintenance planifiée est différente d'un incident : elle est *prévue*, *contrôlée*, *annoncée à l'avance*. Le ton est plus posé que pour un incident.

Règles :

1. **Langue** : français de Suisse romande.
2. **Titre** :
   - 70 caractères maximum.
   - Commence idéalement par « Maintenance planifiée — » suivi du périmètre.
   - Pas d'emojis.
3. **Description** :
   - Markdown, 2 à 3 paragraphes courts.
   - Paragraphe 1 : objectif de la maintenance (mise à jour de version, migration, etc.).
   - Paragraphe 2 : impact attendu (interruption, lenteur, fonctionnalité indisponible…).
   - Paragraphe 3 : fenêtre horaire si fournie.
4. **Ton** : professionnel, informatif, légèrement orienté « action préparatoire ». Remercier l'utilisateur de sa compréhension est OK ici, à dose homéopathique.
5. **Vérité** : ne pas inventer. Si la note ne précise pas l'impact, écrire « impact attendu : possible interruption brève » plutôt qu'un détail spécifique.
6. **Confidentialité** : pas de noms internes ni de versions précises de packages internes.
7. **Format de sortie** : tu DOIS appeler l'outil fourni.
PROMPT;
    }
}
