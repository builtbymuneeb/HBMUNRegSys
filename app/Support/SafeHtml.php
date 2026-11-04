<?php

namespace App\Support;

use App\Actions\Inbox\FetchInbox;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans HTML written in the inbox compose window down to simple formatting that is safe to email:
 * paragraphs, line breaks, bold, italic, underline, strike-through, lists, quotes and http/mailto links.
 * Every attribute except a link's address is dropped, and anything else is unwrapped or removed.
 */
class SafeHtml
{
    /**
     * Tags kept as they are (without attributes).
     */
    private const ALLOWED = ['p', 'div', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'ul', 'ol', 'li', 'a', 'blockquote', 'h1', 'h2', 'h3'];

    /**
     * Tags removed along with everything inside them.
     */
    private const DROPPED = ['script', 'style', 'head', 'title', 'meta', 'link', 'iframe', 'frame', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'noscript', 'img', 'video', 'audio', 'canvas'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?><div id="compose-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $root = $document->getElementById('compose-root');

        if (! $root) {
            return '';
        }

        self::cleanChildren($root);

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        // One line, so the email's Markdown layout treats it as a single block of HTML.
        $clean = trim(preg_replace('/\s*\R\s*/', ' ', $clean));

        return self::toText($clean) === '' ? '' : $clean;
    }

    /**
     * The same message as plain text, for the text part of the email and the inbox record.
     */
    public static function toText(string $html): string
    {
        $html = preg_replace('/<(div|p|ul|ol|blockquote|h\d)\b/i', "\n<$1", $html);
        $html = preg_replace('/<li\b[^>]*>/i', '<li>• ', $html);
        $html = preg_replace('/<a\b[^>]*href="(https?:[^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);

        $text = FetchInbox::htmlToText($html);

        // A link whose text is its own address doesn't need the address twice.
        return preg_replace('/(\S+) \(\1\)/', '$1', $text);
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::cleanElement($child);
            } elseif ($child->nodeType !== XML_TEXT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanElement(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);

        if (in_array($tag, self::DROPPED, true)) {
            $element->parentNode->removeChild($element);

            return;
        }

        self::cleanChildren($element);

        $href = $tag === 'a' ? trim($element->getAttribute('href')) : null;
        $keep = in_array($tag, self::ALLOWED, true) && ($tag !== 'a' || preg_match('/^(https?:\/\/|mailto:)/i', (string) $href));

        if (! $keep) {
            while ($element->firstChild) {
                $element->parentNode->insertBefore($element->firstChild, $element);
            }
            $element->parentNode->removeChild($element);

            return;
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if ($tag === 'a') {
            $element->setAttribute('href', $href);
        }
    }
}
