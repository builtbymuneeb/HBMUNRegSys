<?php

namespace App\Support;

/**
 * The looks a user can pick for the dashboard. Colours live in resources/css/themes.css;
 * these swatches only draw the little previews in the theme picker.
 */
class Themes
{
    public const DEFAULT = 'royal';

    /**
     * @return array<string, array{name: string, dark: bool, bg: string, surface: string, hero: string, accent: string}>
     */
    public static function all(): array
    {
        return [
            'royal' => ['name' => __('Royal Plum'), 'dark' => true, 'bg' => '#0b0810', 'surface' => '#221a2e', 'hero' => '#3b0a6b', 'accent' => '#c9b6ea'],
            'parchment' => ['name' => __('Parchment'), 'dark' => false, 'bg' => '#f3eee4', 'surface' => '#fffdf8', 'hero' => '#62318f', 'accent' => '#3b0a6b'],
            'navy' => ['name' => __('Navy & Gold'), 'dark' => true, 'bg' => '#06101f', 'surface' => '#142745', 'hero' => '#173a7a', 'accent' => '#e0b85c'],
            'emerald' => ['name' => __('Emerald & Brass'), 'dark' => true, 'bg' => '#05120d', 'surface' => '#122d22', 'hero' => '#145a3e', 'accent' => '#d4b06a'],
            'modern' => ['name' => __('Modern Light'), 'dark' => false, 'bg' => '#f5f5f8', 'surface' => '#ffffff', 'hero' => '#5b3bd6', 'accent' => '#5b3bd6'],
        ];
    }
}
