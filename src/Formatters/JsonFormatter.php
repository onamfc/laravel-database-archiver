<?php

namespace LaravelDbArchiver\Formatters;

class JsonFormatter
{
    /**
     * Format data as JSON.
     */
    public function format(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}