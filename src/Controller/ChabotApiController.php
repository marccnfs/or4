<?php

namespace App\Controller;

use App\Services\SpacyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChabotApiController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private SpacyService $spacyService;

    public function __construct(HttpClientInterface $httpClient, SpacyService $spacyService)
    {
        $this->httpClient = $httpClient;
        $this->spacyService = $spacyService;
    }

    #[Route('/chat', name: 'potinschat', methods: ['POST'])]   //version appellé par /chatpotin avec intent
    public function potinschat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return new JsonResponse(['error' => 'Message is required'], 400);
        }

        try {
            $botResponse = $this->spacyService->analyse($userMessage);

            return new JsonResponse([
                'response' => $botResponse['response'],
                'intent' => $botResponse['intent'] ?? 'unknown',]);

        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/analyze', name: 'analyze_question', methods: ['POST'])] // version animation d3
    public function analyzeQuestion(Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $question = $content['question'] ?? '';

        if (empty($question)) {
            return new JsonResponse(['error' => 'Question is required'], 400);
        }

        // Appel à spaCy pour extraire les mots-clés
        try {
            //$spacyKeywords = $this->spacyService->extractKeywords($question);
            $botResponse = $this->spacyService->analyse($question);

            // Assure que les clés nécessaires sont présentes dans la réponse
            $keywords = $botResponse['keywords'] ?? [];
            $relationships = $botResponse['relationships'] ?? [];
            $responseText = $botResponse['response'] ?? 'Pas de réponse définie.';

            // Transformation des données pour D3.js
            $keywordsForD3 = array_map(function ($keyword) {
                return [
                    'keyword' => $keyword,
                    'x' => rand(50, 800), // Position X aléatoire
                    'y' => rand(50, 600), // Position Y aléatoire
                    'size' => rand(15, 30), // Taille aléatoire
                    'color' => sprintf("#%06X", rand(0, 0xFFFFFF)) // Couleur aléatoire
                ];
            }, $keywords);


            // Retour des données au format attendu par D3.js
            return new JsonResponse([
                'keywords' => $keywords,
                'relationships' => $relationships,
                'response' => $responseText,
                'form_html' => $this->renderView('response_form.html.twig', ['question' => $question]),
            ], Response::HTTP_OK, ['Content-Type' => 'application/json; charset=utf-8']);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l’analyse de la question : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/glossary', name: 'glossary', methods: ['POST'])] // version animation d3
    public function fetchGlossary(Request $request): JsonResponse
    {
        // Récupère le contenu JSON de la requête
        $data = json_decode($request->getContent(), true);
        $term = isset($data['term']) ? trim($data['term']) : null;

        // Vérifie si le terme est fourni
        if (!$term) {
            return new JsonResponse(['error' => 'Aucun terme fourni.'], 400);
        }

        // Appelle le service Spacy pour récupérer la définition du glossaire
        $glossary = $this->spacyService->getGlossaryDefinition($term);

        // Vérifie si une définition a été trouvée
        if (!isset($glossary['definition']) || !is_string($glossary['definition'])) {
            return new JsonResponse(['error' => "La définition est invalide ou manquante."], 404);
        }
        // Retourne la réponse JSON avec le terme et la définition
        return new JsonResponse([
            'term' => $term,
            'definition' => $glossary['definition']
        ], Response::HTTP_OK, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    #[Route('/api/analyze_context', name: 'analyze_context', methods: ['POST'])]
    public function analyzContext(Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $question = $content['message'] ?? '';

        if (empty($question)) {
            return new JsonResponse(['error' => 'Question is required'], 400);
        }

        // Appel à spaCy pour extraire les mots-clés et analyser le contexte
        try {
            $botResponse = $this->spacyService->analyzContextMessage($question);

            // Vérifications explicites pour éviter les clés manquantes
            $keywords = $botResponse['keywords'] ?? [];
            $entities = $botResponse['entities'] ?? [];
            $response = $botResponse['response'] ?? 'Je ne peux pas répondre à cette question.';
            $detectedIntent = $botResponse['detected_intent'] ?? 'unknown';
            $context = $botResponse['context'] ?? 'unknown';

            // Retour des données au format attendu par D3.js
            return new JsonResponse([
                'keywords' => $keywords,
                'entities' => $entities,
                'response' => $response,
                'intent' => $detectedIntent,
                'context' => $context,
            ], Response::HTTP_OK, ['Content-Type' => 'application/json; charset=utf-8']);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l’analyse de la question : ' . $e->getMessage()
            ], 500);
        }
    }


    #[Route('/api/explore_clusters', name: 'explore_clusters', methods: ['GET'])]
    public function exploreClusters(): JsonResponse
    {
        try {
            $clusters = $this->spacyService->getClusters();
            return new JsonResponse($clusters, Response::HTTP_OK,
                ['Content-Type' => 'application/json; charset=utf-8']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }


    #[Route('/api/statistics', name: 'get_statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->spacyService->getStatistics();
            return new JsonResponse($statistics,
                Response::HTTP_OK,
                ['Content-Type' => 'application/json; charset=utf-8']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }



    #[Route('/api/chat', name: 'chatbotapi', methods: ['POST'])]  // requete de la question reformulée
    public function chat(Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $userInput = $content['question'] ?? '';

        if (empty($userInput)) {
            return new JsonResponse(['error' => 'Message is required'], 400);
        }

        // 1. Check local knowledge base (JSON file)
        $localResponse = $this->findInKnowledgeBase($userInput);
        if ($localResponse) {
            return new JsonResponse(['response' => $localResponse]);
        }

        // 2. Reformuler pour OpenAI
        $simplifiedQuestion = preg_replace('/[^a-zA-Z0-9\s]/', '', strtolower($userInput));

        // 2. If no local match, query OpenAI API
        $apiKey = $_ENV['OPENAI_API_KEY'];
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
                        ['role' => 'user', 'content' => $simplifiedQuestion],
                    ],
                ],
            ]);

            $data = $response->toArray();
            $botResponse = $data['choices'][0]['message']['content'] ?? 'Une erreur est survenue.';
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la communication avec OpenAI.',
                'details' => $e->getMessage(),
            ], 500);
        }

        return new JsonResponse(['response' => $botResponse]);
    }

    private function findInKnowledgeBase(string $input): ?string
    {
        $filePath = __DIR__ . '/../../public/base/knowledge_base.json';
        if (!file_exists($filePath)) {
            return null;
        }

        $knowledgeBase = json_decode(file_get_contents($filePath), true);

        // Recherche simple par similarité
        $threshold = 10; // Distance maximale pour considérer une correspondance
        foreach ($knowledgeBase as $question => $answer) {
            if (levenshtein(strtolower($input), strtolower($question)) <= $threshold) {
                return $answer;
            }
        }

        return null;
    }

    private function searchLocalKnowledgeBase(string $input): ?string
    {
        // Load the knowledge base from a JSON file
        $filePath = __DIR__ . '/../../public/base/knowledge_base.json'; // Adjust the path
        if (!file_exists($filePath)) {
            return null;
        }

        $knowledgeBase = json_decode(file_get_contents($filePath), true);

        // Simple exact match search
        return $knowledgeBase[$input] ?? null;
    }



    #[Route('/api/relationships', name: 'relationships', methods: ['POST'])]
    public function calculateRelationships(Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $keywords = $content['keywords'] ?? [];

        if (empty($keywords)) {
            return new JsonResponse(['error' => 'Keywords are required'], 400);
        }

        try {
            $relationships = $this->spacyService->calculateRelationships($keywords);
            return new JsonResponse(['relationships' => $relationships], Response::HTTP_OK,
                ['Content-Type' => 'application/json; charset=utf-8']);

        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/explore', name: 'explore', methods: ['POST'])]
    public function exploreCluster(Request $request, SpacyService $spacyService): JsonResponse
    {
        $content = json_decode($request->getContent(), true);
        $keyword = $content['keyword'] ?? '';

        if (empty($keyword)) {
            return new JsonResponse(['error' => 'Keyword is required'], 400);
        }

        // Génère des mots-clés liés à partir de spaCy
        $relatedKeywords = $spacyService->extractKeywords($keyword);

        $keywordsForD3 = array_map(fn($keyword) => [
            'keyword' => $keyword,
            'x' => rand(50, 800),
            'y' => rand(50, 600),
            'size' => rand(15, 30),
            'color' => sprintf("#%06X", rand(0, 0xFFFFFF))
        ], $relatedKeywords);

        return new JsonResponse([
            'keywords' => $keywordsForD3
        ], Response::HTTP_OK, ['Content-Type' => 'application/json; charset=utf-8']);
    }


    // Suppression des stopwords
    //$stopwords = ["et", "ou", "est", "le", "la", "les", "un", "une", "des", "à", "de", "d\"", "dans", "en", "par", "sur", "avec", "ce", "ces", "ça","a","abord","absolument","afin","ah","ai","aie","aient","aies","ailleurs","ainsi","ait","allaient","allo","allons","allô","alors","anterieur","anterieure","anterieures","apres","après","as","assez","attendu","au","aucun","aucune","aucuns","aujourd","aujourd"hui","aupres","auquel","aura","aurai","auraient","aurais","aurait","auras","aurez","auriez","aurions","aurons","auront","aussi","autant","autre","autrefois","autrement","autres","autrui","aux","auxquelles","auxquels","avaient","avais","avait","avant","avec","avez","aviez","avions","avoir","avons","ayant","ayez","ayons","b","bah","bas","basee","bat","beau","beaucoup","bien","bigre","bon","boum","bravo","brrr","c","car","ce","ceci","cela","celle","celle-ci","celle-là","celles","celles-ci","celles-là","celui","celui-ci","celui-là","celà","cent","cependant","certain","certaine","certaines","certains","certes","ces","cet","cette","ceux","ceux-ci","ceux-là","chacun","chacune","chaque","cher","chers","chez","chiche","chut","chère","chères","ci","cinq","cinquantaine","cinquante","cinquantième","cinquième","clac","clic","combien","comme","comment","comparable","comparables","compris","concernant","contre","couic","crac","d","da","dans","de","debout","dedans","dehors","deja","delà","depuis","dernier","derniere","derriere","derrière","des","desormais","desquelles","desquels","dessous","dessus","deux","deuxième","deuxièmement","devant","devers","devra","devrait","different","differentes","differents","différent","différente","différentes","différents","dire","directe","directement","dit","dite","dits","divers","diverse","diverses","dix","dix-huit","dix-neuf","dix-sept","dixième","doit","doivent","donc","dont","dos","douze","douzième","dring","droite","du","duquel","durant","dès","début","désormais","e","effet","egale","egalement","egales","eh","elle","elle-même","elles","elles-mêmes","en","encore","enfin","entre","envers","environ","es","essai","est","et","etant","etc","etre","eu","eue","eues","euh","eurent","eus","eusse","eussent","eusses","eussiez","eussions","eut","eux","eux-mêmes","exactement","excepté","extenso","exterieur","eûmes","eût","eûtes","f","fais","faisaient","faisant","fait","faites","façon","feront","fi","flac","floc","fois","font","force","furent","fus","fusse","fussent","fusses","fussiez","fussions","fut","fûmes","fût","fûtes","g","gens","h","ha","haut","hein","hem","hep","hi","ho","holà","hop","hormis","hors","hou","houp","hue","hui","huit","huitième","hum","hurrah","hé","hélas","i","ici","il","ils","importe","j","je","jusqu","jusque","juste","k","l","la","laisser","laquelle","las","le","lequel","les","lesquelles","lesquels","leur","leurs","longtemps","lors","lorsque","lui","lui-meme","lui-même","là","lès","m","ma","maint","maintenant","mais","malgre","malgré","maximale","me","meme","memes","merci","mes","mien","mienne","miennes","miens","mille","mince","mine","minimale","moi","moi-meme","moi-même","moindres","moins","mon","mot","moyennant","multiple","multiples","même","mêmes","n","na","naturel","naturelle","naturelles","ne","neanmoins","necessaire","necessairement","neuf","neuvième","ni","nombreuses","nombreux","nommés","non","nos","notamment","notre","nous","nous-mêmes","nouveau","nouveaux","nul","néanmoins","nôtre","nôtres","o","oh","ohé","ollé","olé","on","ont","onze","onzième","ore","ou","ouf","ouias","oust","ouste","outre","ouvert","ouverte","ouverts","o|","où","p","paf","pan","par","parce","parfois","parle","parlent","parler","parmi","parole","parseme","partant","particulier","particulière","particulièrement","pas","passé","pendant","pense","permet","personne","personnes","peu","peut","peuvent","peux","pff","pfft","pfut","pif","pire","pièce","plein","plouf","plupart","plus","plusieurs","plutôt","possessif","possessifs","possible","possibles","pouah","pour","pourquoi","pourrais","pourrait","pouvait","prealable","precisement","premier","première","premièrement","pres","probable","probante","procedant","proche","près","psitt","pu","puis","puisque","pur","pure","q","qu","quand","quant","quant-à-soi","quanta","quarante","quatorze","quatre","quatre-vingt","quatrième","quatrièmement","que","quel","quelconque","quelle","quelles","quelqu"un","quelque","quelques","quels","qui","quiconque","quinze","quoi","quoique","r","rare","rarement","rares","relative","relativement","remarquable","rend","rendre","restant","reste","restent","restrictif","retour","revoici","revoilà","rien","s","sa","sacrebleu","sait","sans","sapristi","sauf","se","sein","seize","selon","semblable","semblaient","semble","semblent","sent","sept","septième","sera","serai","seraient","serais","serait","seras","serez","seriez","serions","serons","seront","ses","seul","seule","seulement","si","sien","sienne","siennes","siens","sinon","six","sixième","soi","soi-même","soient","sois","soit","soixante","sommes","son","sont","sous","souvent","soyez","soyons","specifique","specifiques","speculatif","stop","strictement","subtiles","suffisant","suffisante","suffit","suis","suit","suivant","suivante","suivantes","suivants","suivre","sujet","superpose","sur","surtout","t","ta","tac","tandis","tant","tardive","te","tel","telle","tellement","telles","tels","tenant","tend","tenir","tente","tes","tic","tien","tienne","tiennes","tiens","toc","toi","toi-même","ton","touchant","toujours","tous","tout","toute","toutefois","toutes","treize","trente","tres","trois","troisième","troisièmement","trop","très","tsoin","tsouin","tu","té","u","un","une","unes","uniformement","unique","uniques","uns","v","va","vais","valeur","vas","vers","via","vif","vifs","vingt","vivat","vive","vives","vlan","voici","voie","voient","voilà","voire","vont","vos","votre","vous","vous-mêmes","vu","vé","vôtre","vôtres","w","x","y","z","zut","à","â","ça","ès","étaient","étais","était","étant","état","étiez","étions","été","étée","étées","étés","êtes","être","ô"];
    //$words = array_filter(explode(' ', strtolower($question)), fn($word) => !in_array($word, $stopwords));

    // Lemmatisation (simplifiée ici, peut être remplacée par un appel à un service NLP)
    /*$lemmatizer = [
        'mangé' => 'manger',
        'mangeront' => 'manger',
        'mange' => 'manger',
    ];

    $keywords = array_map(fn($word) => $lemmatizer[$word] ?? $word, $words);


    // Pondération par longueur et fréquence
    $weightedKeywords = array_map(fn($word) => [
        'word' => $word,
        'weight' => strlen($word) * (1 + substr_count($question, $word)), // Exemple simple
    ], $keywords);

*/



    /*
        #[Route('/api/analyzeold', name: 'oldanalyze_question', methods: ['POST'])]
        public function analyzeQuestionold(Request $request): JsonResponse
        {
            $content = json_decode($request->getContent(), true);
            $question = $content['question'] ?? '';

            if (empty($question)) {
                return new JsonResponse(['error' => 'Question is required'], 400);
            }

            // Simpler keyword extraction (can be replaced by advanced NLP)
          //  $stopwords = ['et', 'ou', 'est', 'le','l', 'la', 'les', 'un', 'une', 'des', 'à']; // Add more stopwords
         //   $words = array_filter(explode(' ', strtolower($question)), fn($word) => !in_array($word, $stopwords));
         //   dump($words);

         //   $keywords = array_unique($words);
            $keywords = $this->spacyService->extractKeywords($question);

            // Return keywords for D3.js and a response form
           return new JsonResponse([
                'keywords' => array_map(fn($keyword, $i) => [
                    'keyword' => $keyword,
                    'x' => rand(100, 800),
                    'y' => rand(100, 600),
                    'size' => rand(10, 30),
                    'color' => sprintf("#%06X", mt_rand(0, 0xFFFFFF)),
                ], $keywords, array_keys($keywords)),
                'form_html' => $this->renderView('response_form.html.twig', [
                    'question' => $question,
                ]),
            ]);

        }
    */

    private function searchLocalKnowledgeBaseFromDB(string $input): ?string
    {
        $connection = $this->getDoctrine()->getConnection();
        $sql = 'SELECT answer FROM knowledge_base WHERE question = :question';
        $stmt = $connection->prepare($sql);
        $stmt->bindValue('question', $input);
        $result = $stmt->executeQuery()->fetchAssociative();

        return $result['answer'] ?? null;
    }

}
