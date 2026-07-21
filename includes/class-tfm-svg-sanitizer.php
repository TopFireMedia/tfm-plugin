<?php
/**
 * TFM SVG Sanitizer
 * Strips active/script content from SVG markup before an upload is stored, so an
 * uploaded SVG cannot execute JavaScript when rendered or opened in a browser.
 *
 * Defense-in-depth: SVG uploads are also gated to users with `unfiltered_html`.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TFM_SVG_Sanitizer {

    /** SVG/related elements that are allowed to remain. Anything else is removed. */
    private static $allowed_tags = [
        'svg', 'g', 'title', 'desc', 'metadata', 'defs', 'style',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath', 'tref',
        'use', 'symbol', 'marker', 'image',
        'linebreak', 'clippath', 'mask', 'pattern',
        'lineargradient', 'radialgradient', 'stop', 'switch', 'view',
        'filter', 'fecolormatrix', 'fecomposite', 'fegaussianblur', 'feblend',
        'feflood', 'feoffset', 'femerge', 'femergenode', 'femorphology',
        'fedropshadow', 'fetile', 'feturbulence', 'fedisplacementmap',
        'feimage', 'fecomponenttransfer', 'fefunca', 'fefuncr', 'fefuncg', 'fefuncb',
        'a',
    ];

    /**
     * Return sanitized SVG markup, or false if the content can't be parsed as SVG.
     *
     * @param string $svg Raw SVG file contents.
     * @return string|false
     */
    public static function sanitize($svg) {
        if (!is_string($svg) || trim($svg) === '') {
            return false;
        }

        // Remove PHP tags and any DOCTYPE (blocks entity/XXE definitions) up front.
        $svg = preg_replace('/<\?(php|=).*?\?>/is', '', $svg);
        $svg = preg_replace('/<!DOCTYPE.*?>/is', '', $svg);
        $svg = preg_replace('/<!ENTITY.*?>/is', '', $svg);

        if (stripos($svg, '<svg') === false) {
            return false;
        }

        $prev_errors = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->strictErrorChecking = false;

        // LIBXML_NONET blocks network access; not passing LIBXML_NOENT prevents
        // entity substitution. Combined with DOCTYPE/ENTITY stripping above this
        // neutralizes XXE and entity-expansion attacks.
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($prev_errors);

        if (!$loaded || !$dom->documentElement) {
            return false;
        }

        if (strtolower($dom->documentElement->nodeName) !== 'svg') {
            return false;
        }

        // Clean the root <svg>'s own attributes (e.g. onload=), then recurse.
        self::clean_attributes($dom->documentElement);
        self::clean_node($dom->documentElement);

        $output = $dom->saveXML($dom->documentElement, LIBXML_NOEMPTYTAG);
        return $output !== false ? $output : false;
    }

    /** Recursively remove disallowed elements and dangerous attributes. */
    private static function clean_node(DOMElement $node) {
        // Walk children first, on a static copy (we mutate the live list).
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->localName);
                if (!in_array($tag, self::$allowed_tags, true)) {
                    $node->removeChild($child);
                    continue;
                }
                self::clean_attributes($child);
                self::clean_node($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE
                   || $child->nodeType === XML_PI_NODE
                   || $child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                // Strip comments, processing instructions, and doctypes.
                $node->removeChild($child);
            }
        }
    }

    /** Remove event handlers, script URIs, and unsafe references from an element. */
    private static function clean_attributes(DOMElement $node) {
        $attributes = [];
        foreach ($node->attributes as $attr) {
            $attributes[] = $attr;
        }

        foreach ($attributes as $attr) {
            $name  = strtolower($attr->nodeName);
            $local = strtolower($attr->localName);
            $value = $attr->nodeValue;

            // Any on* handler (onload, onclick, …).
            if (strpos($name, 'on') === 0) {
                $node->removeAttributeNode($attr);
                continue;
            }

            // href / xlink:href / src: block script and non-image data URIs and
            // external-resource loads.
            if (in_array($local, ['href', 'src'], true)) {
                $clean = strtolower(preg_replace('/[\s\x00-\x20]+/', '', (string) $value));
                $is_data_image = strpos($clean, 'data:image/') === 0;
                $is_local_ref  = strpos($clean, '#') === 0;
                $is_relative   = ($clean !== '' && !preg_match('#^[a-z][a-z0-9+.-]*:#', $clean));
                if (!$is_data_image && !$is_local_ref && !$is_relative) {
                    $node->removeAttributeNode($attr);
                    continue;
                }
                if (strpos($clean, 'javascript:') !== false || strpos($clean, 'data:text') !== false) {
                    $node->removeAttributeNode($attr);
                    continue;
                }
            }

            // Any attribute value smuggling a javascript: URI.
            if (stripos((string) $value, 'javascript:') !== false) {
                $node->removeAttributeNode($attr);
                continue;
            }
        }
    }
}
