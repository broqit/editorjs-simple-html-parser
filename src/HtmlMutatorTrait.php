<?php

namespace Durlecode\EJSParser;

trait HtmlMutatorTrait
{
    protected static function htmlMutator(&$state): void
    {
        $state = str_replace('</img>', '', $state);
        
        // Встановлюємо правильне кодування UTF-8
        $html = mb_convert_encoding($state, 'HTML-ENTITIES', 'UTF-8');
        
        // Завантажуємо HTML у DOMDocument з правильним кодуванням
        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        
        // Мутація заголовків
        self::mutateHeadings($dom);
            
        // Мутація параграфів
        self::mutateParagraphs($dom);
            
        // Мутація таблиць
        self::mutateTable($dom);
            
        // Мутація списків
        self::mutateLists($dom);
            
        // Мутація iframe
        self::mutateIframes($dom);
            
        // Мутація зображень
        self::mutateImages($dom);
            
        // Мутація спойлерів
        //self::mutateDetails($dom);
        
        // Мутація цитат
        //self::mutateBlockquotes($dom);
        
        // Отримуємо вміст без <doctype>, <html>, <head> та <body>
        $bodyContent = '';
        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) {
            $bodyContent .= $dom->saveHTML($child);
        }
        
        dd($bodyContent);
        // Повертаємо лише змінений вміст
        $state = $bodyContent;
    }
    
    private static function mutateTable($dom)
    {
        // Отримуємо tbody
        $tbody = $dom->getElementsByTagName('tbody')->item(0);
        if (!$tbody) {
            return; // Якщо tbody не існує, просто виходимо з функції
        }
    
        // Отримуємо перший рядок з tbody
        $firstRow = $tbody->getElementsByTagName('tr')->item(0);
        if (!$firstRow) {
            return; // Якщо немає рядків, просто виходимо з функції
        }
    
        // Перевіряємо, чи перший рядок має елементи <th>
        $hasTh = $firstRow->getElementsByTagName('th')->length > 0;
    
        // Якщо є <th> у першому рядку і немає <thead>, створюємо thead
        if ($hasTh && $dom->getElementsByTagName('thead')->length == 0) {
            $thead = $dom->createElement('thead');
            $tbody->parentNode->insertBefore($thead, $tbody);
            $thead->appendChild($firstRow);
        }
        
        // Отримуємо елемент <table> та додаємо клас 'prs-table'
        $table = $dom->getElementsByTagName('table')->item(0);
        if ($table) {
            $existingClass = $table->getAttribute('class');
            $table->setAttribute('class', trim($existingClass . ' prs-table'));
        }
    }
    
    private static function mutateParagraphs($dom)
    {
        // Отримуємо всі елементи <p>
        $paragraphs = $dom->getElementsByTagName('p');
        
        // Додаємо клас до кожного параграфа
        foreach ($paragraphs as $paragraph) {
            $existingClass = $paragraph->getAttribute('class');
            $paragraph->setAttribute('class', trim($existingClass . ' prs-paragraph'));
        }
    }
    
    private static function mutateHeadings($dom)
    {
        // Список заголовків для модифікації
        $headings = ['h2', 'h3', 'h4', 'h5', 'h6'];
    
        // Додаємо клас до кожного заголовка
        foreach ($headings as $heading) {
            $elements = $dom->getElementsByTagName($heading);
            foreach ($elements as $element) {
                $existingClass = $element->getAttribute('class');
                $element->setAttribute('class', trim($existingClass . ' prs-header'));
            }
        }
    }
    
    private static function mutateLists($dom)
    {
        // Список типів списків для модифікації
        $listTags = ['ul', 'ol'];
    
        // Додаємо клас до кожного списку
        foreach ($listTags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            foreach ($elements as $element) {
                $existingClass = $element->getAttribute('class');
                $element->setAttribute('class', trim($existingClass . ' prs-nested-list'));
            }
        }
    }
    
    private static function mutateIframes($dom)
    {
        // Отримуємо всі елементи <iframe>
        $iframes = $dom->getElementsByTagName('iframe');
    
        // Створюємо масив, щоб зберегти елементи для подальшої обробки
        $toProcess = [];
    
        // Перебираємо всі <iframe>
        foreach ($iframes as $iframe) {
            $toProcess[] = $iframe;
        }
    
        foreach ($toProcess as $iframe) {
            // Витягуємо батьківський елемент iframe
            $parentNode = $iframe->parentNode;
    
            // Витягуємо атрибути iframe
            $title = $iframe->getAttribute('title');
            $src = $iframe->getAttribute('src');
            $alt = $iframe->getAttribute('alt');
    
            // Визначаємо сервіс на основі URL
            $htmlParser = new HtmlParser($iframe->ownerDocument->saveHTML($iframe));
            $service = $htmlParser->getServiceNameFromUrl($src);
    
            // Генеруємо figcaption, якщо це необхідно
            $figcaption = null;
            if ($parentNode->nodeName === 'figure') {
                $figcaption = $parentNode->getElementsByTagName('figcaption')->item(0);
            }
    
            if (!$figcaption && $title) {
                $figcaption = $dom->createElement('figcaption', $title);
            } elseif (!$figcaption && $alt) {
                $figcaption = $dom->createElement('figcaption', $alt);
            }
    
            // Створюємо новий елемент figure
            $figure = $dom->createElement('figure');
            $figure->setAttribute('class', "prs-embed prs_$service");
    
            // Переміщуємо iframe у figure
            $figure->appendChild($iframe);
    
            // Якщо є figcaption, додаємо його до figure
            if ($figcaption) {
                $figure->appendChild($figcaption);
            }
    
            // Замість батьківського елемента вставляємо figure
            if (in_array($parentNode->nodeName, ['p', 'div', 'figure'])) {
                $parentNode->parentNode->replaceChild($figure, $parentNode);
            } else {
                $parentNode->replaceChild($figure, $iframe);
            }
        }
    }
    
    private static function mutateImages($dom)
    {
        // Отримуємо всі елементи <img>
        $images = $dom->getElementsByTagName('img');
    
        // Створюємо масив, щоб зберегти елементи для подальшої обробки
        $toProcess = [];
    
        // Перебираємо всі <img>
        foreach ($images as $img) {
            $toProcess[] = $img;
        }
    
        foreach ($toProcess as $img) {
            // Витягуємо атрибути title і alt з тега <img>
            $title = $img->getAttribute('title');
            $alt = $img->getAttribute('alt');
    
            // Визначаємо батьківський елемент
            $parentNode = $img->parentNode;
    
            // Генеруємо figcaption, якщо це необхідно
            $figcaption = null;
            if ($parentNode->nodeName === 'figure') {
                $figcaptionNode = $parentNode->getElementsByTagName('figcaption')->item(0);
                $figcaption = $figcaptionNode ? $figcaptionNode->textContent : null;
            }
    
            if (!$figcaption && $title) {
                $figcaption = $dom->createElement('figcaption', $title);
            } elseif (!$figcaption && $alt) {
                $figcaption = $dom->createElement('figcaption', $alt);
            }
    
            // Створюємо новий елемент figure
            $figure = $dom->createElement('figure');
            $figure->setAttribute('class', "prs-image prs_stretched");
    
            // Переміщуємо img у figure
            $figure->appendChild($img);
    
            // Якщо є figcaption, додаємо його до figure
            if ($figcaption) {
                $figure->appendChild($figcaption);
            }
    
            // Замість батьківського елемента вставляємо figure
            if (in_array($parentNode->nodeName, ['p', 'div', 'figure'])) {
                $parentNode->parentNode->replaceChild($figure, $parentNode);
            } else {
                $parentNode->replaceChild($figure, $img);
            }
        }
    }
    
    private static function mutateDetails($dom)
    {
        // Отримуємо всі елементи <details>
        $detailsElements = $dom->getElementsByTagName('details');
    
        // Створюємо масив, щоб зберегти елементи для подальшої обробки
        $toProcess = [];
    
        // Перебираємо всі <details>
        foreach ($detailsElements as $details) {
            // Перевіряємо, чи містить <details> тег <summary>
            $summary = $details->getElementsByTagName('summary')->item(0);
    
            // Перевіряємо, чи містить <details> тег <div data-type="details-content">
            $detailsContent = null;
            $divs = $details->getElementsByTagName('div');
            foreach ($divs as $div) {
                if ($div->getAttribute('data-type') === 'details-content') {
                    $detailsContent = $div;
                    break;
                }
            }
    
            // Якщо <details> містить обидва необхідні елементи, додаємо його до обробки
            if ($summary && $detailsContent) {
                $toProcess[] = $details;
            }
        }
    
        foreach ($toProcess as $details) {
            // Додаємо клас "prs-spoiler" до <details>
            $existingClass = $details->getAttribute('class');
            $details->setAttribute('class', trim($existingClass . ' prs-spoiler'));
    
            // Додаємо клас до <div data-type="details-content">
            $detailsContent = $details->getElementsByTagName('div')->item(0);
            $existingDivClass = $detailsContent->getAttribute('class');
            $detailsContent->setAttribute('class', trim($existingDivClass . ' prs-details-content'));
        }
    }
    
    private static function mutateBlockquotes($dom)
    {
        // Отримуємо всі елементи <blockquote>
        $blockquotes = $dom->getElementsByTagName('blockquote');
    
        // Створюємо масив, щоб зберегти елементи для подальшої обробки
        $toProcess = [];
    
        // Перебираємо всі <blockquote>
        foreach ($blockquotes as $blockquote) {
            $toProcess[] = $blockquote;
        }
    
        foreach ($toProcess as $blockquote) {
            // Додаємо клас "prs-quote" до blockquote
            $existingClass = $blockquote->getAttribute('class');
            $blockquote->setAttribute('class', trim($existingClass . ' prs-quote'));
    
            // Проходимо по всіх дочірніх елементах всередині <blockquote>
            foreach ($blockquote->childNodes as $childNode) {
                if ($childNode->nodeType === XML_ELEMENT_NODE) {
                    // Додаємо клас "prs-quote-content" до кожного дочірнього елемента
                    $existingChildClass = $childNode->getAttribute('class');
                    $childNode->setAttribute('class', trim($existingChildClass . ' prs-quote-content'));
                }
            }
        }
    }
}
