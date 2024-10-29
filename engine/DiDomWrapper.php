<?php

// https://github.com/Imangazaliev/DiDOM/blob/HEAD/README-RU.md#%D0%A0%D0%B0%D0%B1%D0%BE%D1%82%D0%B0-%D1%81-%D0%B0%D1%82%D1%80%D0%B8%D0%B1%D1%83%D1%82%D0%B0%D0%BC%D0%B8-%D1%8D%D0%BB%D0%B5%D0%BC%D0%B5%D0%BD%D1%82%D0%B0


namespace ATFinder;

use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use JetBrains\PhpStorm\NoReturn;

class DiDomWrapper
{
    private Document $document;

    public function __construct(string $content)
    {
        $this->document = new Document();
        $this->document->loadHtml($content);
    }

    /**
     * @param string $find_pattern
     * @param string $attr - src
     * @param int $index
     * @return Element|\DOMElement|string|null
     * @throws InvalidSelectorException
     */
    public function attr(string $find_pattern = '', string $attr = '', int $index = 0): Element|string|\DOMElement|null
    {
        return $this->document->find($find_pattern)[$index]->attr($attr);
    }

    /**
     * @param string $find_pattern
     * @param string $field - textContent or nodeValue
     * @param int $index
     * @return mixed
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    public function node(string $find_pattern = '', string $field = 'textContent', int $index = 0): mixed
    {
        return $this->document->find($find_pattern)[$index]->getNode()->{$field};
    }

    #[NoReturn]
    public function dnode($find_pattern = '', $field = 'textContent', $index = 0)
    {
        dd(
            $this->document->find($find_pattern)
        );
    }

    public function find($pattern): array
    {
        return $this->document->find($pattern);
    }

}