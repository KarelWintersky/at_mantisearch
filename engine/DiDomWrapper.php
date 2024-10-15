<?php

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

}