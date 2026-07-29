<?php
class AgentResponseFormatter
{
    public static function apply(string $text): string
    {
        $text = str_replace(['**', '__'], '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
