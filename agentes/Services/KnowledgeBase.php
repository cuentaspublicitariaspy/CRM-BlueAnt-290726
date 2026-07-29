<?php
class AgentKnowledgeBase
{
    private PDO $db;
    private string $storagePath;

    private const ALLOWED_EXTENSIONS = ['txt', 'md', 'pdf'];
    private const MAX_UPLOAD_SIZE_TXT = 1048576;
    private const MAX_UPLOAD_SIZE_PDF = 5242880;
    private const CHUNK_MIN_WORDS = 300;
    private const CHUNK_MAX_WORDS = 700;
    private const CHUNK_OVERLAP = 50;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->storagePath = __DIR__ . '/../../storage/knowledge/';
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }

    public function upload(string $agentId, array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir el archivo', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Tipo de archivo no permitido. Solo: ' . implode(', ', self::ALLOWED_EXTENSIONS), 400);
        }

        $maxSize = $ext === 'pdf' ? self::MAX_UPLOAD_SIZE_PDF : self::MAX_UPLOAD_SIZE_TXT;
        if ($file['size'] > $maxSize) {
            throw new RuntimeException('Archivo demasiado grande', 400);
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new RuntimeException('Error al leer el archivo', 500);
        }

        if ($ext === 'pdf') {
            $content = $this->extractPdfText($file['tmp_name']);
        }

        $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = $this->storagePath . $storedFilename;

        if (copy($file['tmp_name'], $destPath)) {
            $stmt = $this->db->prepare(
                "INSERT INTO knowledge_files (agent_id, original_filename, stored_filename, mime_type, filesize, file_hash)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $agentId,
                $file['name'],
                $storedFilename,
                $file['type'],
                $file['size'],
                hash_file('sha256', $destPath),
            ]);
            $fileId = (int)$this->db->lastInsertId();

            $chunks = $this->chunkText($content);
            $stmtChunk = $this->db->prepare(
                "INSERT INTO knowledge_chunks (agent_id, file_id, chunk_index, content) VALUES (?, ?, ?, ?)"
            );
            foreach ($chunks as $i => $chunk) {
                $stmtChunk->execute([$agentId, $fileId, $i, $chunk]);
            }

            return ['id' => $fileId, 'name' => $file['name'], 'chunks' => count($chunks)];
        }

        throw new RuntimeException('Error al guardar el archivo', 500);
    }

    public function listFiles(string $agentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, original_filename, mime_type, filesize, elevenlabs_doc_id, created_at FROM knowledge_files WHERE agent_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$agentId]);
        return $stmt->fetchAll();
    }

    public function delete(int $fileId, string $agentId): void
    {
        $stmt = $this->db->prepare("SELECT stored_filename FROM knowledge_files WHERE id = ? AND agent_id = ?");
        $stmt->execute([$fileId, $agentId]);
        $file = $stmt->fetch();

        if (!$file) {
            throw new RuntimeException('Archivo no encontrado', 404);
        }

        $this->db->prepare("DELETE FROM knowledge_chunks WHERE file_id = ?")->execute([$fileId]);
        $this->db->prepare("DELETE FROM knowledge_files WHERE id = ?")->execute([$fileId]);

        $path = $this->storagePath . $file['stored_filename'];
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function extractPdfText(string $path): string
    {
        $data = @file_get_contents($path);
        if (!$data) return '';

        if (strpos($data, '%PDF-') === false) return '';

        $text = '';
        // Buscar streams en el PDF
        if (preg_match_all('/stream(.*?)endstream/is', $data, $matches)) {
            foreach ($matches[1] as $stream) {
                $stream = trim($stream);
                // Intentar descomprimir
                $decompressed = @gzuncompress($stream);
                if ($decompressed === false) {
                    $decompressed = @gzinflate(substr($stream, 2));
                }

                if ($decompressed !== false) {
                    // Buscar los operadores de texto en PDF: Tj, TJ
                    if (preg_match_all('/\[(.*?)\]\s*TJ/is', $decompressed, $tjMatches)) {
                        foreach ($tjMatches[1] as $match) {
                            if (preg_match_all('/\((.*?)\)/s', $match, $txts)) {
                                $text .= implode('', $txts[1]) . ' ';
                            }
                        }
                    }
                    if (preg_match_all('/\((.*?)\)\s*Tj/is', $decompressed, $tjMatches)) {
                        $text .= implode(' ', $tjMatches[1]) . ' ';
                    }
                }
            }
        }

        // Limpiar secuencias de escape del PDF
        $text = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'], ['(', ')', '\\', "\n", "\r", "\t"], $text);
        
        // Quitar caracteres de control y normalizar espacios
        $text = preg_replace('/[^\PC\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text) ?: '(No se pudo extraer texto del PDF)';
    }

    private function chunkText(string $text): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        $words = explode(' ', $text);
        $total = count($words);
        $chunks = [];
        $pos = 0;

        while ($pos < $total) {
            $end = min($pos + self::CHUNK_MAX_WORDS, $total);
            $chunkWords = array_slice($words, $pos, $end - $pos);
            $chunks[] = implode(' ', $chunkWords);
            $pos += self::CHUNK_MAX_WORDS - self::CHUNK_OVERLAP;
            if ($pos >= $total) break;
        }

        return $chunks ?: [$text];
    }
}
