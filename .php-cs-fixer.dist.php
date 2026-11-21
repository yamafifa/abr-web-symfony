<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        // 説明の最後に「.」を足す決まり。日本語の文には合わないので使わない
        'phpdoc_summary' => false,
    ])
    ->setFinder($finder)
;
