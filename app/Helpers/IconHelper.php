<?php
declare(strict_types=1);

namespace App\Helpers;

final class IconHelper
{
    public static function svg(string $name, string $class = 'svg-icon'): string
    {
        $body = match ($name) {
            'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
            'add' => '<path d="M12 5v14M5 12h14"/>',
            'edit' => '<path d="M4 20h4.5L19 9.5 14.5 5 4 15.5V20Z"/><path d="m13.5 6 4.5 4.5"/>',
            'cancel', 'close' => '<path d="m7 7 10 10M17 7 7 17"/>',
            'check' => '<path d="m5 12 4 4 10-10"/>',
            'view' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="3"/>',
            'more' => '<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>',
            'drag' => '<circle cx="9" cy="5" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="19" r="1"/>',
            'chevron-down' => '<path d="m7 10 5 5 5-5"/>',
            'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
            'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
            'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
            'arrow-up' => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
            'arrow-down' => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
            'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
            'upload' => '<path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M5 3h14"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'filter' => '<path d="M4 5h16l-6.5 7.2V18l-3 1.5v-7.3L4 5Z"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.09a2 2 0 0 1 1 1.74v.5a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z"/><circle cx="12" cy="12" r="3"/>',
            'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>',
            'theme', 'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
            'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
            'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.7.6 2.5a2 2 0 0 1-.4 2.1L8 9.6a16 16 0 0 0 6.4 6.4l1.3-1.3a2 2 0 0 1 2.1-.4c.8.3 1.6.5 2.5.6a2 2 0 0 1 1.7 2Z"/>',
            'telegram' => '<path d="M21.5 4.5 3 11.7l7 2.2 2.2 6.8 3.1-4.7 4.9 3.6 1.3-15.1Z"/><path d="m10 13.9 5.6-5.2"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
            'money' => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 10v4M18 10v4"/>',
            'wallet' => '<path d="M4 7h15a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h13"/><path d="M16 13h5"/>',
            'expense' => '<path d="M5 7h14v13H5z"/><path d="M8 7V4h8v3M9 11h6M9 15h4"/>',
            'profit' => '<path d="M4 18 10 12l4 4 6-8"/><path d="M16 8h4v4"/>',
            'percent' => '<path d="m7 17 10-10"/><circle cx="8" cy="8" r="1.6"/><circle cx="16" cy="16" r="1.6"/>',
            'instagram' => '<rect x="5" y="5" width="14" height="14" rx="4"/><circle cx="12" cy="12" r="3.2"/><circle cx="16.5" cy="7.5" r=".7"/>',
            'rent' => '<path d="M4 11.5 12 5l8 6.5"/><path d="M6.5 10.5V19h11v-8.5"/><path d="M10 19v-5h4v5"/>',
            'accounting' => '<rect x="6" y="4" width="12" height="16" rx="2"/><path d="M9 8h6M9 12h6M9 16h2.5M14 16h1"/>',
            'trip' => '<path d="M3.5 12.5 20 5l-5.5 15-3.2-6.2-7.8-1.3Z"/><path d="m11.3 13.8 3.3-3.3"/>',
            'equipment' => '<rect x="5" y="7" width="14" height="11" rx="2"/><path d="M9 7V5.5h6V7M8.5 11.5h7"/>',
            'card' => '<rect x="4" y="6" width="16" height="12" rx="2"/><path d="M4 10h16M7 14h4"/>',
            'transfer' => '<path d="M4 7h16v10H4z"/><path d="m4 8 8 6 8-6"/>',
            'gallery' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="1.5"/><path d="m21 15-5-5L5 19"/>',
            'document' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/>',
            'notes' => '<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            'age' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M8 12h8"/>',
            'hash' => '<path d="M10 3 8 21M16 3l-2 18M4 9h17M3 15h17"/>',
            default => '<circle cx="12" cy="12" r="1"/><circle cx="6.5" cy="12" r="1"/><circle cx="17.5" cy="12" r="1"/>',
        };

        return '<svg class="' . FormatHelper::e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }
}
