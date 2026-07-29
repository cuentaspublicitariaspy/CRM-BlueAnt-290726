<?php
class AgentKnowledgeBaseHelper
{
    public static function getFormattedKnowledgeBase(PDO $pdo, string $agentId): string
    {
        try {
            $st = $pdo->prepare("SELECT content FROM knowledge_chunks WHERE agent_id = ? ORDER BY file_id, chunk_index LIMIT 200");
            $st->execute([$agentId]);
            $rows = $st->fetchAll(PDO::FETCH_COLUMN);
            if (empty($rows)) return '';

            $parts = [];
            foreach ($rows as $i => $content) {
                $parts[] = "[Fuente " . ($i + 1) . "]: " . trim($content);
            }
            return "Informacion de la base de conocimiento:\n" . implode("\n---\n", $parts);
        } catch (\Throwable $e) {
            AgentLogger::error("KnowledgeBaseHelper: " . $e->getMessage());
            return '';
        }
    }
}
