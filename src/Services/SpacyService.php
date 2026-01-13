<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SpacyService
{
    private string $apiUrl;
    private Client $httpClient;


    public function __construct(string $apiUrl = "http://localhost:5000")
    {
        $this->apiUrl = $apiUrl;
        $this->httpClient = new Client(['base_uri' => $this->apiUrl]);
    }

    public function extractKeywords(string $text): array
    {
        try {
            $response = $this->httpClient->post('/extract_keywords', [
                'json' => ['text' => $text],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['keywords'] ?? [];
        } catch (RequestException $e) {
            // Gérer les erreurs
            throw new \RuntimeException("Erreur lors de la communication avec le microservice spaCy.");
        }
    }

    public function calculateRelationships(array $keywords): array
    {
        try {
            $response = $this->httpClient->post('/calculate_relationships', [
                'json' => ['keywords' => $keywords]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['relationships'] ?? [];
        } catch (RequestException $e) {
            throw new \RuntimeException("Erreur lors de la communication avec spaCy: " . $e->getMessage());
        }
    }

    public function getGlossaryDefinition(string $term): array
    {
        try {
            $response = $this->httpClient->post('/glossary', [
                'json' => ['term' => $term]
            ]);

            $glossary = json_decode($response->getBody()->getContents(), true);
            if (isset($glossary['definition'])) {
                return $glossary; // Retourne le tableau contenant 'term' et 'definition'
            }
            return ['error' => "Terme introuvable ou réponse invalide."];
        } catch (RequestException $e) {
            throw new \RuntimeException("Erreur lors de la communication avec spaCy: " . $e->getMessage());
        }

    }

    public function analyse(String $userMessage): array
    {
        try {
            // Envoi de la requête au service Flask
            $response = $this->httpClient->post('/analyze_combined', [
                'json' => ['message' => $userMessage]
            ]);


            // Récupération et décodage de la réponse
            $body = $response->getBody()->getContents(); // Contenu brut
            $botResponse = json_decode($body, true); // Convertit le JSON en tableau associatif

            // Vérifie que la réponse est bien un tableau
            if (!is_array($botResponse)) {
                throw new \RuntimeException("La réponse de spaCy n'est pas au format attendu.");
            }

            return $botResponse;

        } catch (RequestException $e) {
            throw new \RuntimeException("Erreur lors de la communication avec spaCy: " . $e->getMessage());
        }
    }

    public function analyzContextMessage(String $userMessage): array
    {

        try {
            // Envoi de la requête au service Flask
            $response = $this->httpClient->post('/analyze_context', [
                'json' => ['message' => $userMessage]
            ]);

            /*$response = $this->client->request(
                'POST',
                '/analyze_context', [
                    'json' => ['message' => $userMessage]
            ]);*/
            //$content = $response->toArray();

            // Récupération et décodage de la réponse
            $body = $response->getBody()->getContents(); // Contenu brut
            $botResponse = json_decode($body, true); // Convertit le JSON en tableau associatif

            // Vérifie que la réponse est bien un tableau
            if (!is_array($botResponse)) {
                throw new \RuntimeException("La réponse de spaCy n'est pas au format attendu.");
            }

            //return $botResponse;
            return $botResponse;

        } catch (TransportExceptionInterface  $e) {
            throw new \RuntimeException("Erreur lors de la communication avec spaCy: " . $e->getMessage());
        }

    }

    public function getClusters()
    {

        try {
            // Envoi de la requête au service Flask
            $response = $this->httpClient->get('/explore_clusters');  // Contenu brut
            $content= $response->getBody()->getContents();
            return json_decode($content, true);

        } catch (RequestException $e) {
            throw new \RuntimeException("Erreur lors de la communication avec spaCy: " . $e->getMessage());
        }
    }

    public function getStatistics()
    {
        try {
            // Envoi de la requête au service Flask
            $response= $this->httpClient->get('/statistics');
            $content= $response->getBody()->getContents();
            return json_decode($content, true);

        } catch (RequestException $e) {
            throw new \RuntimeException("Erreur lors de la communication avec spaCy: " . $e->getMessage());
        }
    }

}
