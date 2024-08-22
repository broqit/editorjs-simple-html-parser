<?php

namespace Durlecode\EJSParser;

trait HtmlMutatorTrait
{
    protected function htmlMutator(&$state): void
    {
        $state = str_replace('</img>', '', $state);
        $state = str_replace('<li><p>', '<li>', $state);
        $state = str_replace('</p><li>', '</li>', $state);
        
        // Встановлюємо правильне кодування UTF-8
        $html = mb_convert_encoding($state, 'HTML-ENTITIES', 'UTF-8');
        
        // Завантажуємо HTML у DOMDocument з правильним кодуванням
        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        
        // Мутація заголовків
        $this->mutateHeadings($dom);
        
        // Мутація спойлерів
        // $this->mutateDetails($dom);
        
        // Мутація цитат
        $this->mutateBlockquotes($dom);
            
        // Мутація параграфів
        $this->mutateParagraphs($dom);
            
        // Мутація таблиць
        $this->mutateTable($dom);
            
        // Мутація списків
        $this->mutateLists($dom);
            
        // Мутація iframe
        $this->mutateIframes($dom);
            
        // Мутація зображень
        $this->mutateImages($dom);
        
        // Отримуємо вміст без <doctype>, <html>, <head> та <body>
        $bodyContent = '';
        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) {
            $bodyContent .= $dom->saveHTML($child);
        }
        
        // dd($bodyContent);
        // Повертаємо лише змінений вміст
        $state = $bodyContent;
    }
    
    private function mutateTable($dom)
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
    
    private function mutateParagraphs($dom)
    {
        // Отримуємо всі елементи <p>
        $paragraphs = $dom->getElementsByTagName('p');
        
        // Додаємо клас до кожного параграфа
        foreach ($paragraphs as $paragraph) {
            $existingClass = $paragraph->getAttribute('class');
            $paragraph->setAttribute('class', trim($existingClass . ' prs-paragraph'));
        }
    }
    
    private function mutateHeadings($dom)
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
    
    private function mutateLists($dom)
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
    
    private function mutateIframes($dom)
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
            $service = $this->getServiceNameFromUrl($src);
    
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
    
    private function mutateImages($dom)
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
    
    private function mutateDetails($dom)
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
    
    private function mutateBlockquotes($dom)
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
            // Перевіряємо, чи батьківським елементом є <figure>
            $parentNode = $blockquote->parentNode;
            $isInFigure = ($parentNode && $parentNode->nodeName === 'figure');
    
            // Обробка параграфів усередині <blockquote>
            $paragraphs = $blockquote->getElementsByTagName('p');
            if ($paragraphs->length > 0) {
                $combinedText = '';
    
                // Об'єднуємо текст із усіх параграфів, додаючи перенос рядка
                foreach ($paragraphs as $paragraph) {
                    $combinedText .= $paragraph->textContent . "\n";
                }
    
                // Очищаємо вміст <blockquote>
                while ($blockquote->hasChildNodes()) {
                    $blockquote->removeChild($blockquote->firstChild);
                }
    
                // Додаємо об'єднаний текст як єдиний текстовий вузол до <blockquote>
                $blockquote->appendChild($dom->createTextNode($combinedText));
            }
    
            if (!$isInFigure) {
                // Якщо <blockquote> не знаходиться в <figure>, обгортаємо його в <figure>
                $figure = $dom->createElement('figure');
                $figure->setAttribute('class', 'prs-quote');
    
                // Переміщуємо <blockquote> всередину <figure>
                $parentNode->replaceChild($figure, $blockquote);
                $figure->appendChild($blockquote);
    
            } else {
                // Якщо <blockquote> вже в <figure>, додаємо клас "prs-quote" до <figure>
                $figureClass = $parentNode->getAttribute('class');
                $parentNode->setAttribute('class', trim($figureClass . ' prs-quote'));
            }
    
            // Перевіряємо, чи є всередині <figure> тег <figcaption>
            if ($isInFigure) {
                $figcaption = $parentNode->getElementsByTagName('figcaption')->item(0);
                if ($figcaption) {
                    // Якщо <figcaption> є, нічого не робимо
                    continue;
                }
            }
        }
    }
}
