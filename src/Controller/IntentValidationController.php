<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class IntentValidationController extends AbstractController
{

    #[Route('/intents/validate', name: 'intents_validate')]
    public function showValidationPage(): Response
    {
        return $this->render('intent_validation.html.twig');
    }

    #[Route('/api/intents/unknown', name: 'api_intents_unknown')] // récupere les question sans intent
    public function listUnknown(): JsonResponse
    {
        // Chemin du fichier training_data.json
        $filePath = __DIR__ . '/../../public/base/intents_and_questions.json';

        if (!file_exists($filePath)) {
            return new JsonResponse(['error' => 'Le fichier training_data.json est introuvable.'], 404);
        }

        // Charger les données
        $data = json_decode(file_get_contents($filePath), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Impossible de lire le fichier JSON.'], 500);
        }

        // Filtrer les questions avec l'intention "unknown"
        $unknownQuestions = array_filter($data, fn($entry) => $entry['intent'] === 'unknown');

        return new JsonResponse(['unknown' => array_values($unknownQuestions)]);
    }


    #[Route('/api/intents/validate', name: 'api_intents_validate')]   // valide la question
    public function validateIntent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? '';
        $intent = $data['intent'] ?? '';

        if (!$text || !$intent) {
            return new JsonResponse(['error' => 'Texte ou intention manquante.'], 400);
        }

        // Charger les données JSON existantes
        $filePath = __DIR__ . '/../../public/base/intents_and_questions.json';
        if (!file_exists($filePath)) {
            return new JsonResponse(['error' => 'Le fichier training_data.json est introuvable.'], 404);
        }

        $trainingData = json_decode(file_get_contents($filePath), true);

        // Rechercher et mettre à jour la question
        foreach ($trainingData as &$entry) {
            if ($entry['text'] === $text) {
                $entry['intent'] = $intent;
                break;
            }
        }

        // Sauvegarder le fichier JSON mis à jour
        file_put_contents($filePath, json_encode($trainingData, JSON_PRETTY_PRINT));

        return new JsonResponse(['message' => "L'intention pour la question a été mise à jour."]);
    }

}
