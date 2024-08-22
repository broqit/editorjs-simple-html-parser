<?php

namespace Durlecode\EJSParser;

trait HtmlMutatorTrait
{
    protected static function htmlMutator(&$state): void
    {
        $state = str_replace('</img>', '', $state);

        $state = preg_replace_callback(
            '/<(p|figure)[^>]*>(<img[^>]*>(<figcaption[^>]*>.*?<\/figcaption>)?)<\/(p|figure)>/',
            function ($matches) {
                $img = $matches[2];
                $figcaption = isset($matches[3]) ? $matches[3] : '';

                // Extract the title and alt attribute from the img tag.
                preg_match('/title=["\'](.*?)["\']/', $img, $titleMatches);
                $title = $titleMatches[1] ?? '';
                preg_match('/alt=["\'](.*?)["\']/', $img, $altMatches);
                $alt = $altMatches[1] ?? '';

                // If figcaption doesn't exist and title exists, create figcaption from title
                if (empty($figcaption) && !empty($title)) {
                    $figcaption = "<figcaption>{$title}</figcaption>";
                } elseif (empty($figcaption) && !empty($alt)) { // If figcaption and title don't exist, create figcaption from alt
                    $figcaption = "<figcaption>{$alt}</figcaption>";
                }

                // Ensure the figure tag has the necessary classes
                $desiredTag = 'figure class="prs-image prs_stretched"';

                return sprintf(
                    '<%s>%s%s</%s>',
                    $desiredTag,
                    $img,
                    $figcaption,
                    'figure'
                );
            },
            $state
        );

        $state = preg_replace_callback(
            '/<(p|figure)[^>]*>(<iframe[^>]*><\/iframe>(<figcaption[^>]*>.*?<\/figcaption>)?)<\/(p|figure)>/',
            function ($matches) {
                $iframe = $matches[2];
                $figcaption = isset($matches[3]) ? $matches[3] : '';

                // Extract attributes from the iframe tag.
                preg_match('/title=["\'](.*?)["\']/', $iframe, $titleMatches);
                $title = $titleMatches[1] ?? '';

                preg_match('/src=["\'](.*?)["\']/', $iframe, $srcMatches);
                $src = $srcMatches[1] ?? '';

                preg_match('/alt=["\'](.*?)["\']/', $iframe, $altMatches);
                $alt = $altMatches[1] ?? '';

                $htmlParser = new HtmlParser($iframe);
                $service = $htmlParser->getServiceNameFromUrl($src);

                // If figcaption doesn't exist and title exists, create figcaption from title
                if (empty($figcaption) && !empty($title)) {
                    $figcaption = "<figcaption>{$title}</figcaption>";
                } elseif (empty($figcaption) && !empty($alt)) { // If figcaption and title don't exist, create figcaption from alt
                    $figcaption = "<figcaption>{$alt}</figcaption>";
                }

                // Ensure the figure tag has the necessary classes
                $desiredTag = sprintf('figure class="prs-embed prs_%s"', $service);

                return sprintf(
                    '<%s>%s%s</%s>',
                    $desiredTag,
                    $iframe,
                    $figcaption,
                    'figure'
                );
            },
            $state
        );

        self::replaceHtmlTagWithClass($state, '<h2>', '<h2 class="prs-header">');
        self::replaceHtmlTagWithClass($state, '<h3>', '<h3 class="prs-header">');
        self::replaceHtmlTagWithClass($state, '<h4>', '<p class="prs-header">');
        self::replaceHtmlTagWithClass($state, '<h5>', '<p class="prs-header">');
        self::replaceHtmlTagWithClass($state, '<ul>', '<div class="prs-list"><ul');
        self::replaceHtmlTagWithClass($state, '<ol>', '<div class="prs-list"><ol');
        self::replaceHtmlTagWithClass($state, '</ul>', '</ul></div>');
        self::replaceHtmlTagWithClass($state, '</ol>', '</ol></div>');
        self::replaceHtmlTagWithClass($state, '<p>', '<p class="prs-paragraph">');
    }

    private static function replaceHtmlTagWithClass(&$state, string $tag, string $replacement): void
    {
        $state = str_replace($tag, $replacement, $state);
    }
}
