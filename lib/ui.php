<?php
declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fmtDate($ts, string $tz = 'UTC'): string
{
    if (!is_numeric($ts)) {
        return '—';
    }
    $timestamp = (float) $ts;
    $date = new DateTime('@' . (int) floor($timestamp));
    $date->setTimezone(new DateTimeZone($tz));
    return $date->format('Y-m-d H:i:s');
}

function humanDuration(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' sec';
    }
    if ($seconds < 3600) {
        return (int) floor($seconds / 60) . ' min';
    }
    if ($seconds < 86400) {
        return (int) floor($seconds / 3600) . ' ore';
    }

    return (int) floor($seconds / 86400) . ' zile';
}

function hasValue($value): bool
{
    return !($value === null || $value === '');
}

function iconSvg(string $name): string
{
    $base = 'viewBox="0 0 24 24" width="18" height="18" fill="none" '
        . 'stroke="currentColor" stroke-width="1.6" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true"';

    $icons = [
        'clock' => '<svg ' . $base . '><circle cx="12" cy="12" r="8"></circle><path d="M12 7v6l4 2"></path></svg>',
        'battery' => '<svg ' . $base . '><rect x="3" y="7" width="16" height="10" rx="2"></rect><path d="M21 10v4"></path></svg>',
        'steps' => '<svg ' . $base . '><path d="M5 16h6v4H5z"></path><path d="M13 10h6v6h-6z"></path></svg>',
        'signal' => '<svg ' . $base . '><path d="M4 20h2v-4H4zM10 20h2v-8h-2zM16 20h2v-12h-2z"></path></svg>',
        'location' => '<svg ' . $base . '><path d="M12 21s6-5 6-10a6 6 0 1 0-12 0c0 5 6 10 6 10z"></path><circle cx="12" cy="11" r="2"></circle></svg>',
        'device' => '<svg ' . $base . '><rect x="7" y="3" width="10" height="18" rx="2"></rect><path d="M9 7h6M9 17h6"></path></svg>',
        'protocol' => '<svg ' . $base . '><path d="M10 13a4 4 0 0 1 0-6l2-2a4 4 0 0 1 6 6l-1 1"></path><path d="M14 11a4 4 0 0 1 0 6l-2 2a4 4 0 0 1-6-6l1-1"></path></svg>',
        'channel' => '<svg ' . $base . '><path d="M4 12h6M4 8h10M4 16h10"></path><rect x="16" y="6" width="4" height="12" rx="1"></rect></svg>',
        'message' => '<svg ' . $base . '><path d="M4 5h16v10H7l-3 3z"></path></svg>',
        'server' => '<svg ' . $base . '><rect x="4" y="4" width="16" height="6" rx="1"></rect><rect x="4" y="14" width="16" height="6" rx="1"></rect></svg>',
        'speed' => '<svg ' . $base . '><path d="M5 16a7 7 0 0 1 14 0"></path><path d="M12 12l3-3"></path></svg>',
        'satellite' => '<svg ' . $base . '><circle cx="12" cy="12" r="2"></circle><path d="M4 12a8 8 0 0 1 8-8"></path><path d="M20 12a8 8 0 0 0-8-8"></path></svg>',
        'id' => '<svg ' . $base . '><rect x="4" y="5" width="16" height="14" rx="2"></rect><path d="M8 9h4M8 13h8"></path></svg>',
        'info' => '<svg ' . $base . '><circle cx="12" cy="12" r="9"></circle><path d="M12 10v6"></path><path d="M12 7h.01"></path></svg>',
        'phone' => '<svg ' . $base . '><path d="M5 4l4 1 2 4-2 2c1 2 3 4 5 5l2-2 4 2 1 4c-1 1-3 2-5 2-7 0-13-6-13-13 0-2 1-4 2-5z"></path></svg>',
        'sos' => '<svg ' . $base . '><path d="M12 4l7 4v5c0 4-3 7-7 8-4-1-7-4-7-8V8z"></path><path d="M12 9v4"></path><path d="M12 15h.01"></path></svg>',
        'settings' => '<svg ' . $base . '><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1-1.8 3-0.2-.1a1.7 1.7 0 0 0-2 .3l-.1.1-3-1.8.1-.2a1.7 1.7 0 0 0-.3-2l-.1-.1 1.8-3 .2.1a1.7 1.7 0 0 0 2-.3l.1-.1z"></path></svg>',
        'apn' => '<svg ' . $base . '><path d="M4 12h6"></path><path d="M8 8h6"></path><path d="M4 16h10"></path><circle cx="18" cy="12" r="2"></circle></svg>',
        'shield' => '<svg ' . $base . '><path d="M12 4l7 4v5c0 4-3 7-7 8-4-1-7-4-7-8V8z"></path></svg>',
        'toggle' => '<svg ' . $base . '><rect x="4" y="9" width="16" height="6" rx="3"></rect><circle cx="16" cy="12" r="2"></circle></svg>',
        'timer' => '<svg ' . $base . '><circle cx="12" cy="13" r="7"></circle><path d="M12 10v3l2 2"></path><path d="M9 3h6"></path></svg>',
        'network' => '<svg ' . $base . '><path d="M4 12a8 8 0 0 1 16 0"></path><path d="M7 12a5 5 0 0 1 10 0"></path><path d="M10 12a2 2 0 0 1 4 0"></path><circle cx="12" cy="15" r="1"></circle></svg>',
        'bell' => '<svg ' . $base . '><path d="M6 16h12"></path><path d="M8 16v-4a4 4 0 0 1 8 0v4"></path><path d="M10 18a2 2 0 0 0 4 0"></path></svg>',
        'list' => '<svg ' . $base . '><path d="M9 6h10"></path><path d="M9 12h10"></path><path d="M9 18h10"></path><circle cx="5" cy="6" r="1"></circle><circle cx="5" cy="12" r="1"></circle><circle cx="5" cy="18" r="1"></circle></svg>',
    ];

    return $icons[$name] ?? $icons['info'];
}
