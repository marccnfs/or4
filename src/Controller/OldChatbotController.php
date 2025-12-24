<?php

namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OldChatbotController extends AbstractController
{

    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }


    #[Route('/api/chatold', name: 'chatbot', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        // Parse the JSON payload
        $content = json_decode($request->getContent(), true);
        $userInput = $content['message'] ?? '';

        if (empty($userInput)) {
            return new JsonResponse(['error' => 'Message is required'], 400);
        }

        // Prepare the API request to OpenAI
        $apiKey = $_ENV['OPENAI_API_KEY']; // Store your API key in .env
        $endpoint = 'https://api.openai.com/v1/chat/completions';

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $userInput],
                    ],
                ],
            ]);

            $data = $response->toArray();

            // Extract the response message
            $botResponse = $data['choices'][0]['message']['content'] ?? 'Une erreur est survenue.';
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la communication avec OpenAI.',
                'details' => $e->getMessage(),
            ], 500);
        }

        // Return the chatbot's response
        return new JsonResponse(['response' => $botResponse]);
    }


    #[Route('/api/keywords', name: 'get_keywords', methods: ['POST'])]
    public function getKeywords(Request $request): JsonResponse
    {
        // Simuler l'analyse de la requête utilisateur
        $content = json_decode($request->getContent(), true);
        $userInput = $content['question'] ?? '';

        if (empty($userInput)) {
            return new JsonResponse(['error' => 'Question is required'], 400);
        }

        // Exemple de réponse avec des clusters
        $response = [
            'clusters' => [
                ['keyword' => 'IA', 'weight' => 3],
                ['keyword' => 'Éducation', 'weight' => 2],
                ['keyword' => 'Éthique', 'weight' => 1],
            ],
        ];

        return new JsonResponse($response);
    }
}