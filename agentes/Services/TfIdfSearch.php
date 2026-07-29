<?php
class AgentTfIdfSearch
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function search(string $agentId, string $query, int $topK = 2): array
    {
        $terms = preg_split('/\s+/', mb_strtolower(trim($query)));
        $terms = array_filter($terms, fn($t) => mb_strlen($t) > 2);
        if (empty($terms)) return [];

        $stmt = $this->db->prepare(
            "SELECT id, agent_id, content, chunk_index, file_id FROM knowledge_chunks WHERE agent_id = ?"
        );
        $stmt->execute([$agentId]);
        $chunks = $stmt->fetchAll();

        if (empty($chunks)) return [];

        $scored = [];
        foreach ($chunks as $chunk) {
            $content = mb_strtolower($chunk['content']);
            $score = 0;
            foreach ($terms as $term) {
                $count = mb_substr_count($content, $term);
                if ($count > 0) {
                    $tf = $count / max(1, str_word_count($content));
                    $idf = log(1 + (count($chunks) / max(1, 1)));
                    $score += $tf * $idf * $count;
                }
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'chunk' => $chunk];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice(array_map(fn($s) => $s['chunk'], $scored), 0, $topK);
    }
}
