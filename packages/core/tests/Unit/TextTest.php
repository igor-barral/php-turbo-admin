<?php

declare(strict_types=1);

use PhpTurbo\Admin\Core\Support\Text;

it('extracts class basenames', function (string $class, string $expected): void {
    expect(Text::classBasename($class))->toBe($expected);
})->with([
    ['App\\Models\\BlogPost', 'BlogPost'],
    ['Post', 'Post'],
]);

it('converts to snake case', function (string $value, string $expected): void {
    expect(Text::snake($value))->toBe($expected);
})->with([
    ['BlogPost', 'blog_post'],
    ['createdAt', 'created_at'],
    ['HTMLParser', 'html_parser'],
    ['author_id', 'author_id'],
    ['two words', 'two_words'],
    ['kebab-case', 'kebab_case'],
]);

it('humanizes identifiers', function (string $value, string $expected): void {
    expect(Text::humanize($value))->toBe($expected);
})->with([
    ['author_id', 'Author id'],
    ['BlogPost', 'Blog post'],
    ['title', 'Title'],
]);
