<?php
/**
 * ElevenLabsService.php
 * Servicio para gestionar agentes de ElevenLabs Conversational AI vía REST API.
 * La API key NUNCA sale del servidor.
 */
class ElevenLabsService {

    const BASE_URL = 'https://api.elevenlabs.io/v1';

    private string $apiKey;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Realiza una petición HTTP a la API de ElevenLabs.
     */
    private function request(string $method, string $path, array $body = null): array {
        $url = self::BASE_URL . $path;
        $headers = [
            'xi-api-key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Evitar fallos de cURL por certificados SSL en local (Windows/XAMPP)
        $isLocal = (strpos(__DIR__, 'xampp') !== false) || 
                   (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'], true));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? []));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? []));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('cURL error: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $data['detail']['message'] ?? $data['message'] ?? $data['error'] ?? $response;
            throw new \RuntimeException('ElevenLabs API error (' . $httpCode . '): ' . $msg);
        }

        return $data ?? [];
    }

    /**
     * Crea un nuevo agente en ElevenLabs y devuelve su agent_id.
     */
    public function createAgent(string $name, string $prompt, string $language = 'es'): string {
        $strictPrompt = $prompt . "\n\n" . 
            "[REGLA DE CONTEXTO ESTRICTO: Responde UNICAMENTE utilizando la informacion contenida en tu base de conocimientos (Knowledge Base). " .
            "Si la respuesta a la consulta no se encuentra en tus documentos, responde indicando que no posees esa informacion " .
            "y redirige cordialmente la conversacion. Bajo ningun concepto inventes datos o respondas preguntas fuera de tu contexto.]";

        $body = [
            'name' => $name,
            'conversation_config' => [
                'agent' => [
                    'first_message' => '¡Hola! Soy ' . $name . '. ¿En qué puedo ayudarte?',
                    'language'      => $language,
                    'prompt'        => [
                        'prompt' => $strictPrompt,
                        'llm'    => 'gemini-2.5-flash',
                    ],
                ],
                'tts' => [
                    'model_id' => 'eleven_flash_v2_5',
                ],
            ],
        ];

        $result = $this->request('POST', '/convai/agents/create', $body);
        if (empty($result['agent_id'])) {
            throw new \RuntimeException('ElevenLabs no devolvió agent_id. Respuesta: ' . json_encode($result));
        }
        return $result['agent_id'];
    }

    /**
     * Actualiza nombre, prompt y documentos de base de conocimiento de un agente existente.
     */
    public function updateAgent(string $elAgentId, string $name, string $prompt, array $docIds = []): void {
        $strictPrompt = $prompt . "\n\n" . 
            "[REGLA DE CONTEXTO ESTRICTO: Responde UNICAMENTE utilizando la informacion contenida en tu base de conocimientos (Knowledge Base). " .
            "Si la respuesta a la consulta no se encuentra en tus documentos, responde indicando que no posees esa informacion " .
            "y redirige cordialmente la conversacion. Bajo ningun concepto inventes datos o respondas preguntas fuera de tu contexto.]";

        $knowledgeBase = array_map(fn($id) => ['id' => $id, 'type' => 'file'], $docIds);
        $body = [
            'name' => $name,
            'conversation_config' => [
                'agent' => [
                    'first_message' => '¡Hola! Soy ' . $name . '. ¿En qué puedo ayudarte?',
                    'prompt'        => [
                        'prompt'         => $strictPrompt,
                        'llm'            => 'gemini-2.5-flash',
                        'knowledge_base' => $knowledgeBase,
                    ],
                ],
                'tts' => [
                    'model_id' => 'eleven_flash_v2_5',
                ],
            ],
        ];
        $this->request('PATCH', '/convai/agents/' . $elAgentId, $body);
    }

    /**
     * Obtiene una URL firmada (signed URL) para una sesión WebSocket privada.
     * Esta URL es temporal (~1 minuto) y es la que se pasa al widget del cliente.
     */
    public function getSignedUrl(string $elAgentId): string {
        $result = $this->request('GET', '/convai/conversation/get-signed-url?agent_id=' . urlencode($elAgentId));
        if (empty($result['signed_url'])) {
            throw new \RuntimeException('ElevenLabs no devolvió signed_url. Respuesta: ' . json_encode($result));
        }
        return $result['signed_url'];
    }

    /**
     * Elimina un agente de ElevenLabs.
     */
    public function deleteAgent(string $elAgentId): void {
        try {
            $this->request('DELETE', '/convai/agents/' . $elAgentId);
        } catch (\RuntimeException $e) {
            // Si el agente ya no existe (404), ignorar el error
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Verifica que la API key sea válida haciendo una petición simple a agentes de voz.
     */
    public function verifyApiKey(): bool {
        try {
            $this->request('GET', '/convai/agents');
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Obtiene la lista de agentes desde ElevenLabs para verificar conectividad y credenciales.
     */
    public function getProfile(): array {
        return $this->request('GET', '/convai/agents');
    }

    /**
     * Sube un documento de texto a la Knowledge Base de ElevenLabs.
     * Retorna el document_id asignado por ElevenLabs.
     */
    public function uploadKnowledgeDoc(string $name, string $textContent): string {
        $result = $this->requestJson('POST', '/convai/knowledge-base/text', [
            'name' => $name,
            'text' => $textContent,
        ]);
        if (empty($result['id'])) {
            throw new \RuntimeException('ElevenLabs no devolvió ID del documento. Respuesta: ' . json_encode($result));
        }
        return $result['id'];
    }

    /**
     * Elimina un documento de la Knowledge Base de ElevenLabs.
     */
    public function deleteKnowledgeDoc(string $docId): void {
        try {
            $this->request('DELETE', '/convai/knowledge-base/' . $docId);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                return; // ya fue eliminado
            }
            throw $e;
        }
    }

    /**
     * Actualiza la lista de documentos de Knowledge Base vinculados al agente.
     * $docIds es un array de strings con los IDs de documentos de ElevenLabs.
     */
    public function updateAgentKnowledgeBase(string $elAgentId, array $docIds): void {
        $knowledgeBase = array_map(fn($id) => ['id' => $id, 'type' => 'file'], $docIds);
        $body = [
            'conversation_config' => [
                'agent' => [
                    'prompt' => [
                        'knowledge_base' => $knowledgeBase,
                    ],
                ],
            ],
        ];
        $this->request('PATCH', '/convai/agents/' . $elAgentId, $body);
    }

    /**
     * Obtiene los IDs de documentos de Knowledge Base actualmente vinculados al agente.
     */
    public function getAgentKnowledgeDocs(string $elAgentId): array {
        $result = $this->request('GET', '/convai/agents/' . $elAgentId);
        $kb = $result['conversation_config']['agent']['prompt']['knowledge_base'] ?? [];
        return array_column($kb, 'id');
    }

    /**
     * Petición HTTP interna que siempre devuelve array (para POST con body JSON).
     */
    private function requestJson(string $method, string $path, array $body): array {
        return $this->request($method, $path, $body);
    }

    /**
     * Sube un archivo físico a la Knowledge Base de ElevenLabs.
     * Retorna el document_id asignado.
     */
    public function uploadKnowledgeFile(string $filePath, string $originalName, string $mimeType): string {
        $url = self::BASE_URL . '/convai/knowledge-base/file';
        $ch = curl_init($url);
        
        $cFile = curl_file_create($filePath, $mimeType, $originalName);
        
        $headers = [
            'xi-api-key: ' . $this->apiKey,
        ];
        
        $postFields = [
            'file' => $cFile,
            'name' => $originalName
        ];

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $isLocal = (strpos(__DIR__, 'xampp') !== false) || 
                   (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'], true));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('cURL error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            $data = json_decode($response, true);
            $msg = $data['detail']['message'] ?? $data['message'] ?? $data['error'] ?? $response ?: 'Error desconocido';
            throw new \RuntimeException('ElevenLabs API error (' . $httpCode . '): ' . $msg);
        }

        $result = json_decode($response, true);
        if (empty($result['id'])) {
            throw new \RuntimeException('ElevenLabs no devolvió ID del documento. Respuesta: ' . json_encode($result));
        }
        return $result['id'];
    }

    /**
     * Factoria: crea la instancia usando la API key guardada en la BD.
     * Lanza RuntimeException si la API key no está configurada.
     */
    public static function fromDatabase(\PDO $pdo): self {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'elevenlabs_api_key' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $apiKey = $row['setting_value'] ?? '';
        if (empty($apiKey)) {
            throw new \RuntimeException('ElevenLabs API key no configurada. Ve a Configuración ? ElevenLabs para agregarla.');
        }
        return new self($apiKey);
    }
}
