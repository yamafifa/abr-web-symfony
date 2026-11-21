<?php

namespace App\Elasticsearch;

/** Elasticsearch に接続できない・エラーが返ったとき。notFound は 404（インデックスが無いなど） */
final class EsException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $notFound = false)
    {
        parent::__construct($message);
    }
}
