<?php
/*
 * My Book Library
 *
 * Copyright (C) 2014-2021 Yurii K.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses
 */

namespace App\Actions;

use App\CoverExtractor;
use App\Models\Book;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ConfigDoImportNewCoverFromPdfAction extends AbstractApiAction
{
    /**
     * @var CoverExtractor
     */
    protected $extractor;

    public function __construct(ContainerInterface $container)
    {
        $this->extractor = $container->get(CoverExtractor::class);
        assert($this->extractor instanceof CoverExtractor);
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bookIds = Collection::make(Arr::get($request->getParsedBody(), 'post'))->pluck('book_guid');
        $addedBooks = [];
        foreach ($bookIds as $bookId) {
            try {
                /** @var Book $book */
                $book = Book::query()->findOrFail($bookId);
                $cover = $this->extractor->extract($book->getFilepath());
                $book->book_cover = $cover;
                $book->saveOrFail();
                $addedBooks[] = $book->filename;
            } catch (\Throwable $t) {
                $message = ['data' => $addedBooks, 'result' => false, 'error' => $t->getMessage()];
                $response->getBody()->write(json_encode($message, JSON_UNESCAPED_UNICODE));
                return $response;
            }
        }

        return $this->asJSON($response, ['data' => $addedBooks, 'result' => true, 'error' => null]);
    }

}