<?php

namespace App\Support;

/**
 * The starting portfolio list for each committee. The team edits the real lists on
 * each committee's staff page; these only fill a committee the first time.
 */
class DefaultPortfolios
{
    /**
     * @var array<string, list<string>>
     */
    private const LISTS = [
        'UNW' => [
            'Afghanistan', 'Argentina', 'Australia', 'Bangladesh', 'Brazil', 'Canada', 'China',
            'Democratic Republic of the Congo', 'Egypt', 'Ethiopia', 'France', 'Germany', 'India',
            'Indonesia', 'Iran', 'Iraq', 'Israel', 'Italy', 'Japan', 'Kenya', 'Malaysia', 'Mexico',
            'Netherlands', 'Nigeria', 'Norway', 'Pakistan', 'Palestine', 'Qatar', 'Russia', 'Rwanda',
            'Saudi Arabia', 'South Africa', 'South Korea', 'Spain', 'Sudan', 'Sweden', 'Syria',
            'Türkiye', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Yemen',
        ],
        // The 2026 Council: the five permanent members plus the ten elected members.
        'UNSC' => [
            'China', 'France', 'Russia', 'United Kingdom', 'United States',
            'Bahrain', 'Colombia', 'Democratic Republic of the Congo', 'Denmark', 'Greece',
            'Latvia', 'Liberia', 'Pakistan', 'Panama', 'Somalia',
        ],
        'PNA' => [
            'Shehbaz Sharif', 'Nawaz Sharif', 'Maryam Nawaz', 'Ishaq Dar', 'Khawaja Asif',
            'Ahsan Iqbal', 'Sardar Ayaz Sadiq', 'Rana Sanaullah', 'Azam Nazeer Tarar', 'Attaullah Tarar',
            'Maryam Aurangzeb', 'Asif Ali Zardari', 'Bilawal Bhutto Zardari', 'Aseefa Bhutto Zardari',
            'Syed Khurshid Shah', 'Raja Pervaiz Ashraf', 'Syed Naveed Qamar', 'Sherry Rehman',
            'Shazia Marri', 'Imran Khan', 'Omar Ayub Khan', 'Barrister Gohar Ali Khan', 'Asad Qaiser',
            'Shah Mahmood Qureshi', 'Ali Amin Gandapur', 'Sher Afzal Marwat', 'Hammad Azhar',
            'Maulana Fazl-ur-Rehman', 'Khalid Maqbool Siddiqui', 'Mustafa Kamal', 'Farooq Sattar',
            'Abdul Aleem Khan', 'Chaudhry Salik Hussain', 'Mahmood Khan Achakzai', 'Akhtar Mengal',
            'Aimal Wali Khan',
        ],
        // Kept off the registration form until the theme is revealed.
        'SPECIAL' => [
            'Tony Stark / Iron Man', 'Steve Rogers / Captain America', 'Natasha Romanoff / Black Widow',
            'Peter Parker / Spider-Man', "T'Challa / Black Panther", 'Wanda Maximoff / Scarlet Witch',
            'Vision', 'Sam Wilson / Falcon', 'Bucky Barnes / Winter Soldier', 'Clint Barton / Hawkeye',
            'Scott Lang / Ant-Man', 'James Rhodes / War Machine', 'Thaddeus Ross', 'Sharon Carter',
            'Helmut Zemo', 'Nick Fury', 'Maria Hill', 'Reed Richards / Mister Fantastic',
            'Sue Storm / Invisible Woman', 'Jennifer Walters / She-Hulk', 'Matt Murdock / Daredevil',
            'Luke Cage', 'Logan / Wolverine', 'Carol Danvers / Captain Marvel',
        ],
    ];

    /**
     * @var list<string>
     */
    public const HIDDEN = ['SPECIAL'];

    /**
     * @return list<string>
     */
    public static function for(string $code): array
    {
        return self::LISTS[$code] ?? [];
    }
}
