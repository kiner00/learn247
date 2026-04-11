import DOMPurify from 'dompurify';

/**
 * Sanitize HTML string to prevent XSS attacks.
 * Allows safe formatting tags but strips scripts, event handlers, etc.
 */
export function sanitizeHtml(html) {
    if (!html) return '';
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: [
            'b', 'i', 'em', 'strong', 'u', 'a', 'p', 'br', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div', 'img',
            'blockquote', 'pre', 'code', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'hr', 'sup', 'sub', 'small', 'figure', 'figcaption', 'video', 'source',
        ],
        ALLOWED_ATTR: [
            'href', 'target', 'rel', 'src', 'alt', 'class', 'style',
            'width', 'height', 'colspan', 'rowspan', 'type', 'controls',
        ],
        ALLOW_DATA_ATTR: false,
    });
}
