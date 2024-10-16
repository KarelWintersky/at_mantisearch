<?php

// https://github.com/Imangazaliev/DiDOM/blob/HEAD/README-RU.md#%D0%A0%D0%B0%D0%B1%D0%BE%D1%82%D0%B0-%D1%81-%D0%B0%D1%82%D1%80%D0%B8%D0%B1%D1%83%D1%82%D0%B0%D0%BC%D0%B8-%D1%8D%D0%BB%D0%B5%D0%BC%D0%B5%D0%BD%D1%82%D0%B0


namespace ATFinder;

use DiDom\Document;

class DiDomWrapper
{
    private Document $document;

    public function __construct(string $content)
    {
        $this->document = new Document();
        $this->document->loadHtml($content);
    }

    /**
     * @param $find_pattern
     * @param $attr - src
     * @return \DiDom\Element|\DOMElement|string|null
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    public function attr($find_pattern = '', $attr = '', $index = 0)
    {
        return $this->document->find($find_pattern)[$index]->attr($attr);
    }

    /**
     * @param $find_pattern
     * @param $field - textContent or nodeValue
     * @param $index
     * @return mixed
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    public function node($find_pattern = '', $field = 'textContent', $index = 0)
    {
        return $this->document->find($find_pattern)[$index]->getNode()->{$field};
    }

    public function dnode($find_pattern = '', $field = 'textContent', $index = 0)
    {
        dd(
            $this->document->find($find_pattern)
        );
    }

    public function find($pattern)
    {
        return $this->document->find($pattern);
    }

}