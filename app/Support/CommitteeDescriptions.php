<?php

namespace App\Support;

/**
 * The "about this committee" text shown when a committee card is clicked on the homepage.
 */
class CommitteeDescriptions
{
    /**
     * @var array<string, array{title: string, paragraphs: list<string>}>
     */
    private const DESCRIPTIONS = [
        'UNW' => [
            'title' => 'United Nations Entity for Gender Equality and the Empowerment of Women (UN Women)',
            'paragraphs' => [
                'UN Women is a United Nations entity established in 2010 to promote gender equality and the empowerment of women and girls worldwide. It works towards eliminating discrimination against women, advancing equal opportunities and ensuring that women have an active role in social, economic and political development. The organisation supports governments and international institutions through research, advocacy, policy development and programmes aimed at improving the lives of women and girls.',
                'In an MUN simulation of UN Women, delegates are assigned different countries and are expected to represent their respective nations\' policies, perspectives and positions on issues concerning gender equality and women\'s empowerment. They engage in formal debates, negotiate with other representatives and collaborate on developing policy recommendations and draft resolutions to address the challenges under discussion. Delegates must balance their country\'s interests with the broader objective of promoting women\'s rights, strengthening gender equality and developing effective international solutions.',
            ],
        ],
        'UNSC' => [
            'title' => 'United Nations Security Council (UNSC)',
            'paragraphs' => [
                'The United Nations Security Council (UNSC) is one of the six principal organs of the United Nations, established in 1945 under the UN Charter. It holds the primary responsibility for maintaining international peace and security. The Council consists of 15 member states, including five permanent members and ten non-permanent members. Its decisions can be binding on all UN member states, making it one of the most influential institutions within the international system.',
                'In an MUN simulation of UNSC, delegates are assigned countries that hold membership in the Security Council and must represent their respective nations\' foreign policies and diplomatic interests. They discuss international security concerns, assess potential threats and negotiate appropriate responses to ongoing conflicts and crises. Through formal debates, consultations and negotiations, delegates work towards drafting resolutions and reaching decisions while navigating alliances, disagreements and the differing interests of member states.',
            ],
        ],
        'PNA' => [
            'title' => 'Pakistan National Assembly (PNA)',
            'paragraphs' => [
                'The Pakistan National Assembly is the lower house of Pakistan\'s Parliament and serves as a central institution of the country\'s democratic and legislative system. It consists of elected representatives, known as Members of the National Assembly (MNAs), who represent constituencies from across the country. The Assembly provides a platform for representatives to voice the concerns of their constituents, debate national issues and participate in decisions concerning the country\'s governance and future.',
                'In an MUN simulation of PNA, delegates assume the roles of Members of the National Assembly and are assigned political parties or constituencies to represent. They participate in parliamentary debates, defend their party\'s positions, question opposing representatives and discuss matters of national importance. Delegates are expected to propose legislative measures, challenge existing policies and collaborate on possible solutions through parliamentary proceedings, ultimately simulating the functioning of Pakistan\'s legislative institution.',
            ],
        ],
        'SPECIAL' => [
            'title' => 'Marvel Special Committee',
            'paragraphs' => [
                'The Marvel Special Committee is a fictional committee inspired by the Marvel Cinematic Universe (MCU), bringing together characters and influential figures from a universe of superheroes, powerful organisations and extraordinary events. Unlike traditional MUN committees that simulate real-world international institutions, this committee operates within a fictional setting where delegates represent characters and stakeholders from the Marvel universe.',
                'In an MUN simulation of this committee, delegates are assigned Marvel characters and are expected to represent their personalities, motivations, abilities, affiliations and established perspectives. They participate in discussions, negotiate alliances, and develop strategies to address the challenges presented before the committee.',
            ],
        ],
    ];

    /**
     * The description for a committee code, or null when there isn't one.
     *
     * @return array{title: string, paragraphs: list<string>}|null
     */
    public static function for(string $code): ?array
    {
        return self::DESCRIPTIONS[$code] ?? null;
    }
}
