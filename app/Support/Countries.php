<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The fixed list of country portfolios: the 193 UN member states plus the two
 * permanent observer states. Country preferences and country allocations must
 * use one of these names, so "France" and "frances" can never both be handed out.
 */
class Countries
{
    /**
     * Each country's display name, with other names people search for it by.
     *
     * @var array<string, list<string>>
     */
    private const ALL = [
        'Afghanistan' => [],
        'Albania' => [],
        'Algeria' => [],
        'Andorra' => [],
        'Angola' => [],
        'Antigua and Barbuda' => [],
        'Argentina' => [],
        'Armenia' => [],
        'Australia' => [],
        'Austria' => [],
        'Azerbaijan' => [],
        'Bahamas' => ['The Bahamas'],
        'Bahrain' => [],
        'Bangladesh' => [],
        'Barbados' => [],
        'Belarus' => [],
        'Belgium' => [],
        'Belize' => [],
        'Benin' => [],
        'Bhutan' => [],
        'Bolivia' => [],
        'Bosnia and Herzegovina' => ['Bosnia'],
        'Botswana' => [],
        'Brazil' => [],
        'Brunei' => ['Brunei Darussalam'],
        'Bulgaria' => [],
        'Burkina Faso' => [],
        'Burundi' => [],
        'Cabo Verde' => ['Cape Verde'],
        'Cambodia' => [],
        'Cameroon' => [],
        'Canada' => [],
        'Central African Republic' => ['CAR'],
        'Chad' => [],
        'Chile' => [],
        'China' => ['PRC', "People's Republic of China"],
        'Colombia' => [],
        'Comoros' => [],
        'Congo (Republic of the)' => ['Republic of the Congo', 'Congo-Brazzaville'],
        'Costa Rica' => [],
        "Côte d'Ivoire" => ['Ivory Coast'],
        'Croatia' => [],
        'Cuba' => [],
        'Cyprus' => [],
        'Czechia' => ['Czech Republic'],
        'Democratic Republic of the Congo' => ['DRC', 'DR Congo', 'Congo-Kinshasa'],
        'Denmark' => [],
        'Djibouti' => [],
        'Dominica' => [],
        'Dominican Republic' => [],
        'Ecuador' => [],
        'Egypt' => [],
        'El Salvador' => [],
        'Equatorial Guinea' => [],
        'Eritrea' => [],
        'Estonia' => [],
        'Eswatini' => ['Swaziland'],
        'Ethiopia' => [],
        'Fiji' => [],
        'Finland' => [],
        'France' => [],
        'Gabon' => [],
        'Gambia' => ['The Gambia'],
        'Georgia' => [],
        'Germany' => [],
        'Ghana' => [],
        'Greece' => [],
        'Grenada' => [],
        'Guatemala' => [],
        'Guinea' => [],
        'Guinea-Bissau' => [],
        'Guyana' => [],
        'Haiti' => [],
        'Holy See' => ['Vatican', 'Vatican City'],
        'Honduras' => [],
        'Hungary' => [],
        'Iceland' => [],
        'India' => ['Bharat'],
        'Indonesia' => [],
        'Iran' => ['Islamic Republic of Iran'],
        'Iraq' => [],
        'Ireland' => [],
        'Israel' => [],
        'Italy' => [],
        'Jamaica' => [],
        'Japan' => [],
        'Jordan' => [],
        'Kazakhstan' => [],
        'Kenya' => [],
        'Kiribati' => [],
        'Kuwait' => [],
        'Kyrgyzstan' => [],
        'Laos' => ["Lao People's Democratic Republic"],
        'Latvia' => [],
        'Lebanon' => [],
        'Lesotho' => [],
        'Liberia' => [],
        'Libya' => [],
        'Liechtenstein' => [],
        'Lithuania' => [],
        'Luxembourg' => [],
        'Madagascar' => [],
        'Malawi' => [],
        'Malaysia' => [],
        'Maldives' => [],
        'Mali' => [],
        'Malta' => [],
        'Marshall Islands' => [],
        'Mauritania' => [],
        'Mauritius' => [],
        'Mexico' => [],
        'Micronesia' => ['Federated States of Micronesia'],
        'Moldova' => ['Republic of Moldova'],
        'Monaco' => [],
        'Mongolia' => [],
        'Montenegro' => [],
        'Morocco' => [],
        'Mozambique' => [],
        'Myanmar' => ['Burma'],
        'Namibia' => [],
        'Nauru' => [],
        'Nepal' => [],
        'Netherlands' => ['Holland'],
        'New Zealand' => [],
        'Nicaragua' => [],
        'Niger' => [],
        'Nigeria' => [],
        'North Korea' => ['DPRK', "Democratic People's Republic of Korea"],
        'North Macedonia' => ['Macedonia'],
        'Norway' => [],
        'Oman' => [],
        'Pakistan' => [],
        'Palau' => [],
        'Palestine' => ['State of Palestine'],
        'Panama' => [],
        'Papua New Guinea' => ['PNG'],
        'Paraguay' => [],
        'Peru' => [],
        'Philippines' => [],
        'Poland' => [],
        'Portugal' => [],
        'Qatar' => [],
        'Romania' => [],
        'Russia' => ['Russian Federation'],
        'Rwanda' => [],
        'Saint Kitts and Nevis' => ['St Kitts'],
        'Saint Lucia' => ['St Lucia'],
        'Saint Vincent and the Grenadines' => ['St Vincent'],
        'Samoa' => [],
        'San Marino' => [],
        'São Tomé and Príncipe' => [],
        'Saudi Arabia' => ['KSA'],
        'Senegal' => [],
        'Serbia' => [],
        'Seychelles' => [],
        'Sierra Leone' => [],
        'Singapore' => [],
        'Slovakia' => [],
        'Slovenia' => [],
        'Solomon Islands' => [],
        'Somalia' => [],
        'South Africa' => [],
        'South Korea' => ['Republic of Korea', 'ROK', 'Korea'],
        'South Sudan' => [],
        'Spain' => [],
        'Sri Lanka' => [],
        'Sudan' => [],
        'Suriname' => [],
        'Sweden' => [],
        'Switzerland' => [],
        'Syria' => ['Syrian Arab Republic'],
        'Tajikistan' => [],
        'Tanzania' => ['United Republic of Tanzania'],
        'Thailand' => [],
        'Timor-Leste' => ['East Timor'],
        'Togo' => [],
        'Tonga' => [],
        'Trinidad and Tobago' => [],
        'Tunisia' => [],
        'Türkiye' => ['Turkey'],
        'Turkmenistan' => [],
        'Tuvalu' => [],
        'Uganda' => [],
        'Ukraine' => [],
        'United Arab Emirates' => ['UAE', 'Emirates'],
        'United Kingdom' => ['UK', 'Britain', 'Great Britain', 'England'],
        'United States' => ['USA', 'US', 'America', 'United States of America'],
        'Uruguay' => [],
        'Uzbekistan' => [],
        'Vanuatu' => [],
        'Venezuela' => [],
        'Vietnam' => ['Viet Nam'],
        'Yemen' => [],
        'Zambia' => [],
        'Zimbabwe' => [],
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * Options for a searchable picker: each name and every lowercase, accent-free term it
     * matches. Countries also match their other names, so "usa" finds United States.
     *
     * @param  list<string>|null  $names  Defaults to every country.
     * @return list<array{name: string, terms: list<string>}>
     */
    public static function options(?array $names = null): array
    {
        return array_map(fn (string $name) => [
            'name' => $name,
            'terms' => array_map(self::normalise(...), [$name, ...(self::ALL[$name] ?? [])]),
        ], $names ?? self::names());
    }

    /**
     * The entry in $names that a loosely typed value refers to, if any.
     *
     * @param  list<string>  $names
     */
    public static function match(?string $value, array $names): ?string
    {
        $value = self::normalise((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (self::options($names) as $option) {
            if (in_array($value, $option['terms'], true)) {
                return $option['name'];
            }
        }

        return null;
    }

    /**
     * The listed name for something typed loosely ("turkey", "usa", "Cote d'Ivoire"), if it matches one.
     */
    public static function canonical(?string $value): ?string
    {
        return self::match($value, self::names());
    }

    private static function normalise(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
