<?php

namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class TrainController extends AbstractController
{

    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    #[Route('/train')]
    public function api(): Response
    {
        //return $this->render('basepixi1.html.twig');
        return $this->render('train.html.twig');
    }



    #[Route('/trainchat', name: 'traincaht', methods: ['POST'])]
    public function train(Request $request): JsonResponse
    {
        $response = $this->httpClient->request(
            'POST',
            'http://localhost:5000/train' // Appel API Python pour entraîner
        );
        dump($response);
        return new JsonResponse([
            'message' => 'Training triggered successfully.',
            'details' => $response->toArray(),
        ]);
    }
}